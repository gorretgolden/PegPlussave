<?php

namespace App\Support;

use App\Models\InterestAllocation;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Services\LoanLedger;
use App\Services\LoanSchedule;
use Illuminate\Support\Facades\DB;

/**
 * The group's money as it stands today, worked out from the posted entries rather than stored
 * beside them. A balance that is derived cannot disagree with the book it came from, which is the
 * one thing a savings group's records have to get right in front of a general meeting.
 */
class TreasuryPosition
{
    /**
     * What one member has saved at a given date: deposits posted on or before it, less whatever an
     * exit settlement already paid their principal back to them.
     */
    public static function savingsAt(int $memberId, string $cutOff): int
    {
        $deposits = (int) Transaction::query()
            ->where('member_id', $memberId)
            ->where('type', Transaction::DEPOSIT)
            ->posted()
            ->whereDate('value_date', '<=', $cutOff)
            ->sum('amount');

        $settled = (int) Transaction::query()
            ->where('member_id', $memberId)
            ->where('type', Transaction::EXIT_SETTLEMENT)
            ->posted()
            ->whereDate('value_date', '<=', $cutOff)
            ->get(['meta'])
            ->sum(fn (Transaction $t) => (int) ($t->meta['savings'] ?? 0));

        return $deposits - $settled;
    }

    /**
     * Interest owed to one member at a date: postings credited to them, less payouts and the
     * interest half of any exit settlement.
     */
    public static function interestAt(int $memberId, string $cutOff): int
    {
        $credited = (int) InterestAllocation::query()
            ->where('member_id', $memberId)
            ->whereHas('transaction', function ($query) use ($cutOff) {
                $query->where('type', Transaction::INTEREST_POSTING)
                    ->posted()
                    ->whereDate('value_date', '<=', $cutOff);
            })
            ->sum('amount');

        $paidOut = (int) InterestAllocation::query()
            ->where('member_id', $memberId)
            ->whereHas('transaction', function ($query) use ($cutOff) {
                $query->where('type', Transaction::INTEREST_PAYOUT)
                    ->posted()
                    ->whereDate('value_date', '<=', $cutOff);
            })
            ->sum('amount');

        $exits = (int) Transaction::query()
            ->where('member_id', $memberId)
            ->where('type', Transaction::EXIT_SETTLEMENT)
            ->posted()
            ->whereDate('value_date', '<=', $cutOff)
            ->get(['meta'])
            ->sum(fn (Transaction $t) => (int) ($t->meta['interest'] ?? 0));

        return $credited - $paidOut - $exits;
    }

    /**
     * Per-member balances, in one pass. The month keys are what the loan rule "saved for at least
     * three months" and the contribution register are both counted from.
     *
     * @return array<int, array{balance: int, interest: int, months: array<string>, deposits_by_month: array<string, int>, last_deposit: ?string}>
     */
    public static function memberBalances(?string $cutOff = null): array
    {
        $cutOff = $cutOff ?: now()->toDateString();

        $balances = Member::query()->get(['id'])->mapWithKeys(fn (Member $m) => [
            $m->id => [
                'balance' => 0,
                'interest' => 0,
                'interest_earned' => 0,
                'interest_paid_out' => 0,
                'months' => [],
                'deposits_by_month' => [],
                'last_deposit' => null,
            ],
        ])->all();

        $deposits = Transaction::query()
            ->where('type', Transaction::DEPOSIT)
            ->posted()
            ->whereDate('value_date', '<=', $cutOff)
            ->get(['member_id', 'amount', 'value_date', 'meta']);

        foreach ($deposits as $deposit) {
            $row = &$balances[$deposit->member_id];
            $month = $deposit->meta['month'] ?? $deposit->value_date->format('Y-m');
            $row['balance'] += (int) $deposit->amount;
            $row['months'][$month] = true;
            $row['deposits_by_month'][$month] = ($row['deposits_by_month'][$month] ?? 0) + (int) $deposit->amount;
            $date = $deposit->value_date->toDateString();
            if (! $row['last_deposit'] || $date > $row['last_deposit']) {
                $row['last_deposit'] = $date;
            }
            unset($row);
        }

        $settlements = Transaction::query()
            ->where('type', Transaction::EXIT_SETTLEMENT)
            ->posted()
            ->whereDate('value_date', '<=', $cutOff)
            ->get(['member_id', 'meta']);

        foreach ($settlements as $settlement) {
            $balances[$settlement->member_id]['balance'] -= (int) ($settlement->meta['savings'] ?? 0);
            $balances[$settlement->member_id]['interest'] -= (int) ($settlement->meta['interest'] ?? 0);
            $balances[$settlement->member_id]['interest_paid_out'] += (int) ($settlement->meta['interest'] ?? 0);
        }

        $allocations = InterestAllocation::query()
            ->whereHas('transaction', fn ($q) => $q->whereIn('type', [Transaction::INTEREST_POSTING, Transaction::INTEREST_PAYOUT])
                ->posted()
                ->whereDate('value_date', '<=', $cutOff))
            ->with('transaction:id,type')
            ->get();

        foreach ($allocations as $allocation) {
            $credited = $allocation->transaction->type === Transaction::INTEREST_POSTING;
            $sign = $credited ? 1 : -1;
            $balances[$allocation->member_id]['interest'] += $sign * (int) $allocation->amount;

            if ($credited) {
                $balances[$allocation->member_id]['interest_earned'] += (int) $allocation->amount;
            } else {
                $balances[$allocation->member_id]['interest_paid_out'] += (int) $allocation->amount;
            }
        }

        foreach ($balances as &$row) {
            $row['months'] = array_keys($row['months']);
            sort($row['months']);
        }

        return $balances;
    }

    /**
     * The whole group position: cash held, what is owed to savers, what borrowers still owe, and
     * how much of the cash may actually be lent out today.
     */
    public static function group(?string $cutOff = null): array
    {
        $cutOff = $cutOff ?: now()->toDateString();

        $cash = self::cash($cutOff);

        $balances = self::memberBalances($cutOff);
        $totalSavings = array_sum(array_column($balances, 'balance'));
        $interestDue = array_sum(array_map(fn (array $b) => max(0, $b['interest']), $balances));

        $ledgers = self::loanLedgers($cutOff);
        $outstandingPrincipal = 0;
        $interestCollected = 0;
        $running = [];
        $overThirty = 0;

        foreach ($ledgers as $ledger) {
            if (! $ledger['loan']->disbursed_on) {
                continue;
            }
            $outstandingPrincipal += $ledger['outstandingPrincipal'];
            $interestCollected += array_sum(array_column($ledger['splits'], 'interest'));
            if ($ledger['balance'] > 0) {
                $running[] = $ledger;
                if ($ledger['daysLate'] > 30) {
                    $overThirty += $ledger['outstandingPrincipal'];
                }
            }
        }

        $externalOutstanding = \App\Models\ExternalLoan::query()->get()->sum(fn ($loan) => $loan->info()['outstanding']);
        $externalInterestPaid = (int) Transaction::query()
            ->where('type', Transaction::EXTERNAL_REPAYMENT)
            ->posted()
            ->get()->sum(fn (Transaction $t) => (int) ($t->meta['interest'] ?? 0));

        $distributed = (int) Transaction::query()
            ->where('type', Transaction::INTEREST_POSTING)
            ->posted()
            ->sum('amount');

        $reserve = (int) round($totalSavings * Policy::get('reserve_percent') / 100);

        return [
            'cash' => $cash,
            'total_savings' => $totalSavings,
            'interest_due' => $interestDue,
            'outstanding_principal' => $outstandingPrincipal,
            'external_outstanding' => (int) $externalOutstanding,
            'external_interest_paid' => $externalInterestPaid,
            'interest_collected' => $interestCollected,
            'distributed' => $distributed,
            'retained' => $interestCollected - $externalInterestPaid - $distributed,
            'reserve' => $reserve,
            'committed' => self::committed(),
            'available' => max(0, $cash - $reserve - self::committed()),
            'ledgers' => $ledgers,
            'running' => $running,
            'par30_percent' => $outstandingPrincipal > 0 ? $overThirty / $outstandingPrincipal * 100 : 0.0,
            'balances' => $balances,
        ];
    }

    /**
     * Money already promised to a booking that is still being checked. It is held back from what the
     * group may lend, because the day the entry clears it leaves the box.
     */
    public static function committed(?int $excludeLoan = null): int
    {
        return (int) Transaction::query()
            ->where('type', Transaction::LOAN_BOOKING)
            ->where('status', 'like', 'pending%')
            ->when($excludeLoan, fn ($query) => $query->where('loan_id', '!=', $excludeLoan))
            ->sum('amount');
    }

    /**
     * Cash the group is free to part with. The floor at zero is deliberate: a reserve that is
     * already underwater is a matter for the meeting, not a number that makes a loan look lawful.
     */
    public static function available(?string $cutOff = null, int $addBack = 0, ?int $excludeLoan = null): int
    {
        $cutOff = $cutOff ?: now()->toDateString();
        $balances = self::memberBalances($cutOff);
        $totalSavings = array_sum(array_column($balances, 'balance'));

        $cash = self::cash($cutOff);
        $reserve = (int) round($totalSavings * Policy::get('reserve_percent') / 100);

        return max(0, $cash - $reserve - self::committed($excludeLoan) + $addBack);
    }

    /**
     * Money in the box at a date: every posted entry that moves cash, added up with its sign.
     */
    public static function cash(?string $cutOff = null): int
    {
        $cutOff = $cutOff ?: now()->toDateString();

        return (int) Transaction::query()
            ->posted()
            ->whereIn('type', array_keys(Transaction::CASH_EFFECT))
            ->whereDate('value_date', '<=', $cutOff)
            ->get(['type', 'amount'])
            ->sum(fn (Transaction $t) => Transaction::CASH_EFFECT[$t->type] * (int) $t->amount);
    }

    /**
     * Who has paid for a month. Three groups, because "not yet" and "told you, waiting to be
     * recorded" are different things to chase: one is a reminder and the other is a queue.
     *
     * @return array{month: string, active: \Illuminate\Support\Collection, saved: \Illuminate\Support\Collection, pending: \Illuminate\Support\Collection, not_yet: \Illuminate\Support\Collection}
     */
    public static function contributions(?string $yearMonth = null): array
    {
        $yearMonth = $yearMonth ?: now()->format('Y-m');
        $balances = self::memberBalances(PegDates::monthEnd($yearMonth));

        $waiting = Transaction::query()
            ->where('type', Transaction::DEPOSIT)
            ->where('status', 'like', 'pending%')
            ->get(['member_id', 'meta'])
            ->filter(fn (Transaction $t) => ($t->meta['month'] ?? null) === $yearMonth)
            ->pluck('member_id')
            ->unique()
            ->all();

        $groups = ['saved' => [], 'pending' => [], 'not_yet' => []];

        $active = Member::query()->active()
            ->whereRaw("DATE_FORMAT(joined_on,'%Y-%m') <= ?", [$yearMonth])
            ->orderBy('name')
            ->get();

        foreach ($active as $member) {
            $month = $balances[$member->id]['deposits_by_month'] ?? [];
            if (! empty($month[$yearMonth])) {
                $groups['saved'][] = $member->id;
            } elseif (in_array($member->id, $waiting, true)) {
                $groups['pending'][] = $member->id;
            } else {
                $groups['not_yet'][] = $member->id;
            }
        }

        $byId = $active->keyBy('id');

        return [
            'month' => $yearMonth,
            'active' => $active,
            'saved' => collect($groups['saved'])->map(fn ($id) => $byId[$id])->values(),
            'pending' => collect($groups['pending'])->map(fn ($id) => $byId[$id])->values(),
            'not_yet' => collect($groups['not_yet'])->map(fn ($id) => $byId[$id])->values(),
        ];
    }

    /**
     * Every loan with its schedule applied, in one set of queries. Screens that list loans read
     * this instead of asking for each loan one by one.
     */
    public static function loanLedgers(?string $cutOff = null): array
    {
        $cutOff = $cutOff ?: now()->toDateString();

        $loans = Loan::query()->with('member')->get();

        $repayments = Transaction::query()
            ->where('type', Transaction::REPAYMENT)
            ->posted()
            ->orderBy('value_date')
            ->orderBy('ref')
            ->get(['ref', 'amount', 'value_date', 'loan_id'])
            ->groupBy('loan_id');

        return $loans->map(function (Loan $loan) use ($repayments, $cutOff) {
            if (! $loan->disbursed_on) {
                return LoanLedger::for($loan, $cutOff);
            }

            return LoanLedger::apply(
                $loan,
                LoanSchedule::build(
                    (int) $loan->amount,
                    (float) $loan->rate,
                    (int) $loan->months,
                    $loan->method,
                    $loan->disbursed_on->toDateString()
                ),
                $repayments->get($loan->id, collect()),
                $cutOff
            );
        })->keyBy(fn (array $ledger) => $ledger['loan']->id)->all();
    }
}
