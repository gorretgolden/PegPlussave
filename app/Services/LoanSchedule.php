<?php

namespace App\Services;

use App\Support\PegDates;

/**
 * The two ways PEG splits a loan. Flat is the policy default: the interest is fixed on the money
 * borrowed, so every installment carries the same interest figure. Reducing charges interest on
 * what is still owing, which is the alternative the committee can switch to in policy settings.
 */
class LoanSchedule
{
    /**
     * @return array<int, array{number: int, due: string, principal: int, interest: int, total: int}>
     */
    public static function build(int $principal, float $ratePercent, int $months, string $method, string $startDate): array
    {
        if ($principal <= 0 || $months <= 0) {
            return [];
        }

        $rate = $ratePercent / 100;

        return $method === 'reducing'
            ? self::reducing($principal, $rate, $months, $startDate)
            : self::flat($principal, $rate, $months, $startDate);
    }

    private static function flat(int $principal, float $rate, int $months, string $startDate): array
    {
        $principalEach = (int) round($principal / $months);
        $interestEach = (int) round($principal * $rate);
        $rows = [];

        for ($i = 1; $i <= $months; $i++) {
            // The last installment takes whatever the rounding left, so the book closes on the
            // amount that was actually paid out rather than a few shillings short of it.
            $principalPart = $i === $months
                ? $principal - $principalEach * ($months - 1)
                : $principalEach;

            $rows[] = self::row($i, $startDate, $principalPart, $interestEach);
        }

        return $rows;
    }

    private static function reducing(int $principal, float $rate, int $months, string $startDate): array
    {
        $balance = $principal;
        $instalment = $rate === 0.0
            ? (int) round($principal / $months)
            : (int) round($principal * $rate / (1 - (1 + $rate) ** -$months));
        $rows = [];

        for ($i = 1; $i <= $months; $i++) {
            $interest = (int) round($balance * $rate);
            $principalPart = $i === $months ? $balance : (int) round($instalment - $interest);
            $balance -= $principalPart;

            $rows[] = self::row($i, $startDate, $principalPart, $interest);
        }

        return $rows;
    }

    private static function row(int $number, string $startDate, int $principal, int $interest): array
    {
        return [
            'number' => $number,
            'due' => PegDates::addMonths($startDate, $number),
            'principal' => $principal,
            'interest' => $interest,
            'total' => $principal + $interest,
        ];
    }
}
