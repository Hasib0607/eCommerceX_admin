<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Staff;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminReactPromotionController extends Controller
{
    private function authType(): string
    {
        return strtolower((string) (Auth::user()->type ?? ''));
    }

    private function resolveStoreId(Request $request): int
    {
        $requestedStoreId = (int) $request->query('store_id', 0);
        if ($requestedStoreId > 0) {
            return $requestedStoreId;
        }

        $authUser = Auth::user();
        if (!$authUser) {
            return 0;
        }

        $type = $this->authType();
        if (in_array($type, ['admin', 'dropshipper'], true)) {
            $customer = Customer::where('uid', $authUser->id)->first();
            return (int) ($customer->active_store ?? 0);
        }

        if ($type === 'staff') {
            $staff = Staff::where('uid', $authUser->id)->first();
            return (int) ($staff->store_id ?? 0);
        }

        return 0;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasColumn($table, $column);
        }

        return $cache[$key];
    }

    private function currentStore(Request $request): ?Store
    {
        $storeId = $this->resolveStoreId($request);
        return $storeId > 0 ? Store::find($storeId) : null;
    }

    private function statusFromBool(bool $value): string
    {
        return $value ? 'active' : 'inactive';
    }

    private function statusToBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return strtolower((string) $value) === 'active';
    }

    private function normalizeCsvIds($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value), fn ($item) => $item !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($item) => $item !== ''));
    }

    private function csvString($value): ?string
    {
        $items = $this->normalizeCsvIds($value);
        return count($items) ? implode(',', array_unique($items)) : null;
    }

    private function scopeQuery(string $type, Request $request)
    {
        $storeId = $this->resolveStoreId($request);
        $model = match ($type) {
            'coupons' => Coupon::query(),
            'campaigns' => Campaign::query(),
            default => Offer::query(),
        };

        if ($storeId > 0 && $this->tableHasColumn($model->getModel()->getTable(), 'store_id')) {
            $model->where('store_id', $storeId);
        }

        if ($this->tableHasColumn($model->getModel()->getTable(), 'position')) {
            $model->orderBy('position');
        }

        return $model->orderByDesc('id');
    }

    private function appendSharedMeta($model, string $table, Request $request): void
    {
        $authId = (int) (Auth::id() ?? 0);
        $store = $this->currentStore($request);
        $storeId = (int) ($store->id ?? 0);
        $customer = Customer::where('uid', $authId)->first();

        if ($this->tableHasColumn($table, 'uid')) {
            $model->uid = $authId;
        }
        if ($this->tableHasColumn($table, 'customer_id')) {
            $model->customer_id = (int) ($customer->id ?? 0);
        }
        if ($this->tableHasColumn($table, 'store_id')) {
            $model->store_id = $storeId;
        }
        if ($this->tableHasColumn($table, 'creator') && !$model->exists) {
            $model->creator = $authId;
        }
        if ($this->tableHasColumn($table, 'editor')) {
            $model->editor = $authId;
        }
        if ($this->tableHasColumn($table, 'currency_id') && $store) {
            $model->currency_id = (int) ($store->currency ?? 0);
        }
    }

    private function nextPosition(string $table, Request $request): int
    {
        if (!$this->tableHasColumn($table, 'position')) {
            return 0;
        }

        $query = match ($table) {
            'coupons' => Coupon::query(),
            'campaigns' => Campaign::query(),
            default => Offer::query(),
        };

        $storeId = $this->resolveStoreId($request);
        if ($storeId > 0 && $this->tableHasColumn($table, 'store_id')) {
            $query->where('store_id', $storeId);
        }

        return (int) $query->max('position') + 1;
    }

    private function serializeCoupon(Coupon $coupon): array
    {
        return [
            'id' => (int) $coupon->id,
            'name' => (string) ($coupon->name ?? ''),
            'code' => (string) ($coupon->code ?? ''),
            'start_date' => (string) ($coupon->start_date ?? ''),
            'end_date' => (string) ($coupon->end_date ?? ''),
            'min_purchase' => (string) ($coupon->min_purchase ?? ''),
            'max_purchase' => (string) ($coupon->max_purchase ?? ''),
            'max_use' => (string) ($coupon->max_use ?? ''),
            'shipping_area' => $this->tableHasColumn('coupons', 'shipping_area') ? (string) ($coupon->shipping_area ?? '') : '',
            'payment_method' => $this->tableHasColumn('coupons', 'payment_method') ? (string) ($coupon->payment_method ?? '') : '',
            'auto_apply' => $this->tableHasColumn('coupons', 'auto_apply') ? (int) ($coupon->auto_apply ?? 0) : 0,
            'discount_type' => (string) ($coupon->discount_type ?? 'percent'),
            'discount_amount' => (string) ($coupon->discount_amount ?? ''),
            'status' => $this->statusToBool($coupon->status ?? ''),
            'position' => $this->tableHasColumn('coupons', 'position') ? (int) ($coupon->position ?? 0) : 0,
            'created_at' => optional($coupon->created_at)->toISOString(),
            'updated_at' => optional($coupon->updated_at)->toISOString(),
        ];
    }

    private function serializeCampaign(Campaign $campaign): array
    {
        $shippingAreas = $this->tableHasColumn('campaigns', 'shipping_area')
            ? $this->normalizeCsvIds($campaign->shipping_area ?? '')
            : [];

        return [
            'id' => (int) $campaign->id,
            'name' => (string) ($campaign->name ?? ''),
            'length_type' => (string) ($campaign->length_type ?? 'date_range'),
            'start_date' => (string) ($campaign->start_date ?? ''),
            'end_date' => (string) ($campaign->end_date ?? ''),
            'specific_date' => (string) ($campaign->specific_dates ?? ''),
            'repeat_dates' => $this->normalizeCsvIds($campaign->repeat_dates ?? ''),
            'time_enabled' => filled($campaign->start_time) || filled($campaign->end_time),
            'start_time' => (string) ($campaign->start_time ?? ''),
            'end_time' => (string) ($campaign->end_time ?? ''),
            'discount_type' => (string) ($campaign->discount_type ?? 'percent'),
            'discount_amount' => (string) ($campaign->discount_amount ?? ''),
            'shipping_areas' => $shippingAreas,
            'shipping_area' => count($shippingAreas) ? $shippingAreas[0] : '',
            'campaign_type' => (string) ($campaign->campaign_type ?? 'product'),
            'product_ids' => $this->normalizeCsvIds($campaign->products ?? ''),
            'category_ids' => $this->normalizeCsvIds($campaign->category ?? ''),
            'status' => $this->statusToBool($campaign->status ?? ''),
            'position' => $this->tableHasColumn('campaigns', 'position') ? (int) ($campaign->position ?? 0) : 0,
            'created_at' => optional($campaign->created_at)->toISOString(),
            'updated_at' => optional($campaign->updated_at)->toISOString(),
        ];
    }

    private function serializeOffer(Offer $offer): array
    {
        return [
            'id' => (int) $offer->id,
            'name' => (string) ($offer->name ?? ''),
            'start_date' => (string) ($offer->start_date ?? ''),
            'end_date' => (string) ($offer->end_date ?? ''),
            'product_ids' => $this->normalizeCsvIds($offer->products ?? ''),
            'status' => $this->statusToBool($offer->status ?? ''),
            'position' => $this->tableHasColumn('offers', 'position') ? (int) ($offer->position ?? 0) : 0,
            'created_at' => optional($offer->created_at)->toISOString(),
            'updated_at' => optional($offer->updated_at)->toISOString(),
        ];
    }

    private function syncOfferProductDiscountVisibility(Request $request): void
    {
        $storeId = $this->resolveStoreId($request);
        if ($storeId <= 0 || !$this->tableHasColumn('products', 'discount_product') || !$this->tableHasColumn('products', 'prev_discount')) {
            return;
        }

        $hasActiveOffer = Offer::query()
            ->when($this->tableHasColumn('offers', 'store_id'), fn ($query) => $query->where('store_id', $storeId))
            ->where('status', 'active')
            ->exists();

        $products = Product::query()
            ->where('store_id', $storeId)
            ->where('discount_type', '!=', 'no_discount')
            ->where(function ($query) {
                $query->where('discount_product', 0)->orWhereNull('prev_discount');
            })
            ->get();

        foreach ($products as $product) {
            if ($product->discount_type !== 'no_discount') {
                $product->discount_product = 1;
                $product->prev_discount = $product->prev_discount ?: $product->discount_type;
                $product->save();
            }
        }

        $discountProducts = Product::query()
            ->where('store_id', $storeId)
            ->where('discount_product', 1)
            ->get();

        foreach ($discountProducts as $product) {
            if ($hasActiveOffer) {
                $product->discount_type = $product->prev_discount ?? 'no_discount';
            } else {
                $product->prev_discount = $product->discount_type;
                $product->discount_type = 'no_discount';
            }
            $product->save();
        }
    }

    public function listCoupons(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $search = trim((string) $request->query('search', ''));
        $query = $this->scopeQuery('coupons', $request);

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('discount_type', 'like', "%{$search}%");
                if ($this->tableHasColumn('coupons', 'payment_method')) {
                    $sub->orWhere('payment_method', 'like', "%{$search}%");
                }
            });
        }

        $paginator = $query->paginate($perPage);
        return response()->json([
            'items' => collect($paginator->items())->map(fn (Coupon $coupon) => $this->serializeCoupon($coupon))->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function showCoupon(Request $request, int $id): JsonResponse
    {
        $coupon = $this->scopeQuery('coupons', $request)->findOrFail($id);
        return response()->json(['item' => $this->serializeCoupon($coupon)]);
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coupons', 'code')->where(fn ($query) => $query->where('store_id', $storeId)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'min_purchase' => ['nullable'],
            'max_purchase' => ['nullable'],
            'max_use' => ['nullable'],
            'shipping_area' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'auto_apply' => ['nullable', 'boolean'],
            'discount_type' => ['required', 'string', 'max:255'],
            'discount_amount' => ['nullable'],
            'status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        $coupon = new Coupon();
        $coupon->name = $payload['name'];
        $coupon->code = strtoupper((string) $payload['code']);
        $coupon->start_date = $payload['start_date'];
        $coupon->end_date = $payload['end_date'];
        $coupon->min_purchase = $payload['min_purchase'] ?? null;
        $coupon->max_purchase = $payload['max_purchase'] ?? null;
        $coupon->max_use = $payload['max_use'] ?? null;
        if ($this->tableHasColumn('coupons', 'shipping_area')) {
            $coupon->shipping_area = is_numeric($payload['shipping_area'] ?? null) ? $payload['shipping_area'] : null;
        }
        if ($this->tableHasColumn('coupons', 'payment_method')) {
            $coupon->payment_method = $payload['payment_method'] ?? null;
        }
        if ($this->tableHasColumn('coupons', 'auto_apply')) {
            $coupon->auto_apply = (int) ($payload['auto_apply'] ?? false);
        }
        if ($this->tableHasColumn('coupons', 'discount_type')) {
            $coupon->discount_type = $payload['discount_type'];
        }
        if ($this->tableHasColumn('coupons', 'discount_amount')) {
            $coupon->discount_amount = $payload['discount_type'] === 'delivery_charge' ? 0 : ($payload['discount_amount'] ?? 0);
        }
        $coupon->status = $this->statusFromBool((bool) ($payload['status'] ?? false));
        if ($this->tableHasColumn('coupons', 'position')) {
            $coupon->position = (int) ($payload['position'] ?? $this->nextPosition('coupons', $request));
        }
        $this->appendSharedMeta($coupon, 'coupons', $request);
        $coupon->save();

        return response()->json(['item' => $this->serializeCoupon($coupon)], 201);
    }

    public function updateCoupon(Request $request, int $id): JsonResponse
    {
        $storeId = $this->resolveStoreId($request);
        $coupon = $this->scopeQuery('coupons', $request)->findOrFail($id);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coupons', 'code')->where(fn ($query) => $query->where('store_id', $storeId))->ignore($id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'min_purchase' => ['nullable'],
            'max_purchase' => ['nullable'],
            'max_use' => ['nullable'],
            'shipping_area' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'auto_apply' => ['nullable', 'boolean'],
            'discount_type' => ['required', 'string', 'max:255'],
            'discount_amount' => ['nullable'],
            'status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        $coupon->name = $payload['name'];
        $coupon->code = strtoupper((string) $payload['code']);
        $coupon->start_date = $payload['start_date'];
        $coupon->end_date = $payload['end_date'];
        $coupon->min_purchase = $payload['min_purchase'] ?? null;
        $coupon->max_purchase = $payload['max_purchase'] ?? null;
        $coupon->max_use = $payload['max_use'] ?? null;
        if ($this->tableHasColumn('coupons', 'shipping_area')) {
            $coupon->shipping_area = is_numeric($payload['shipping_area'] ?? null) ? $payload['shipping_area'] : null;
        }
        if ($this->tableHasColumn('coupons', 'payment_method')) {
            $coupon->payment_method = $payload['payment_method'] ?? null;
        }
        if ($this->tableHasColumn('coupons', 'auto_apply')) {
            $coupon->auto_apply = (int) ($payload['auto_apply'] ?? false);
        }
        if ($this->tableHasColumn('coupons', 'discount_type')) {
            $coupon->discount_type = $payload['discount_type'];
        }
        if ($this->tableHasColumn('coupons', 'discount_amount')) {
            $coupon->discount_amount = $payload['discount_type'] === 'delivery_charge' ? 0 : ($payload['discount_amount'] ?? 0);
        }
        $coupon->status = $this->statusFromBool((bool) ($payload['status'] ?? false));
        if ($this->tableHasColumn('coupons', 'position') && array_key_exists('position', $payload)) {
            $coupon->position = (int) $payload['position'];
        }
        $this->appendSharedMeta($coupon, 'coupons', $request);
        $coupon->save();

        return response()->json(['item' => $this->serializeCoupon($coupon)]);
    }

    public function destroyCoupon(Request $request, int $id): JsonResponse
    {
        $coupon = $this->scopeQuery('coupons', $request)->findOrFail($id);
        $coupon->delete();
        return response()->json(['success' => true]);
    }

    public function toggleCouponStatus(Request $request, int $id): JsonResponse
    {
        $coupon = $this->scopeQuery('coupons', $request)->findOrFail($id);
        $coupon->status = $this->statusFromBool(!$this->statusToBool($coupon->status));
        $coupon->save();

        return response()->json([
            'success' => true,
            'status' => $this->statusToBool($coupon->status),
        ]);
    }

    public function updateCouponPosition(Request $request, int $id): JsonResponse
    {
        $coupon = $this->scopeQuery('coupons', $request)->findOrFail($id);
        $payload = $request->validate([
            'position' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->tableHasColumn('coupons', 'position')) {
            $coupon->position = (int) $payload['position'];
            $coupon->save();
        }

        return response()->json(['success' => true]);
    }

    public function bulkCouponAction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = $this->scopeQuery('coupons', $request)->whereIn('id', $payload['ids']);
        if ($payload['action'] === 'delete') {
            $query->delete();
            return response()->json(['success' => true]);
        }

        $status = $payload['action'] === 'active' ? 'active' : 'inactive';
        $query->update(['status' => $status]);
        return response()->json(['success' => true]);
    }

    public function listCampaigns(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $search = trim((string) $request->query('search', ''));
        $query = $this->scopeQuery('campaigns', $request);

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('campaign_type', 'like', "%{$search}%");
                if ($this->tableHasColumn('campaigns', 'length_type')) {
                    $sub->orWhere('length_type', 'like', "%{$search}%");
                }
                $sub->orWhere('discount_type', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        return response()->json([
            'items' => collect($paginator->items())->map(fn (Campaign $campaign) => $this->serializeCampaign($campaign))->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function showCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = $this->scopeQuery('campaigns', $request)->findOrFail($id);
        return response()->json(['item' => $this->serializeCampaign($campaign)]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'length_type' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'specific_date' => ['nullable', 'string', 'max:255'],
            'repeat_dates' => ['nullable', 'array'],
            'repeat_dates.*' => ['string', 'max:255'],
            'time_enabled' => ['nullable', 'boolean'],
            'time' => ['nullable'],
            'start_time' => ['nullable', 'string', 'max:255'],
            'end_time' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'string', 'max:255'],
            'discount_amount' => ['nullable'],
            'shipping_areas' => ['nullable', 'array'],
            'shipping_areas.*' => ['string', 'max:255'],
            'shipping_area' => ['nullable', 'string', 'max:255'],
            'campaign_type' => ['required', 'string', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'max:255'],
            'text2' => ['nullable', 'string'],
            'text3' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        $campaign = new Campaign();
        $campaign->name = $payload['name'];
        if ($this->tableHasColumn('campaigns', 'length_type')) {
            $campaign->length_type = $payload['length_type'] ?? 'date_range';
        }
        if ($this->tableHasColumn('campaigns', 'start_date')) {
            $campaign->start_date = $payload['start_date'] ?? null;
        }
        if ($this->tableHasColumn('campaigns', 'end_date')) {
            $campaign->end_date = $payload['end_date'] ?? null;
        }
        if ($this->tableHasColumn('campaigns', 'specific_dates')) {
            $campaign->specific_dates = $payload['specific_date'] ?? null;
        }
        if ($this->tableHasColumn('campaigns', 'repeat_dates')) {
            $campaign->repeat_dates = $this->csvString($payload['repeat_dates'] ?? []);
        }
        $timeEnabled = (bool) (($payload['time_enabled'] ?? false) || (int) ($payload['time'] ?? 0) === 1);
        if ($this->tableHasColumn('campaigns', 'start_time')) {
            $campaign->start_time = $timeEnabled ? ($payload['start_time'] ?? null) : null;
        }
        if ($this->tableHasColumn('campaigns', 'end_time')) {
            $campaign->end_time = $timeEnabled ? ($payload['end_time'] ?? null) : null;
        }
        $campaign->discount_type = $payload['discount_type'];
        $campaign->discount_amount = $payload['discount_type'] === 'delivery_charge' ? 0 : ($payload['discount_amount'] ?? 0);
        if ($this->tableHasColumn('campaigns', 'campaign_type')) {
            $campaign->campaign_type = $payload['campaign_type'];
        }
        if ($this->tableHasColumn('campaigns', 'shipping_area')) {
            $campaign->shipping_area = $payload['discount_type'] === 'delivery_charge'
                ? $this->csvString($payload['shipping_areas'] ?? ($payload['shipping_area'] ? [$payload['shipping_area']] : []))
                : null;
        }
        if ($this->tableHasColumn('campaigns', 'products')) {
            $campaign->products = $payload['campaign_type'] === 'product'
                ? ($this->csvString($payload['product_ids'] ?? []) ?? $this->csvString($payload['text2'] ?? ''))
                : null;
        }
        if ($this->tableHasColumn('campaigns', 'category')) {
            $campaign->category = $payload['campaign_type'] === 'category'
                ? ($this->csvString($payload['category_ids'] ?? []) ?? $this->csvString($payload['text3'] ?? ''))
                : null;
        }
        $campaign->status = $this->statusFromBool((bool) ($payload['status'] ?? false));
        if ($this->tableHasColumn('campaigns', 'position')) {
            $campaign->position = (int) ($payload['position'] ?? $this->nextPosition('campaigns', $request));
        }
        $this->appendSharedMeta($campaign, 'campaigns', $request);
        $campaign->save();

        return response()->json(['item' => $this->serializeCampaign($campaign)], 201);
    }

    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = $this->scopeQuery('campaigns', $request)->findOrFail($id);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'length_type' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'specific_date' => ['nullable', 'string', 'max:255'],
            'repeat_dates' => ['nullable', 'array'],
            'repeat_dates.*' => ['string', 'max:255'],
            'time_enabled' => ['nullable', 'boolean'],
            'time' => ['nullable'],
            'start_time' => ['nullable', 'string', 'max:255'],
            'end_time' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'string', 'max:255'],
            'discount_amount' => ['nullable'],
            'shipping_areas' => ['nullable', 'array'],
            'shipping_areas.*' => ['string', 'max:255'],
            'shipping_area' => ['nullable', 'string', 'max:255'],
            'campaign_type' => ['required', 'string', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'max:255'],
            'text2' => ['nullable', 'string'],
            'text3' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        $campaign->name = $payload['name'];
        if ($this->tableHasColumn('campaigns', 'length_type')) {
            $campaign->length_type = $payload['length_type'] ?? 'date_range';
        }
        if ($this->tableHasColumn('campaigns', 'start_date')) {
            $campaign->start_date = $payload['start_date'] ?? null;
        }
        if ($this->tableHasColumn('campaigns', 'end_date')) {
            $campaign->end_date = $payload['end_date'] ?? null;
        }
        if ($this->tableHasColumn('campaigns', 'specific_dates')) {
            $campaign->specific_dates = $payload['specific_date'] ?? null;
        }
        if ($this->tableHasColumn('campaigns', 'repeat_dates')) {
            $campaign->repeat_dates = $this->csvString($payload['repeat_dates'] ?? []);
        }
        $timeEnabled = (bool) (($payload['time_enabled'] ?? false) || (int) ($payload['time'] ?? 0) === 1);
        if ($this->tableHasColumn('campaigns', 'start_time')) {
            $campaign->start_time = $timeEnabled ? ($payload['start_time'] ?? null) : null;
        }
        if ($this->tableHasColumn('campaigns', 'end_time')) {
            $campaign->end_time = $timeEnabled ? ($payload['end_time'] ?? null) : null;
        }
        $campaign->discount_type = $payload['discount_type'];
        $campaign->discount_amount = $payload['discount_type'] === 'delivery_charge' ? 0 : ($payload['discount_amount'] ?? 0);
        if ($this->tableHasColumn('campaigns', 'campaign_type')) {
            $campaign->campaign_type = $payload['campaign_type'];
        }
        if ($this->tableHasColumn('campaigns', 'shipping_area')) {
            $campaign->shipping_area = $payload['discount_type'] === 'delivery_charge'
                ? $this->csvString($payload['shipping_areas'] ?? ($payload['shipping_area'] ? [$payload['shipping_area']] : []))
                : null;
        }
        if ($this->tableHasColumn('campaigns', 'products')) {
            $campaign->products = $payload['campaign_type'] === 'product'
                ? ($this->csvString($payload['product_ids'] ?? []) ?? $this->csvString($payload['text2'] ?? ''))
                : null;
        }
        if ($this->tableHasColumn('campaigns', 'category')) {
            $campaign->category = $payload['campaign_type'] === 'category'
                ? ($this->csvString($payload['category_ids'] ?? []) ?? $this->csvString($payload['text3'] ?? ''))
                : null;
        }
        $campaign->status = $this->statusFromBool((bool) ($payload['status'] ?? false));
        if ($this->tableHasColumn('campaigns', 'position') && array_key_exists('position', $payload)) {
            $campaign->position = (int) $payload['position'];
        }
        $this->appendSharedMeta($campaign, 'campaigns', $request);
        $campaign->save();

        return response()->json(['item' => $this->serializeCampaign($campaign)]);
    }

    public function destroyCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = $this->scopeQuery('campaigns', $request)->findOrFail($id);
        $campaign->delete();
        return response()->json(['success' => true]);
    }

    public function toggleCampaignStatus(Request $request, int $id): JsonResponse
    {
        $campaign = $this->scopeQuery('campaigns', $request)->findOrFail($id);
        $campaign->status = $this->statusFromBool(!$this->statusToBool($campaign->status));
        $campaign->save();

        return response()->json([
            'success' => true,
            'status' => $this->statusToBool($campaign->status),
        ]);
    }

    public function updateCampaignPosition(Request $request, int $id): JsonResponse
    {
        $campaign = $this->scopeQuery('campaigns', $request)->findOrFail($id);
        $payload = $request->validate([
            'position' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->tableHasColumn('campaigns', 'position')) {
            $campaign->position = (int) $payload['position'];
            $campaign->save();
        }

        return response()->json(['success' => true]);
    }

    public function bulkCampaignAction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = $this->scopeQuery('campaigns', $request)->whereIn('id', $payload['ids']);
        if ($payload['action'] === 'delete') {
            $query->delete();
            return response()->json(['success' => true]);
        }

        $status = $payload['action'] === 'active' ? 'active' : 'inactive';
        $query->update(['status' => $status]);
        return response()->json(['success' => true]);
    }

    public function listOffers(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $search = trim((string) $request->query('search', ''));
        $query = $this->scopeQuery('offers', $request);

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('start_date', 'like', "%{$search}%")
                    ->orWhere('end_date', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        return response()->json([
            'items' => collect($paginator->items())->map(fn (Offer $offer) => $this->serializeOffer($offer))->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function showOffer(Request $request, int $id): JsonResponse
    {
        $offer = $this->scopeQuery('offers', $request)->findOrFail($id);
        return response()->json(['item' => $this->serializeOffer($offer)]);
    }

    public function storeOffer(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'max:255'],
            'text2' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        $offer = new Offer();
        $offer->name = $payload['name'];
        $offer->start_date = $payload['start_date'] ?? null;
        $offer->end_date = $payload['end_date'] ?? null;
        $offer->products = $this->csvString($payload['product_ids'] ?? []) ?? $this->csvString($payload['text2'] ?? '');
        $offer->status = $this->statusFromBool((bool) ($payload['status'] ?? false));
        if ($this->tableHasColumn('offers', 'position')) {
            $offer->position = (int) ($payload['position'] ?? $this->nextPosition('offers', $request));
        }
        $this->appendSharedMeta($offer, 'offers', $request);
        $offer->save();
        $this->syncOfferProductDiscountVisibility($request);

        return response()->json(['item' => $this->serializeOffer($offer)], 201);
    }

    public function updateOffer(Request $request, int $id): JsonResponse
    {
        $offer = $this->scopeQuery('offers', $request)->findOrFail($id);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'max:255'],
            'text2' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
        ]);

        $offer->name = $payload['name'];
        $offer->start_date = $payload['start_date'] ?? null;
        $offer->end_date = $payload['end_date'] ?? null;
        $offer->products = $this->csvString($payload['product_ids'] ?? []) ?? $this->csvString($payload['text2'] ?? '');
        $offer->status = $this->statusFromBool((bool) ($payload['status'] ?? false));
        if ($this->tableHasColumn('offers', 'position') && array_key_exists('position', $payload)) {
            $offer->position = (int) $payload['position'];
        }
        $this->appendSharedMeta($offer, 'offers', $request);
        $offer->save();
        $this->syncOfferProductDiscountVisibility($request);

        return response()->json(['item' => $this->serializeOffer($offer)]);
    }

    public function destroyOffer(Request $request, int $id): JsonResponse
    {
        $offer = $this->scopeQuery('offers', $request)->findOrFail($id);
        $offer->delete();
        $this->syncOfferProductDiscountVisibility($request);
        return response()->json(['success' => true]);
    }

    public function toggleOfferStatus(Request $request, int $id): JsonResponse
    {
        $offer = $this->scopeQuery('offers', $request)->findOrFail($id);
        $offer->status = $this->statusFromBool(!$this->statusToBool($offer->status));
        $offer->save();
        $this->syncOfferProductDiscountVisibility($request);

        return response()->json([
            'success' => true,
            'status' => $this->statusToBool($offer->status),
        ]);
    }

    public function updateOfferPosition(Request $request, int $id): JsonResponse
    {
        $offer = $this->scopeQuery('offers', $request)->findOrFail($id);
        $payload = $request->validate([
            'position' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->tableHasColumn('offers', 'position')) {
            $offer->position = (int) $payload['position'];
            $offer->save();
        }

        return response()->json(['success' => true]);
    }

    public function bulkOfferAction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = $this->scopeQuery('offers', $request)->whereIn('id', $payload['ids']);
        if ($payload['action'] === 'delete') {
            $query->delete();
            $this->syncOfferProductDiscountVisibility($request);
            return response()->json(['success' => true]);
        }

        $status = $payload['action'] === 'active' ? 'active' : 'inactive';
        $query->update(['status' => $status]);
        $this->syncOfferProductDiscountVisibility($request);
        return response()->json(['success' => true]);
    }
}
