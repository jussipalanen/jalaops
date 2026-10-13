<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Finnish business ID (y-tunnus): seven digits, a hyphen and a check digit.
 * The check digit comes from the digits weighted 7, 9, 10, 5, 8, 4 and 2:
 * the remainder of their sum divided by 11 gives 0 for 0, never 1, otherwise
 * 11 minus the remainder.
 */
class BusinessId implements ValidationRule
{
    private const WEIGHTS = [7, 9, 10, 5, 8, 4, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{7})-(\d)$/', $value, $match)) {
            $fail('Y-tunnuksen muoto on 1234567-8.');

            return;
        }

        $sum = 0;
        foreach (str_split($match[1]) as $index => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$index];
        }

        $remainder = $sum % 11;
        $check = $remainder === 0 ? 0 : 11 - $remainder;

        if ($remainder === 1 || $check !== (int) $match[2]) {
            $fail('Y-tunnuksen tarkistusmerkki ei täsmää.');
        }
    }
}
