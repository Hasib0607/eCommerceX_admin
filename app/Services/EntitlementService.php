<?php

namespace App\Services;

use App\Models\PlanEntitlement;
use App\Models\SaasFeature;
use App\Models\Store;
use App\Models\StoreEntitlementOverride;
use Illuminate\Support\Collection;

class EntitlementService
{
    public function isEngineEnabled(): bool
    {
        $raw = (string) getSuperAdminSetting('entitlements_v1', '0');
        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    public function resolveForStore(Store $store): array
    {
        $features = SaasFeature::query()->get(['key', 'type', 'enabled_by_default', 'default_limit']);
        $planRows = PlanEntitlement::query()
            ->where('plan_id', (int) ($store->plan_id ?? 0))
            ->get(['feature_key', 'is_enabled', 'limit_value'])
            ->keyBy('feature_key');
        $storeRows = StoreEntitlementOverride::query()
            ->where('store_id', (int) $store->id)
            ->get(['feature_key', 'is_enabled', 'limit_value'])
            ->keyBy('feature_key');

        $result = [];
        foreach ($features as $feature) {
            $key = (string) $feature->key;
            $enabled = (bool) $feature->enabled_by_default;
            $limit = $feature->default_limit !== null ? (int) $feature->default_limit : null;

            $plan = $planRows->get($key);
            if ($plan) {
                $enabled = (bool) $plan->is_enabled;
                if ($plan->limit_value !== null) {
                    $limit = (int) $plan->limit_value;
                }
            }

            $override = $storeRows->get($key);
            if ($override) {
                if ($override->is_enabled !== null) {
                    $enabled = (bool) $override->is_enabled;
                }
                if ($override->limit_value !== null) {
                    $limit = (int) $override->limit_value;
                }
            }

            $result[$key] = [
                'enabled' => $enabled,
                'limit' => $limit,
                'type' => (string) $feature->type,
            ];
        }

        return $result;
    }

    public function can(array $entitlements, string $featureKey): bool
    {
        if (isset($entitlements[$featureKey])) {
            return (bool) ($entitlements[$featureKey]['enabled'] ?? false);
        }
        // Unknown keys are allowed for backward compatibility.
        return true;
    }

    public function limit(array $entitlements, string $featureKey): ?int
    {
        if (!isset($entitlements[$featureKey])) {
            return null;
        }
        $value = $entitlements[$featureKey]['limit'] ?? null;
        return $value === null ? null : (int) $value;
    }

    public function toFrontendPayload(array $entitlements): array
    {
        return collect($entitlements)
            ->map(function ($val, $key) {
                return [
                    'key' => $key,
                    'enabled' => (bool) ($val['enabled'] ?? false),
                    'limit' => $val['limit'] ?? null,
                    'type' => (string) ($val['type'] ?? 'action'),
                ];
            })
            ->values()
            ->all();
    }
}

