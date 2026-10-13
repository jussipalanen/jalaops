<?php

namespace App\Support;

/**
 * Finnish reference numbers (viitenumero) for invoices.
 *
 * The check digit comes from the base number's digits weighted 7, 3, 1, 7, 3,
 * 1, … from the right: it's the difference between the sum and the next
 * multiple of ten (0 when the sum already is one).
 */
class ReferenceNumber
{
    private const WEIGHTS = [7, 3, 1];

    /**
     * The reference number for a base number of 3–19 digits, e.g. 1001 → "10016".
     */
    public static function fromBase(int|string $base): string
    {
        $digits = (string) $base;

        if (! preg_match('/^\d{3,19}$/', $digits)) {
            throw new \InvalidArgumentException('A reference number needs a base of 3–19 digits.');
        }

        return $digits.self::checkDigit($digits);
    }

    /**
     * Whether a reference number's check digit is right.
     */
    public static function isValid(string $reference): bool
    {
        $digits = preg_replace('/\s/', '', $reference);

        return (bool) preg_match('/^\d{4,20}$/', $digits)
            && self::checkDigit(substr($digits, 0, -1)) === (int) substr($digits, -1);
    }

    /**
     * Grouped in fives from the right, as printed on invoices: "1234 56789".
     */
    public static function format(string $reference): string
    {
        return trim(strrev(chunk_split(strrev($reference), 5, ' ')));
    }

    private static function checkDigit(string $digits): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $index => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$index % 3];
        }

        return (10 - $sum % 10) % 10;
    }
}
