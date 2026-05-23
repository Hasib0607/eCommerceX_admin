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
}
