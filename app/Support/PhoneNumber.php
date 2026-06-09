<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class PhoneNumber
{
    /**
     * Normalise any Iranian mobile representation to the canonical 09XXXXXXXXX
     * form used as the unique key on the users table.
     *
     * Accepts: 09123456789, 9123456789, +989123456789, 00989123456789, 989123456789
     */
    public static function normalize(string $raw): string
    {
        // Keep digits only (drops +, spaces, dashes).
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // Strip international prefixes down to the national 9XXXXXXXXX core.
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // At this point we expect 9XXXXXXXXX (10 digits, leading 9).
        if (!preg_match('/^9\d{9}$/', $digits)) {
            throw new InvalidArgumentException("Invalid Iranian mobile number: {$raw}");
        }

        return '0' . $digits;
    }
}
