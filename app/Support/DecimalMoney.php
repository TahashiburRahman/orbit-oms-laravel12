<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** All arithmetic is performed in integer poisha, never binary floating point. */
final class DecimalMoney
{
    public static function minor(string $amount): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount with no more than two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function net(string $base, string $allowances, string $deductions): string
    {
        $minor = self::minor($base) + self::minor($allowances) - self::minor($deductions);
        if ($minor < 0 || $minor > 999999999999) {
            throw ValidationException::withMessages(['deductions' => 'Net pay must be between BDT 0.00 and 9,999,999,999.99.']);
        }

        return self::decimal($minor);
    }

    public static function display(string $amount): string
    {
        [$whole, $fraction] = explode('.', self::decimal(self::minor($amount)));

        return 'BDT '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }
}
