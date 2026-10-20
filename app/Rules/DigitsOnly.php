<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A phone number kept as digits and nothing else: no spaces, dashes, brackets, "+" or
 * letters. The country code, when there is one, is simply part of the number.
 */
class DigitsOnly implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[0-9]+$/', (string) $value)) {
            $fail('The :attribute must be numbers only.');
        }
    }
}
