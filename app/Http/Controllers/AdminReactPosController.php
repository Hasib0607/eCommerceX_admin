<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Branchproduct;
use App\Models\AddonsExpired;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Headersetting;
use App\Models\Holdorder;
use App\Models\Holdorderitem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\Product;
use App\Models\Posplan;
use App\Models\Staff;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Veriant;
use Auth;
use Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class AdminReactPosController extends Controller
{
    private array $schemaColumnCache = [];

    private function orderHasColumn(string $column): bool
    {
        if (!array_key_exists($column, $this->schemaColumnCache)) {
            $this->schemaColumnCache[$column] = Schema::hasColumn('orders', $column);
        }

        return $this->schemaColumnCache[$column];
    }

    private function staffPosBranchIds(): array
    {
        $user = Auth::user();
        if (($user->type ?? '') !== 'staff') {
            return [];
        }
        $staff = Staff::where('uid', $user->id)->first();
        $raw = (string) ($staff->pos ?? '');
        if ($raw === '') {
            return [];
        }
        return collect(explode(',', $raw))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    private function branchScopedProductIds(int $branchId): array
    {
        return Branchproduct::where('branch_id', $branchId)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveContext(): array
    {
        $user = Auth::user();
        $storeId = 0;
        $customerId = 0;
        if (($user->type ?? '') === 'admin' || ($user->type ?? '') === 'dropshipper') {
            $customer = Customer::where('uid', $user->id)->first();
            $storeId = (int) ($customer->active_store ?? 0);
            $customerId = (int) ($customer->id ?? 0);
        } elseif (($user->type ?? '') === 'staff') {
            $staff = Staff::where('uid', $user->id)->first();
            $storeId = (int) ($staff->store_id ?? 0);
            $customerId = (int) ($staff->customer_id ?? 0);
        }
        return ['store_id' => $storeId, 'customer_id' => $customerId];
    }

    private function productPrice(Product $product): array
    {
        if ($product->discount_type === 'fixed') {
            $discount = (float) $product->promotional_price;
            return [(float) $product->regular_price - $discount, $discount];
        }
        if ($product->discount_type === 'percent') {
            $discount = ((float) $product->promotional_price / 100) * (float) $product->regular_price;
            return [(float) $product->regular_price - $discount, $discount];
        }
        return [(float) $product->regular_price, 0.0];
    }

    private function cartSummary(): array
    {
        $rows = [];
        $rawTotal = 0;
        foreach (Cart::instance('cart')->content() as $item) {
            $regularPrice = (float) ($item->model->regular_price ?? $item->price);
            $rowTotal = (float) $item->qty * $regularPrice;
            $rawTotal += $rowTotal;
            $rows[] = [
                'row_id' => (string) $item->rowId,
                'product_id' => (int) $item->id,
                'name' => (string) ($item->model->name ?? $item->name),
                'qty' => (int) $item->qty,
                'price' => (float) $item->price,
                'regular_price' => $regularPrice,
                'row_total' => $rowTotal,
                'discount' => (float) ($item->options->discount ?? 0),
                'color' => $item->options->color ?? null,
                'size' => $item->options->size ?? null,
                'volume' => $item->options->volume ?? null,
                'unit' => $item->options->unit ?? null,
                'additional_price' => (float) ($item->options->additional_price ?? 0),
            ];
        }

        $subtotal = (float) Cart::instance('cart')->subtotal(2, '.', '');
        $discount = array_reduce($rows, fn ($carry, $r) => $carry + ($r['qty'] * $r['discount']), 0);
        $extraDiscount = (float) Session::get('extra_discount', 0);
        $tax = (float) Session::get('tax', Cart::instance('cart')->tax(2, '.', ''));
        $shipping = (float) Session::get('shipping', 0);
        $otherCharge = (float) Session::get('other_charge', 0);
        $payable = (float) Session::get('payableamount', ($subtotal + $tax + $shipping + $otherCharge - $extraDiscount));

        return [
            'items' => $rows,
            'total_items' => Cart::instance('cart')->count(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'extra_discount' => $extraDiscount,
            'tax' => $tax,
            'shipping' => $shipping,
            'other_charge' => $otherCharge,
            'payable_total' => $payable,
            'raw_total' => $rawTotal,
        ];
    }

    private function currentCustomer(): array
    {
        return [
            'id' => Session::get('customer_id'),
            'name' => Session::get('customer_name', 'Customer'),
            'phone' => Session::get('customer_phone', ''),
            'email' => Session::get('customer_email', ''),
            'address' => Session::get('customer_address', ''),
        ];
    }

    private function categories(?int $branchId = null): array
    {
        $context = $this->resolveContext();
        $storeId = (int) ($context['store_id'] ?? 0);
        if ($storeId < 1) {
            return [];
        }

        $query = Category::query()
            ->where('store_id', $storeId)
            ->orderBy('position')
            ->orderBy('id');

        return $query->get()->map(function (Category $category) use ($branchId) {
            $productQuery = Product::query()->where(function ($q) use ($category) {
                $q->where('category', $category->id)->orWhere('subcategory', $category->id);
            });

            if ($branchId && $branchId > 0) {
                $productIds = $this->branchScopedProductIds($branchId);
                if (count($productIds) > 0) {
                    $productQuery->whereIn('id', $productIds);
                } else {
                    $productQuery->whereRaw('1 = 0');
                }
            }

            return [
                'id' => (int) $category->id,
                'name' => (string) ($category->name ?? ''),
                'icon' => $category->icon ? asset('assets/images/icon/' . $category->icon) : null,
                'product_count' => (int) $productQuery->count(),
            ];
        })->filter(fn ($category) => $category['product_count'] > 0)->values()->all();
    }

    private function selectedBranchMeta(int $branchId): ?array
    {
        if ($branchId < 1) {
            return null;
        }

        $branch = Branch::find($branchId);
        if (!$branch) {
            return null;
        }

        return [
            'id' => (int) $branch->id,
            'name' => (string) ($branch->name ?? ''),
            'email' => (string) ($branch->email ?? ''),
            'phone' => (string) ($branch->phone ?? ''),
            'address' => (string) ($branch->address ?? ''),
            'tax' => (float) ($branch->tax ?? 0),
            'status' => (string) ($branch->status ?? ''),
        ];
    }

    private function products(?string $search = null, ?int $branchId = null, ?int $categoryId = null): array
    {
        $query = Product::query()->orderByDesc('id');
        if ($branchId && $branchId > 0) {
            $productIds = $this->branchScopedProductIds($branchId);
            if (count($productIds) < 1) {
                return [];
            }
            $query->whereIn('id', $productIds);
        }
        if ($categoryId && $categoryId > 0) {
            $query->where(function ($sub) use ($categoryId) {
                $sub->where('category', $categoryId)->orWhere('subcategory', $categoryId);
            });
        }
        if ($search !== null && trim($search) !== '') {
            $value = trim($search);
            $query->where(function ($sub) use ($value) {
                $sub->where('barcode', $value)
                    ->orWhere('name', 'like', "%{$value}%");
            });
        }
        return $query->limit(120)->get()->map(function (Product $product) {
            [$price] = $this->productPrice($product);
            $image = null;
            if ($product->images) {
                $parts = explode(',', (string) $product->images);
                $image = $parts[0] ?? null;
            }
            $variants = Veriant::where('pid', $product->id)->get(['id', 'color', 'size', 'volume', 'unit', 'additional_price']);
            return [
                'id' => (int) $product->id,
                'name' => (string) ($product->name ?? ''),
                'regular_price' => (float) ($product->regular_price ?? 0),
                'price' => (float) $price,
                'barcode' => (string) ($product->barcode ?? ''),
                'image_url' => $image ? asset('assets/images/product/' . $image) : null,
                'variants' => $variants->map(fn ($v) => [
                    'id' => (int) $v->id,
                    'color' => $v->color,
                    'size' => $v->size,
                    'volume' => $v->volume,
                    'unit' => $v->unit,
                    'additional_price' => (float) ($v->additional_price ?? 0),
                ]),
            ];
        })->values()->all();
    }

    public function context(Request $request): JsonResponse
    {
        $selectedBranchId = (int) Session::get('pos_branch_id', 0);
        $holdOrders = Holdorder::orderByDesc('id')->get(['id', 'order_id', 'oids', 'created_at']);
        return response()->json([
            'products' => $this->products(
                $request->query('search'),
                $selectedBranchId > 0 ? $selectedBranchId : null,
                (int) $request->query('category_id', 0) ?: null,
            ),
            'categories' => $this->categories($selectedBranchId > 0 ? $selectedBranchId : null),
            'cart' => $this->cartSummary(),
            'customer' => $this->currentCustomer(),
            'hold_orders' => $holdOrders,
            'branch_id' => $selectedBranchId,
            'branch' => $this->selectedBranchMeta($selectedBranchId),
        ]);
    }

    public function branches(Request $request): JsonResponse
    {
        $context = $this->resolveContext();
        $storeId = (int) ($context['store_id'] ?? 0);
        $search = trim((string) $request->query('search', ''));
        $allowedBranchIds = $this->staffPosBranchIds();

        $query = Branch::query()->where('store_id', $storeId);
        if (count($allowedBranchIds) > 0) {
            $query->whereIn('id', $allowedBranchIds);
        } elseif ((Auth::user()->type ?? '') === 'staff') {
            $query->whereRaw('1 = 0');
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $branches = $query->orderByDesc('id')->get()->map(fn (Branch $branch) => [
            'id' => (int) $branch->id,
            'name' => (string) ($branch->name ?? ''),
            'email' => (string) ($branch->email ?? ''),
            'phone' => (string) ($branch->phone ?? ''),
            'address' => (string) ($branch->address ?? ''),
            'status' => (string) ($branch->status ?? ''),
            'created_at' => optional($branch->created_at)->toDateTimeString(),
            'products_count' => Branchproduct::where('branch_id', $branch->id)->count(),
        ])->values();

        $plan = null;
        $currentDate = now();
        $posAddon = AddonsExpired::where('store_id', $storeId)
            ->where('addons_id', 13)
            ->where('expired_date', '>=', $currentDate)
            ->first();
        if ($posAddon && $posAddon->pos_plan_id) {
            $plan = Posplan::where('id', $posAddon->pos_plan_id)->first();
        }
        $branchLimit = (int) ($plan->branch ?? 0);

        return response()->json([
            'branches' => $branches,
            'selected_branch_id' => (int) Session::get('pos_branch_id', 0),
            'branch_limit' => $branchLimit,
        ]);
    }

    public function createBranch(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (($user->type ?? '') === 'staff') {
            return response()->json(['message' => 'Staff users are not allowed to create branches.'], 403);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax' => ['nullable', 'numeric'],
        ]);

        $context = $this->resolveContext();
        $storeId = (int) ($context['store_id'] ?? 0);
        $customerId = (int) ($context['customer_id'] ?? 0);
        if ($storeId < 1 || $customerId < 1) {
            return response()->json(['message' => 'Store context missing.'], 422);
        }

        $currentDate = now();
        $posAddon = AddonsExpired::where('store_id', $storeId)
            ->where('addons_id', 13)
            ->where('expired_date', '>=', $currentDate)
            ->first();
        if ($posAddon && $posAddon->pos_plan_id) {
            $plan = Posplan::where('id', $posAddon->pos_plan_id)->first();
            $limit = (int) ($plan->branch ?? 0);
            if ($limit > 0) {
                $count = Branch::where('store_id', $storeId)->count();
                if ($count >= $limit) {
                    return response()->json([
                        'message' => 'Already reached branch limit. Please upgrade POS plan to add more branches.',
                    ], 422);
                }
            }
        }

        $branch = new Branch();
        $branch->name = $payload['name'];
        $branch->email = $payload['email'] ?? null;
        $branch->phone = $payload['phone'] ?? null;
        $branch->address = $payload['address'] ?? null;
        $branch->tax = $payload['tax'] ?? null;
        $branch->uid = (int) Auth::id();
        $branch->customer_id = $customerId;
        $branch->store_id = $storeId;
        $branch->creator = (int) Auth::id();
        $branch->editor = (int) Auth::id();
        $branch->status = 'active';
        $branch->save();

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
                'email' => (string) ($branch->email ?? ''),
                'phone' => (string) ($branch->phone ?? ''),
                'address' => (string) ($branch->address ?? ''),
                'status' => (string) ($branch->status ?? ''),
                'created_at' => optional($branch->created_at)->toDateTimeString(),
            ],
        ]);
    }

    public function selectBranch(int $id): JsonResponse
    {
        $context = $this->resolveContext();
        $storeId = (int) ($context['store_id'] ?? 0);
        $query = Branch::query()->where('id', $id)->where('store_id', $storeId);
        $allowedBranchIds = $this->staffPosBranchIds();
        if ((Auth::user()->type ?? '') === 'staff') {
            if (count($allowedBranchIds) < 1 || !in_array($id, $allowedBranchIds, true)) {
                return response()->json(['message' => 'Unauthorized branch for this staff user.'], 403);
            }
        }
        $branch = $query->first();
        if (!$branch) {
            return response()->json(['message' => 'Branch not found.'], 404);
        }

        Cart::instance('cart')->destroy();
        Session::forget(['tax', 'shipping', 'other_charge', 'extra_discount', 'payableamount']);
        Session::put('tax', (float) ($branch->tax ?? 0));
        Session::put('pos_branch_id', (int) $branch->id);

        return response()->json([
            'success' => true,
            'branch' => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
                'status' => (string) ($branch->status ?? ''),
            ],
        ]);
    }

    public function addCart(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required_without:variant_id', 'integer'],
            'variant_id' => ['required_without:product_id', 'integer'],
        ]);

        if (!empty($payload['variant_id'])) {
            $variant = Veriant::findOrFail((int) $payload['variant_id']);
            $product = Product::findOrFail((int) $variant->pid);
            [$price, $discount] = $this->productPrice($product);
            $price += (float) ($variant->additional_price ?? 0);
            Cart::instance('cart')->add($product->id, $product->name, 1, $price, [
                'discount' => $discount,
                'color' => $variant->color,
                'size' => $variant->size,
                'volume' => $variant->volume,
                'unit' => $variant->unit,
                'additional_price' => $variant->additional_price,
            ])->associate('App\Models\Product');
        } else {
            $product = Product::findOrFail((int) $payload['product_id']);
            [$price, $discount] = $this->productPrice($product);
            Cart::instance('cart')->add($product->id, $product->name, 1, $price, ['discount' => $discount])->associate('App\Models\Product');
        }

        return response()->json(['cart' => $this->cartSummary()]);
    }

    public function incrementCart(Request $request): JsonResponse
    {
        $rowId = (string) $request->validate(['row_id' => ['required', 'string']])['row_id'];
        $item = Cart::instance('cart')->get($rowId);
        if ($item) {
            Cart::instance('cart')->update($rowId, (int) $item->qty + 1);
        }
        return response()->json(['cart' => $this->cartSummary()]);
    }

    public function decrementCart(Request $request): JsonResponse
    {
        $rowId = (string) $request->validate(['row_id' => ['required', 'string']])['row_id'];
        $item = Cart::instance('cart')->get($rowId);
        if ($item) {
            $qty = max(1, (int) $item->qty - 1);
            Cart::instance('cart')->update($rowId, $qty);
        }
        return response()->json(['cart' => $this->cartSummary()]);
    }

    public function removeCart(Request $request): JsonResponse
    {
        $rowId = (string) $request->validate(['row_id' => ['required', 'string']])['row_id'];
        Cart::instance('cart')->remove($rowId);
        return response()->json(['cart' => $this->cartSummary()]);
    }

    public function setTotals(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'discount' => ['nullable', 'numeric'],
            'extra_discount' => ['nullable', 'numeric'],
            'tax' => ['nullable', 'numeric'],
            'shipping' => ['nullable', 'numeric'],
            'other_charge' => ['nullable', 'numeric'],
        ]);
        $subtotal = (float) Cart::instance('cart')->subtotal(2, '.', '');
        $discount = (float) ($payload['extra_discount'] ?? $payload['discount'] ?? 0);
        $tax = (float) ($payload['tax'] ?? 0);
        $shipping = (float) ($payload['shipping'] ?? 0);
        $other = (float) ($payload['other_charge'] ?? 0);
        $payable = ($subtotal + $tax + $shipping + $other) - $discount;
        Session::put('tax', $tax);
        Session::put('shipping', $shipping);
        Session::put('other_charge', $other);
        Session::put('extra_discount', $discount);
        Session::put('payableamount', $payable);
        return response()->json(['cart' => $this->cartSummary()]);
    }

    public function searchCustomer(Request $request): JsonResponse
    {
        $phone = (string) $request->query('phone', '');
        if ($phone === '') {
            return response()->json(['customer' => null]);
        }
        $user = User::where('phone', $phone)->first();
        return response()->json([
            'customer' => $user ? [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?? ''),
                'phone' => (string) ($user->phone ?? ''),
                'email' => (string) ($user->email ?? ''),
                'address' => (string) ($user->address ?? ''),
            ] : null,
        ]);
    }

    public function saveCustomer(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $user = User::where('phone', $payload['phone'])->first();
        if ($user) {
            if (empty($user->name) && !empty($payload['name'])) $user->name = $payload['name'];
            if (empty($user->email) && !empty($payload['email'])) $user->email = $payload['email'];
            if (empty($user->address) && !empty($payload['address'])) $user->address = $payload['address'];
            $user->save();
        } else {
            $user = new User();
            $user->name = $payload['name'] ?? 'Walking Customer';
            $user->email = $payload['email'] ?? null;
            $user->phone = $payload['phone'];
            $user->address = $payload['address'] ?? null;
            $user->password = Hash::make('12345678');
            $user->type = 'walking_customer';
            $user->otp = '1234';
            $user->save();
        }

        Session::put('customer_id', $user->id);
        Session::put('customer_phone', $user->phone);
        Session::put('customer_name', $user->name);
        Session::put('customer_email', $user->email);
        Session::put('customer_address', $user->address);

        return response()->json(['customer' => $this->currentCustomer()]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        if (Cart::instance('cart')->count() < 1) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }
        $payload = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'payment_type' => ['required', 'in:cod,online,ssl'],
            'payment_method' => ['nullable', 'in:cod,online'],
            'transaction_id' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string'],
            'totals' => ['required', 'array'],
            'totals.discount' => ['nullable', 'numeric'],
            'totals.tax' => ['nullable', 'numeric'],
            'totals.shipping' => ['nullable', 'numeric'],
            'totals.other_charge' => ['nullable', 'numeric'],
            'totals.paid' => ['nullable', 'numeric'],
            'totals.due' => ['nullable', 'numeric'],
            'totals.total' => ['required', 'numeric'],
            'customer' => ['nullable', 'array'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['nullable', 'string', 'max:255'],
            'customer.address' => ['nullable', 'string', 'max:255'],
        ]);

        $context = $this->resolveContext();
        $store = Store::findOrFail((int) $context['store_id']);
        $branch = Branch::findOrFail((int) ($payload['branch_id'] ?? Session::get('pos_branch_id', 0)));

        DB::beginTransaction();

        try {
            $customerUserId = Session::get('customer_id');
            $customerName = Session::get('customer_name');
            $customerPhone = Session::get('customer_phone');
            $customerEmail = Session::get('customer_email');
            $customerAddress = Session::get('customer_address');

            $customerPayload = $payload['customer'] ?? [];
            $customerPhone = trim((string) ($customerPayload['phone'] ?? $customerPhone ?? ''));
            $customerName = trim((string) ($customerPayload['name'] ?? $customerName ?? 'Walking Customer'));
            $customerEmail = trim((string) ($customerPayload['email'] ?? $customerEmail ?? ''));
            $customerAddress = trim((string) ($customerPayload['address'] ?? $customerAddress ?? ''));

            if ($customerPhone !== '') {
                $user = User::query()->where('phone', $customerPhone)->first();
                if (!$user) {
                    $user = new User();
                    $user->phone = $customerPhone;
                    $user->name = $customerName !== '' ? $customerName : 'Walking Customer';
                    $user->email = $customerEmail !== '' ? $customerEmail : null;
                    $user->address = $customerAddress !== '' ? $customerAddress : null;
                    $user->password = Hash::make(substr(str_shuffle('0123456789'), 0, 8));
                    $user->type = 'walking_customer';
                    $user->otp = '1234';
                    if (isset($user->store_id)) {
                        $user->store_id = $branch->store_id;
                    }
                    $user->save();
                } else {
                    if (($user->name ?? '') === '' && $customerName !== '') $user->name = $customerName;
                    if (($user->email ?? '') === '' && $customerEmail !== '') $user->email = $customerEmail;
                    if (($user->address ?? '') === '' && $customerAddress !== '') $user->address = $customerAddress;
                    $user->save();
                }

                $customerUserId = (int) $user->id;
                $customerName = (string) ($user->name ?? $customerName);
                $customerPhone = (string) ($user->phone ?? $customerPhone);
                $customerEmail = (string) ($user->email ?? $customerEmail);
                $customerAddress = (string) ($user->address ?? $customerAddress);
            }

            $paid = (float) ($payload['totals']['paid'] ?? $payload['totals']['total'] ?? 0);
            $total = (float) $payload['totals']['total'];
            $due = max(0, (float) ($payload['totals']['due'] ?? ($total - $paid)));

            $order = new Order();
            $order->uid = $customerUserId;
            $order->subtotal = (float) Cart::instance('cart')->subtotal(2, '.', '');
            $order->tax = (float) ($payload['totals']['tax'] ?? 0);
            $order->shipping = (float) ($payload['totals']['shipping'] ?? 0);
            if ($this->orderHasColumn('other_charge')) {
                $order->other_charge = (float) ($payload['totals']['other_charge'] ?? 0);
            }
            $order->discount = (float) ($payload['totals']['discount'] ?? 0);
            $order->total = $total;
            $order->reference_no = 'BN' . substr(str_shuffle('0123456789'), 0, 4);
            $order->name = $customerName !== '' ? $customerName : 'Walking Customer';
            $order->phone = $customerPhone !== '' ? $customerPhone : null;
            $order->email = $customerEmail !== '' ? $customerEmail : null;
            $order->address = $customerAddress !== '' ? $customerAddress : 'NULL';
            $order->note = $payload['note'] ?? null;
            $order->status = 'Delivered';
            if ($this->orderHasColumn('extradiscount')) {
                $order->extradiscount = (float) ($payload['totals']['discount'] ?? 0);
            }
            if ($this->orderHasColumn('paid')) {
                $order->paid = $paid;
            }
            if ($this->orderHasColumn('due')) {
                $order->due = $due;
            }
            if ($this->orderHasColumn('payment_method')) {
                $order->payment_method = (string) ($payload['payment_method'] ?? $payload['payment_type']);
            }
            if ($this->orderHasColumn('transaction_id') && !empty($payload['transaction_id'])) {
                $order->transaction_id = (string) $payload['transaction_id'];
            }
            $order->creator = Auth::id();
            $order->editor = Auth::id();
            $order->branch_id = (int) $branch->id;
            $order->currency_id = $store->currency;
            $order->customer_id = (int) $context['customer_id'];
            $order->store_id = (int) $context['store_id'];
            $order->type = 'walking_customer';
            $order->save();

            $invoiceItems = [];
            foreach (Cart::instance('cart')->content() as $item) {
                $product = Product::find($item->model->id);
                if ($product) {
                    $product->quantity = max(0, (float) ($product->quantity ?? 0) - (float) $item->qty);
                    $product->save();
                }

                if (!empty($item->options->size) || !empty($item->options->color) || !empty($item->options->volume)) {
                    $variant = Veriant::query()
                        ->where('pid', $item->model->id)
                        ->when(!empty($item->options->color), fn ($q) => $q->where('color', $item->options->color))
                        ->when(!empty($item->options->size), fn ($q) => $q->where('size', $item->options->size))
                        ->when(!empty($item->options->volume), fn ($q) => $q->where('volume', $item->options->volume))
                        ->when(!empty($item->options->unit), fn ($q) => $q->where('unit', $item->options->unit))
                        ->first();

                    if ($variant && isset($variant->quantity)) {
                        $variant->quantity = max(0, (float) ($variant->quantity ?? 0) - (float) $item->qty);
                        $variant->save();
                    }
                }

                $orderItem = new Orderitem();
                $orderItem->product_id = $item->model->id;
                $orderItem->order_id = $order->id;
                $orderItem->currency_id = $store->currency;
                $orderItem->price = $item->price;
                $orderItem->quantity = $item->qty;
                $orderItem->color = $item->options->color ?? null;
                $orderItem->size = $item->options->size ?? null;
                $orderItem->volume = $item->options->volume ?? null;
                $orderItem->unit = $item->options->unit ?? null;
                $orderItem->additional_price = $item->options->additional_price ?? null;
                $orderItem->save();

                $invoiceItems[] = [
                    'product_id' => (int) $item->id,
                    'name' => (string) ($item->model->name ?? $item->name),
                    'quantity' => (int) $item->qty,
                    'price' => (float) $item->price,
                ];
            }

            $transaction = new Transaction();
            $transaction->uid = $customerUserId;
            $transaction->order_id = $order->id;
            $transaction->mode = $payload['payment_type'] === 'ssl' ? 'online' : $payload['payment_type'];
            if (!empty($payload['transaction_id'])) {
                $transaction->transaction_id = (string) $payload['transaction_id'];
            }
            $transaction->status = 'pending';
            $transaction->save();

            $invoice = new Invoice();
            $invoice->reference_no = 'INV' . substr(str_shuffle('0123456789'), 0, 4);
            $invoice->order_id = $order->id;
            $invoice->type = 'POS';
            $invoice->uid = $customerUserId ?: Auth::id();
            $invoice->customer_id = (int) $context['customer_id'];
            $invoice->store_id = (int) $context['store_id'];
            $invoice->creator = Auth::id();
            $invoice->editor = Auth::id();
            $invoice->save();

            $headerSetting = Headersetting::query()->where('store_id', $branch->store_id)->first();

            DB::commit();

            Cart::instance('cart')->destroy();
            Session::forget([
                'customer_id',
                'customer_name',
                'customer_phone',
                'customer_email',
                'customer_address',
                'tax',
                'shipping',
                'other_charge',
                'extra_discount',
                'payableamount',
            ]);
            Session::put('tax', (float) ($branch->tax ?? 0));

            return response()->json([
                'success' => true,
                'order_id' => (int) $order->id,
                'invoice' => [
                    'order' => [
                        'id' => (int) $order->id,
                        'reference_no' => (string) ($order->reference_no ?? ''),
                        'subtotal' => (float) ($order->subtotal ?? 0),
                        'tax' => (float) ($order->tax ?? 0),
                        'discount' => (float) ($order->discount ?? 0),
                        'extradiscount' => (float) ($order->extradiscount ?? ($payload['totals']['discount'] ?? 0)),
                        'total' => (float) ($order->total ?? 0),
                        'paid' => (float) ($order->paid ?? $paid),
                        'due' => (float) ($order->due ?? $due),
                        'updated_at' => optional($order->updated_at)->toDateTimeString(),
                    ],
                    'logo' => ($headerSetting && !empty($headerSetting->logo))
                        ? asset('assets/images/setting/' . $headerSetting->logo)
                        : null,
                    'cashier' => (string) (Auth::user()->name ?? ''),
                    'products' => $invoiceItems,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to place POS order.'], 500);
        }
    }

    public function holdSave(Request $request): JsonResponse
    {
        if (Cart::instance('cart')->count() < 1) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }
        $payload = $request->validate([
            'order_id' => ['nullable', 'string', 'max:255'],
            'totals' => ['required', 'array'],
            'totals.discount' => ['nullable', 'numeric'],
            'totals.tax' => ['nullable', 'numeric'],
            'totals.shipping' => ['nullable', 'numeric'],
            'totals.other_charge' => ['nullable', 'numeric'],
            'totals.total' => ['required', 'numeric'],
        ]);

        $hold = new Holdorder();
        $hold->order_id = $payload['order_id'] ?? null;
        $hold->oids = 'HR' . substr(str_shuffle('0123456789'), 0, 4);
        $hold->uid = Session::get('customer_id');
        $hold->subtotal = (float) Cart::instance('cart')->subtotal(2, '.', '');
        $hold->discount = (float) ($payload['totals']['discount'] ?? 0);
        $hold->tax = (float) ($payload['totals']['tax'] ?? 0);
        $hold->shipping = (float) ($payload['totals']['shipping'] ?? 0);
        $hold->other_charge = (float) ($payload['totals']['other_charge'] ?? 0);
        $hold->payable_amount = (float) $payload['totals']['total'];
        $hold->save();

        foreach (Cart::instance('cart')->content() as $item) {
            $row = new Holdorderitem();
            $row->oid = $hold->id;
            $row->pid = $item->model->id;
            $row->quantity = $item->qty;
            $row->save();
        }

        Cart::instance('cart')->destroy();
        Session::forget(['customer_id', 'customer_name', 'customer_phone', 'customer_email', 'customer_address', 'tax', 'shipping', 'other_charge', 'extra_discount', 'payableamount']);

        return response()->json(['success' => true, 'hold_id' => (int) $hold->id]);
    }

    public function holdDetails(int $id): JsonResponse
    {
        $hold = Holdorder::findOrFail($id);
        $customer = null;
        if ($hold->uid) {
            $user = User::find($hold->uid);
            if ($user) {
                $customer = [
                    'name' => $user->name,
                    'phone' => $user->phone,
                ];
            }
        }
        if (!$customer) {
            $customer = ['name' => 'Walking Customer', 'phone' => ''];
        }

        $items = Holdorderitem::where('oid', $hold->id)->get()->map(function ($row) {
            $product = Product::find($row->pid);
            if (!$product) return null;
            [$price] = $this->productPrice($product);
            return [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'qty' => (int) $row->quantity,
                'price' => (float) $price,
            ];
        })->filter()->values();

        return response()->json([
            'id' => (int) $hold->id,
            'order_id' => $hold->order_id,
            'oids' => $hold->oids,
            'customer' => $customer,
            'items' => $items,
            'totals' => [
                'subtotal' => (float) ($hold->subtotal ?? 0),
                'discount' => (float) ($hold->discount ?? 0),
                'tax' => (float) ($hold->tax ?? 0),
                'shipping' => (float) ($hold->shipping ?? 0),
                'other_charge' => (float) ($hold->other_charge ?? 0),
                'total' => (float) ($hold->payable_amount ?? 0),
            ],
        ]);
    }

    public function holdDelete(int $id): JsonResponse
    {
        $hold = Holdorder::findOrFail($id);
        Holdorderitem::where('oid', $hold->id)->delete();
        $hold->delete();
        return response()->json(['success' => true]);
    }

    public function holdRestore(int $id): JsonResponse
    {
        Session::forget(['customer_id', 'customer_name', 'customer_phone', 'customer_email', 'tax', 'shipping', 'other_charge', 'payableamount']);
        Cart::instance('cart')->destroy();
        $hold = Holdorder::findOrFail($id);
        $details = Holdorderitem::where('oid', $hold->id)->get();
        foreach ($details as $line) {
            $product = Product::find($line->pid);
            if (!$product) continue;
            [$price, $discount] = $this->productPrice($product);
            Cart::instance('cart')->add($product->id, $product->name, $line->quantity, $price, ['discount' => $discount])
                ->associate('App\Models\Product');
        }
        Session::put('tax', (float) ($hold->tax ?? 0));
        Session::put('shipping', (float) ($hold->shipping ?? 0));
        Session::put('other_charge', (float) ($hold->other_charge ?? 0));
        Session::put('payableamount', (float) ($hold->payable_amount ?? 0));

        if ($hold->uid) {
            $user = User::find($hold->uid);
            if ($user) {
                Session::put('customer_id', $user->id);
                Session::put('customer_phone', $user->phone);
                Session::put('customer_name', $user->name);
                Session::put('customer_email', $user->email);
                Session::put('customer_address', $user->address);
            }
        }
        Holdorderitem::where('oid', $hold->id)->delete();
        $hold->delete();

        return response()->json([
            'success' => true,
            'cart' => $this->cartSummary(),
            'customer' => $this->currentCustomer(),
        ]);
    }
}
