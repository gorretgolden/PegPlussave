<?php

namespace App\Support;

/**
 * The way figures and dates are spoken to a member. Money is whole UGX with thousands separated,
 * never decimals, because the group keeps no cents; a month is written the way a chairperson reads
 * it out at a meeting.
 */
class Format
{
    public static function money($amount): string
    {
        return number_format((int) round((float) $amount));
    }

    public static function ugx($amount): string
    {
        return 'UGX '.self::money($amount);
    }

    /**
     * Money with the currency set small and grey beside it. Every figure on a screen is UGX, so the
     * mark is there to confirm it rather than to be read.
     */
    public static function cash($amount): string
    {
        return '<span class="cur">UGX</span>'.self::money($amount);
    }

    /**
     * A day of the month as it is said: the 28th, not 28.
     */
    public static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }

    /**
     * A signed figure: deposits and repayments read with a plus, money leaving with a minus, so a
     * ledger column can be totalled by eye.
     */
    public static function signed($amount): string
    {
        $amount = (int) round((float) $amount);

        return $amount < 0 ? '−'.self::money(abs($amount)) : '+'.self::money($amount);
    }

    public static function date(?string $date): string
    {
        return $date ? \Carbon\CarbonImmutable::parse($date)->format('j M Y') : '';
    }

    public static function dateTime(?string $stamp): string
    {
        return $stamp ? \Carbon\CarbonImmutable::parse($stamp)->format('j M Y, H:i') : '';
    }

    /**
     * A date without the year, for the days that sit inside one meeting cycle.
     */
    public static function dayMonth(?string $date): string
    {
        return $date ? \Carbon\CarbonImmutable::parse($date)->format('j M') : '';
    }

    /**
     * A month said in full, the way a deadline is read out: "by the 28th of November".
     */
    public static function monthName(?string $yearMonth): string
    {
        return $yearMonth ? \Carbon\CarbonImmutable::parse($yearMonth.'-01')->format('F') : '';
    }

    /**
     * The short month on its own, for the small square a calendar row begins with.
     */
    public static function monthTag(?string $date): string
    {
        return $date ? \Carbon\CarbonImmutable::parse($date)->format('M') : '';
    }

    public static function month(?string $yearMonth): string
    {
        if (! $yearMonth) {
            return '';
        }

        return PegDates::monthLabel($yearMonth);
    }

    /**
     * A percentage as it is said out loud: 3%, not 3.00%. A rate is a policy figure, not a
     * measurement, so the trailing zeros carry no information.
     */
    public static function rate($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%';
    }

    public static function percent($value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals).'%';
    }

    /**
     * How long ago something happened, in the words a notice board uses: "just now", "3 hours ago".
     */
    public static function ago(?string $stamp): string
    {
        return $stamp ? \Carbon\CarbonImmutable::parse($stamp)->diffForHumans(['precision' => 1]) : '';
    }

    /**
     * A short figure for an axis label or a band: 1.5M, 50K. The books keep the whole number; only
     * the drawing is compressed.
     */
    public static function compact($amount): string
    {
        $amount = (int) round((float) $amount);

        return match (true) {
            $amount >= 1_000_000 => rtrim(rtrim(number_format($amount / 1_000_000, 2, '.', ''), '0'), '.').'M',
            $amount >= 1_000 => (string) (int) round($amount / 1_000).'K',
            default => (string) $amount,
        };
    }

    /**
     * The initial pair on the avatar. Both names are used because two members can share a surname.
     */
    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[count($parts) - 1], 0, 1).substr($parts[0], 0, 1));
    }

    /**
     * The name people are called by. In this group it is the last one written.
     */
    public static function given(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return (string) end($parts);
    }

    /**
     * The top of a chart scale, rounded to a number a reader can divide in their head.
     */
    public static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }

        $exponent = 10 ** floor(log10($value));
        $fraction = $value / $exponent;

        $step = match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 2.5 => 2.5,
            $fraction <= 5 => 5,
            default => 10,
        };

        return $step * $exponent;
    }
}
