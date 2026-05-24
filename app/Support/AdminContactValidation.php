<?php

namespace App\Support;

use Closure;

class AdminContactValidation
{
    public const PHONE_REGEX = '/^(?:\+?\d{1,4})?[0-9]\d{5,14}$/';

    public static function phoneRules(bool $required = false, int $max = 50): array
    {
        $rules = ['string', 'max:' . $max, 'regex:' . self::PHONE_REGEX];

        return array_merge([$required ? 'required' : 'nullable'], $rules);
    }

    public static function emailRules(bool $required = false, int $max = 255): array
    {
        $rules = ['string', 'email', 'max:' . $max];

        return array_merge([$required ? 'required' : 'nullable'], $rules);
    }

    public static function emailOrPhoneRules(bool $required = true, int $max = 255): array
    {
        return array_merge(
            [$required ? 'required' : 'nullable', 'string', 'max:' . $max],
            [self::emailOrPhoneRule()]
        );
    }

    public static function emailOrPhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $raw = trim((string) $value);
            if ($raw === '') {
                return;
            }

            if (str_contains($raw, '@')) {
                if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $fail('Enter a valid email address.');
                }

                return;
            }

            $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
            if ($digits === '' || !preg_match(self::PHONE_REGEX, $digits)) {
                $fail('Enter a valid phone number.');
            }
        };
    }

    public static function normalizePhone(?string $value): string
    {
        return preg_replace('/[^\d+]/', '', trim((string) $value)) ?? '';
    }

    public static function normalizeEmail(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    /**
     * Normalize BD/local phones for DB lookup (matches LoginRequest + staff records).
     */
    public static function normalizeBdPhoneForStorage(?string $value): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $value)) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '880') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (!str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    public static function phoneLoginLookupVariants(?string $value): array
    {
        $primary = self::normalizeBdPhoneForStorage($value);
        if ($primary === '') {
            return [];
        }

        $variants = [$primary];
        $withoutLeadingZero = ltrim($primary, '0');
        if ($withoutLeadingZero !== '' && $withoutLeadingZero !== $primary) {
            $variants[] = $withoutLeadingZero;
        }
        if ($withoutLeadingZero !== '') {
            $variants[] = '880' . $withoutLeadingZero;
            $variants[] = '+880' . $withoutLeadingZero;
        }

        return array_values(array_unique($variants));
    }
}
