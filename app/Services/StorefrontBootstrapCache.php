<?php

namespace App\Services;

use App\Models\Store;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StorefrontBootstrapCache
{
    public static function remember(Store $store, Closure $builder): array
    {
        try {
            return Cache::remember(
                self::key($store),
                now()->addSeconds(self::ttl()),
                $builder
            );
        } catch (\Throwable $exception) {
            report($exception);

            return $builder();
        }
    }

    public static function forget(Store $store): bool
    {
        try {
            return Cache::forget(self::key($store));
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public static function key(Store $store): string
    {
        return implode(':', [
            'storefront_bootstrap',
            (int) $store->id,
            (int) ($store->template_id ?? 0),
            'v7',
            self::versionToken((int) $store->id),
        ]);
    }

    private static function ttl(): int
    {
        return max(1, (int) config('storefront.bootstrap_cache_ttl', 120));
    }

    private static function versionToken(int $storeId): string
    {
        $parts = [
            'design' => self::maxUpdatedAt('designs', $storeId),
            'product' => self::maxUpdatedAt('products', $storeId),
            'banner' => self::maxUpdatedAt('banners', $storeId),
            'slider' => self::maxUpdatedAt('sliders', $storeId),
            'layout' => self::maxUpdatedAt('design_positions', $storeId),
            'header' => self::maxUpdatedAt('headersettings', $storeId),
        ];

        return substr(sha1(json_encode($parts)), 0, 16);
    }

    private static function maxUpdatedAt(string $table, int $storeId): int
    {
        try {
            $value = DB::table($table)
                ->where('store_id', $storeId)
                ->max('updated_at');
        } catch (\Throwable $exception) {
            report($exception);

            return 0;
        }

        return $value ? (strtotime((string) $value) ?: 0) : 0;
    }
}
