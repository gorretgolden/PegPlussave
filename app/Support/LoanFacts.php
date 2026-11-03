<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\Member;

/**
 * What a loan form needs to answer before it is sent, in one read. The server uses it to decide
 * whether a request may go in; the same figures go to the browser so the preview under the amount
 * box moves with the amount being typed rather than sitting at whatever the form opened with.
 */
class LoanFacts
{
    /**
     * @param  array<int, int>|null  $memberIds  null means every member
     * @return array<int, array{name: string, balance: int, months: int, active: bool, exit: bool, savedLast: bool, runningRefs: array<int, string>}>
     */
    public static function for(?array $memberIds = null, ?int $excludeLoan = null): array
    {
        $balances = TreasuryPosition::memberBalances();
        $ledgers = TreasuryPosition::loanLedgers();
        $lastMonth = PegDates::previousMonth(PegDates::monthOf(now()->toDateString()));

        $members = Member::query()
            ->when($memberIds, fn ($query) => $query->whereIn('id', $memberIds))
            ->with('loans')
            ->orderBy('name')
            ->get();

        return $members->mapWithKeys(function (Member $member) use ($balances, $ledgers, $lastMonth, $excludeLoan) {
            $row = $balances[$member->id] ?? ['balance' => 0, 'months' => []];

            $running = $member->loans
                ->reject(fn (Loan $loan) => $loan->id === $excludeLoan)
                ->filter(function (Loan $loan) use ($ledgers) {
                    if (in_array($loan->status, [Loan::REQUESTED, Loan::IN_APPROVAL], true)) {
                        return true;
                    }

                    return $loan->disbursed_on && ($ledgers[$loan->id]['state'] ?? 'Closed') !== 'Closed';
                });

            return [
                $member->id => [
                    'name' => $member->name,
                    'balance' => (int) $row['balance'],
                    'months' => count($row['months']),
                    'active' => $member->isActive(),
                    'exit' => (bool) $member->exit_flagged,
                    'savedLast' => in_array($lastMonth, $row['months'], true)
                        || $member->joined_on->format('Y-m') > $lastMonth,
                    'runningRefs' => $running->pluck('ref')->all(),
                ],
            ];
        })->all();
    }

    /**
     * The policy figures a form has to hold so the preview can apply the rules the server will apply.
     *
     * @return array<string, mixed>
     */
    public static function policy(?int $available = null): array
    {
        $settings = Policy::all();

        return [
            'rate' => (float) $settings['loan_rate'],
            'method' => $settings['loan_method'],
            'multiple' => (int) $settings['loan_multiple'],
            'minMonthsSaved' => (int) $settings['min_saving_months'],
            'maxMonths' => (int) $settings['max_months'],
            'bands' => $settings['tenure_bands'],
            'patronThreshold' => (int) $settings['patron_threshold'],
            'minMonthly' => (int) $settings['min_monthly'],
            'lastMonth' => Format::month(PegDates::previousMonth(PegDates::monthOf(now()->toDateString()))),
            'available' => $available,
        ];
    }
}
