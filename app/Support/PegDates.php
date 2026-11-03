<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Every PEG date rule runs on calendar months, not fixed 30-day spans: an installment due on the
 * 31st of a month falls on the last day of a shorter month, and a posting month closes on its own
 * last day. Keeping that in one place is what stops a February repayment drifting a day forward.
 */
class PegDates
{
    public static function addMonths(string $date, int $months): string
    {
        return CarbonImmutable::parse($date)->addMonthsNoOverflow($months)->toDateString();
    }

    public static function addDays(string $date, int $days): string
    {
        return CarbonImmutable::parse($date)->addDays($days)->toDateString();
    }

    public static function monthEnd(string $yearMonth): string
    {
        return CarbonImmutable::parse($yearMonth.'-01')->endOfMonth()->toDateString();
    }

    public static function nextMonth(string $yearMonth): string
    {
        return CarbonImmutable::parse($yearMonth.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    public static function previousMonth(string $yearMonth): string
    {
        return CarbonImmutable::parse($yearMonth.'-01')->subMonthNoOverflow()->format('Y-m');
    }

    /**
     * The months a member may still be paying into, newest first: the one we are in and the ones
     * behind it that have not been shared out yet.
     *
     * @return array<int, string>
     */
    public static function recentMonths(int $count, ?string $from = null): array
    {
        $month = $from ?: now()->format('Y-m');
        $months = [$month];

        for ($i = 1; $i < $count; $i++) {
            $month = self::previousMonth($month);
            $months[] = $month;
        }

        return $months;
    }

    /**
     * Every month in a span, oldest first. The register and the charts both run on this spine, so a
     * month with no entries still appears as a zero rather than vanishing from the row.
     *
     * @return array<int, string>
     */
    public static function monthSpan(string $from, string $to): array
    {
        $months = [];
        $cursor = $from;

        while ($cursor <= $to) {
            $months[] = $cursor;
            $cursor = self::nextMonth($cursor);
        }

        return $months;
    }

    public static function monthOf(string $date): string
    {
        return substr($date, 0, 7);
    }

    public static function daysBetween(string $later, string $earlier): int
    {
        return Carbon::parse($earlier)->diffInDays(Carbon::parse($later), false);
    }

    public static function label(?string $date): string
    {
        return $date ? CarbonImmutable::parse($date)->format('j M Y') : '';
    }

    public static function monthLabel(string $yearMonth): string
    {
        return CarbonImmutable::parse($yearMonth.'-01')->format('M Y');
    }
}
