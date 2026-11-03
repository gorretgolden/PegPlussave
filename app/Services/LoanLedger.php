<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Transaction;
use App\Support\PegDates;
use Illuminate\Support\Collection;

/**
 * What a loan owes today. The schedule is never stored: it is rebuilt from the amount, rate,
 * method and payout date, and every posted repayment is then applied to it in the order the money
 * arrived, interest first. That is what keeps a part payment from being counted as a settled
 * installment, and what makes the arrears figure agree with the installment table on the screen.
 */
class LoanLedger
{
    /**
     * @param  Collection<int, array{ref: string, amount: int, value_date: string}>|null  $repayments  keyed by loan id
     */
    public static function for(Loan $loan, ?string $asOf = null, ?Collection $repayments = null): array
    {
        $asOf = $asOf ?: now()->toDateString();

        if (! $loan->disbursed_on) {
            return [
                'loan' => $loan,
                'state' => self::preDisbursementState($loan),
                'schedule' => [],
                'splits' => [],
                'balance' => 0,
                'outstandingPrincipal' => 0,
                'arrears' => 0,
                'daysLate' => 0,
                'missed' => 0,
                'next' => null,
                'principalPaid' => 0,
                'interestPaid' => 0,
                'totalDue' => 0,
                'paid' => 0,
            ];
        }

        $rows = LoanSchedule::build(
            (int) $loan->amount,
            (float) $loan->rate,
            (int) $loan->months,
            $loan->method,
            $loan->disbursed_on->toDateString()
        );

        $paid = $repayments
            ? ($repayments->get($loan->ref, collect()))
            : $loan->postedRepayments()->get(['ref', 'amount', 'value_date']);

        return self::apply($loan, $rows, $paid, $asOf);
    }

    /**
     * What is still owing on a loan at a date. Screens that only need the one figure read this
     * instead of building the whole installment table.
     */
    public static function balance(Loan $loan, ?string $asOf = null): int
    {
        return self::for($loan, $asOf)['balance'];
    }

    /**
     * @param  array<int, array{number: int, due: string, principal: int, interest: int, total: int}>  $rows
     * @param  iterable  $repayments
     */
    public static function apply(Loan $loan, array $rows, iterable $repayments, string $asOf): array
    {
        $schedule = array_map(fn (array $row) => $row + ['paidInterest' => 0, 'paidPrincipal' => 0], $rows);

        $splits = [];
        $pointer = 0;
        $interestPaid = 0;
        $principalPaid = 0;
        $paidTotal = 0;
        $lastPay = null;

        foreach ($repayments as $repayment) {
            $remaining = (int) $repayment->amount;
            $interestHere = 0;
            $principalHere = 0;

            while ($remaining > 0 && $pointer < count($schedule)) {
                $row = $pointer;

                $interestPart = min($remaining, $schedule[$row]['interest'] - $schedule[$row]['paidInterest']);
                $schedule[$row]['paidInterest'] += $interestPart;
                $remaining -= $interestPart;
                $interestHere += $interestPart;

                $principalPart = min($remaining, $schedule[$row]['principal'] - $schedule[$row]['paidPrincipal']);
                $schedule[$row]['paidPrincipal'] += $principalPart;
                $remaining -= $principalPart;
                $principalHere += $principalPart;

                // A payment that clears an installment lets the next one be touched; a short
                // payment leaves the rest of it unapplied rather than pushing it forward.
                if ($schedule[$row]['paidInterest'] >= $schedule[$row]['interest']
                    && $schedule[$row]['paidPrincipal'] >= $schedule[$row]['principal']) {
                    $pointer++;
                } else {
                    break;
                }
            }

            $splits[$repayment->ref] = [
                'interest' => $interestHere,
                'principal' => $principalHere,
                'date' => $repayment->value_date instanceof \Carbon\CarbonInterface
                    ? $repayment->value_date->toDateString()
                    : $repayment->value_date,
            ];

            $interestPaid += $interestHere;
            $principalPaid += $principalHere;
            $paidTotal += (int) $repayment->amount;
            $lastPay = $repayment->value_date;
        }

        $totalDue = array_sum(array_column($schedule, 'total'));
        $applied = $interestPaid + $principalPaid;
        $dueToDate = array_sum(array_column(
            array_filter($schedule, fn (array $row) => $row['due'] < $asOf),
            'total'
        ));

        $next = null;
        foreach ($schedule as $row) {
            if ($row['paidInterest'] + $row['paidPrincipal'] < $row['total']) {
                $next = $row;
                break;
            }
        }

        $daysLate = 0;
        if ($next && $next['due'] < $asOf) {
            $daysLate = max(0, PegDates::daysBetween($asOf, $next['due']));
        }

        $missed = count(array_filter(
            $schedule,
            fn (array $row) => $row['due'] < $asOf && $row['paidInterest'] + $row['paidPrincipal'] < $row['total']
        ));

        $balance = max(0, $totalDue - $applied);

        return [
            'loan' => $loan,
            'state' => $balance <= 0 ? 'Closed' : ($daysLate > 0 ? 'In arrears' : 'Current'),
            'schedule' => array_values($schedule),
            'splits' => $splits,
            'balance' => $balance,
            'outstandingPrincipal' => max(0, (int) $loan->amount - $principalPaid),
            'arrears' => max(0, $dueToDate - $applied),
            'daysLate' => $daysLate,
            'missed' => $missed,
            'next' => $next,
            'principalPaid' => $principalPaid,
            'interestPaid' => $interestPaid,
            'totalDue' => $totalDue,
            'paid' => $paidTotal,
            'lastPay' => $lastPay,
        ];
    }

    private static function preDisbursementState(Loan $loan): string
    {
        return match ($loan->status) {
            'requested' => 'Requested',
            'declined' => 'Declined',
            'rejected' => 'Rejected',
            'in_approval' => Transaction::query()
                ->where('loan_id', $loan->id)
                ->where('type', Transaction::LOAN_BOOKING)
                ->where('status', Transaction::PENDING_PATRON)
                ->exists() ? 'With Patron' : 'In approval',
            default => 'Requested',
        };
    }
}
