<?php

namespace App\Services;

use App\Models\QuickLogin;

class TrackingCredentialService
{
    public static function facebookForStore($storeId): ?array
    {
        if (empty($storeId)) {
            return null;
        }

        $credential = QuickLogin::query()
            ->where('store_id', $storeId)
            ->where('modulus_id', 11)
            ->first([
                'facebook_pixel',
                'general_access_token',
                'test_event_code',
            ]);

        if (!$credential) {
            return null;
        }

        return [
            'pixel_id' => trim((string) ($credential->facebook_pixel ?? '')),
            'access_token' => trim((string) ($credential->general_access_token ?? '')),
            'test_event_code' => trim((string) ($credential->test_event_code ?? '')),
        ];
    }
}
