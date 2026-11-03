<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The numbers the books are read by: TX-0042, LN-007, PEG-019. They are handed out in sequence and
 * never reused, because a member quoting "TX-0042" in a meeting has to mean one entry forever.
 */
class Refs
{
    public static function next(string $table, string $prefix, int $width): string
    {
        $last = (int) DB::table($table)
            ->where('ref', 'like', $prefix.'%')
            ->max(DB::raw('CAST(SUBSTRING(ref, '.(strlen($prefix) + 2).') AS UNSIGNED)'));

        return $prefix.str_pad((string) ($last + 1), $width, '0', STR_PAD_LEFT);
    }

    public static function transaction(): string
    {
        return self::next('transactions', 'TX-', 4);
    }

    public static function loan(): string
    {
        return self::next('loans', 'LN-', 3);
    }

    public static function externalLoan(): string
    {
        return self::next('external_loans', 'EXT-', 3);
    }

    public static function event(): string
    {
        return self::next('events', 'EV-', 3);
    }

    public static function notice(): string
    {
        return self::next('notices', 'NT-', 3);
    }

    public static function minute(): string
    {
        return self::next('minutes', 'MN-', 3);
    }

    public static function prospect(): string
    {
        return self::next('prospects', 'PR-', 3);
    }

    public static function paymentNotice(): string
    {
        return self::next('payment_notices', 'PN-', 3);
    }

    /**
     * Member codes fill the gaps left by members who have gone, so a register never shows the same
     * number twice and a leaver's history stays readable.
     */
    public static function member(): string
    {
        $used = \App\Models\Member::query()->pluck('code')->all();

        for ($n = 1; $n <= 10000; $n++) {
            $candidate = 'PEG-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            if (! in_array($candidate, $used, true)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No member code available.');
    }

    /**
     * The login name a new member gets: their last name, with a number on the end if somebody
     * already signs in under it.
     */
    public static function username(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        $base = strtolower(preg_replace('/[^a-z]/i', '', (string) end($parts))) ?: 'member';

        $username = $base;
        $suffix = 2;

        while (\App\Models\User::query()->where('username', $username)->exists()) {
            $username = $base.$suffix++;
        }

        return $username;
    }
}
