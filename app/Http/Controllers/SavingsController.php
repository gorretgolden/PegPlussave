<?php

namespace App\Http\Controllers;

use App\Models\InterestAllocation;
use App\Models\Member;
use App\Models\Transaction;
use App\Support\PegDates;
use App\Support\Policy;
use App\Support\TreasuryPosition;

/**
 * A member's own savings book: what is in it, how it grew, and every entry that moved it. The
 * balances are derived from the posted entries, so this screen cannot tell a different story from
 * the Treasurer's cash book.
 */
class SavingsController extends Controller
{
    public function index()
    {
        $member = Member::query()->findOrFail(auth()->user()->member_id);
        $row = TreasuryPosition::memberBalances()[$member->id];
        $year = (int) now()->format('Y');

        $earnedByMonth = [];

        foreach (InterestAllocation::query()
                     ->where('member_id', $member->id)
                     ->whereHas('transaction', fn ($q) => $q->where('type', Transaction::INTEREST_POSTING)->posted())
                     ->with('transaction:id,value_date')
                     ->get() as $allocation) {
            $month = $allocation->transaction->value_date->format('Y-m');
            $earnedByMonth[$month] = ($earnedByMonth[$month] ?? 0) + (int) $allocation->amount;
        }

        return view('pages.savings', [
            'page' => 'savings',
            'member' => $member,
            'row' => $row,
            'chart' => collect(PegDates::recentMonths(12, sprintf('%04d-12', $year)))
                ->reverse()
                ->values()
                ->map(fn (string $ym) => [
                    'label' => \App\Support\Format::monthTag($ym.'-01'),
                    'saved' => $row['deposits_by_month'][$ym] ?? 0,
                    'interest' => $earnedByMonth[$ym] ?? 0,
                ])
                ->all(),
            'year' => $year,
            'statement' => $this->statement($member->id),
            'settings' => [
                'min_monthly' => (int) Policy::get('min_monthly'),
                'contribution_day' => (int) Policy::get('contribution_day'),
                'payout_month' => (int) Policy::get('payout_month'),
            ],
        ]);
    }

    /**
     * Every entry that touched this member's savings or interest, oldest first, with the savings
     * balance running only over the posted ones. An entry still being checked shows in the column
     * so the reader can see the figure coming, but it is not in the balance yet.
     *
     * @return array<int, array{transaction: Transaction, description: string, savings: int, interest: int, balance: int}>
     */
    private function statement(int $memberId): array
    {
        $rows = [];

        $transactions = Transaction::query()
            ->where('member_id', $memberId)
            ->whereNot('status', Transaction::REJECTED)
            ->orderBy('value_date')
            ->orderBy('ref')
            ->get();

        $allocations = InterestAllocation::query()
            ->where('member_id', $memberId)
            ->whereIn('transaction_id', $transactions->pluck('id'))
            ->with('transaction:id')
            ->get()
            ->keyBy('transaction_id');

        foreach ($transactions as $transaction) {
            $description = null;
            $savings = 0;
            $interest = 0;

            switch ($transaction->type) {
                case Transaction::DEPOSIT:
                    $description = 'Saving for '.PegDates::monthLabel((string) ($transaction->meta['month'] ?? $transaction->value_date->format('Y-m'))).
                        ' · '.($transaction->meta['channel'] ?? '');
                    $savings = (int) $transaction->amount;
                    break;

                case Transaction::INTEREST_POSTING:
                    $description = 'Interest share for '.PegDates::monthLabel((string) ($transaction->meta['month'] ?? $transaction->value_date->format('Y-m')));
                    $interest = (int) ($allocations[$transaction->id]->amount ?? 0);
                    break;

                case Transaction::INTEREST_PAYOUT:
                    $description = 'Annual interest payout '.($transaction->meta['year'] ?? '');
                    $interest = -(int) ($allocations[$transaction->id]->amount ?? 0);
                    break;

                case Transaction::EXIT_SETTLEMENT:
                    $description = 'Exit settlement';
                    $savings = -(int) ($transaction->meta['savings'] ?? 0);
                    $interest = -(int) ($transaction->meta['interest'] ?? 0);
                    break;
            }

            if ($description === null) {
                continue;
            }

            $rows[] = ['transaction' => $transaction, 'description' => $description, 'savings' => $savings, 'interest' => $interest];
        }

        $balance = 0;

        foreach ($rows as $index => $row) {
            if ($row['transaction']->isPosted()) {
                $balance += $row['savings'];
            }
            $rows[$index]['balance'] = $balance;
        }

        return array_reverse($rows);
    }
}
