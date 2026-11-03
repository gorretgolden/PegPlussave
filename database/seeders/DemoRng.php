<?php

namespace Database\Seeders;

/**
 * The generator the reviewed prototype rolls its sample figures with, kept in PHP so the demo
 * books served from a database show the same amounts the committee signed off on screen.
 *
 * mulberry32 in JavaScript wraps through 32-bit integer coercion, so every step here masks the
 * same way: u32() keeps the low 32 bits, s32() reads them back as signed, and imul() multiplies
 * the two halves apart to avoid a 64-bit overflow the original never had.
 */
final class DemoRng
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = self::s32($seed);
    }

    public function next(): float
    {
        $this->state = self::s32($this->state + 0x6D2B79F5);

        $t = self::imul($this->state ^ self::u32($this->state) >> 15, 1 | $this->state);
        $t = self::s32(self::imul($t ^ self::u32($t) >> 7, 61 | $t) + $t) ^ $t;

        return (float) (self::u32(self::s32($t) ^ (self::u32($t) >> 14)) / 4294967296);
    }

    private static function u32(int $x): int
    {
        return $x & 0xFFFFFFFF;
    }

    private static function s32(int $x): int
    {
        $x = $x & 0xFFFFFFFF;

        return $x >= 0x80000000 ? $x - 0x100000000 : $x;
    }

    private static function imul(int $a, int $b): int
    {
        $a = self::u32($a);
        $b = self::u32($b);

        return self::s32(
            ($a & 0xFFFF) * ($b & 0xFFFF)
            + (((($a >> 16 & 0xFFFF) * ($b & 0xFFFF)) + (($a & 0xFFFF) * ($b >> 16 & 0xFFFF))) << 16)
        );
    }
}
