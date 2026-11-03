<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Support\PegDates;
use App\Support\Policy;
use App\Support\TreasuryPosition;
use Illuminate\Support\Collection;

/**
 * How PEG turns loan interest into a share for each saver.
 *
 * The pool is what the group actually earned in the month: interest applied to member repayments,
 * less the interest PEG paid its own lender. Only the agreed share of that is distributed, the rest
 * stays as retained earnings. Each saver is credited in proportion to the savings they held at the
 * month's end, so a member who joined late or saved twice takes a bigger or smaller slice
 * accordingly. Rounding always goes down, which leaves a few shillings unshared rather than
 * crediting money the group does not have.
 */
class InterestDistribution
{
    public static function compute(string $yearMonth): array
    {
        $monthEnd = PegDates::monthEnd($yearMonth);

        $income = 0;
        $loans = Loan::query()->whereNotNull('disbursed_on')->with('member')->get();
        $repayments = Transaction::query()
            ->where('type', Transaction::REPAYMENT)
            ->where('status', Transaction::POSTED)
            ->orderBy('value_date')
            ->orderBy('ref')
            ->get(['ref', 'amount', 'value_date', 'loan_id'])
            ->groupBy('loan_id');

        foreach ($loans as $loan) {
            $ledger = LoanLedger::apply(
                $loan,
                LoanSchedule::build(
                    (int) $loan->amount,
                    (float) $loan->rate,
                    (int) $loan->months,
                    $loan->method,
                    $loan->disbursed_on->toDateString()
                ),
                $repayments->get($loan->id, collect()),
                $monthEnd
            );

            foreach ($ledger['splits'] as $split) {
                if (PegDates::monthOf($split['date']) === $yearMonth) {
                    $income += $split['interest'];
                }
            }
        }

        $externalInterest = (int) Transaction::query()
            ->where('type', Transaction::EXTERNAL_REPAYMENT)
            ->where('status', Transaction::POSTED)
            ->where('value_date', 'like', $yearMonth.'-%')
            ->get()
            ->sum(fn (Transaction $t) => (int) ($t->meta['interest'] ?? 0));

        $net = max(0, $income - $externalInterest);
        $pool = (int) floor($net * Policy::get('distribution_share') / 100);

        return [
            'month' => $yearMonth,
            'income' => $income,
            'external_interest' => $externalInterest,
            'net' => $net,
            'pool' => $pool,
            'share' => Policy::get('distribution_share'),
            'bases' => self::bases($monthEnd),
        ];
    }

    /**
     * Savings held by each member at the cut-off, as the base for the share.
     *
     * @return Collection<int, array{member_id: int, base: int}>
     */
    public static function bases(string $cutOff): Collection
    {
        return Member::query()
            ->get(['id'])
            ->map(fn (Member $member) => [
                'member_id' => $member->id,
                'base' => TreasuryPosition::savingsAt($member->id, $cutOff),
            ])
            ->filter(fn (array $row) => $row['base'] > 0)
            ->values();
    }

    /**
     * @param  Collection<int, array{member_id: int, base: int}>  $bases
     * @return array<int, array{member_id: int, base: int, amount: int}>
     */
    public static function allocate(int $pool, Collection $bases): array
    {
        $total = $bases->sum('base');

        if ($total <= 0 || $pool <= 0) {
            return [];
        }

        return $bases
            ->map(fn (array $row) => $row + ['amount' => (int) floor($pool * $row['base'] / $total)])
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * The share worked out from the month's own figures, as rows the entry table can store. Written
     * when the posting is booked so the Chairperson approves the amounts the savers will see.
     *
     * @return array<int, array{member_id: int, base: int, amount: int}>
     */
    public static function allocateToRows(int $pool, string $yearMonth): array
    {
        return self::allocate($pool, self::bases(PegDates::monthEnd($yearMonth)));
    }

    /**
     * The most recent month that has not been posted or put in front of the Chairperson yet.
     */
    public static function nextOpenMonth(): string
    {
        $posted = Transaction::query()
            ->where('type', Transaction::INTEREST_POSTING)
            ->where('status', '!=', Transaction::REJECTED)
            ->pluck('meta')
            ->map(fn ($meta) => $meta['month'] ?? null)
            ->filter()
            ->sort()
            ->values();

        return $posted->isEmpty()
            ? now()->startOfYear()->format('Y-m')
            : PegDates::nextMonth($posted->last());
    }
}
