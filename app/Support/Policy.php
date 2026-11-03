<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The PEG constitution as numbers. Everything the committee can change without a code change
 * (rate, limits, the Patron threshold, how much interest is shared) lives here, and the defaults
 * are the policy the group adopted, so a fresh install behaves like the reviewed prototype.
 */
class Policy
{
    public const DEFAULTS = [
        'min_monthly' => 50000,
        'contribution_day' => 28,
        'loan_rate' => 3,
        'loan_method' => 'flat',
        'max_months' => 6,
        'loan_multiple' => 3,
        'min_saving_months' => 3,
        'patron_threshold' => 5000000,
        'distribution_share' => 90,
        'reserve_percent' => 10,
        'payout_month' => 12,
        'tenure_bands' => [
            ['upTo' => 1000000, 'months' => 3],
            ['upTo' => 3000000, 'months' => 4],
            ['upTo' => null, 'months' => 6],
        ],
    ];

    private static ?array $cached = null;

    public static function all(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $stored = Setting::query()->where('group', 'policy')->pluck('value', 'key')->all();

        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $raw = $stored[$key] ?? null;
            $values[$key] = match (true) {
                $raw === null => $default,
                $key === 'tenure_bands' => json_decode($raw, true) ?: $default,
                $key === 'loan_method' => $raw,
                is_int($default) => (int) $raw,
                default => (float) $raw,
            };
        }

        return self::$cached = $values;
    }

    public static function get(string $key)
    {
        return self::all()[$key] ?? null;
    }

    public static function put(string $key, $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key, 'group' => 'policy'],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );

        self::$cached = null;
        Cache::forget('peg:branding');
    }

    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::put($key, $value);
        }
    }

    /**
     * The longest a loan of this size may run. Bands are read low to high, and the last one has no
     * ceiling, so any amount lands on one of them; the group's overall cap still applies.
     */
    public static function maxTenure(int $amount): int
    {
        $settings = self::all();

        foreach ($settings['tenure_bands'] as $band) {
            if ($band['upTo'] === null || $amount <= $band['upTo']) {
                return min((int) $band['months'], (int) $settings['max_months']);
            }
        }

        return (int) $settings['max_months'];
    }

    /**
     * How the bands read in a sentence, for the loan form and the settings screen.
     */
    public static function tenureBandsText(): string
    {
        $floor = 0;
        $parts = [];

        foreach (self::get('tenure_bands') as $band) {
            $ceiling = $band['upTo'];
            $parts[] = ($ceiling === null
                ? 'Above '.self::short($floor)
                : 'Up to '.self::short($ceiling))
                .': '.min((int) $band['months'], (int) self::get('max_months')).' months';
            $floor = $ceiling ?: $floor;
        }

        return implode(' · ', $parts);
    }

    public static function short(int $amount): string
    {
        if ($amount >= 1_000_000) {
            return rtrim(rtrim(number_format($amount / 1_000_000, 2, '.', ''), '0'), '.').'M';
        }

        if ($amount >= 1_000) {
            return round($amount / 1_000).'K';
        }

        return (string) round($amount);
    }

    public static function flush(): void
    {
        self::$cached = null;
    }
}
