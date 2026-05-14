<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;

class ReactAdminEntitlementMiddleware
{
    public function __construct(private EntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->entitlements->isEngineEnabled()) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $customer = Customer::where('uid', $user->id)->first();
        if (!$customer) {
            return $next($request);
        }

        $requestedStore = trim((string) $request->query('store_id', ''));
        $storeId = $requestedStore !== '' ? $requestedStore : (string) ($customer->active_store ?? '');
        if ($storeId === '' || $storeId === '0') {
            return $next($request);
        }

        $store = Store::query()
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->where('customer_id', $customer->id)
            ->first();
        if (!$store) {
            return $next($request);
        }

        $routeFeature = $this->mapFeatureKey($request);
        if (!$routeFeature) {
            return $next($request);
        }

        $entitlements = $this->entitlements->resolveForStore($store);
        if (!$this->entitlements->can($entitlements, $routeFeature)) {
            return response()->json([
                'message' => 'This feature is not available in your current package.',
                'code' => 'feature_not_enabled',
                'feature' => $routeFeature,
            ], 403);
        }

        $quotaError = $this->checkQuota($request, $entitlements, (string) $store->id, (string) $customer->id);
        if ($quotaError) {
            return $quotaError;
        }

        return $next($request);
    }

    private function mapFeatureKey(Request $request): ?string
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        $method = strtoupper((string) $request->method());

        if (str_starts_with($path, '/react-admin-api/products')) {
            if ($method === 'GET') return 'pages.products';
            if ($method === 'POST') return 'actions.products.create';
            if (in_array($method, ['PUT', 'PATCH'], true)) return 'actions.products.update';
            if ($method === 'DELETE') return 'actions.products.delete';
        }

        if (str_starts_with($path, '/react-admin-api/catalog/categories')) {
            if ($method === 'GET') {
                $scope = trim((string) $request->query('scope', 'categories'));
                return $scope === 'subcategories' ? 'pages.products.subcategories' : 'pages.products.categories';
            }
            if ($method === 'POST') return 'actions.catalog.categories.create';
            if (in_array($method, ['PUT', 'PATCH'], true)) return 'actions.catalog.categories.update';
            if ($method === 'DELETE') return 'actions.catalog.categories.delete';
        }

        if (str_starts_with($path, '/react-admin-api/catalog/brands')) {
            if ($method === 'GET') return 'pages.products.brands';
            if ($method === 'POST') return 'actions.catalog.brands.create';
            if (in_array($method, ['PUT', 'PATCH'], true)) return 'actions.catalog.brands.update';
            if ($method === 'DELETE') return 'actions.catalog.brands.delete';
        }

        if (str_starts_with($path, '/react-admin-api/catalog/variants')) {
            if ($method === 'GET') return 'pages.products.variants';
            if ($method === 'POST' && str_contains($path, '/reorder/')) return 'actions.catalog.variants.reorder';
            if ($method === 'POST') return 'actions.catalog.variants.create';
            if (in_array($method, ['PUT', 'PATCH'], true)) return 'actions.catalog.variants.update';
            if ($method === 'DELETE') return 'actions.catalog.variants.delete';
        }

        if (str_starts_with($path, '/react-admin-api/media-library')) {
            if ($method === 'GET' && str_contains($path, '/file')) return 'pages.mediaLibrary';
            if ($method === 'GET') return 'pages.mediaLibrary';
            if ($method === 'POST') return 'actions.media.upload';
            if ($method === 'DELETE') return 'actions.media.delete';
        }

        if (str_starts_with($path, '/react-admin-api/notifications')) {
            return 'pages.notifications';
        }

        if (str_starts_with($path, '/react-admin-api/stores') || str_starts_with($path, '/react-admin-api/me')) {
            return null;
        }

        return null;
    }

    private function checkQuota(Request $request, array $entitlements, string $storeId, string $customerId)
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        $method = strtoupper((string) $request->method());

        if ($method === 'POST' && str_starts_with($path, '/react-admin-api/products')) {
            $limit = $this->entitlements->limit($entitlements, 'quota.products.max');
            if ($limit !== null) {
                $used = Product::query()
                    ->where('store_id', $storeId)
                    ->where('customer_id', $customerId)
                    ->count();
                if ($used >= $limit) {
                    return response()->json([
                        'message' => 'Product limit reached for your package.',
                        'code' => 'quota_exceeded',
                        'feature' => 'quota.products.max',
                    ], 429);
                }
            }
        }

        return null;
    }
}
