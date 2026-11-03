<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\Format;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cash book and the entry behind a number. The book is only a listing of what has already been
 * posted; the running balance in the last column is arithmetic on that list, which is why an entry
 * that has not been checked never appears in it.
 */
class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $rows = $this->rows();
        $months = $rows->pluck('ym')->unique()->sortDesc()->values();
        $month = $request->query('month') === 'all'
            ? 'all'
            : (in_array($request->query('month'), $months->all(), true) ? $request->query('month') : now()->format('Y-m'));

        $shown = $month === 'all' ? $rows : $rows->filter(fn (array $r) => $r['ym'] === $month)->values();
        $forward = $month === 'all' ? 0 : $rows->sum(fn (array $r) => $r['ym'] < $month ? $r['amount'] : 0);

        $balance = $forward;
        $lines = $shown->map(function (array $row) use (&$balance) {
            $balance += $row['amount'];

            return [...$row, 'balance' => $balance];
        });

        return view('pages.ledger', [
            'page' => 'ledger',
            'month' => $month,
            'months' => $months,
            'forward' => $forward,
            'lines' => $lines,
            'receipts' => $shown->filter(fn (array $r) => $r['amount'] > 0)->sum('amount'),
            'payments' => abs((int) $shown->filter(fn (array $r) => $r['amount'] < 0)->sum('amount')),
            'carried' => $balance,
            'nonCash' => $this->nonCash($month),
            'csv' => $this->csv($lines),
            'isTreasurer' => $request->user()->can('make-treasury-entry'),
        ]);
    }

    /**
     * One entry on its own, with the trail, the notes and the note box. The treasury lists point here
     * rather than carrying a copy of the entry, because 200 modals on a page is not a screen.
     */
    public function show(Request $request, Transaction $transaction)
    {
        $position = TreasuryPosition::group();

        return view('pages.entry', [
            'page' => 'ledger',
            'transaction' => $transaction->load(self::WITH),
            'balances' => $position['balances'],
            'ledgers' => $position['ledgers'],
            'canCheck' => $this->canCheck($request, $transaction),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->rowsForCsv($request->query('month'));

        $filename = 'peg-cash-book-'.preg_replace('/[^0-9a-z-]/i', '', (string) $request->query('month', 'all')).'.csv';

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    public function rowsForCsv(?string $month): array
    {
        $rows = $this->rows();
        $forward = $month && $month !== 'all'
            ? $rows->sum(fn (array $r) => $r['ym'] < $month ? $r['amount'] : 0)
            : 0;

        if ($month && $month !== 'all') {
            $rows = $rows->filter(fn (array $r) => $r['ym'] === $month);
        }

        $balance = $forward;

        return array_merge(
            [['Date', 'Ref', 'Description', 'Receipts', 'Payments', 'Balance']],
            $rows->map(function (array $row) use (&$balance) {
                $balance += $row['amount'];

                return [
                    $row['date'],
                    $row['ref'],
                    $row['description'],
                    $row['amount'] > 0 ? $row['amount'] : '',
                    $row['amount'] < 0 ? -$row['amount'] : '',
                    $balance,
                ];
            })->all()
        );
    }

    /**
     * The posted entries that move cash, oldest first. Everything the cash book, the reports and the
     * funds position are drawn from starts as this list.
     *
     * @return \Illuminate\Support\Collection<int, array{transaction: Transaction, ref: string, date: string, ym: string, amount: int, description: string}>
     */
    private function rows()
    {
        return Transaction::query()->with(['member', 'loan', 'externalLoan'])
            ->posted()
            ->whereIn('type', array_keys(Transaction::CASH_EFFECT))
            ->orderBy('value_date')
            ->orderBy('ref')
            ->get()
            ->map(function (Transaction $transaction) {
                $effect = Transaction::CASH_EFFECT[$transaction->type];

                return [
                    'transaction' => $transaction,
                    'ref' => $transaction->ref,
                    'date' => $transaction->value_date->toDateString(),
                    'ym' => $transaction->value_date->format('Y-m'),
                    'amount' => (int) $transaction->amount * $effect,
                    'description' => self::describe($transaction),
                ];
            })
            ->values();
    }

    private function nonCash(string $month)
    {
        return Transaction::query()->with('allocations')
            ->posted()
            ->where('type', Transaction::INTEREST_POSTING)
            ->when($month !== 'all', fn ($query) => $query->where('value_date', 'like', $month.'-%'))
            ->orderBy('value_date')
            ->get();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csv($lines): array
    {
        return array_merge(
            [['Date', 'Ref', 'Description', 'Receipts', 'Payments', 'Balance']],
            $lines->map(fn (array $row) => [
                $row['date'],
                $row['ref'],
                $row['description'],
                $row['amount'] > 0 ? (string) $row['amount'] : '',
                $row['amount'] < 0 ? (string) -$row['amount'] : '',
                (string) $row['balance'],
            ])->all()
        );
    }

    private static function describe(Transaction $transaction): string
    {
        $name = (string) ($transaction->member?->name ?? '');
        $loanRef = (string) ($transaction->loan?->ref ?? '');

        return match ($transaction->type) {
            Transaction::DEPOSIT => 'Saving · '.$name.' · '.Format::month((string) ($transaction->meta['month'] ?? '')),
            Transaction::LOAN_BOOKING => 'Loan paid out · '.$loanRef.' · '.$name,
            Transaction::REPAYMENT => 'Repayment · '.$loanRef.' · '.$name,
            Transaction::INTEREST_PAYOUT => 'Interest payout '.$transaction->meta['year'].' · '
                .count($transaction->allocations).' savers',
            Transaction::EXIT_SETTLEMENT => 'Exit settlement · '.$name,
            Transaction::EXTERNAL_LOAN => 'Borrowed · '.(string) ($transaction->externalLoan?->lender ?? ''),
            Transaction::EXTERNAL_REPAYMENT => 'Repaid lender · '.$transaction->externalLoan?->ref,
            default => '',
        };
    }

    /**
     * Whether this officer is the one the entry is waiting for. The workflow still decides it on the
     * way in; this only decides whether to draw the two buttons.
     */
    private function canCheck(Request $request, Transaction $transaction): bool
    {
        $user = $request->user();

        if ($transaction->maker_id === $user->id) {
            return false;
        }

        return match ($transaction->status) {
            Transaction::PENDING_PATRON => $user->hasRole('patron') || $user->hasRole('admin'),
            Transaction::PENDING_CHAIR => $user->hasRole('chair') || $user->hasRole('admin'),
            default => false,
        };
    }

    private const WITH = [
        'member', 'loan.member', 'loan.guarantors', 'externalLoan', 'maker', 'checks', 'notes', 'allocations',
    ];
}
