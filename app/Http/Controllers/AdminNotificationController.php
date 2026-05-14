<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\AddonsOrder;
use App\Models\Customer;
use App\Models\ExpoDeviceToken;
use App\Models\Headersetting;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Staff;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminNotificationController extends Controller
{
    private function resolveStoreId(): int
    {
        $user = Auth::user()->id;
        $user_type = Auth::user()->type;

        if ($user_type == 'admin') {
            $customer = Customer::where('uid', $user)->first();
            return (int) $customer->active_store;
        }

        $staff = Staff::where('uid', $user)->first();
        return (int) $staff->store_id;
    }

    public function notification()
    {
        $store_id = $this->resolveStoreId();
        $urls = "notification";
        $notification = AdminNotification::where('store_id', $store_id)->get();
        return view('admin.notification.index')->with('urls', $urls)->with('notification', $notification);

    }

    public function createnotification()
    {
        $urls = "notification";
        return view('admin.notification.create')->with('urls', $urls);
    }

    public function savenotification(Request $request)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $notification = new AdminNotification();
        $notification->store_id = $store_id;
        $notification->message = $request->message;
        $notification->body = $request->body;
        $notification->link = $request->link;
        $notification->save();

        $expoDeviceToken = ExpoDeviceToken::where('store_id', $store_id)->get();

        if (count($expoDeviceToken) > 0 && !empty($expoDeviceToken)) {
            foreach ($expoDeviceToken as $key => $value) {
                $response = Http::post('https://exp.host/--/api/v2/push/send', [
                    'to' => $value->expo_token,
                    'title' => $notification->message,
                    'body' => $notification->body,
                ]);
            }

            // Handle the response as needed
            if (isset($response) && $response->successful()) {
                Session::flash('message', 'Notification Save Successfully');
            } else {
                Session::flash('error', "Notification Not Save Successfully");
//                return response()->json(['success' => false, 'error' => $response->json()]);
            }

        }

        return redirect()->route('admin.notification');
    }

    public function editnotification($id)
    {
        $store_id = $this->resolveStoreId();
        $urls = "notification";
        $notification = AdminNotification::where('id', $id)->where('store_id', $store_id)->first();
        return view('admin.notification.edit')->with('urls', $urls)->with('notification', $notification);
    }

    public function updatenotification(Request $request, $id)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $notification = AdminNotification::where('id', $id)->where('store_id', $store_id)->first();

        if (isset($notification)) {
            $notification->message = $request->message;
            $notification->body = $request->body;
            $notification->link = $request->link;
            $notification->update();

            Session::flash('message', 'Notification Update Successfully');
            return redirect()->route('admin.notification');
        }

        Session::flash('message', 'Notification Not Found');
        return redirect()->route('admin.notification');
    }

    public function deletenotification($id)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $urls = "notification";
        $notification = AdminNotification::where('id', $id)->where('store_id', $store_id)->first();
        $notification->delete();
        Session::flash('message', 'Notification Deleted Successfully');
        return back();
    }

    public function indexApi(Request $request): JsonResponse
    {
        $context = $this->currentNotificationContext();
        $category = strtolower((string) $request->query('category', 'all'));
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOL);
        $limit = min(120, max(20, (int) $request->query('limit', 80)));

        $databaseItems = $this->databaseNotificationItems($context);
        $signalItems = $this->operationalSignalItems($context);

        $items = $databaseItems
            ->merge($signalItems)
            ->when($category !== 'all', fn ($rows) => $rows->where('category', $category))
            ->when($unreadOnly, fn ($rows) => $rows->where('read', false))
            ->sortByDesc('sort_at')
            ->take($limit)
            ->values()
            ->map(fn ($item) => collect($item)->except('sort_at')->all());

        $categoryCounts = $databaseItems
            ->merge($signalItems)
            ->groupBy('category')
            ->map(fn ($rows) => $rows->count())
            ->all();

        $latestId = $databaseItems
            ->where('source', 'notification')
            ->max('id') ?? 0;

        return response()->json([
            'items' => $items,
            'meta' => [
                'unread_count' => $databaseItems->where('read', false)->count() + $signalItems->where('read', false)->count(),
                'total_count' => $databaseItems->count() + $signalItems->count(),
                'latest_id' => (int) $latestId,
                'category_counts' => $categoryCounts,
                'generated_at' => Carbon::now()->toIso8601String(),
                'live_refresh_seconds' => 10,
            ],
        ]);
    }

    public function markReadApi(int $id): JsonResponse
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'view')) {
            $notification = $this->scopedNotificationQuery($this->currentNotificationContext())->findOrFail($id);
            $notification->view = 1;
            $notification->save();
        }

        return response()->json(['success' => true]);
    }

    public function markAllReadApi(): JsonResponse
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'view')) {
            $this->scopedNotificationQuery($this->currentNotificationContext())->update(['view' => 1]);
        }

        return response()->json(['success' => true]);
    }

    public function showApi(int $id): \Illuminate\Http\JsonResponse
    {
        $notification = AdminNotification::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id, ['id', 'message', 'body', 'link', 'created_at', 'updated_at']);

        return response()->json([
            'item' => $notification,
        ]);
    }

    public function storeApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $this->validatedNotification($request);

        $notification = new AdminNotification();
        $notification->store_id = $this->resolveStoreId();
        $notification->message = $payload['message'];
        $notification->body = $payload['body'];
        $notification->link = $payload['link'] ?? null;
        $notification->save();

        return response()->json([
            'item' => $notification,
        ], 201);
    }

    public function updateApi(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $payload = $this->validatedNotification($request);

        $notification = AdminNotification::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id);

        $notification->message = $payload['message'];
        $notification->body = $payload['body'];
        $notification->link = $payload['link'] ?? null;
        $notification->save();

        return response()->json([
            'item' => $notification,
        ]);
    }

    public function destroyApi(int $id): \Illuminate\Http\JsonResponse
    {
        if (Schema::hasTable('notifications')) {
            $notification = $this->scopedNotificationQuery($this->currentNotificationContext())->find($id);
            if ($notification) {
                $notification->delete();
                return response()->json(['success' => true]);
            }
        }

        $notification = AdminNotification::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function bulkDestroyApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            throw ValidationException::withMessages([
                'ids' => 'At least one notification must be selected.',
            ]);
        }

        if (Schema::hasTable('notifications')) {
            $this->scopedNotificationQuery($this->currentNotificationContext())
                ->whereIn('id', $ids)
                ->delete();
        }

        return response()->json([
            'success' => true,
        ]);
    }

    private function currentNotificationContext(): array
    {
        $user = Auth::user();
        $userData = function_exists('getUserData') ? getUserData() : [];
        $authType = strtolower((string) ($user->type ?? ''));
        $userType = strtolower((string) ($userData['user_type'] ?? $authType));
        $storeId = $userData['store_id'] ?? null;

        if ($authType === 'superadmin' || $authType === 'superstaff') {
            $userType = $authType;
            $storeId = null;
        } elseif ($userType === 'staff') {
            $userType = 'admin';
        }

        return [
            'user_id' => (int) ($user->id ?? ($userData['user_id'] ?? 0)),
            'user_type' => $userType ?: 'admin',
            'store_id' => $storeId !== '' ? $storeId : null,
        ];
    }

    private function scopedNotificationQuery(array $context)
    {
        $query = Notification::query();

        if (!Schema::hasTable('notifications')) {
            return $query->whereRaw('1 = 0');
        }

        $userType = strtolower((string) ($context['user_type'] ?? 'admin'));
        $userId = (string) ($context['user_id'] ?? '');
        $storeId = $context['store_id'] ?? null;

        if (Schema::hasColumn('notifications', 'user_type')) {
            if (in_array($userType, ['superadmin', 'superstaff'], true)) {
                if (Schema::hasColumn('notifications', 'store_id')) {
                    $query->whereNull('store_id');
                }
                $query->whereRaw('LOWER(COALESCE(user_type, "")) = ?', [$userType]);
            } else {
                $query->where(function ($subQuery) use ($userType) {
                    $subQuery->whereRaw('LOWER(COALESCE(user_type, "")) = ?', [$userType])
                        ->orWhereRaw('LOWER(COALESCE(user_type, "")) = ?', ['all']);
                });
            }
        }

        if (Schema::hasColumn('notifications', 'store_id') && !in_array($userType, ['superadmin', 'superstaff'], true)) {
            $query->where(function ($subQuery) use ($storeId) {
                $subQuery->whereNull('store_id');
                if ($storeId !== null) {
                    $subQuery->orWhere('store_id', $storeId);
                }
            });
        }

        if (Schema::hasColumn('notifications', 'user_id')) {
            $query->where(function ($subQuery) use ($userId) {
                $subQuery->whereNull('user_id');
                if ($userId !== '') {
                    $subQuery->orWhere('user_id', $userId);
                }
            });
        }

        return $query;
    }

    private function databaseNotificationItems(array $context)
    {
        if (!Schema::hasTable('notifications')) {
            return collect();
        }

        return $this->scopedNotificationQuery($context)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (Notification $notification) {
                $type = strtolower((string) ($notification->type ?? 'general'));
                $createdAt = $notification->created_at ? Carbon::parse($notification->created_at) : Carbon::now();
                return [
                    'id' => (int) $notification->id,
                    'source' => 'notification',
                    'category' => $this->normalizeNotificationCategory($type),
                    'category_label' => $this->notificationCategoryLabel($type),
                    'title' => (string) ($notification->title ?: 'Notification'),
                    'body' => (string) ($notification->body ?: ''),
                    'link' => (string) ($notification->link ?: ''),
                    'read' => (int) ($notification->view ?? 0) === 1,
                    'created_at' => $createdAt->toIso8601String(),
                    'time_ago' => $createdAt->diffForHumans(),
                    'priority' => $this->notificationPriority($type),
                    'sort_at' => $createdAt->timestamp,
                ];
            })
            ->toBase();
    }

    private function operationalSignalItems(array $context)
    {
        $items = collect();
        $storeId = $context['store_id'] ?? null;

        if ($storeId && Schema::hasTable('products')) {
            $stockThreshold = 5;
            if (Schema::hasTable('headersettings') && Schema::hasColumn('headersettings', 'stock_out_qty')) {
                $stockThreshold = (int) (Headersetting::query()->where('store_id', $storeId)->value('stock_out_qty') ?: 5);
            }

            if (Schema::hasColumn('products', 'quantity')) {
                $lowStockCount = Product::query()
                    ->where('store_id', $storeId)
                    ->whereRaw('CAST(COALESCE(quantity, 0) AS SIGNED) <= ?', [$stockThreshold])
                    ->count();

                if ($lowStockCount > 0) {
                    $items->push([
                        'id' => 'stock-alert-' . $storeId,
                        'source' => 'stock',
                        'category' => 'stock',
                        'category_label' => 'Stock alert',
                        'title' => 'Low stock needs attention',
                        'body' => $lowStockCount . ' products are at or below the alert quantity.',
                        'link' => '/inventory/stock-list?stock_state=alert',
                        'read' => false,
                        'created_at' => Carbon::now()->toIso8601String(),
                        'time_ago' => 'Live now',
                        'priority' => 'high',
                        'sort_at' => Carbon::now()->timestamp + 1,
                    ]);
                }
            }

            $productColumns = array_values(array_filter(['id', 'name', 'created_at'], fn ($column) => Schema::hasColumn('products', $column)));
            if (in_array('created_at', $productColumns, true)) {
                Product::query()
                    ->where('store_id', $storeId)
                    ->latest('id')
                    ->limit(8)
                    ->get($productColumns)
                    ->each(function (Product $product) use ($items) {
                        $createdAt = $product->created_at ? Carbon::parse($product->created_at) : Carbon::now();
                        $items->push([
                            'id' => 'product-' . $product->id,
                            'source' => 'product',
                            'category' => 'product',
                            'category_label' => 'Product',
                            'title' => 'Product added',
                            'body' => (string) ($product->name ?? 'A product') . ' was added to your catalog.',
                            'link' => '/products',
                            'read' => true,
                            'created_at' => $createdAt->toIso8601String(),
                            'time_ago' => $createdAt->diffForHumans(),
                            'priority' => 'normal',
                            'sort_at' => $createdAt->timestamp,
                        ]);
                    });
            }
        }

        if ($storeId && Schema::hasTable('addons_orders')) {
            AddonsOrder::query()
                ->where('store_id', $storeId)
                ->latest('id')
                ->limit(8)
                ->get(['id', 'total', 'status', 'payment_method', 'created_at'])
                ->each(function (AddonsOrder $order) use ($items) {
                    $createdAt = $order->created_at ? Carbon::parse($order->created_at) : Carbon::now();
                    $items->push([
                        'id' => 'payment-' . $order->id,
                        'source' => 'payment',
                        'category' => 'payment',
                        'category_label' => 'Payment',
                        'title' => 'Payment/order update',
                        'body' => trim('Addon order #' . $order->id . ' ' . ($order->status ? '(' . $order->status . ')' : '') . ' - BDT ' . (float) ($order->total ?? 0)),
                        'link' => '/account/payment/history',
                        'read' => true,
                        'created_at' => $createdAt->toIso8601String(),
                        'time_ago' => $createdAt->diffForHumans(),
                        'priority' => 'normal',
                        'sort_at' => $createdAt->timestamp,
                    ]);
                });
        }

        return $items;
    }

    private function normalizeNotificationCategory(string $type): string
    {
        return match ($type) {
            'store_order' => 'order',
            'addon_order', 'plan_order' => 'payment',
            'product', 'product_create' => 'product',
            'stock', 'stock_alert' => 'stock',
            'message', 'ticket' => 'message',
            default => 'system',
        };
    }

    private function notificationCategoryLabel(string $type): string
    {
        return [
            'store_order' => 'Order',
            'addon_order' => 'Payment',
            'plan_order' => 'Payment',
            'product' => 'Product',
            'stock_alert' => 'Stock alert',
            'message' => 'Message',
            'ticket' => 'Ticket',
        ][$type] ?? Str::headline($type ?: 'System');
    }

    private function notificationPriority(string $type): string
    {
        return in_array($type, ['stock_alert', 'ticket'], true) ? 'high' : 'normal';
    }

    private function validatedNotification(Request $request): array
    {
        return $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'link' => ['nullable', 'string', 'max:255'],
        ]);
    }


    public function changenotificationstatus(Request $request)
    {
        if ($request->text2 == '') {
            Session::flash('error', 'Please Select at least one item');
            return back();
        }
        if ($request->action == 'select') {
            Session::flash('error', 'Please Select a Option');
            return back();
        }
        if ($request->action == 'delete') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = AdminNotification::find($ids);
                    $product->delete();
                }
            }
            Session::flash('message', 'Successfully Deleted');
            return back();
        }
    }


    public function getStoreNotification($user, $store = null)
    {
        $store_id = $store ?? NULL;
        $user_id = $user ?? NULL;

        if (!isset($user_id) && !empty($user_id)) {
            return sendError("Store ID missing!");
        }

        $user = User::where("id", $user_id)->first();
        if (!isset($user)) {
            return sendError("User not found!");
        }

        $user_type = $user->type ?? NULL;

        $html = "";
        $totalNotification = 0;

        $notificationQuery = Notification::query();

        // Apply the user_type condition for superadmins and superstaff
        if (isset($user_type) && in_array($user_type, ["superadmin", "superstaff"])) {
            $notificationQuery->whereNull("store_id") // Ensuring store_id is NULL for superadmin/superstaff
            ->where(function ($query) use ($user_id, $user_type) {
                $query->whereRaw("LOWER(user_type) = ?", [strtolower($user_type)]);
            });
        } else {
            if (isset($user_type) && in_array($user_type, ["admin", "staff"])) {
                $user_type = "admin";
            }

            $notificationQuery->where(function ($subQuery) use ($user_type) {
                $subQuery->whereRaw("LOWER(user_type) = ?", [strtolower($user_type)]) // Match user_type exactly
                ->orWhere("user_type", "all"); // Allow global notifications
            });
        }

        // Apply the filtering logic for store_id and user_id before pagination
        $notificationQuery->where(function ($query) use ($store_id, $user_id) {
            // Store ID filter: either match store_id or allow null store_id
            $query->where(function ($subQuery) use ($store_id) {
                $subQuery->whereNull("store_id")
                    ->orWhere("store_id", $store_id); // Match store_id if not null
            });

            // User ID filter: either match user_id or allow null user_id
            $query->where(function ($subQuery) use ($user_id) {
                $subQuery->whereNull("user_id")
                    ->orWhere("user_id", $user_id); // Match user_id if not null
            });
        });


        $allNotifications = $notificationQuery->where("view", 0)
            ->orderBy('id', 'DESC')
            ->get()
            ->groupBy('type')
            ->map(function ($items) {
                return $items->take(10); // Limit each group to 10 items
            });


        $typesArr = [
            "store_order" => "Order",
            "plan_order" => "Plan Order",
            "addon_order" => "Addon Order",
            "domain_request" => "Domain Request",
            "user_create" => "User Register",
            "theme_customize" => "Theme Customize",
            "message" => "Message",
            "ticket" => "Ticket",
        ];

        $html = '<div class="accordion" id="notificationAccordion">';

        foreach ($allNotifications as $type => $notifications) {
            $totalNotification += count($notifications);
            $typeSlug = Str::slug($type);

            $typeValue = $typesArr[$type] ?? ucfirst(Str::replace(" ", "_", $type));

            if (is_null($typeValue) || empty($typeValue)) {
                $typeValue = "General";
            }

            $html .= '<div class="accordion-item">
                <h2 class="accordion-header" id="heading' . $typeSlug . '">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $typeSlug . '" aria-expanded="false" aria-controls="collapse' . $typeSlug . '">
                        ' . $typeValue . ' (' . count($notifications) . ')
                    </button>
                </h2>
                <div id="collapse' . $typeSlug . '" class="accordion-collapse collapse" aria-labelledby="heading' . $typeSlug . '" data-bs-parent="#notificationAccordion">
                    <ul class="list-group">';

            foreach ($notifications as $value) {
                $url = route("notification.view-notification", ["id" => $value->id]);
                $title = $value->title;
                $body = $value->body;

                $html .= '<li class="notification-item">
                    <a class="dropdown-item border-radius-md p-0 px-2" href="' . $url . '">
                        <div class="d-flex flex-column py-1">
                            <h6 class="wrap-text">' . $title . '</h6>
                            <p class="m-0 wrap-text">' . $body . '</p>
                        </div>
                    </a>
                  </li>';
            }

            $html .= '</ul></div></div>';
        }

        $html .= '</div>';

        $url = route("notification.notification.list");
        $html .= '<div class="all_notification ' . ($totalNotification > 0 ? 'border-top' : '') . '">
                <a href="' . $url . '">See All Notification</a>
            </div>';


        return sendResponse("Success", [
            "html" => $html,
            "totalNotification" => $totalNotification,
        ]);

    }


    public function notificationList()
    {
        $userData = getUserData();
        $user_id = $userData['user_id'];
        $store_id = $userData['store_id'];
        $user_type = $userData['user_type'];

        $notificationQuery = Notification::query();

        // Apply the user_type condition for superadmins and superstaff
        if (isset($user_type) && in_array($user_type, ["superadmin", "superstaff"])) {
            $notificationQuery->whereNull("store_id") // Ensuring store_id is NULL for superadmin/superstaff
            ->where(function ($query) use ($user_id, $user_type) {
                $query->whereRaw("LOWER(user_type) = ?", [strtolower($user_type)]);
            });
        } else {
            if (isset($user_type) && in_array($user_type, ["admin", "staff"])) {
                $user_type = "admin";
            }

            $notificationQuery->where(function ($subQuery) use ($user_type) {
                $subQuery->whereRaw("LOWER(user_type) = ?", [strtolower($user_type)]) // Match user_type exactly
                ->orWhere("user_type", "all"); // Allow global notifications
            });
        }

        // Apply the filtering logic for store_id and user_id before pagination
        $notificationQuery->where(function ($query) use ($store_id, $user_id) {
            // Store ID filter: either match store_id or allow null store_id
            $query->where(function ($subQuery) use ($store_id) {
                $subQuery->whereNull("store_id")
                    ->orWhere("store_id", $store_id); // Match store_id if not null
            });

            // User ID filter: either match user_id or allow null user_id
            $query->where(function ($subQuery) use ($user_id) {
                $subQuery->whereNull("user_id")
                    ->orWhere("user_id", $user_id); // Match user_id if not null
            });
        });

        // Fetch the filtered notifications with pagination
        $notifications = $notificationQuery->orderBy('id', 'DESC')
            ->paginate(10);


        return view('notification.index', ['notifications' => $notifications]);

    }

    public function viewNotification($id)
    {
        if (!isset($id) && !empty($id)) {
            \Illuminate\Support\Facades\Session::flash('error', 'Record ID missing!');
            return redirect()->to('/');
        }

        $notification = Notification::where('id', $id)->first();

        if (!isset($notification)) {
            \Illuminate\Support\Facades\Session::flash('error', 'Notification not found!');
            return redirect()->to('/');
        }

        $notification->view = "1";
        $notification->update();

        if (isset($notification->link) && !empty($notification->link)) {
            return redirect()->away($notification->link);
        }

        return view("notification.view")->with('notification', $notification);
    }


}
