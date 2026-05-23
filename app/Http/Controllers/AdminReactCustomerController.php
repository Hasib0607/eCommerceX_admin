<?php

namespace App\Http\Controllers;

use App\Models\BlockUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Support\AdminContactValidation;

class AdminReactCustomerController extends Controller
{
    private function resolveContext(): array
    {
        $authUser = auth()->user();
        $userType = strtolower((string) ($authUser->type ?? ''));
        $storeId = 0;
        $customerId = 0;

        if ($userType === 'admin' || $userType === 'dropshipper') {
            $customer = Customer::where('uid', $authUser->id)->first();
            $storeId = (int) ($customer->active_store ?? 0);
            $customerId = (int) ($customer->id ?? 0);
        } elseif ($userType === 'staff') {
            $staff = Staff::where('uid', $authUser->id)->first();
            $storeId = (int) ($staff->store_id ?? 0);
            $customerId = (int) ($staff->customer_id ?? 0);
        }

        return [
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ];
    }

    private function customerQuery()
    {
        $context = $this->resolveContext();
        $storeId = (int) $context['store_id'];

        return User::query()
            ->where(function ($query) use ($storeId) {
                $query->where(function ($sub) use ($storeId) {
                    $sub->where('type', 'customer')
                        ->where('store_id', $storeId);
                })->orWhere(function ($sub) use ($storeId) {
                    $sub->where('type', 'walking_customer')
                        ->where('store_id', $storeId);
                });
            });
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min((int) $request->query('per_page', 30), 100));

        $query = $this->customerQuery()->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $storeId = (int) $this->resolveContext()['store_id'];

        $items = collect($paginator->items())->map(function (User $row) use ($storeId) {
            $block = BlockUser::query()
                ->where('store_id', $storeId)
                ->where('user_id', $row->id)
                ->first();
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'email' => (string) ($row->email ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'address' => (string) ($row->address ?? ''),
                'auth_type' => (string) ($row->auth_type ?? ''),
                'image_url' => $row->image ? asset('assets/images/img/' . $row->image) : null,
                'is_blocked' => (int) ($block->status ?? 0) === 1,
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => AdminContactValidation::emailRules(false, 255),
            'phone' => AdminContactValidation::phoneRules(false, 50),
            'password' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $context = $this->resolveContext();
        $password = (string) ($payload['password'] ?? '');
        if ($password === '') {
            $password = str()->random(10);
        }

        $row = new User();
        $row->name = $payload['name'];
        $row->email = $payload['email'] ?? null;
        $row->phone = $payload['phone'] ?? null;
        $row->password = Hash::make($password);
        $row->type = 'customer';
        $row->address = $payload['address'] ?? null;
        $row->customer_id = (int) $context['customer_id'];
        $row->store_id = (int) $context['store_id'];
        $row->save();

        return response()->json([
            'id' => (int) $row->id,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $storeId = (int) $this->resolveContext()['store_id'];
        $customer = $this->customerQuery()->findOrFail($id);
        $block = BlockUser::query()->where('store_id', $storeId)->where('user_id', $id)->first();

        $orderStats = Order::query()
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', ['pending'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS processing', ['processing'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS payment_failed', ['payment_failed'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS on_hold', ['on_hold'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS delivered', ['delivered'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS shipping', ['shipping'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS returned', ['returned'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', ['cancelled'])
            ->where('store_id', $storeId)
            ->where('uid', $customer->id)
            ->first();

        return response()->json([
            'item' => [
                'id' => (int) $customer->id,
                'name' => (string) ($customer->name ?? ''),
                'email' => (string) ($customer->email ?? ''),
                'phone' => (string) ($customer->phone ?? ''),
                'address' => (string) ($customer->address ?? ''),
                'image_url' => $customer->image ? asset('assets/images/img/' . $customer->image) : null,
                'is_blocked' => (int) ($block->status ?? 0) === 1,
            ],
            'order_stats' => [
                'pending' => (int) ($orderStats->pending ?? 0),
                'processing' => (int) ($orderStats->processing ?? 0),
                'payment_failed' => (int) ($orderStats->payment_failed ?? 0),
                'on_hold' => (int) ($orderStats->on_hold ?? 0),
                'delivered' => (int) ($orderStats->delivered ?? 0),
                'shipping' => (int) ($orderStats->shipping ?? 0),
                'returned' => (int) ($orderStats->returned ?? 0),
                'cancelled' => (int) ($orderStats->cancelled ?? 0),
            ],
        ]);
    }

    public function toggleBlock(int $id): JsonResponse
    {
        $storeId = (int) $this->resolveContext()['store_id'];
        $this->customerQuery()->findOrFail($id);

        $row = BlockUser::query()->where('store_id', $storeId)->where('user_id', $id)->first();
        if ($row) {
            $row->status = (int) !$row->status;
            $row->save();
        } else {
            $row = new BlockUser();
            $row->store_id = $storeId;
            $row->user_id = $id;
            $row->status = 1;
            $row->save();
        }

        return response()->json([
            'success' => true,
            'is_blocked' => (int) $row->status === 1,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = $this->customerQuery()->findOrFail($id);
        $row->delete();
        return response()->json(['success' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $this->customerQuery()->whereIn('id', $payload['ids'])->delete();
        return response()->json(['success' => true]);
    }

    public function exportCsv()
    {
        $rows = $this->customerQuery()->orderByDesc('id')->get(['name', 'phone', 'email', 'address', 'created_at']);
        $filename = 'customers-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Phone', 'Email', 'Address', 'Created At']);
            foreach ($rows as $row) {
                fputcsv($file, [
                    (string) ($row->name ?? ''),
                    (string) ($row->phone ?? ''),
                    (string) ($row->email ?? ''),
                    (string) ($row->address ?? ''),
                    (string) ($row->created_at ?? ''),
                ]);
            }
            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}

