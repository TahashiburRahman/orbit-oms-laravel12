<?php

namespace App\Support;

use InvalidArgumentException;

class BangladeshPhone
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s()\-]/u', '', trim($phone));
        $phone = preg_replace('/^(?:\+88|88)/', '', $phone);
        if (! preg_match('/^01\d{9}$/D', $phone)) {
            throw new InvalidArgumentException('Use an 11-digit Bangladesh mobile number beginning with 01.');
        }

        return $phone;
    }
}
