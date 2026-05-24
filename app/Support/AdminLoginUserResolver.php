<?php

namespace App\Support;

use App\Models\Staff;
use App\Models\Superstaff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminLoginUserResolver
{
    public const PANEL_USER_TYPES = [
        'admin',
        'affiliate',
        'superadmin',
        'dropshipper',
        'superstaff',
        'staff',
    ];

    public static function resolve(string $emailOrPhone, bool $isEmail): ?User
    {
        $credential = trim($emailOrPhone);
        if ($credential === '') {
            return null;
        }

        $user = User::query()
            ->where(function ($query) use ($isEmail, $credential) {
                if ($isEmail) {
                    $query->where('email', $credential);
                } else {
                    $query->whereIn('phone', AdminContactValidation::phoneLoginLookupVariants($credential));
                }
            })
            ->whereIn('type', self::PANEL_USER_TYPES)
            ->first();

        if ($user) {
            return $user;
        }

        if ($isEmail) {
            $normalizedEmail = AdminContactValidation::normalizeEmail($credential);

            $superstaff = Superstaff::query()
                ->where(function ($query) use ($credential, $normalizedEmail) {
                    $query->where('email', $credential)
                        ->orWhere('username', $credential);
                    if ($normalizedEmail !== '') {
                        $query->orWhere('email', $normalizedEmail);
                    }
                })
                ->first();

            if ($superstaff?->uid) {
                return User::query()
                    ->where('id', $superstaff->uid)
                    ->whereIn('type', self::PANEL_USER_TYPES)
                    ->first();
            }

            $storeStaff = Staff::query()
                ->where(function ($query) use ($credential, $normalizedEmail) {
                    $query->where('email', $credential)
                        ->orWhere('username', $credential);
                    if ($normalizedEmail !== '') {
                        $query->orWhere('email', $normalizedEmail);
                    }
                })
                ->first();

            if ($storeStaff?->uid) {
                return User::query()
                    ->where('id', $storeStaff->uid)
                    ->whereIn('type', self::PANEL_USER_TYPES)
                    ->first();
            }

            return null;
        }

        $phoneVariants = AdminContactValidation::phoneLoginLookupVariants($credential);
        if (empty($phoneVariants)) {
            return null;
        }

        $superstaff = Superstaff::query()->whereIn('phone', $phoneVariants)->first();
        if ($superstaff?->uid) {
            return User::query()
                ->where('id', $superstaff->uid)
                ->whereIn('type', self::PANEL_USER_TYPES)
                ->first();
        }

        $storeStaff = Staff::query()->whereIn('phone', $phoneVariants)->first();
        if ($storeStaff?->uid) {
            return User::query()
                ->where('id', $storeStaff->uid)
                ->whereIn('type', self::PANEL_USER_TYPES)
                ->first();
        }

        return null;
    }

    public static function passwordsMatch(User $user, string $password): bool
    {
        if (Hash::check($password, (string) $user->password)) {
            return true;
        }

        $type = strtolower((string) $user->type);
        if ($type === 'superstaff') {
            $staff = Superstaff::query()->where('uid', $user->id)->first();
            return $staff && is_string($staff->password) && $staff->password !== '' && Hash::check($password, $staff->password);
        }

        if ($type === 'staff') {
            $staff = Staff::query()->where('uid', $user->id)->first();
            return $staff && is_string($staff->password) && $staff->password !== '' && Hash::check($password, $staff->password);
        }

        return false;
    }

    public static function isActive(User $user): bool
    {
        $type = strtolower((string) $user->type);

        if ($type === 'superstaff') {
            $staff = Superstaff::query()->where('uid', $user->id)->first();
            return $staff && strtolower((string) $staff->status) === 'active';
        }

        if ($type === 'staff') {
            $staff = Staff::query()->where('uid', $user->id)->first();
            return $staff && strtolower((string) $staff->status) === 'active';
        }

        return true;
    }
}
