<?php

namespace App\Support;

use Closure;

/**
 * Bangladeshi phone numbers (spec R3). Numbers are stored in the local 0 form so duplicate checks
 * compare like with like; CRM-BR-02 asked for +880, which users never used.
 */
final class Phone
{
    public const MOBILE = '/^01[3-9]\d{8}$/';

    public const LANDLINE = '/^0[2-9]\d{6,9}$/';

    /**
     * Strip spaces, dashes and brackets and turn +880 / 880 prefixes into the local 0 form.
     */
    public static function normalise(?string $phone): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', (string) $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        return (string) preg_replace('/^\+?880(?=1)/', '0', $digits);
    }

    public static function isValid(?string $phone): bool
    {
        $phone = self::normalise($phone);

        return $phone !== null && (preg_match(self::MOBILE, $phone) === 1 || preg_match(self::LANDLINE, $phone) === 1);
    }

    /**
     * A validation rule closure for a normalised phone field.
     *
     * @return Closure(string, mixed, Closure): void
     */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! self::isValid($value)) {
                $fail(__('The :attribute must be a valid Bangladeshi phone number.'));
            }
        };
    }
}
