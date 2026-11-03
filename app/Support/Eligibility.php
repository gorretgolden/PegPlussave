<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanLedger;
use Illuminate\Support\Collection;

/**
 * The six questions a loan has to answer before it is booked, and a seventh when the Treasurer is
 * the one asking. Each check is returned whether it passes or not so the screen can show what was
 * met and what was missed, and a soft check warns without stopping the entry.
 */
class Eligibility
{
    /**
     * @param  array{treasury?:bool,excludeLoan?:int|null,addBack?:int|null,asOf?:string|null}  $options
     * @return array<int, array{label:string, ok:bool, detail:string, soft?:bool}>
     */
    public static function checks(Member|int $member, int $amount, int $months, array $options = []): array
    {
        $member = $member instanceof Member ? $member : Member::findOrFail($member);
        $settings = Policy::all();
        $asOf = $options['asOf'] ?? now()->toDateString();
        $excludeLoan = $options['excludeLoan'] ?? null;

        $balances = TreasuryPosition::memberBalances($asOf);
        $balance = $balances[$member->id] ?? ['balance' => 0, 'months' => []];

        $limit = (int) $balance['balance'] * (int) $settings['loan_multiple'];
        $monthsSaved = count($balance['months']);
        $running = self::runningLoans($member, $excludeLoan, $asOf);

        $lastMonth = PegDates::previousMonth(PegDates::monthOf($asOf));
        $savedLast = in_array($lastMonth, $balance['months'], true)
            || $member->joined_on->format('Y-m') > $lastMonth;

        $maxTenure = Policy::maxTenure($amount);

        $checks = [
            [
                'label' => 'Active PEG member',
                'ok' => $member->isActive() && ! $member->exit_flagged,
                'detail' => $member->exit_flagged ? 'Exit notice recorded' : ucfirst($member->status),
            ],
            [
                'label' => "Saved for at least {$settings['min_saving_months']} months",
                'ok' => $monthsSaved >= (int) $settings['min_saving_months'],
                'detail' => "{$monthsSaved} months saved",
            ],
            [
                'label' => 'No running loan or open application',
                'ok' => $running->isEmpty(),
                'detail' => $running->isEmpty() ? 'None' : $running->map->ref->implode(', '),
            ],
            [
                'label' => "Within {$settings['loan_multiple']}× savings",
                'ok' => $amount > 0 && $amount <= $limit,
                'detail' => 'Limit '.Format::ugx($limit),
            ],
            [
                'label' => 'Tenure within policy',
                'ok' => $months >= 1 && $months <= $maxTenure,
                'detail' => "Up to {$maxTenure} months for this amount",
            ],
            [
                'label' => 'Contributions up to date',
                'ok' => $savedLast,
                'soft' => true,
                'detail' => $savedLast
                    ? Format::month($lastMonth).' saved'
                    : 'No saving for '.Format::month($lastMonth),
            ],
        ];

        if (! empty($options['treasury'])) {
            $available = TreasuryPosition::available($asOf, $options['addBack'] ?? 0, $excludeLoan);
            $checks[] = [
                'label' => 'Funds available in PEG',
                'ok' => $amount <= $available,
                'detail' => 'Available '.Format::ugx($available),
            ];
        }

        return $checks;
    }

    /**
     * Count how many of the hard checks pass; a soft one is never held against a member.
     *
     * @return array<int, array{label:string, ok:bool, detail:string, soft?:bool}>
     */
    public static function hard(array $checks): array
    {
        return array_values(array_filter($checks, fn (array $check) => ! $check['ok'] && empty($check['soft'])));
    }

    public static function met(array $checks): int
    {
        return count(array_filter($checks, fn (array $check) => $check['ok']));
    }

    public static function passes(array $checks): bool
    {
        return self::hard($checks) === [];
    }

    /**
     * Loans that still hold the member back: one being checked, or one paid out and not yet closed.
     *
     * @return Collection<int, Loan>
     */
    public static function runningLoans(Member $member, ?int $excludeLoan = null, ?string $asOf = null): Collection
    {
        $asOf = $asOf ?: now()->toDateString();

        return $member->loans()
            ->where('id', '!=', $excludeLoan)
            ->get()
            ->filter(function (Loan $loan) use ($asOf) {
                if (in_array($loan->status, [Loan::REQUESTED, Loan::IN_APPROVAL], true)) {
                    return true;
                }
                if (! $loan->disbursed_on) {
                    return false;
                }

                return LoanLedger::for($loan, $asOf)['state'] !== 'Closed';
            });
    }
}
