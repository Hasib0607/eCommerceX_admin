<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Mail\OPTSendMail;
use App\Models\BusinessCategory;
use App\Models\AiSeedImageLibrary;
use App\Models\Booking;
use App\Models\BookingCustomerFiled;
use App\Models\BookingTag;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Courier;
use App\Models\CourierDelivery;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DemoStoreData;
use App\Models\Domain;
use App\Models\Designlist;
use App\Models\AddonsApi;
use App\Models\AddonsExpired;
use App\Models\AddonsOrder;
use App\Models\AiSeedProduct;
use App\Models\AdminUserAnalytics;
use App\Models\AdminCoupon;
use App\Models\Banner;
use App\Models\ModulusPayment;
use App\Models\Modulus;
use App\Models\BuyModulus;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\OrderStatus;
use App\Models\Headersetting;
use App\Models\Iconpack;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlanDetail;
use App\Models\PlanEntitlement;
use App\Models\Product;
use App\Models\QuickLogin;
use App\Models\Paymentgateway;
use App\Models\Posplan;
use App\Models\Review;
use App\Models\Role;
use App\Models\Size;
use App\Models\Slider;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Superrole;
use App\Models\SuperAdminSetting;
use App\Models\Superstaff;
use App\Models\SaasFeature;
use App\Models\Testimonial;
use App\Models\Template;
use App\Models\Temposition;
use App\Models\Unit;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Veriant;
use App\Models\WebsiteSetupDetails;
use App\Models\Websitesetup;
use App\Models\SuperstaffSalesCommissionBalance;
use App\Models\Staff;
use App\Models\SupportQueue;
use App\Models\MarchantPaymentGetway;
use App\Models\Digitalplan;
use App\Support\AdminContactValidation;
use App\Services\EntitlementService;
use App\Services\AiStoreSeedService;
use App\Services\DomainNameApiService;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AdminReactController extends Controller
{
    private const REGISTRATION_SESSION_KEY = 'react_admin.pending_registration';
    private const PASSWORD_RESET_SESSION_KEY = 'react_admin.pending_password_reset';
    private const ACCESS_MODES_SETTING_KEY = 'admin_access_modes_v1';
    private const TRIAL_PERIOD_DAYS_SETTING_KEY = 'trial_period_days';
    private const DEFAULT_TRIAL_PERIOD_DAYS = 14;
    private const OTP_GATEWAY_TENANT_CACHE_KEY = 'whatsapp_gateway.active_otp_tenant_id';

    public function superadminAiFill(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'mode' => 'nullable|string|in:field,page',
            'route' => 'nullable|string|max:180',
            'page_title' => 'nullable|string|max:180',
            'field' => 'nullable|array',
            'field.key' => 'nullable|string|max:80',
            'field.label' => 'nullable|string|max:120',
            'field.value' => 'nullable|string|max:2000',
            'field.type' => 'nullable|string|max:40',
            'field.placeholder' => 'nullable|string|max:180',
            'field.name' => 'nullable|string|max:120',
            'field.section' => 'nullable|string|max:240',
            'field.nearby_text' => 'nullable|string|max:800',
            'fields' => 'nullable|array|max:30',
            'fields.*.key' => 'nullable|string|max:80',
            'fields.*.label' => 'nullable|string|max:120',
            'fields.*.value' => 'nullable|string|max:2000',
            'fields.*.type' => 'nullable|string|max:40',
            'fields.*.placeholder' => 'nullable|string|max:180',
            'fields.*.name' => 'nullable|string|max:120',
            'fields.*.section' => 'nullable|string|max:240',
            'fields.*.nearby_text' => 'nullable|string|max:800',
            'context' => 'nullable|array',
        ]);

        $mode = (string) ($validated['mode'] ?? 'field');
        $fields = $mode === 'page'
            ? array_values((array) ($validated['fields'] ?? []))
            : [array_merge((array) ($validated['field'] ?? []), ['key' => (string) data_get($validated, 'field.key', 'field')])];

        $fields = collect($fields)
            ->map(function ($field, $index) {
                return [
                    'key' => (string) ($field['key'] ?? "field_{$index}"),
                    'label' => (string) ($field['label'] ?? $field['key'] ?? "Field {$index}"),
                    'value' => (string) ($field['value'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'placeholder' => (string) ($field['placeholder'] ?? ''),
                    'name' => (string) ($field['name'] ?? ''),
                    'section' => (string) ($field['section'] ?? ''),
                    'nearby_text' => (string) ($field['nearby_text'] ?? ''),
                ];
            })
            ->filter(fn ($field) => trim($field['label'] . $field['value']) !== '')
            ->take(30)
            ->values()
            ->all();

        if (empty($fields)) {
            return response()->json(['message' => 'No AI-fillable field was found.'], 422);
        }

        $generated = app(AiStoreSeedService::class)->generateFieldCopy([
            'mode' => $mode,
            'route' => (string) ($validated['route'] ?? ''),
            'page_title' => (string) ($validated['page_title'] ?? ''),
            'context' => (array) ($validated['context'] ?? []),
            'fields' => $fields,
        ]);

        if (empty($generated)) {
            return response()->json(['message' => 'AI bot did not return any generated field value.'], 502);
        }

        return response()->json(['fields' => $generated]);
    }

    public function merchantPaymentClients(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = MarchantPaymentGetway::query()
            ->with(['store:id,user_id,name,url', 'header:id,store_id,balance_min_withdraw,balance_max_withdraw'])
            ->orderByDesc('created_at');

        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->whereHas('store', function ($subQuery) use ($search) {
                    $subQuery->where('user_id', $search);
                });
            } else {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('store', function ($subQuery) use ($search) {
                        $subQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('url', 'like', "%{$search}%");
                    })->orWhere('payment_gatway', 'like', "%{$search}%");
                });
            }
        }

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'user_id' => (int) ($row->store->user_id ?? 0),
                'store_name' => (string) ($row->store->name ?? ''),
                'store_url' => (string) ($row->store->url ?? ''),
                'payment_gateway' => ucfirst((string) ($row->payment_gatway ?? '')),
                'withdraw_min' => (float) ($row->header->balance_min_withdraw ?? 0),
                'withdraw_max' => (float) ($row->header->balance_max_withdraw ?? 0),
                'header_id' => (int) ($row->header->id ?? 0),
                'status' => (int) ($row->status ?? 0),
                'created_date' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
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

    public function publicBranding(): JsonResponse
    {
        $settings = SuperAdminSetting::query()->pluck('value', 'name');
        $baseColor = (string) ($settings['base_color'] ?? '#c24d2c');
        $themeColor = (string) ($settings['theme_color'] ?? '#17384d');
        $legacyLogo = (string) ($settings['panel_logo'] ?? '');
        $darkLogo = (string) ($settings['panel_logo_dark'] ?? $legacyLogo);
        $lightLogo = (string) ($settings['panel_logo_light'] ?? $legacyLogo);
        $favicon = (string) ($settings['panel_favicon'] ?? '');

        return response()->json([
            'base_color' => $baseColor,
            'theme_color' => $themeColor,
            'button_color' => (string) ($settings['button_color'] ?? $baseColor),
            'active_color' => (string) ($settings['active_color'] ?? $baseColor),
            'nav_color' => (string) ($settings['nav_color'] ?? '#171414'),
            'topbar_color' => (string) ($settings['topbar_color'] ?? '#f4d9cf'),
            'background_color' => (string) ($settings['background_color'] ?? '#f4efe8'),
            'surface_color' => (string) ($settings['surface_color'] ?? '#fffdf9'),
            'panel_logo' => $darkLogo,
            'panel_logo_url' => $this->resolveSettingAssetPublicUrl($darkLogo, 'assets/images/setting'),
            'panel_logo_dark' => $darkLogo,
            'panel_logo_dark_url' => $this->resolveSettingAssetPublicUrl($darkLogo, 'assets/images/setting'),
            'panel_logo_light' => $lightLogo,
            'panel_logo_light_url' => $this->resolveSettingAssetPublicUrl($lightLogo, 'assets/images/setting'),
            'panel_favicon' => $favicon,
            'panel_favicon_url' => $this->resolveSettingAssetPublicUrl($favicon, 'assets/images/setting/favicon'),
        ]);
    }

    public function merchantPaymentClientStatus(Request $request, int $id): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $payload = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        $row = MarchantPaymentGetway::query()->find($id);
        if (!$row) {
            return response()->json(['message' => 'Client payment entry not found.'], 404);
        }

        $row->status = $payload['status'] ? 1 : 0;
        $row->save();

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully.',
            'value' => (int) $row->status,
        ]);
    }

    public function merchantPaymentClientWithdraw(Request $request, int $id): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $payload = $request->validate([
            'min' => ['required', 'numeric', 'min:0'],
            'max' => ['required', 'numeric', 'min:0'],
        ]);

        $row = MarchantPaymentGetway::query()->with('header')->find($id);
        if (!$row || !$row->header) {
            return response()->json(['message' => 'Withdraw configuration record not found.'], 404);
        }

        $header = $row->header;
        $header->balance_min_withdraw = (float) $payload['min'];
        $header->balance_max_withdraw = (float) $payload['max'];
        $header->save();

        return response()->json([
            'status' => true,
            'message' => 'Withdraw limits updated.',
            'withdraw_min' => (float) $header->balance_min_withdraw,
            'withdraw_max' => (float) $header->balance_max_withdraw,
        ]);
    }

    public function superadminOrderPlanRequests(Request $request, ?string $paymentStatus = null, string $sourceType = 'plan'): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $status = trim((string) ($paymentStatus ?: $request->query('payment_status', 'processing')));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $search = trim((string) $request->query('search', ''));

        $hasPaymentHistoryTable = Schema::hasTable('addons_order_payment_histories');
        $addonsRelations = ['store:id,name,url', 'user:id,name,phone,email'];
        if ($hasPaymentHistoryTable) {
            $addonsRelations[] = 'paymentHistories';
        }
        $addonsQuery = AddonsOrder::query()->with($addonsRelations);
        $modulusQuery = ModulusPayment::query()->with(['getModulus:id,name', 'store:id,name,url']);

        if ($status === '' || $status === 'processing') {
            if ($hasPaymentHistoryTable) {
                $addonsQuery->where(function ($q) {
                    $q->where('status', 'Processing')
                        ->orWhereHas('paymentHistories', function ($h) {
                            $h->where('due_amount_status', 'pending_acceptance');
                        });
                });
            } else {
                $addonsQuery->where(function ($q) {
                    $q->where('status', 'Processing')
                        ->orWhere('due_amount_status', 'pending_acceptance');
                });
            }
            $modulusQuery->whereNull('status');
        } elseif ($status === 'complete') {
            $addonsQuery->whereIn('status', ['Complete', 'complete', 'Accepted', 'accepted', 'Success', 'success']);
            $modulusQuery->where('status', 1);
        } elseif ($status === 'failed' || $status === 'rejected') {
            $addonsQuery->whereIn('status', ['Failed', 'failed', 'Rejected', 'rejected']);
            $modulusQuery->where('status', 0);
        } elseif ($status === 'pending_acceptance') {
            if ($hasPaymentHistoryTable) {
                $addonsQuery->whereHas('paymentHistories', function ($h) {
                    $h->where('due_amount_status', 'pending_acceptance');
                });
            } else {
                $addonsQuery->where('due_amount_status', 'pending_acceptance');
            }
            $modulusQuery->whereRaw('1=0');
        } elseif ($status === 'today') {
            $addonsQuery->whereDate('created_at', Carbon::today());
            $modulusQuery->whereDate('created_at', Carbon::today());
        }

        if ($search !== '') {
            $addonsQuery->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('url', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
            $modulusQuery->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('url', 'like', "%{$search}%");
                    })
                    ->orWhereHas('getModulus', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $addonsRows = $addonsQuery->get()->map(function ($row) use ($hasPaymentHistoryTable) {
            $latestHistory = $hasPaymentHistoryTable
                ? collect($row->paymentHistories ?? [])->sortByDesc('id')->first()
                : null;
            $addons = is_array($row->addons) ? $row->addons : (json_decode((string) $row->addons, true) ?: []);
            $addonNames = collect($addons)->map(function ($item) {
                if (!is_array($item)) return null;
                return $item['title'] ?? $item['name'] ?? null;
            })->filter()->values()->all();
            return [
                'id' => (int) $row->id,
                'kind' => 'plan_order',
                'store_id' => (int) ($row->store_id ?? 0),
                'store_name' => (string) ($row->store->name ?? ''),
                'store_url' => (string) ($row->store->url ?? ''),
                'customer_name' => (string) ($row->user->name ?? ''),
                'customer_phone' => (string) ($row->user->phone ?? ''),
                'plan_name' => (string) ($this->resolvePlanOrderName($row) ?? ''),
                'package_name' => (string) ($this->resolvePlanPackageName($row) ?? ''),
                'months' => (string) ($this->resolvePlanOrderMonths($row) ?? ''),
                'amount' => (float) ($row->total ?? 0),
                'payment_method' => (string) ($row->method ?? ''),
                'transaction_id' => (string) ($row->transaction_id ?? ''),
                'number' => (string) ($row->number ?? ''),
                'addon_names' => $addonNames,
                'status' => (string) ($row->status ?? ''),
                'due_status' => (string) ($latestHistory->due_amount_status ?? $row->due_amount_status ?? ''),
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y h:i A') : '',
            ];
        });

        $modulusRows = $modulusQuery->get()->map(function ($row) {
            $status = is_null($row->status) ? 'Processing' : ((int) $row->status === 1 ? 'Complete' : 'Failed');
            return [
                'id' => (int) $row->id,
                'kind' => 'modulus_order',
                'store_id' => (int) ($row->store_id ?? 0),
                'store_name' => (string) ($row->store->name ?? ''),
                'store_url' => (string) ($row->store->url ?? ''),
                'customer_name' => '',
                'customer_phone' => '',
                'plan_name' => (string) ($row->getModulus->name ?? 'Modulus'),
                'package_name' => 'Modulus',
                'months' => '-',
                'amount' => (float) ($row->price ?? 0),
                'payment_method' => (string) ($row->payment_type ?? ''),
                'transaction_id' => (string) ($row->transaction_id ?? ''),
                'number' => (string) ($row->number ?? ''),
                'addon_names' => [],
                'status' => $status,
                'due_status' => '',
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y h:i A') : '',
            ];
        });

        if ($sourceType === 'modulus') {
            $merged = $modulusRows;
        } elseif ($sourceType === 'all') {
            $merged = $addonsRows->merge($modulusRows);
        } else {
            $merged = $addonsRows;
        }

        $merged = $merged->sortByDesc(function ($row) {
            return strtotime((string) ($row['created_at'] ?? ''));
        })->values();

        $total = $merged->count();
        $items = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'items' => $items,
            'summary' => [
                'total_amount' => (float) $merged->sum('amount'),
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function superadminOrderPlanRequestAccept(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $order = AddonsOrder::query()->find($id);
        if (!$order) {
            return response()->json(['message' => 'Plan order not found.'], 404);
        }

        $result = app(SuperAdminController::class)->newacceptplanorder($id, true);
        if (is_array($result) && !($result['status'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Could not accept request.'], 422);
        }

        return response()->json(['status' => true, 'message' => 'Order accepted successfully.']);
    }

    public function superadminOrderPlanRequestReject(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $order = AddonsOrder::query()->find($id);
        if (!$order) {
            return response()->json(['message' => 'Plan order not found.'], 404);
        }
        $order->status = 'Failed';
        $order->save();

        return response()->json(['status' => true, 'message' => 'Order rejected successfully.']);
    }

    public function superadminModulusRequests(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->superadminOrderPlanRequests($request, (string) $request->query('payment_status', 'processing'), 'modulus');
    }

    public function superadminModulusRequestAccept(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = ModulusPayment::query()->find($id);
        if (!$row) return response()->json(['message' => 'Modulus request not found.'], 404);
        $row->status = 1;
        $row->save();
        return response()->json(['status' => true, 'message' => 'Modulus request accepted.']);
    }

    public function superadminModulusRequestReject(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = ModulusPayment::query()->find($id);
        if (!$row) return response()->json(['message' => 'Modulus request not found.'], 404);
        $row->status = 0;
        $row->save();
        return response()->json(['status' => true, 'message' => 'Modulus request rejected.']);
    }

    public function superadminInvoiceOrders(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $scope = trim((string) $request->query('scope', 'pending'));
        $search = trim((string) $request->query('search', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));

        $query = DB::table('invoiceorders as io')
            ->leftJoin('designlists as d', 'd.id', '=', 'io.invoice_id')
            ->leftJoin('stores as s', 's.id', '=', 'io.store_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.uid')
            ->select([
                'io.id',
                'io.amount',
                'io.payment_method',
                'io.transaction_id',
                'io.number',
                'io.status',
                'io.created_at',
                'd.name as invoice_name',
                's.name as store_name',
                'u.name as customer_name',
            ]);

        if ($scope === 'pending') {
            $query->where('io.status', 'Processing');
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('io.transaction_id', 'like', "%{$search}%")
                    ->orWhere('d.name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('io.id')->paginate($perPage, ['*'], 'page', $page);
        $items = collect($paginator->items())->map(function ($row, $idx) use ($paginator) {
            return [
                'sl' => (($paginator->currentPage() - 1) * $paginator->perPage()) + $idx + 1,
                'id' => (int) $row->id,
                'invoice_name' => (string) ($row->invoice_name ?? ''),
                'store_name' => (string) ($row->store_name ?? ''),
                'customer_name' => (string) ($row->customer_name ?? ''),
                'amount' => (float) ($row->amount ?? 0),
                'payment_method' => (string) ($row->payment_method ?? ''),
                'transaction_id' => (string) ($row->transaction_id ?? ''),
                'number' => (string) ($row->number ?? ''),
                'status' => (string) ($row->status ?? ''),
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function superadminInvoiceOrderAccept(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = DB::table('invoiceorders')->where('id', $id)->first();
        if (!$row) return response()->json(['message' => 'Invoice order not found.'], 404);
        DB::table('invoiceorders')->where('id', $id)->update(['status' => 'Complete', 'updated_at' => now()]);
        return response()->json(['status' => true, 'message' => 'Invoice order accepted.']);
    }

    public function superadminInvoiceOrderReject(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = DB::table('invoiceorders')->where('id', $id)->first();
        if (!$row) return response()->json(['message' => 'Invoice order not found.'], 404);
        DB::table('invoiceorders')->where('id', $id)->update(['status' => 'Failed', 'updated_at' => now()]);
        return response()->json(['status' => true, 'message' => 'Invoice order rejected.']);
    }

    public function superadminPlansCatalog(Request $request, string $planType): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $type = strtolower(trim($planType));
        $search = trim((string) $request->query('search', ''));

        if ($type === 'website') {
            $query = Plan::query()->orderBy('position', 'asc');
            if ($search !== '') $query->where('name', 'like', "%{$search}%");
            $items = $query->get()->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'price' => (float) ($row->price ?? 0),
                    'usd_price' => $row->usd_price !== null ? (float) $row->usd_price : null,
                    'price_usd' => $row->usd_price !== null ? (float) $row->usd_price : null,
                    'staff' => (string) ($row->staff ?? ''),
                    'product' => (string) ($row->product ?? ''),
                    'google_ad' => (string) ($row->google_ad ?? ''),
                    'order' => (string) ($row->order ?? ''),
                    'monthly_chat_support' => (string) ($row->monthly_chat_support ?? ''),
                    'position' => (int) ($row->position ?? 0),
                    'status' => (string) ($row->status ?? ''),
                    'is_trial' => $this->tableHasColumn('plans', 'is_trial') ? (bool) ($row->is_trial ?? false) : in_array((int) $row->id, [6, 9], true),
                    'plan_type' => 'website',
                ];
            })->values();
            return response()->json(['items' => $items]);
        }

        if ($type === 'pos') {
            $query = Posplan::query()->orderBy('position', 'asc');
            if ($search !== '') $query->where('name', 'like', "%{$search}%");
            $items = $query->get()->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'price' => (float) ($row->price ?? 0),
                    'price_usd' => (float) ($row->usd_price ?? 0),
                    'staff' => (string) ($row->staff ?? ''),
                    'product' => (string) ($row->product ?? ''),
                    'google_ad' => '',
                    'order' => (string) ($row->order ?? ''),
                    'monthly_chat_support' => (string) ($row->monthly_chat_support ?? ''),
                    'position' => (int) ($row->position ?? 0),
                    'status' => (string) ($row->status ?? ''),
                    'plan_type' => 'pos',
                ];
            })->values();
            return response()->json(['items' => $items]);
        }

        if ($type === 'digital') {
            $query = Digitalplan::query()->orderBy('position', 'asc');
            if ($search !== '') $query->where('name', 'like', "%{$search}%");
            $items = $query->get()->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'price' => (float) ($row->price ?? 0),
                    'price_usd' => null,
                    'staff' => (string) ($row->page_setup ?? ''),
                    'product' => (string) ($row->static_content ?? ''),
                    'google_ad' => (string) ($row->google_ad ?? ''),
                    'order' => (string) ($row->video_content ?? ''),
                    'monthly_chat_support' => (string) ($row->monthly_chat_support ?? ''),
                    'position' => (int) ($row->position ?? 0),
                    'status' => (string) ($row->status ?? ''),
                    'plan_type' => 'digital',
                ];
            })->values();
            return response()->json(['items' => $items]);
        }

        return response()->json(['message' => 'Invalid plan type.'], 422);
    }

    public function superadminPlanShow(Request $request, string $planType, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [$type, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);

        $query = $modelClass::query();
        if ($type === 'website') {
            $query->with(['details' => function ($detailsQuery) {
                $detailsQuery->orderBy('position', 'asc');
            }]);
        }

        $row = $query->find($id);
        if (!$row) return response()->json(['message' => 'Plan not found.'], 404);

        $item = [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ''),
            'subtitle' => (string) ($row->subtitle ?? ''),
            'price' => (float) ($row->price ?? 0),
            'discount_type' => (string) ($row->discount_type ?? 'no_discount'),
            'onedis' => $row->onedis,
            'sixdis' => $row->sixdis,
            'twelvedis' => $row->twelvedis,
            'twentyfourdis' => $row->twentyfourdis,
            'usd_price' => $row->usd_price !== null ? (float) $row->usd_price : null,
            'price_usd' => $row->usd_price !== null ? (float) $row->usd_price : null,
            'usd_discount_type' => (string) ($row->usd_discount_type ?? 'no_discount'),
            'usd_1_dis' => $row->usd_1_dis,
            'usd_6_dis' => $row->usd_6_dis,
            'usd_12_dis' => $row->usd_12_dis,
            'usd_24_dis' => $row->usd_24_dis,
            'staff' => $type === 'digital' ? (string) ($row->page_setup ?? '') : (string) ($row->staff ?? ''),
            'product' => $type === 'digital' ? (string) ($row->static_content ?? '') : (string) ($row->product ?? ''),
            'category' => (string) ($row->category ?? ''),
            'sub_category' => (string) ($row->sub_category ?? ''),
            'inventory' => (string) ($row->inventory ?? 'Yes'),
            'google_ad' => (string) ($row->google_ad ?? ''),
            'advance_report' => (string) ($row->advance_report ?? 'Yes'),
            'website_setup' => (string) ($row->website_setup ?? 'Yes'),
            'order' => $type === 'digital' ? (string) ($row->video_content ?? '') : (string) ($row->order ?? ''),
            'payment_processing_charge' => $row->payment_processing_charge,
            'monthly_chat_support' => (string) ($row->monthly_chat_support ?? ''),
            'upload_file_limit' => $row->upload_file_limit,
            'position' => (int) ($row->position ?? 0),
            'status' => $row->status,
            'is_trial' => $type === 'website'
                ? ($this->tableHasColumn('plans', 'is_trial') ? (bool) ($row->is_trial ?? false) : in_array((int) $row->id, [6, 9], true))
                : false,
            'plan_type' => $type,
            'details' => $type === 'website'
                ? collect($row->details ?? [])->map(function ($detail) {
                    return [
                        'id' => (int) $detail->id,
                        'title' => (string) ($detail->title ?? ''),
                        'position' => (int) ($detail->position ?? 0),
                        'type' => (string) ($detail->type ?? 'features'),
                        'status' => (bool) $detail->status,
                    ];
                })->values()
                : [],
        ];

        return response()->json(['item' => $item]);
    }

    public function superadminPlanEntitlementCatalog(Request $request, string $planType, ?int $id = null): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);
        if ($id) {
            $row = $modelClass::query()->find($id);
            if (!$row) return response()->json(['message' => 'Plan not found.'], 404);
        }

        $this->syncDiscoveredFeatureCatalog();
        $features = SaasFeature::query()
            ->where(function ($query) {
                $query
                    ->where('key', 'like', 'pages.%')
                    ->orWhere('key', 'like', 'quota.%')
                    ->orWhere('key', 'like', 'admin.%');
            })
            ->orderBy('type', 'asc')
            ->orderBy('key', 'asc')
            ->get(['key', 'name', 'type', 'enabled_by_default', 'default_limit']);

        $planMap = collect();
        if ($id) {
            $planMap = PlanEntitlement::query()
                ->where('plan_id', $id)
                ->get(['feature_key', 'is_enabled', 'limit_value'])
                ->keyBy('feature_key');
        }

        $items = $features->map(function ($feature) use ($planMap) {
            $planRow = $planMap->get((string) $feature->key);
            $enabled = $planRow ? (bool) $planRow->is_enabled : (bool) $feature->enabled_by_default;
            $limit = $planRow && $planRow->limit_value !== null
                ? (int) $planRow->limit_value
                : ($feature->default_limit !== null ? (int) $feature->default_limit : null);
            return [
                'key' => (string) $feature->key,
                'name' => (string) $feature->name,
                'type' => (string) $feature->type,
                'enabled' => $enabled,
                'limit' => $limit,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function superadminPlanCreate(Request $request, string $planType): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [$type, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'max:255'],
            'onedis' => ['nullable', 'numeric', 'min:0'],
            'sixdis' => ['nullable', 'numeric', 'min:0'],
            'twelvedis' => ['nullable', 'numeric', 'min:0'],
            'twentyfourdis' => ['nullable', 'numeric', 'min:0'],
            'usd_price' => ['nullable', 'numeric', 'min:0'],
            'price_usd' => ['nullable', 'numeric', 'min:0'],
            'usd_discount_type' => ['nullable', 'string', 'max:255'],
            'usd_1_dis' => ['nullable', 'numeric', 'min:0'],
            'usd_6_dis' => ['nullable', 'numeric', 'min:0'],
            'usd_12_dis' => ['nullable', 'numeric', 'min:0'],
            'usd_24_dis' => ['nullable', 'numeric', 'min:0'],
            'staff' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'inventory' => ['nullable', 'string', 'max:255'],
            'google_ad' => ['nullable', 'string', 'max:255'],
            'advance_report' => ['nullable', 'string', 'max:255'],
            'website_setup' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'string', 'max:255'],
            'payment_processing_charge' => ['nullable', 'numeric', 'min:0'],
            'monthly_chat_support' => ['nullable', 'string', 'max:255'],
            'upload_file_limit' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable'],
            'is_trial' => ['nullable', 'boolean'],
            'details' => ['nullable', 'array'],
            'details.*.id' => ['nullable', 'integer'],
            'details.*.title' => ['nullable', 'string', 'max:255'],
            'details.*.position' => ['nullable', 'integer', 'min:1'],
            'details.*.type' => ['nullable', 'string', 'max:255'],
            'details.*.status' => ['nullable'],
            'entitlements' => ['nullable', 'array'],
            'entitlements.*.key' => ['required_with:entitlements', 'string', 'max:120'],
            'entitlements.*.enabled' => ['nullable', 'boolean'],
            'entitlements.*.limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $row = new $modelClass();
        $row->name = (string) $payload['name'];
        $row->price = (float) $payload['price'];
        $row->position = (int) ($payload['position'] ?? ((int) $modelClass::query()->max('position') + 1));

        if ($type === 'website') {
            $row->subtitle = (string) ($payload['subtitle'] ?? '');
            $row->discount_type = (string) ($payload['discount_type'] ?? 'no_discount');
            $row->onedis = $payload['onedis'] ?? null;
            $row->sixdis = $payload['sixdis'] ?? null;
            $row->twelvedis = $payload['twelvedis'] ?? null;
            $row->twentyfourdis = $payload['twentyfourdis'] ?? null;
            $row->usd_price = $payload['usd_price'] ?? $payload['price_usd'] ?? null;
            $row->usd_discount_type = (string) ($payload['usd_discount_type'] ?? 'no_discount');
            $row->usd_1_dis = $payload['usd_1_dis'] ?? null;
            $row->usd_6_dis = $payload['usd_6_dis'] ?? null;
            $row->usd_12_dis = $payload['usd_12_dis'] ?? null;
            $row->usd_24_dis = $payload['usd_24_dis'] ?? null;
            $row->staff = (string) ($payload['staff'] ?? '');
            $row->product = (string) ($payload['product'] ?? '');
            $row->category = (string) ($payload['category'] ?? '');
            $row->sub_category = (string) ($payload['sub_category'] ?? '');
            $row->inventory = (string) ($payload['inventory'] ?? 'No');
            $row->google_ad = (string) ($payload['google_ad'] ?? 'No');
            $row->advance_report = (string) ($payload['advance_report'] ?? 'No');
            $row->website_setup = (string) ($payload['website_setup'] ?? 'No');
            $row->order = (string) ($payload['order'] ?? '');
            $row->payment_processing_charge = $payload['payment_processing_charge'] ?? 0;
            $row->monthly_chat_support = (string) ($payload['monthly_chat_support'] ?? '');
            $row->upload_file_limit = $payload['upload_file_limit'] ?? 0;
            $this->applyWebsiteTrialPlanFlag($row, (bool) ($payload['is_trial'] ?? false));
        } elseif ($type === 'pos') {
            $row->usd_price = $payload['usd_price'] ?? $payload['price_usd'] ?? 0;
            $row->staff = (string) ($payload['staff'] ?? '');
            $row->product = (string) ($payload['product'] ?? '');
            $row->order = (string) ($payload['order'] ?? '');
            $row->monthly_chat_support = (string) ($payload['monthly_chat_support'] ?? '');
        } else {
            $row->page_setup = (string) ($payload['staff'] ?? '');
            $row->static_content = (string) ($payload['product'] ?? '');
            $row->google_ad = (string) ($payload['google_ad'] ?? '');
            $row->video_content = (string) ($payload['order'] ?? '');
            $row->monthly_chat_support = (string) ($payload['monthly_chat_support'] ?? '');
        }

        $row->status = array_key_exists('status', $payload)
            ? $this->normalizePlanStatusValue($payload['status'], $row->status ?? null)
            : 'active';
        $row->save();
        if ($type === 'website') {
            $this->syncExclusiveWebsiteTrialPlan($row);
        }

        if ($type === 'website') {
            foreach (($payload['details'] ?? []) as $index => $detailPayload) {
                $title = trim((string) ($detailPayload['title'] ?? ''));
                if ($title === '') continue;
                $detail = new PlanDetail();
                $detail->plan_id = $row->id;
                $detail->title = $title;
                $detail->position = (int) ($detailPayload['position'] ?? ($index + 1));
                $detail->type = (string) ($detailPayload['type'] ?? 'features');
                $detail->status = !empty($detailPayload['status']);
                $detail->save();
            }
        }

        $this->syncPlanEntitlements((int) $row->id, $payload['entitlements'] ?? []);

        return response()->json(['status' => true, 'message' => 'Plan created successfully.', 'id' => (int) $row->id], 201);
    }

    public function superadminPlanUpdate(Request $request, string $planType, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [$type, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);

        $row = $modelClass::query()->find($id);
        if (!$row) return response()->json(['message' => 'Plan not found.'], 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'max:255'],
            'onedis' => ['nullable', 'numeric', 'min:0'],
            'sixdis' => ['nullable', 'numeric', 'min:0'],
            'twelvedis' => ['nullable', 'numeric', 'min:0'],
            'twentyfourdis' => ['nullable', 'numeric', 'min:0'],
            'usd_price' => ['nullable', 'numeric', 'min:0'],
            'price_usd' => ['nullable', 'numeric', 'min:0'],
            'usd_discount_type' => ['nullable', 'string', 'max:255'],
            'usd_1_dis' => ['nullable', 'numeric', 'min:0'],
            'usd_6_dis' => ['nullable', 'numeric', 'min:0'],
            'usd_12_dis' => ['nullable', 'numeric', 'min:0'],
            'usd_24_dis' => ['nullable', 'numeric', 'min:0'],
            'staff' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'inventory' => ['nullable', 'string', 'max:255'],
            'google_ad' => ['nullable', 'string', 'max:255'],
            'advance_report' => ['nullable', 'string', 'max:255'],
            'website_setup' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'string', 'max:255'],
            'payment_processing_charge' => ['nullable', 'numeric', 'min:0'],
            'monthly_chat_support' => ['nullable', 'string', 'max:255'],
            'upload_file_limit' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable'],
            'is_trial' => ['nullable', 'boolean'],
            'details' => ['nullable', 'array'],
            'details.*.id' => ['nullable', 'integer'],
            'details.*.title' => ['nullable', 'string', 'max:255'],
            'details.*.position' => ['nullable', 'integer', 'min:1'],
            'details.*.type' => ['nullable', 'string', 'max:255'],
            'details.*.status' => ['nullable'],
            'entitlements' => ['nullable', 'array'],
            'entitlements.*.key' => ['required_with:entitlements', 'string', 'max:120'],
            'entitlements.*.enabled' => ['nullable', 'boolean'],
            'entitlements.*.limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $row->name = (string) $payload['name'];
        $row->price = (float) $payload['price'];
        if (array_key_exists('position', $payload)) $row->position = (int) $payload['position'];

        if ($type === 'website') {
            $row->subtitle = (string) ($payload['subtitle'] ?? '');
            $row->discount_type = (string) ($payload['discount_type'] ?? 'no_discount');
            $row->onedis = $payload['onedis'] ?? null;
            $row->sixdis = $payload['sixdis'] ?? null;
            $row->twelvedis = $payload['twelvedis'] ?? null;
            $row->twentyfourdis = $payload['twentyfourdis'] ?? null;
            $row->usd_price = $payload['usd_price'] ?? $payload['price_usd'] ?? null;
            $row->usd_discount_type = (string) ($payload['usd_discount_type'] ?? 'no_discount');
            $row->usd_1_dis = $payload['usd_1_dis'] ?? null;
            $row->usd_6_dis = $payload['usd_6_dis'] ?? null;
            $row->usd_12_dis = $payload['usd_12_dis'] ?? null;
            $row->usd_24_dis = $payload['usd_24_dis'] ?? null;
            $row->staff = (string) ($payload['staff'] ?? '');
            $row->product = (string) ($payload['product'] ?? '');
            $row->category = (string) ($payload['category'] ?? '');
            $row->sub_category = (string) ($payload['sub_category'] ?? '');
            $row->inventory = (string) ($payload['inventory'] ?? 'No');
            $row->google_ad = (string) ($payload['google_ad'] ?? 'No');
            $row->advance_report = (string) ($payload['advance_report'] ?? 'No');
            $row->website_setup = (string) ($payload['website_setup'] ?? 'No');
            $row->order = (string) ($payload['order'] ?? '');
            $row->payment_processing_charge = $payload['payment_processing_charge'] ?? 0;
            $row->monthly_chat_support = (string) ($payload['monthly_chat_support'] ?? '');
            $row->upload_file_limit = $payload['upload_file_limit'] ?? 0;
            if (array_key_exists('is_trial', $payload)) {
                $this->applyWebsiteTrialPlanFlag($row, (bool) $payload['is_trial']);
            }
        } elseif ($type === 'pos') {
            if (array_key_exists('usd_price', $payload) || array_key_exists('price_usd', $payload)) {
                $row->usd_price = (float) ($payload['usd_price'] ?? $payload['price_usd'] ?? 0);
            }
            $row->staff = (string) ($payload['staff'] ?? '');
            $row->product = (string) ($payload['product'] ?? '');
            $row->order = (string) ($payload['order'] ?? '');
            $row->monthly_chat_support = (string) ($payload['monthly_chat_support'] ?? '');
        } else {
            $row->page_setup = (string) ($payload['staff'] ?? '');
            $row->static_content = (string) ($payload['product'] ?? '');
            $row->google_ad = (string) ($payload['google_ad'] ?? '');
            $row->video_content = (string) ($payload['order'] ?? '');
            $row->monthly_chat_support = (string) ($payload['monthly_chat_support'] ?? '');
        }

        if (array_key_exists('status', $payload)) {
            $row->status = $this->normalizePlanStatusValue($payload['status'], $row->status ?? null);
        }
        $row->save();
        if ($type === 'website') {
            $this->syncExclusiveWebsiteTrialPlan($row);
        }

        if ($type === 'website') {
            $submittedIds = [];
            foreach (($payload['details'] ?? []) as $index => $detailPayload) {
                $title = trim((string) ($detailPayload['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $detailId = isset($detailPayload['id']) ? (int) $detailPayload['id'] : null;
                $detail = $detailId
                    ? PlanDetail::query()->where('plan_id', $row->id)->find($detailId)
                    : new PlanDetail();

                if (!$detail) {
                    $detail = new PlanDetail();
                }

                $detail->plan_id = $row->id;
                $detail->title = $title;
                $detail->position = (int) ($detailPayload['position'] ?? ($index + 1));
                $detail->type = (string) ($detailPayload['type'] ?? 'features');
                $detail->status = !empty($detailPayload['status']);
                $detail->save();
                $submittedIds[] = (int) $detail->id;
            }

            PlanDetail::query()
                ->where('plan_id', $row->id)
                ->when(!empty($submittedIds), function ($query) use ($submittedIds) {
                    $query->whereNotIn('id', $submittedIds);
                })
                ->when(empty($submittedIds), function ($query) {
                    return $query;
                })
                ->delete();
        }

        $this->syncPlanEntitlements((int) $row->id, $payload['entitlements'] ?? []);

        return response()->json(['status' => true, 'message' => 'Plan updated successfully.']);
    }

    public function superadminPlanDelete(Request $request, string $planType, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);
        $row = $modelClass::query()->find($id);
        if (!$row) return response()->json(['message' => 'Plan not found.'], 404);
        $row->delete();
        return response()->json(['status' => true, 'message' => 'Plan deleted successfully.']);
    }

    public function superadminPlanToggleStatus(Request $request, string $planType, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);
        $row = $modelClass::query()->find($id);
        if (!$row) return response()->json(['message' => 'Plan not found.'], 404);

        $current = strtolower((string) ($row->status ?? ''));
        $isNumericStatus = is_numeric($row->status);
        $isActive = in_array($current, ['1', 'true', 'active', 'enabled', 'on'], true);
        $row->status = $isNumericStatus ? ($isActive ? 0 : 1) : ($isActive ? 'inactive' : 'active');
        $row->save();

        return response()->json([
            'status' => true,
            'message' => 'Plan status updated.',
            'value' => $row->status,
        ]);
    }

    public function superadminPlanToggleTrial(Request $request, string $planType, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [$type, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if ($type !== 'website' || !$modelClass) {
            return response()->json(['message' => 'Trial package can be selected only for website plans.'], 422);
        }
        if (!$this->tableHasColumn('plans', 'is_trial')) {
            return response()->json(['message' => 'Trial package column is missing. Please run the latest migration.'], 422);
        }

        $row = $modelClass::query()->find($id);
        if (!$row) return response()->json(['message' => 'Plan not found.'], 404);

        $row->is_trial = !((bool) ($row->is_trial ?? false));
        $row->save();
        $this->syncExclusiveWebsiteTrialPlan($row);

        return response()->json([
            'status' => true,
            'message' => (bool) $row->is_trial ? 'Trial package selected.' : 'Trial package removed.',
            'value' => (bool) $row->is_trial,
        ]);
    }

    private function applyWebsiteTrialPlanFlag($row, bool $isTrial): void
    {
        if ($this->tableHasColumn('plans', 'is_trial')) {
            $row->is_trial = $isTrial;
        }
    }

    private function syncExclusiveWebsiteTrialPlan($row): void
    {
        if (!$this->tableHasColumn('plans', 'is_trial') || !((bool) ($row->is_trial ?? false))) {
            return;
        }

        Plan::query()
            ->where('id', '!=', (int) $row->id)
            ->where('is_trial', true)
            ->update(['is_trial' => false]);
    }

    private function currentWebsiteTrialPlanId(): ?int
    {
        if ($this->tableHasColumn('plans', 'is_trial')) {
            $id = Plan::query()
                ->where('is_trial', true)
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereIn('status', ['1', 1, 'true', true, 'active', 'enabled', 'on']);
                })
                ->orderBy('position', 'asc')
                ->orderBy('id', 'asc')
                ->value('id');

            return $id ? (int) $id : null;
        }

        $legacyId = Plan::query()
            ->whereIn('id', [6, 9])
            ->orderByRaw('FIELD(id, 6, 9)')
            ->value('id');

        return $legacyId ? (int) $legacyId : null;
    }

    private function isWebsiteTrialPlanId($planId): bool
    {
        $planId = (int) $planId;
        if ($planId <= 0) {
            return false;
        }

        if ($this->tableHasColumn('plans', 'is_trial')) {
            return Plan::query()->where('id', $planId)->where('is_trial', true)->exists();
        }

        return in_array($planId, [6, 9], true);
    }

    public function superadminPlanReorder(Request $request, string $planType): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        [, $modelClass] = $this->resolvePlanTypeAndModel($planType);
        if (!$modelClass) return response()->json(['message' => 'Invalid plan type.'], 422);

        $payload = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $payload['ordered_ids'] ?? [])));
        $rows = $modelClass::query()->whereIn('id', $ids)->get()->keyBy('id');
        if (count($ids) !== $rows->count()) {
            return response()->json(['message' => 'Some plans were not found for reordering.'], 422);
        }

        DB::transaction(function () use ($ids, $rows) {
            foreach ($ids as $index => $id) {
                $row = $rows->get($id);
                if ($row) {
                    $row->position = $index + 1;
                    $row->save();
                }
            }
        });

        return response()->json(['status' => true, 'message' => 'Plan positions updated successfully.']);
    }

    private function resolvePlanTypeAndModel(string $planType): array
    {
        $type = strtolower(trim($planType));
        if ($type === 'website') return ['website', Plan::class];
        if ($type === 'pos') return ['pos', Posplan::class];
        if ($type === 'digital') return ['digital', Digitalplan::class];
        return [$type, null];
    }

    private function normalizePlanStatusValue($incoming, $current)
    {
        $value = strtolower(trim((string) $incoming));
        if (in_array($value, ['1', 'true', 'active', 'enabled', 'on'], true)) {
            return is_numeric($current) ? 1 : 'active';
        }
        if (in_array($value, ['0', 'false', 'inactive', 'disabled', 'off'], true)) {
            return is_numeric($current) ? 0 : 'inactive';
        }
        return $incoming;
    }

    private function syncPlanEntitlements(int $planId, array $entitlements): void
    {
        $this->syncDiscoveredFeatureCatalog();
        $knownFeatureKeys = SaasFeature::query()->pluck('key')->map(fn($x) => (string) $x)->values()->all();
        if (empty($knownFeatureKeys)) {
            PlanEntitlement::query()->where('plan_id', $planId)->delete();
            return;
        }

        $knownMap = array_fill_keys($knownFeatureKeys, true);
        $submittedKeys = [];

        foreach ($entitlements as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '' || !isset($knownMap[$key])) continue;

            $submittedKeys[] = $key;
            PlanEntitlement::query()->updateOrCreate(
                ['plan_id' => $planId, 'feature_key' => $key],
                [
                    'is_enabled' => !empty($item['enabled']),
                    'limit_value' => array_key_exists('limit', $item) && $item['limit'] !== null && $item['limit'] !== ''
                        ? (int) $item['limit']
                        : null,
                ]
            );
        }

        PlanEntitlement::query()
            ->where('plan_id', $planId)
            ->whereNotIn('feature_key', $submittedKeys)
            ->delete();
    }

    private function syncDiscoveredFeatureCatalog(): void
    {
        $features = [];

        // Auto-discover React RouteFeatureGuard keys so newly added guarded routes appear automatically.
        $routesFile = base_path('../Admin_FrontEnd_React/src/routes.jsx');
        if (is_file($routesFile)) {
            $content = @file_get_contents($routesFile) ?: '';
            if ($content !== '') {
                preg_match_all('/feature="([^"]+)"/', $content, $matches);
                foreach (($matches[1] ?? []) as $rawKey) {
                    $key = trim((string) $rawKey);
                    if ($key === '') continue;
                    $features[$key] = [
                        'key' => $key,
                        'name' => $this->humanizeFeatureKey($key),
                        'type' => str_starts_with($key, 'quota.') ? 'quota' : 'page',
                        'enabled_by_default' => true,
                        'default_limit' => null,
                    ];
                }
            }
        }

        // Auto-discover admin API routes (create/edit/delete/update etc.) for dynamic access matrix.
        $webRoutesFile = base_path('routes/web.php');
        if (is_file($webRoutesFile)) {
            $content = @file_get_contents($webRoutesFile) ?: '';
            if ($content !== '') {
                preg_match_all('/Route::(get|post|put|patch|delete)\(\s*[\'"]\/([^\'"]+)[\'"]\s*,/i', $content, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $method = strtolower(trim((string) ($match[1] ?? 'get')));
                    $path = trim((string) ($match[2] ?? ''));
                    if ($path === '') continue;
                    if ($this->shouldSkipAdminEntitlementRoute($path)) continue;

                    $normalizedPath = preg_replace('/\{[^}]+\}/', '*', $path);
                    $normalizedPath = str_replace('/', '.', (string) $normalizedPath);
                    $normalizedPath = preg_replace('/[^a-zA-Z0-9._*-]/', '', (string) $normalizedPath) ?: 'unknown';
                    $key = 'admin.' . $method . '.' . strtolower($normalizedPath);

                    $type = $method === 'get' ? 'page' : 'action';
                    $name = strtoupper($method) . ': ' . $this->humanizeFeatureKey($path);

                    $features[$key] = [
                        'key' => $key,
                        'name' => $name,
                        'type' => $type,
                        'enabled_by_default' => true,
                        'default_limit' => null,
                    ];
                }
            }
        }

        foreach ($features as $feature) {
            SaasFeature::query()->updateOrCreate(
                ['key' => $feature['key']],
                [
                    'name' => $feature['name'],
                    'type' => $feature['type'],
                    'enabled_by_default' => $feature['enabled_by_default'],
                    'default_limit' => $feature['default_limit'],
                ]
            );
        }
    }

    private function shouldSkipAdminEntitlementRoute(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') return true;

        foreach ([
            'csrf-token',
            'login',
            'logout',
            'register',
            'password',
            'public',
            'superadmin',
        ] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function humanizeFeatureKey(string $value): string
    {
        $label = str_replace(['.', '_', '-'], ' ', $value);
        $label = preg_replace('/\s+/', ' ', $label) ?: $value;
        return Str::title(trim($label));
    }

    public function superadminAddonsList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $rows = AddonsApi::query()->orderBy('position')->orderByDesc('id')->get();
        $items = $rows->map(function (AddonsApi $row) {
            return [
                'id' => (int) $row->id,
                'title' => (string) ($row->title ?? ''),
                'type' => (string) ($row->type ?? 'oneTime'),
                'name' => is_array($row->name) ? $row->name : [],
                'price' => is_array($row->price) ? $row->price : [],
                'offerprice' => is_array($row->offerprice) ? $row->offerprice : [],
                'monthorvalue' => is_array($row->monthorvalue) ? $row->monthorvalue : [],
                'position' => (int) ($row->position ?? 0),
                'status' => (int) ($row->status ?? 0),
                'image' => (string) ($row->image ?? ''),
                'image_url' => $this->resolveAddonImagePublicUrl($row->image),
                'usd_price' => json_decode((string) ($row->getRawOriginal('usd_price') ?? '[]'), true) ?: [],
                'usd_offer_price' => json_decode((string) ($row->getRawOriginal('usd_offer_price') ?? '[]'), true) ?: [],
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function superadminAddonsStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $addon = new AddonsApi();
        return $this->saveSuperadminAddon($request, $addon);
    }

    public function superadminAddonsUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $addon = AddonsApi::query()->find($id);
        if (!$addon) {
            return response()->json(['message' => 'Addon not found.'], 404);
        }
        return $this->saveSuperadminAddon($request, $addon);
    }

    public function superadminAddonsToggleStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $addon = AddonsApi::query()->find($id);
        if (!$addon) {
            return response()->json(['message' => 'Addon not found.'], 404);
        }
        $addon->status = (int) ($addon->status ?? 0) === 1 ? 0 : 1;
        $addon->save();
        return response()->json(['status' => true, 'value' => (int) $addon->status]);
    }

    public function superadminAddonsReorder(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $payload['ordered_ids'])));
        $existing = AddonsApi::query()->whereIn('id', $ids)->pluck('id')->map(fn($id) => (int) $id)->all();
        if (count($existing) !== count($ids)) {
            return response()->json(['message' => 'Some addons were not found for reordering.'], 422);
        }

        foreach ($ids as $index => $id) {
            AddonsApi::query()->where('id', $id)->update(['position' => $index + 1]);
        }

        return response()->json(['status' => true]);
    }

    public function superadminModulusList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $rows = Modulus::query()->orderBy('position')->orderByDesc('id')->get();
        $items = $rows->map(function (Modulus $row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'title' => (string) ($row->title ?? ''),
                'price' => (float) ($row->price ?? 0),
                'price_usd' => (float) ($row->price_usd ?? 0),
                'rating' => (int) ($row->rating ?? 0),
                'no_of_rating' => (int) ($row->no_of_rating ?? 0),
                'no_of_user' => (int) ($row->no_of_user ?? 0),
                'review' => (int) ($row->review ?? 0),
                'type' => (int) ($row->type ?? 0),
                'modulus_type' => (int) ($row->modulus_type ?? 0),
                'config_status' => (int) ($row->config_status ?? 0),
                'position' => (int) ($row->position ?? 0),
                'status' => (int) ($row->status ?? 0),
                'image' => (string) ($row->image ?? ''),
                'image_url' => $this->resolveAddonImagePublicUrl($row->image),
            ];
        })->values();
        return response()->json(['items' => $items]);
    }

    public function superadminModulusStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = new Modulus();
        return $this->saveSuperadminModulus($request, $row);
    }

    public function superadminModulusUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Modulus::query()->find($id);
        if (!$row) {
            return response()->json(['message' => 'Modulus not found.'], 404);
        }
        return $this->saveSuperadminModulus($request, $row);
    }

    public function superadminModulusToggleStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Modulus::query()->find($id);
        if (!$row) {
            return response()->json(['message' => 'Modulus not found.'], 404);
        }
        $row->status = (int) ($row->status ?? 0) === 1 ? 0 : 1;
        $row->save();
        return response()->json(['status' => true, 'value' => (int) $row->status]);
    }

    public function superadminAccessModesShow(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $state = $this->readAccessModeSetting();

        return response()->json([
            'status' => true,
            'source' => 'database',
            'items' => $state['items'],
            'viewer_ip' => $request->ip(),
            'viewerIp' => $request->ip(),
            'updated_at' => $state['updated_at'],
        ]);
    }

    public function superadminAccessModesSave(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $payload = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.mode' => ['nullable', 'in:active,deactive,dev,beta'],
            'items.*.devIp' => ['nullable', 'string', 'max:120'],
            'items.*.dev_ip' => ['nullable', 'string', 'max:120'],
        ]);

        $items = [];
        foreach (($payload['items'] ?? []) as $key => $record) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '' || strlen($normalizedKey) > 220) {
                continue;
            }
            $mode = (string) ($record['mode'] ?? 'active');
            if (!in_array($mode, ['active', 'deactive', 'dev', 'beta'], true)) {
                $mode = 'active';
            }
            $devIp = trim((string) ($record['devIp'] ?? $record['dev_ip'] ?? ''));
            $items[$normalizedKey] = [
                'mode' => $mode,
                'devIp' => $devIp,
            ];
        }

        $state = [
            'items' => $items,
            'updated_at' => now()->toIso8601String(),
            'updated_by' => auth()->id(),
        ];

        SuperAdminSetting::setValue(
            self::ACCESS_MODES_SETTING_KEY,
            json_encode($state, JSON_UNESCAPED_SLASHES),
            auth()->id() ?? null
        );

        return response()->json([
            'status' => true,
            'source' => 'database',
            'message' => 'Access modes saved successfully.',
            'items' => $items,
            'viewer_ip' => $request->ip(),
            'viewerIp' => $request->ip(),
            'updated_at' => $state['updated_at'],
        ]);
    }

    public function superadminSettingsShow(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $settings = SuperAdminSetting::query()->pluck('value', 'name');
        return response()->json([
            'items' => $settings,
            'domain_connect_status' => (string) ($settings['domain_connect_status'] ?? '0'),
            'trial_period_days' => $this->superadminTrialPeriodDays($settings),
            'base_color' => (string) ($settings['base_color'] ?? ''),
            'theme_color' => (string) ($settings['theme_color'] ?? ''),
            'panel_logo' => (string) ($settings['panel_logo'] ?? ''),
            'panel_logo_dark' => (string) ($settings['panel_logo_dark'] ?? ''),
            'panel_logo_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_logo'] ?? $settings['panel_logo_dark'] ?? ''),
                'assets/images/setting'
            ),
            'panel_logo_dark_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_logo_dark'] ?? $settings['panel_logo'] ?? ''),
                'assets/images/setting'
            ),
            'panel_logo_light' => (string) ($settings['panel_logo_light'] ?? ''),
            'panel_logo_light_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_logo_light'] ?? $settings['panel_logo'] ?? ''),
                'assets/images/setting'
            ),
            'panel_favicon' => (string) ($settings['panel_favicon'] ?? ''),
            'panel_favicon_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_favicon'] ?? ''),
                'assets/images/setting/favicon'
            ),
        ]);
    }

    public function superadminSettingsSave(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'domain_connect_status' => ['nullable', 'in:0,1'],
            'base_color' => ['nullable', 'string', 'max:20'],
            'theme_color' => ['nullable', 'string', 'max:20'],
            // Accept SVG and common raster formats for logo uploads.
            'panel_logo_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'panel_logo_dark_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'panel_logo_light_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'dark_logo_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'light_logo_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'],
            'panel_favicon_upload' => ['nullable', 'file', 'mimes:ico,jpg,jpeg,png,gif,webp,svg', 'max:5120'],
            'favicon_upload' => ['nullable', 'file', 'mimes:ico,jpg,jpeg,png,gif,webp,svg', 'max:5120'],
            'settings' => ['nullable', 'array'],
        ]);

        $pairs = [];
        if (array_key_exists('domain_connect_status', $payload)) {
            $pairs['domain_connect_status'] = (string) $payload['domain_connect_status'];
        }
        if (array_key_exists('base_color', $payload)) {
            $pairs['base_color'] = (string) ($payload['base_color'] ?? '');
        }
        if (array_key_exists('theme_color', $payload)) {
            $pairs['theme_color'] = (string) ($payload['theme_color'] ?? '');
        }
        $legacyLogoFile = $request->file('panel_logo_upload');
        $darkLogoFile = $request->file('panel_logo_dark_upload') ?: $request->file('dark_logo_upload');
        $lightLogoFile = $request->file('panel_logo_light_upload') ?: $request->file('light_logo_upload');
        $faviconFile = $request->file('panel_favicon_upload') ?: $request->file('favicon_upload');

        if ($darkLogoFile) {
            $storedDark = $this->storeUploadedPublicImage($darkLogoFile, 'assets/images/setting');
            $pairs['panel_logo_dark'] = $storedDark;
            // Keep legacy key in sync for older UI consumers.
            $pairs['panel_logo'] = $storedDark;
        }
        if ($lightLogoFile) {
            $pairs['panel_logo_light'] = $this->storeUploadedPublicImage($lightLogoFile, 'assets/images/setting');
        }
        if ($legacyLogoFile) {
            $storedLegacy = $this->storeUploadedPublicImage($legacyLogoFile, 'assets/images/setting');
            $pairs['panel_logo'] = $storedLegacy;
            if (!isset($pairs['panel_logo_dark'])) {
                $pairs['panel_logo_dark'] = $storedLegacy;
            }
        }
        if ($faviconFile) {
            $pairs['panel_favicon'] = $this->storeUploadedPublicImage($faviconFile, 'assets/images/setting/favicon');
        }
        foreach (($payload['settings'] ?? []) as $key => $value) {
            $name = trim((string) $key);
            if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) continue;
            if ($name === self::TRIAL_PERIOD_DAYS_SETTING_KEY) {
                $pairs[$name] = (string) $this->normalizeTrialPeriodDays($value);
                continue;
            }
            $pairs[$name] = is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value);
        }

        foreach ($pairs as $name => $value) {
            SuperAdminSetting::setValue($name, $value, auth()->id() ?? null);
        }
        $settings = SuperAdminSetting::query()->pluck('value', 'name');
        return response()->json([
            'status' => true,
            'message' => 'Settings saved successfully.',
            'trial_period_days' => $this->superadminTrialPeriodDays($settings),
            'panel_logo' => (string) ($settings['panel_logo'] ?? ''),
            'panel_logo_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_logo'] ?? $settings['panel_logo_dark'] ?? ''),
                'assets/images/setting'
            ),
            'panel_logo_dark' => (string) ($settings['panel_logo_dark'] ?? ''),
            'panel_logo_dark_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_logo_dark'] ?? $settings['panel_logo'] ?? ''),
                'assets/images/setting'
            ),
            'panel_logo_light' => (string) ($settings['panel_logo_light'] ?? ''),
            'panel_logo_light_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_logo_light'] ?? $settings['panel_logo'] ?? ''),
                'assets/images/setting'
            ),
            'panel_favicon' => (string) ($settings['panel_favicon'] ?? ''),
            'panel_favicon_url' => $this->resolveSettingAssetPublicUrl(
                (string) ($settings['panel_favicon'] ?? ''),
                'assets/images/setting/favicon'
            ),
        ]);
    }

    private function superadminTrialPeriodDays($settings = null): int
    {
        if ($settings instanceof \Illuminate\Support\Collection || is_array($settings)) {
            $raw = $settings[self::TRIAL_PERIOD_DAYS_SETTING_KEY] ?? null;
        } else {
            $raw = SuperAdminSetting::getValue(
                self::TRIAL_PERIOD_DAYS_SETTING_KEY,
                self::DEFAULT_TRIAL_PERIOD_DAYS
            );
        }

        return $this->normalizeTrialPeriodDays($raw);
    }

    private function normalizeTrialPeriodDays($value): int
    {
        $days = (int) $value;
        if ($days < 1) {
            return self::DEFAULT_TRIAL_PERIOD_DAYS;
        }

        return min($days, 365);
    }

    public function superadminSettingsCurrencyList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $rows = Currency::query()->orderByDesc('id')->get();
        $hasRateStatus = $this->tableHasColumn('currencies', 'customize_rate_status');
        $items = $rows->map(function (Currency $row) use ($hasRateStatus) {
            return [
                'id' => (int) $row->id,
                'country' => (string) ($row->country ?? ''),
                'code' => (string) ($row->code ?? ''),
                'symbol' => (string) ($row->symbol ?? ''),
                'rate' => (string) ($row->rate ?? ''),
                'customize_rate_status' => $hasRateStatus ? (int) ($row->customize_rate_status ?? 0) : 0,
                'status' => (int) ($row->status ?? 0),
            ];
        })->values();
        return response()->json(['items' => $items]);
    }

    public function superadminSettingsCurrencyStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminCurrency($request, new Currency());
    }

    public function superadminSettingsCurrencyUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Currency::query()->find($id);
        if (!$row) return response()->json(['message' => 'Currency not found.'], 404);
        return $this->saveSuperadminCurrency($request, $row);
    }

    public function superadminSettingsCurrencyToggleStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Currency::query()->find($id);
        if (!$row) return response()->json(['message' => 'Currency not found.'], 404);
        $row->status = (int) ($row->status ?? 0) === 1 ? 0 : 1;
        $row->save();
        return response()->json(['status' => true, 'value' => (int) $row->status]);
    }

    public function superadminSettingsCurrencyToggleRateStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Currency::query()->find($id);
        if (!$row) return response()->json(['message' => 'Currency not found.'], 404);
        if (!$this->tableHasColumn('currencies', 'customize_rate_status')) {
            return response()->json(['message' => 'Customize rate status column not found.'], 422);
        }
        $row->customize_rate_status = (int) ($row->customize_rate_status ?? 0) === 1 ? 0 : 1;
        $row->save();
        return response()->json(['status' => true, 'value' => (int) $row->customize_rate_status]);
    }

    public function superadminSettingsStoreStaticData(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $filePath = public_path('static-data.js');
        $content = is_file($filePath) ? (string) file_get_contents($filePath) : '';
        if (!is_file($filePath)) @file_put_contents($filePath, $content);
        return response()->json(['content' => $content]);
    }

    public function superadminSettingsStoreStaticDataSave(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate(['content' => ['nullable', 'string']]);
        $content = (string) ($payload['content'] ?? '');
        @file_put_contents(public_path('static-data.js'), $content);
        return response()->json(['status' => true, 'message' => 'Store static data saved.', 'content' => $content]);
    }

    public function superadminModulusReorder(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $payload['ordered_ids'])));
        $existing = Modulus::query()->whereIn('id', $ids)->pluck('id')->map(fn($id) => (int) $id)->all();
        if (count($existing) !== count($ids)) {
            return response()->json(['message' => 'Some modulus items were not found for reordering.'], 422);
        }

        foreach ($ids as $index => $id) {
            Modulus::query()->where('id', $id)->update(['position' => $index + 1]);
        }

        return response()->json(['status' => true]);
    }

    public function superadminDomainRequests(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $search = trim((string) $request->query('search', ''));
        $query = Domain::query()
            ->with('store:id,name,url')
            ->whereIn('status', ['Requested', 'Processing', 'Buying Request'])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('url', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->get()->map(function (Domain $row) {
            return [
                'id' => (int) $row->id,
                'uid' => (int) ($row->uid ?? 0),
                'name' => (string) ($row->name ?? ''),
                'store_name' => (string) ($row->store->name ?? ''),
                'store_url' => (string) ($row->store->url ?? ''),
                'email' => (string) ($row->email ?? ''),
                'remark' => (string) ($row->remark ?? ''),
                'status' => (string) ($row->status ?? ''),
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function superadminDomainRequestProcessing(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Domain::query()->find($id);
        if (!$row) return response()->json(['message' => 'Domain request not found.'], 404);
        $row->status = 'Processing';
        $row->save();
        return response()->json(['status' => true, 'message' => 'Domain moved to processing.']);
    }

    public function superadminDomainRequestAccept(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Domain::query()->find($id);
        if (!$row) return response()->json(['message' => 'Domain request not found.'], 404);
        $row->status = 'Active';
        $row->save();
        return response()->json(['status' => true, 'message' => 'Domain accepted successfully.']);
    }

    public function superadminDomainRequestReject(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Domain::query()->find($id);
        if (!$row) return response()->json(['message' => 'Domain request not found.'], 404);
        $row->status = 'Deactive';
        $row->save();
        return response()->json(['status' => true, 'message' => 'Domain rejected successfully.']);
    }

    public function superadminDesigns(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', 'all'));

        $query = Designlist::query()->orderByDesc('id');
        if ($type !== '' && $type !== 'all') {
            $query->where('type', $type);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }
        $rows = $query->paginate(20);

        $items = collect($rows->items())->map(function (Designlist $row, $idx) use ($rows) {
            $categoryIds = array_filter(explode(',', (string) ($row->category ?? '')));
            $categoryNames = [];
            if (!empty($categoryIds)) {
                $categoryNames = BusinessCategory::query()->whereIn('id', $categoryIds)->pluck('name')->values()->all();
            }
            return [
                'sl' => (($rows->currentPage() - 1) * $rows->perPage()) + $idx + 1,
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'image_url' => $this->resolveCatalogAssetPublicUrl($row->image ?? null, 'assets/images/design'),
                'value' => (string) ($row->value ?? ''),
                'type' => (string) ($row->type ?? ''),
                'category_names' => $categoryNames,
                'status' => (string) ($row->status ?? ''),
            ];
        })->values();

        $types = Designlist::query()->select('type')->distinct()->orderBy('type')->pluck('type')->values();
        return response()->json([
            'items' => $items,
            'types' => $types,
            'pagination' => $this->paginationPayload($rows),
        ]);
    }

    public function superadminDesignMeta(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $types = Designlist::query()->select('type')->distinct()->orderBy('type')->pluck('type')->values();
        $categories = BusinessCategory::query()->select('id', 'name', 'parent_id')->orderBy('name')->get();
        return response()->json(['types' => $types, 'categories' => $categories]);
    }

    public function superadminDesignShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Designlist::query()->find($id);
        if (!$row) return response()->json(['message' => 'Design not found.'], 404);
        $aiPreferences = [];
        if (Schema::hasColumn('designlists', 'ai_preferences')) {
            $decoded = json_decode((string) ($row->ai_preferences ?? ''), true);
            $aiPreferences = is_array($decoded) ? $decoded : [];
        }
        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'type' => (string) ($row->type ?? ''),
                'category' => array_values(array_filter(explode(',', (string) ($row->category ?? '')))),
                'value' => (string) ($row->value ?? ''),
                'title' => (string) ($row->title ?? ''),
                'title_color' => (string) ($row->title_color ?? ''),
                'subtitle' => (string) ($row->subtitle ?? ''),
                'subtitle_color' => (string) ($row->subtitle_color ?? ''),
                'button' => (string) ($row->button ?? ''),
                'button_color' => (string) ($row->button_color ?? ''),
                'button_bg_color' => (string) ($row->button_bg_color ?? ''),
                'button1' => (string) ($row->button1 ?? ''),
                'button1_color' => (string) ($row->button1_color ?? ''),
                'button1_bg_color' => (string) ($row->button1_bg_color ?? ''),
                'link' => (string) ($row->link ?? ''),
                'image_description' => (string) ($row->image_description ?? ''),
                'ai_brand_colors' => (string) ($aiPreferences['brand_colors'] ?? ''),
                'ai_style_preset' => (string) ($aiPreferences['style_preset'] ?? ''),
                'ai_tone_preset' => (string) ($aiPreferences['tone_preset'] ?? ''),
                'ai_primary_goal' => (string) ($aiPreferences['primary_goal'] ?? ''),
                'ai_meta_focus' => (string) ($aiPreferences['meta_focus'] ?? ''),
                'ai_brand_keywords' => (string) ($aiPreferences['brand_keywords'] ?? ''),
                'status' => (string) ($row->status ?? 'inactive'),
                'image_url' => $this->resolveCatalogAssetPublicUrl($row->image ?? null, 'assets/images/design'),
                'bg_image_url' => $this->resolveCatalogAssetPublicUrl($row->bg_image ?? null, 'assets/images/design'),
            ],
        ]);
    }

    public function superadminDesignStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminDesign($request);
    }

    public function superadminDesignUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminDesign($request, $id);
    }

    public function superadminDesignToggleStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Designlist::query()->find($id);
        if (!$row) return response()->json(['message' => 'Design not found.'], 404);
        $row->status = $row->status === 'active' ? 'inactive' : 'active';
        $row->save();
        return response()->json(['status' => true, 'value' => $row->status]);
    }

    public function superadminDesignDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Designlist::query()->find($id);
        if (!$row) return response()->json(['message' => 'Design not found.'], 404);
        $row->delete();
        return response()->json(['status' => true]);
    }

    public function superadminDesignBulkAction(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:active,deactive,delete'],
        ]);
        $query = Designlist::query()->whereIn('id', $payload['ids']);
        if ($payload['action'] === 'delete') {
            $query->delete();
        } else {
            $query->update(['status' => $payload['action'] === 'active' ? 'active' : 'inactive']);
        }
        return response()->json(['status' => true]);
    }

    public function superadminTemplates(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $search = trim((string) $request->query('search', ''));
        $query = Template::query()->orderBy('position', 'asc');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }

        $items = $query->get()->map(function (Template $row, $idx) {
            $categoryIds = array_filter(explode(',', (string) ($row->category ?? '')));
            $categoryNames = [];
            if (!empty($categoryIds)) {
                $categoryNames = BusinessCategory::query()->whereIn('id', $categoryIds)->pluck('name')->values()->all();
            }
            return [
                'sl' => $idx + 1,
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'feature_image_url' => $this->resolveCatalogAssetPublicUrl($row->feature_image ?? null, 'assets/images/template'),
                'category_names' => $categoryNames,
                'value' => (string) ($row->value ?? ''),
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
                'position' => (int) ($row->position ?? 0),
                'status' => (string) ($row->status ?? ''),
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function superadminTemplateToggleStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Template::query()->find($id);
        if (!$row) return response()->json(['message' => 'Template not found.'], 404);
        $row->status = $row->status === 'active' ? 'inactive' : 'active';
        $row->save();
        return response()->json(['status' => true, 'value' => $row->status]);
    }

    public function superadminTemplateUpdatePosition(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'position' => ['required', 'integer'],
        ]);
        $row = Template::query()->find($id);
        if (!$row) return response()->json(['message' => 'Template not found.'], 404);
        $row->position = (int) $payload['position'];
        $row->save();
        return response()->json(['status' => true, 'position' => (int) $row->position]);
    }

    public function superadminTemplateMeta(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $categories = BusinessCategory::query()->select('id', 'name', 'parent_id')->orderBy('name')->get();
        $designs = Designlist::query()
            ->where('status', 'active')
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'value', 'type'])
            ->groupBy('type');
        return response()->json(['categories' => $categories, 'designs' => $designs]);
    }

    public function superadminTemplateShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Template::query()->find($id);
        if (!$row) return response()->json(['message' => 'Template not found.'], 404);
        $positions = Temposition::query()->where('template_id', $row->id)->pluck('position', 'name');
        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'liveurl' => (string) ($row->liveurl ?? ''),
                'category' => array_values(array_filter(explode(',', (string) ($row->category ?? '')))),
                'value' => (string) ($row->value ?? ''),
                'short_description' => (string) ($row->short_description ?? ''),
                'feature_image_url' => $this->resolveCatalogAssetPublicUrl($row->feature_image ?? null, 'assets/images/template'),
                'main_image_url' => $this->resolveCatalogAssetPublicUrl($row->main_image ?? null, 'assets/images/template'),
                'status' => (string) ($row->status ?? 'inactive'),
                'position' => (int) ($row->position ?? 0),
                'price' => (string) ($row->price ?? ''),
                'is_premium' => (string) ($row->is_premium ?? 'No'),
                'review' => (string) ($row->review ?? 'Yes'),
                'reviewer' => (string) ($row->reviewer ?? ''),
                'downlad' => (string) ($row->downlad ?? ''),
                'components' => [
                    'header' => $row->header, 'slider' => $row->slider, 'banner' => $row->banner, 'banner_bottom' => $row->banner_bottom,
                    'feature_category' => $row->feature_category, 'product' => $row->product, 'feature_product' => $row->feature_product,
                    'best_sell_product' => $row->best_sell_product, 'new_arrival' => $row->new_arrival, 'testimonial' => $row->testimonial,
                    'youtube' => $row->youtube, 'footer' => $row->footer, 'auth' => $row->auth,
                ],
                'component_positions' => $positions,
            ],
        ]);
    }

    public function superadminTemplateStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminTemplate($request);
    }

    public function superadminTemplateUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminTemplate($request, $id);
    }

    public function superadminTemplateDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Template::query()->find($id);
        if (!$row) return response()->json(['message' => 'Template not found.'], 404);
        $row->delete();
        return response()->json(['status' => true]);
    }

    public function superadminTemplateBulkAction(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:active,deactive,delete'],
        ]);
        $query = Template::query()->whereIn('id', $payload['ids']);
        if ($payload['action'] === 'delete') {
            $query->delete();
        } else {
            $query->update(['status' => $payload['action'] === 'active' ? 'active' : 'inactive']);
        }
        return response()->json(['status' => true]);
    }

    public function superadminIconPackList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $search = trim((string) $request->query('search', ''));
        $rows = Iconpack::query()
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('value', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate(20);

        $items = collect($rows->items())->map(function (Iconpack $row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'value' => (string) ($row->value ?? ''),
                'image_url' => $this->resolveIconImagePublicUrl($row->image ?? null),
            ];
        })->values();

        return response()->json(['items' => $items, 'pagination' => $this->paginationPayload($rows)]);
    }

    public function superadminIconPackShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Iconpack::query()->find($id);
        if (!$row) return response()->json(['message' => 'Icon not found.'], 404);
        return response()->json(['item' => [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ''),
            'value' => (string) ($row->value ?? ''),
            'image_url' => $this->resolveIconImagePublicUrl($row->image ?? null),
        ]]);
    }

    public function superadminIconPackStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminIconPack($request);
    }

    public function superadminIconPackUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminIconPack($request, $id);
    }

    public function superadminIconPackDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Iconpack::query()->find($id);
        if (!$row) return response()->json(['message' => 'Icon not found.'], 404);
        if (!empty($row->image)) {
            $oldPath = public_path('assets/images/icon/' . $row->image);
            if (is_file($oldPath)) @unlink($oldPath);
        }
        $row->delete();
        return response()->json(['status' => true]);
    }

    public function superadminIconPackBulkAction(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:delete'],
        ]);
        $rows = Iconpack::query()->whereIn('id', $payload['ids'])->get();
        foreach ($rows as $row) {
            if (!empty($row->image)) {
                $oldPath = public_path('assets/images/icon/' . $row->image);
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $row->delete();
        }
        return response()->json(['status' => true]);
    }

    public function superadminBusinessCategoryList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        if ($request->boolean('options_only')) {
            $hasPosition = Schema::hasColumn('business_categories', 'position');
            $optsQuery = BusinessCategory::query()
                ->when($hasPosition, fn($q) => $q->orderByRaw('CASE WHEN position = 0 THEN 1 ELSE 0 END')->orderBy('position', 'asc'))
                ->orderBy('name');
            if ($request->boolean('roots_only')) {
                $optsQuery->whereNull('parent_id');
            }
            $opts = $optsQuery->get(['id', 'name']);

            return response()->json([
                'options' => $opts->map(static fn(BusinessCategory $row) => [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                ])->values(),
            ]);
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $hasPosition = Schema::hasColumn('business_categories', 'position');
        $columns = ['id', 'name', 'slug', 'parent_id'];
        if ($hasPosition) {
            $columns[] = 'position';
        }

        $baseQuery = BusinessCategory::query()
            ->when($search !== '', static function ($q) use ($search) {
                $q->where(static function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($hasPosition, static fn($q) => $q->orderByRaw('CASE WHEN position = 0 THEN 1 ELSE 0 END')->orderBy('position', 'asc'))
            ->orderBy('name', 'asc');

        $paginator = $baseQuery->paginate($perPage, $columns, 'page', $page);

        $parentIds = $paginator->getCollection()->pluck('parent_id')->filter()->unique()->values()->all();
        $parentNames = $parentIds !== []
            ? BusinessCategory::query()->whereIn('id', $parentIds)->pluck('name', 'id')
            : collect();

        $items = $paginator->getCollection()->map(static function (BusinessCategory $row) use ($parentNames) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'slug' => (string) ($row->slug ?? ''),
                'parent_id' => $row->parent_id ? (int) $row->parent_id : null,
                'parent_name' => $row->parent_id ? (string) ($parentNames[$row->parent_id] ?? '') : '',
                'position' => (int) ($row->position ?? 0),
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function superadminBusinessCategoryShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        $row = BusinessCategory::query()->find($id);
        if (!$row) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $parentName = '';
        if ($row->parent_id) {
            $parentName = (string) (BusinessCategory::query()->where('id', $row->parent_id)->value('name') ?? '');
        }

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'slug' => (string) ($row->slug ?? ''),
                'parent_id' => $row->parent_id ? (int) $row->parent_id : null,
                'parent_name' => $parentName,
                'position' => (int) ($row->position ?? 0),
            ],
        ]);
    }

    public function superadminBusinessCategoryStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:business_categories,name'],
            'slug' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:business_categories,id'],
        ]);

        $row = new BusinessCategory();
        $row->name = $payload['name'];
        $row->slug = trim((string) ($payload['slug'] ?? '')) !== '' ? $payload['slug'] : Str::slug($payload['name']);
        $row->parent_id = $payload['parent_id'] ?? null;
        if (Schema::hasColumn('business_categories', 'position')) {
            $row->position = ((int) BusinessCategory::query()->max('position')) + 1;
        }
        $row->save();

        return response()->json(['status' => true, 'id' => (int) $row->id], 201);
    }

    public function superadminBusinessCategoryUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = BusinessCategory::query()->find($id);
        if (!$row) return response()->json(['message' => 'Category not found.'], 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:business_categories,name,' . $id],
            'slug' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:business_categories,id'],
        ]);
        $parentId = $payload['parent_id'] ?? null;
        if ($parentId === $row->id) {
            return response()->json(['message' => 'Category cannot be its own parent.'], 422);
        }

        $row->name = $payload['name'];
        $row->slug = $payload['slug'];
        $row->parent_id = $parentId;
        $row->save();
        return response()->json(['status' => true]);
    }

    public function superadminBusinessCategoryDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = BusinessCategory::query()->find($id);
        if (!$row) return response()->json(['message' => 'Category not found.'], 404);
        BusinessCategory::query()->where('parent_id', $row->id)->update(['parent_id' => null]);
        $row->delete();
        return response()->json(['status' => true]);
    }

    public function superadminBusinessCategoryReorder(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        if (!Schema::hasColumn('business_categories', 'position')) {
            return response()->json(['message' => 'Category position column is missing. Please run the latest migrations.'], 422);
        }

        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:business_categories,id'],
        ]);

        foreach (array_values($payload['ids']) as $index => $id) {
            BusinessCategory::query()
                ->where('id', (int) $id)
                ->update(['position' => $index + 1]);
        }

        return response()->json(['status' => true]);
    }

    public function superadminStaffMeta(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $roles = Superrole::query()->orderBy('name')->get(['id', 'name']);
        return response()->json(['roles' => $roles, 'permission_keys' => $this->superadminRolePermissionKeys()]);
    }

    public function superadminStaffList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $search = trim((string) $request->query('search', ''));
        $query = Superstaff::query()->with('role:id,name')->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $rows = $query->get();
        $items = $rows->map(function (Superstaff $row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'username' => (string) ($row->username ?? ''),
                'role_id' => (int) ($row->role_id ?? 0),
                'role_name' => (string) ($row->role->name ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'email' => (string) ($row->email ?? ''),
                'address' => (string) ($row->address ?? ''),
                'new_commission' => (string) ($row->new_commission ?? ''),
                'renew_commission' => (string) ($row->renew_commission ?? ''),
                'setup_commission' => (string) ($row->setup_commission ?? ''),
                'status' => (string) ($row->status ?? 'inactive'),
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s') : '',
            ];
        })->values();
        return response()->json(['items' => $items]);
    }

    public function superadminStaffShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Superstaff::query()->find($id);
        if (!$row) return response()->json(['message' => 'Staff not found.'], 404);
        return response()->json(['item' => [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ''),
            'username' => (string) ($row->username ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'email' => (string) ($row->email ?? ''),
            'address' => (string) ($row->address ?? ''),
            'new_commission' => (string) ($row->new_commission ?? ''),
            'renew_commission' => (string) ($row->renew_commission ?? ''),
            'setup_commission' => (string) ($row->setup_commission ?? ''),
            'role_id' => (int) ($row->role_id ?? 0),
            'status' => (string) ($row->status ?? 'inactive'),
        ]]);
    }

    public function superadminStaffStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminStaff($request);
    }

    public function superadminStaffUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        return $this->saveSuperadminStaff($request, $id);
    }

    public function superadminStaffDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $staff = Superstaff::query()->find($id);
        if (!$staff) return response()->json(['message' => 'Staff not found.'], 404);
        User::query()->where('id', $staff->uid)->delete();
        $staff->delete();
        return response()->json(['status' => true]);
    }

    public function superadminStaffToggleStatus(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $staff = Superstaff::query()->find($id);
        if (!$staff) return response()->json(['message' => 'Staff not found.'], 404);
        $staff->status = $staff->status === 'active' ? 'inactive' : 'active';
        $staff->save();
        return response()->json(['status' => true, 'value' => $staff->status]);
    }

    public function superadminRoleList(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $search = trim((string) $request->query('search', ''));
        $rows = Superrole::query()
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get(['id', 'name', 'permission']);
        $items = $rows->map(function (Superrole $row) {
            $permissions = array_values(array_filter(array_map('trim', explode(',', (string) ($row->permission ?? '')))));
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'permission' => $permissions,
                'permission_count' => count($permissions),
            ];
        })->values();
        return response()->json(['items' => $items, 'permission_keys' => $this->superadminRolePermissionKeys()]);
    }

    public function superadminRoleShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Superrole::query()->find($id);
        if (!$row) return response()->json(['message' => 'Role not found.'], 404);
        return response()->json(['item' => [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ''),
            'permission' => array_values(array_filter(array_map('trim', explode(',', (string) ($row->permission ?? ''))))),
        ]]);
    }

    public function superadminRoleStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row = new Superrole();
        $row->name = $payload['name'];
        $row->permission = '';
        $row->save();
        return response()->json(['status' => true, 'id' => (int) $row->id], 201);
    }

    public function superadminRoleUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $row = Superrole::query()->find($id);
        if (!$row) return response()->json(['message' => 'Role not found.'], 404);
        $row->name = $payload['name'];
        $row->save();
        return response()->json(['status' => true]);
    }

    public function superadminRoleDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $row = Superrole::query()->find($id);
        if (!$row) return response()->json(['message' => 'Role not found.'], 404);
        $row->delete();
        return response()->json(['status' => true]);
    }

    public function superadminRolePermissionUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $payload = $request->validate([
            'permission' => ['nullable', 'array'],
            'permission.*' => ['string'],
        ]);
        $allowed = $this->superadminRolePermissionKeys();
        $selected = array_values(array_intersect($payload['permission'] ?? [], $allowed));
        $row = Superrole::query()->find($id);
        if (!$row) return response()->json(['message' => 'Role not found.'], 404);
        $row->permission = implode(',', $selected);
        $row->save();
        return response()->json(['status' => true]);
    }

    /**
     * Platform-wide metrics for the React superadmin dashboard overview.
     */
    public function superadminOverview(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        $clientTypes = ['admin', 'affiliate', 'dropshipper'];
        $addonCompleteStatuses = ['Complete', 'complete', 'Accepted', 'accepted', 'Success', 'success'];

        $totalStores = (int) Store::query()->count();
        $now = Carbon::now();
        $activeStores = (int) Store::query()
            ->where('store_status', 1)
            ->where(function ($q) use ($now) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $now->toDateString());
            })
            ->count();

        $totalClients = (int) User::query()->whereIn('type', $clientTypes)->count();
        $newClientsToday = (int) User::query()
            ->whereIn('type', $clientTypes)
            ->whereDate('created_at', $now->toDateString())
            ->count();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $monthStart->copy()->subMonth()->endOfMonth();

        $planRevenueThisMonth = $this->superadminOverviewPlanRevenueBetween(
            $monthStart,
            $monthEnd,
            $addonCompleteStatuses,
        );
        $planRevenuePrevMonth = $this->superadminOverviewPlanRevenueBetween(
            $prevMonthStart,
            $prevMonthEnd,
            $addonCompleteStatuses,
        );
        $revenueChangePercent = $this->dashboardPercentChange($planRevenuePrevMonth, $planRevenueThisMonth);

        $openSupportTickets = 0;
        $resolvedToday = 0;
        if (Schema::hasTable('support_queues')) {
            $openSupportTickets = (int) SupportQueue::query()->where('status', 'waiting')->count();
            $resolvedToday = (int) SupportQueue::query()
                ->where('status', 'assigned')
                ->whereDate('assigned_at', $now->toDateString())
                ->count();
        }

        $newStoresThisMonth = (int) Store::query()->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $totalSubscriptions = (int) Store::query()
            ->where('store_status', 1)
            ->whereNotNull('plan_id')
            ->where('plan_id', '!=', '')
            ->where('plan_id', '!=', '0')
            ->where(function ($q) use ($now) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $now->toDateString());
            })
            ->count();
        $expiringSoon = (int) Store::query()
            ->where('store_status', 1)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [
                $now->toDateString(),
                $now->copy()->addDays(7)->toDateString(),
            ])
            ->count();

        $chartFrom = $now->copy()->subMonths(11)->startOfMonth();
        $chartTo = $now->copy()->endOfMonth();
        $addonsForChart = AddonsOrder::query()
            ->whereBetween('created_at', [$chartFrom, $chartTo])
            ->whereIn('status', $addonCompleteStatuses)
            ->get(['created_at', 'total']);
        $modulusForChart = ModulusPayment::query()
            ->whereBetween('created_at', [$chartFrom, $chartTo])
            ->where('status', 1)
            ->get(['created_at', 'price']);
        $storesForChart = Store::query()
            ->whereBetween('created_at', [$chartFrom, $chartTo])
            ->get(['created_at']);

        $revenueByYm = [];
        foreach ($addonsForChart as $row) {
            $key = Carbon::parse($row->created_at)->format('Y-m');
            $revenueByYm[$key] = ($revenueByYm[$key] ?? 0.0) + (float) ($row->total ?? 0);
        }
        foreach ($modulusForChart as $row) {
            $key = Carbon::parse($row->created_at)->format('Y-m');
            $revenueByYm[$key] = ($revenueByYm[$key] ?? 0.0) + (float) ($row->price ?? 0);
        }
        $newStoresByYm = [];
        foreach ($storesForChart as $row) {
            $key = Carbon::parse($row->created_at)->format('Y-m');
            $newStoresByYm[$key] = ($newStoresByYm[$key] ?? 0) + 1;
        }

        $monthlyRevenueChart = [];
        $newStoresChart = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $chartFrom->copy()->addMonths($i);
            $ym = $m->format('Y-m');
            $label = $m->format('M Y');
            $monthlyRevenueChart[] = [
                'month' => $label,
                'amount' => round((float) ($revenueByYm[$ym] ?? 0.0), 2),
            ];
            $newStoresChart[] = [
                'month' => $label,
                'count' => (int) ($newStoresByYm[$ym] ?? 0),
            ];
        }

        $recentStores = Store::query()
            ->with(['getUser:id,name,email'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Store $store) use ($now) {
                $rawExpiry = $store->getRawOriginal('expiry_date');
                $active = (int) $store->store_status === 1
                    && (empty($rawExpiry)
                        || Carbon::parse($rawExpiry)->endOfDay()->greaterThanOrEqualTo($now->copy()->startOfDay()));

                return [
                    'id' => (int) $store->id,
                    'name' => (string) ($store->name ?? ''),
                    'domain' => (string) ($store->url ?? ''),
                    'email' => (string) ($store->getUser?->email ?? ''),
                    'created_at' => $store->created_at
                        ? Carbon::parse($store->created_at)->format('d M, Y h:i A')
                        : '',
                    'status' => $active ? 'Active' : 'Inactive',
                ];
            })
            ->values();

        $recentClients = User::query()
            ->whereIn('type', $clientTypes)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => (int) $user->id,
                    'name' => (string) ($user->name ?? ''),
                    'phone' => (string) ($user->phone ?? ''),
                    'email' => (string) ($user->email ?? ''),
                    'created_at' => $user->created_at
                        ? Carbon::parse($user->created_at)->format('d M, Y h:i A')
                        : '',
                    'status' => (string) ($user->type ?? ''),
                ];
            })
            ->values();

        $recentActivity = collect();

        foreach (
            Store::query()->orderByDesc('id')->limit(6)->get(['id', 'name', 'created_at']) as $s
        ) {
            $ts = $s->created_at ? Carbon::parse($s->created_at)->timestamp : 0;
            $recentActivity->push([
                '_ts' => $ts,
                'type' => 'store',
                'message' => 'New store registered',
                'detail' => (string) ($s->name ?? ''),
                'time' => $s->created_at ? Carbon::parse($s->created_at)->diffForHumans() : '',
            ]);
        }
        foreach (
            User::query()->whereIn('type', $clientTypes)->orderByDesc('id')->limit(6)->get(['id', 'name', 'type', 'created_at']) as $u
        ) {
            $ts = $u->created_at ? Carbon::parse($u->created_at)->timestamp : 0;
            $recentActivity->push([
                '_ts' => $ts,
                'type' => 'client',
                'message' => 'New client registered',
                'detail' => trim((string) ($u->name ?? '') . ' (' . (string) ($u->type ?? '') . ')'),
                'time' => $u->created_at ? Carbon::parse($u->created_at)->diffForHumans() : '',
            ]);
        }
        foreach (
            AddonsOrder::query()
                ->with(['store:id,name'])
                ->whereIn('status', $addonCompleteStatuses)
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'store_id', 'total', 'created_at']) as $o
        ) {
            $storeName = (string) ($o->store?->name ?? '');
            $ts = $o->created_at ? Carbon::parse($o->created_at)->timestamp : 0;
            $recentActivity->push([
                '_ts' => $ts,
                'type' => 'payment',
                'message' => 'Plan / subscription payment',
                'detail' => $storeName !== '' ? "{$storeName} · ৳" . number_format((float) ($o->total ?? 0), 0) : '৳' . number_format((float) ($o->total ?? 0), 0),
                'time' => $o->created_at ? Carbon::parse($o->created_at)->diffForHumans() : '',
            ]);
        }

        $recentActivity = $recentActivity
            ->sortByDesc('_ts')
            ->take(20)
            ->map(function (array $row) {
                unset($row['_ts']);

                return $row;
            })
            ->values()
            ->all();

        return response()->json([
            'summary' => [
                'total_stores' => $totalStores,
                'active_stores' => $activeStores,
                'total_clients' => $totalClients,
                'new_clients_today' => $newClientsToday,
                'monthly_revenue' => round($planRevenueThisMonth, 2),
                'revenue_change_percent' => $revenueChangePercent,
                'open_support_tickets' => $openSupportTickets,
                'resolved_today' => $resolvedToday,
                'new_stores_this_month' => $newStoresThisMonth,
                'total_subscriptions' => $totalSubscriptions,
                'expiring_soon' => $expiringSoon,
            ],
            'charts' => [
                'monthly_revenue' => $monthlyRevenueChart,
                'new_stores' => $newStoresChart,
            ],
            'recent_stores' => $recentStores,
            'recent_clients' => $recentClients,
            'recent_activity' => $recentActivity,
        ]);
    }

    private function superadminOverviewPlanRevenueBetween(
        Carbon $from,
        Carbon $to,
        array $addonCompleteStatuses,
    ): float {
        $addons = (float) AddonsOrder::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', $addonCompleteStatuses)
            ->sum('total');
        $modulus = (float) ModulusPayment::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 1)
            ->sum('price');

        return $addons + $modulus;
    }

    public function superadminClients(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $search = trim((string) $request->query('search', ''));
        $idSearch = filter_var($request->query('id_search', false), FILTER_VALIDATE_BOOLEAN);
        $fromDate = trim((string) $request->query('from_date', $request->query('formdate', '')));
        $toDate = trim((string) $request->query('to_date', $request->query('enddate', '')));
        $category = trim((string) $request->query('category', ''));
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $page = max(1, (int) $request->query('page', 1));

        $query = User::query()
            ->whereIn('type', ['admin', 'affiliate', 'dropshipper'])
            ->with(['getStore:id,user_id,name,url,plan_id,purchase_date,expiry_date,created_at']);

        if ($fromDate !== '' || $toDate !== '') {
            $from = $fromDate !== '' ? Carbon::parse($fromDate)->startOfDay() : null;
            $to = $toDate !== '' ? Carbon::parse($toDate)->endOfDay() : null;
            if ($from && $to) $query->whereBetween('created_at', [$from, $to]);
            elseif ($from) $query->where('created_at', '>=', $from);
            elseif ($to) $query->where('created_at', '<=', $to);
        }

        if ($category !== '' && ctype_digit($category)) {
            $query->whereHas('getStore', function ($storeQuery) use ($category) {
                $storeQuery->where('category_id', (int) $category);
            });
        }

        if ($search !== '') {
            if ($idSearch && ctype_digit($search)) {
                $query->where('id', (int) $search);
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "{$search}%")
                        ->orWhere('phone', 'like', "{$search}%")
                        ->orWhere('name', 'like', "{$search}%")
                        ->orWhere('email', 'like', "{$search}%")
                        ->orWhere('comment', 'like', "{$search}%")
                        ->orWhereHas('getStore', function ($storeQuery) use ($search) {
                            $storeQuery->where('name', 'like', "{$search}%")
                                ->orWhere('url', 'like', "{$search}%");
                        });
                });
            }
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        $storeIds = collect($paginator->items())->pluck('getStore.id')->filter()->values();
        $latestComments = $this->latestCommentsByStore($storeIds);
        $userIds = collect($paginator->items())->pluck('id')->filter()->values();
        $superstaffTable = (new Superstaff())->getTable();
        $sellerAssignments = DB::table('superstaff_sales_commissions as ssc')
            ->leftJoin("{$superstaffTable} as ss", 'ss.id', '=', 'ssc.staff_id')
            ->whereIn('ssc.user_id', $userIds)
            ->select(
                'ssc.id',
                'ssc.user_id',
                'ssc.staff_id',
                'ssc.new_commission',
                'ssc.renew_commission',
                'ssc.setup_commission',
                'ssc.setup_amount',
                'ss.name as staff_name'
            )
            ->orderByDesc('ssc.id')
            ->get()
            ->keyBy('user_id');

        $items = collect($paginator->items())->map(function ($client, $idx) use ($paginator, $latestComments, $sellerAssignments) {
            $store = $client->getStore;
            $comment = $store ? ($latestComments[$store->id] ?? null) : null;
            $assignment = $sellerAssignments->get($client->id);
            return [
                'sl' => (($paginator->currentPage() - 1) * $paginator->perPage()) + $idx + 1,
                'id' => (int) $client->id,
                'name' => (string) ($client->name ?? ''),
                'phone' => (string) ($client->phone ?? ''),
                'email' => (string) ($client->email ?? ''),
                'type' => (string) ($client->type ?? ''),
                'comment' => (string) ($client->comment ?? ''),
                'store_id' => (int) ($store->id ?? 0),
                'store_name' => (string) ($store->name ?? ''),
                'store_url' => (string) ($store->url ?? ''),
                'follow_up_status' => (string) ($comment->short_comment ?? ''),
                'follow_up_date' => $comment?->follow_up_date,
                'follow_up_time' => $comment?->follow_up_time,
                'seller_assigned' => (bool) $assignment,
                'seller_name' => (string) ($assignment->staff_name ?? ''),
                'staff_id' => (int) ($assignment->staff_id ?? 0),
                'sales_id' => (int) ($assignment->id ?? 0),
                'new_commission' => (string) ($assignment->new_commission ?? ''),
                'renew_commission' => (string) ($assignment->renew_commission ?? ''),
                'setup_commission' => (string) ($assignment->setup_commission ?? ''),
                'setup_amount' => (string) ($assignment->setup_amount ?? ''),
                'created_at' => $client->created_at ? Carbon::parse($client->created_at)->format('d M, Y h:i A') : '',
            ];
        })->values();

        return response()->json(['items' => $items, 'pagination' => $this->paginationPayload($paginator)]);
    }

    public function superadminPaidClients(Request $request): JsonResponse
    {
        return $this->superadminStoreClientsList($request, false);
    }

    public function superadminClientShow(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $query = User::query()
            ->whereIn('type', ['admin', 'affiliate', 'dropshipper'])
            ->with(['getStore' => function ($query) {
                $query->select('id', 'user_id', 'name', 'slug', 'url', 'status', 'plan_id', 'purchase_date', 'expiry_date', 'created_at');
            }, 'getStore.getPlan:id,name']);
        $client = $query->find($id);
        if (!$client) {
            $storeUserId = Store::query()->where('id', $id)->value('user_id');
            if ($storeUserId) {
                $client = (clone $query)->find((int) $storeUserId);
            }
        }

        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $customer = Customer::query()->where('uid', $client->id)->first();
        $stores = collect();
        $activeStore = null;
        if ($customer) {
            $stores = Store::query()
                ->with(['branches:id,store_id', 'getPlan:id,name'])
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->get();
            if (!empty($customer->active_store)) {
                $activeStore = $stores->firstWhere('id', (int) $customer->active_store);
            }
        }
        if (!$activeStore) {
            $activeStore = $client->getStore;
        }

        $domainCount = 0;
        $productCount = 0;
        if ($customer) {
            $domainCount = (int) Domain::query()->where('customer_id', $customer->id)->count();
            $productCount = (int) Product::query()->where('customer_id', $customer->id)->count();
        }

        $comments = collect();
        $commentStoreId = (int) ($activeStore->id ?? 0);
        if ($commentStoreId > 0) {
            $comments = DB::table('client_activitie_comments')
                ->where('store_id', $commentStoreId)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return response()->json([
            'client' => [
                'id' => (int) $client->id,
                'name' => (string) ($client->name ?? ''),
                'phone' => (string) ($client->phone ?? ''),
                'email' => (string) ($client->email ?? ''),
                'address' => (string) ($client->address ?? ''),
                'image' => (string) ($client->image ?? ''),
                'type' => (string) ($client->type ?? ''),
                'created_at' => $client->created_at ? Carbon::parse($client->created_at)->format('d M, Y h:i A') : '',
                'raw_created_at' => !empty($client->created_at) ? (string) $client->created_at : '',
                'comment' => (string) ($client->comment ?? ''),
            ],
            'summary' => [
                'total_store' => (int) $stores->count(),
                'total_domain' => $domainCount,
                'total_product' => $productCount,
            ],
            'store' => $activeStore ? [
                'id' => (int) $activeStore->id,
                'name' => (string) ($activeStore->name ?? ''),
                'slug' => (string) ($activeStore->slug ?? ''),
                'url' => (string) ($activeStore->url ?? ''),
                'status' => (string) ($activeStore->status ?? ''),
                'store_status' => (int) ($activeStore->store_status ?? 0),
                'plan_id' => (int) ($activeStore->plan_id ?? 0),
                'plan_name' => (string) ($activeStore?->getPlan?->name ?? ''),
                'purchase_date' => $activeStore->purchase_date ? Carbon::parse($activeStore->purchase_date)->format('d M, Y') : '',
                'expiry_date' => $activeStore->expiry_date ? Carbon::parse($activeStore->expiry_date)->format('d M, Y') : '',
                'expiry_date_legacy' => $activeStore->expiry_date ? Carbon::parse($activeStore->expiry_date)->format('d-m-Y') : '',
                'created_at' => $activeStore->created_at ? Carbon::parse($activeStore->created_at)->format('d M, Y h:i A') : '',
            ] : null,
            'stores' => $stores->map(static function ($store) {
                $planTypes = [];
                if (!empty($store->plan_id)) $planTypes[] = 'WEB';
                if (!empty($store->pos_plan_id)) $planTypes[] = 'POS';
                if (!empty($store->digital_plan_id)) $planTypes[] = 'SMM';
                $planLabel = empty($planTypes) ? '' : implode('+', $planTypes);
                return [
                    'id' => (int) ($store->id ?? 0),
                    'name' => (string) ($store->name ?? ''),
                    'url' => (string) ($store->url ?? ''),
                    'purpose' => (string) ($store->purpose ?? ''),
                    'store_status' => (int) ($store->store_status ?? 0),
                    'branch_count' => (int) ($store->branches?->count() ?? 0),
                    'plan_label' => $planLabel,
                ];
            })->values(),
            'comments' => $comments->map(static function ($row) {
                return [
                    'id' => (int) ($row->id ?? 0),
                    'short_comment' => (string) ($row->short_comment ?? ''),
                    'follow_up_date' => (string) ($row->follow_up_date ?? ''),
                    'follow_up_time' => (string) ($row->follow_up_time ?? ''),
                    'comment' => (string) ($row->comment ?? ''),
                    'comment_by' => (string) ($row->comment_by ?? ''),
                    'created_at' => !empty($row->created_at) ? Carbon::parse($row->created_at)->format('d M, Y h:i A') : '',
                ];
            })->values(),
        ]);
    }

    public function superadminClientCreateWebsite(Request $request, int $id)
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $validated = $request->validate([
            'storeName' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer', 'exists:business_categories,id'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'integer', 'exists:currencies,id'],
            'package_type' => ['nullable', 'string', 'max:20'],
            'phone' => AdminContactValidation::phoneRules(false, 30),
        ]);

        $client = User::query()
            ->whereIn('type', ['admin', 'affiliate', 'dropshipper'])
            ->find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $existingStore = Store::query()->where('user_id', $client->id)->exists();
        if ($existingStore) {
            return response()->json(['message' => 'This client already has a website.'], 422);
        }

        if (!Customer::query()->where('uid', $client->id)->exists()) {
            $customer = new Customer();
            $customer->uid = $client->id;
            $customer->active_store = null;
            $customer->plan_status = 'inactive';
            $customer->template_id = 1;
            $customer->save();
        }

        $actor = $request->user();
        Auth::login($client);
        try {
            $merged = array_merge($request->request->all(), [
                'storeName' => $validated['storeName'],
                'type' => (string) $validated['type'],
                'slug' => $validated['slug'],
                'currency' => (string) $validated['currency'],
                'package_type' => $validated['package_type'] ?? 'ecw',
                'phone' => $validated['phone'] ?? '',
            ]);
            $forward = $request->duplicate(null, $merged);
            $forward->headers->set('Accept', 'application/json');
            return $this->createStoreFromReactPayload($forward, $validated);
        } finally {
            if ($actor) {
                Auth::login($actor);
            } else {
                Auth::logout();
            }
        }
    }

    public function superadminClientQuickComment(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $payload = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $client = User::query()->find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $client->comment = trim((string) $payload['comment']);
        $client->comment_date = now();
        $client->save();

        return response()->json([
            'status' => true,
            'message' => 'Comment updated successfully.',
        ]);
    }

    public function superadminClientAssignSeller(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $payload = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:superstaff,id'],
            'sales_id' => ['nullable', 'integer'],
            'new_commission' => ['nullable', 'numeric'],
            'renew_commission' => ['nullable', 'numeric'],
            'setup_commission' => ['nullable', 'numeric'],
            'setup_amount' => ['nullable', 'numeric'],
        ]);

        $client = User::query()->find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found.'], 404);
        }

        $staff = Superstaff::query()->where('id', (int) $payload['staff_id'])->where('status', 'active')->first();
        if (!$staff) {
            return response()->json(['message' => 'Active seller not found.'], 422);
        }

        $salesId = (int) ($payload['sales_id'] ?? 0);
        $assignClient = null;
        if ($salesId > 0) {
            $assignClient = DB::table('superstaff_sales_commissions')->where('id', $salesId)->first();
        }
        if (!$assignClient) {
            $assignClient = DB::table('superstaff_sales_commissions')->where('user_id', $client->id)->first();
        }

        $savePayload = [
            'user_id' => $client->id,
            'staff_id' => (int) $payload['staff_id'],
            'new_commission' => $payload['new_commission'] ?? $staff->new_commission ?? 0.00,
            'renew_commission' => $payload['renew_commission'] ?? $staff->renew_commission ?? 0.00,
            'setup_commission' => $payload['setup_commission'] ?? $staff->setup_commission ?? 0.00,
            'setup_amount' => $payload['setup_amount'] ?? null,
            'updated_at' => now(),
        ];

        if ($assignClient) {
            DB::table('superstaff_sales_commissions')
                ->where('id', $assignClient->id)
                ->update($savePayload);
            $salesId = (int) $assignClient->id;
        } else {
            $savePayload['created_at'] = now();
            $salesId = (int) DB::table('superstaff_sales_commissions')->insertGetId($savePayload);
        }

        return response()->json([
            'status' => true,
            'message' => 'Seller assigned successfully.',
            'sales_id' => $salesId,
        ]);
    }

    public function superadminRegisterClients(Request $request): JsonResponse
    {
        return $this->superadminStoreClientsList($request, true);
    }

    public function superadminLandingPageClients(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $website = trim((string) $request->query('website', ''));
        $type = trim((string) $request->query('type', ''));
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));

        $query = User::query()
            ->whereIn('type', ['admin', 'affiliate', 'dropshipper'])
            ->with(['getStore' => function ($q) {
                $q->where('status', 'active')->select('id', 'user_id', 'name', 'url', 'plan_id', 'purchase_date', 'expiry_date', 'created_at');
            }])->withCount('getStore as total_store_count');

        if ($fromDate !== '' || $toDate !== '') {
            $from = $fromDate !== '' ? Carbon::parse($fromDate)->startOfDay() : null;
            $to = $toDate !== '' ? Carbon::parse($toDate)->endOfDay() : null;
            if ($from && $to) $query->whereBetween('created_at', [$from, $to]);
            elseif ($from) $query->where('created_at', '>=', $from);
            elseif ($to) $query->where('created_at', '<=', $to);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->where('id', (int) $search);
                } else {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('store', function ($subQuery) use ($search) {
                            $subQuery->where('name', 'like', "%{$search}%");
                        });
                }
            });
        }
        if ($website !== '') $query->where('register_from', $website);
        if ($type !== '') $query->where('paid_registration', $type);

        $allMatching = (clone $query)->get();
        $paginator = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function ($client, $idx) use ($paginator) {
            $store = $client->getStore;
            return [
                'sl' => (($paginator->currentPage() - 1) * $paginator->perPage()) + $idx + 1,
                'id' => (int) $client->id,
                'name' => (string) ($client->name ?? ''),
                'phone' => (string) ($client->phone ?? ''),
                'email' => (string) ($client->email ?? ''),
                'type' => (string) ($client->type ?? ''),
                'register_from' => (string) ($client->register_from ?? ''),
                'paid_registration' => (int) ($client->paid_registration ?? 0),
                'store_count' => (int) ($client->total_store_count ?? 0),
                'store_name' => (string) ($store->name ?? ''),
                'store_url' => (string) ($store->url ?? ''),
                'plan_name' => (string) ($store?->getPlan?->name ?? ''),
                'active_date' => $store?->purchase_date ? Carbon::parse($store->purchase_date)->format('j M, Y') : '',
                'expire_date' => $store?->expiry_date ? Carbon::parse($store->expiry_date)->format('j M, Y') : '',
                'store_created_at' => $store?->created_at ? Carbon::parse($store->created_at)->format('j M, Y h:i:s A') : '',
                'user_created_at' => $client->created_at ? Carbon::parse($client->created_at)->format('j M, Y h:i:s A') : '',
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'pagination' => $this->paginationPayload($paginator),
            'summary' => [
                'total_clients' => $allMatching->count(),
                'active_count' => $allMatching->where('total_store_count', '>', 0)->count(),
                'inactive_count' => $allMatching->where('total_store_count', '<=', 0)->count(),
                'websites' => User::query()->whereNotNull('register_from')->distinct()->pluck('register_from')->values(),
            ],
        ]);
    }

    public function superadminClientActivities(Request $request): JsonResponse
    {
        return $this->superadminActivityList($request, false);
    }

    public function superadminClientFollowUps(Request $request): JsonResponse
    {
        return $this->superadminActivityList($request, true);
    }

    public function superadminClientCommentStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;

        $payload = $request->validate([
            'user_id' => ['required', 'integer'],
            'store_id' => ['nullable', 'integer'],
            'client_status' => ['required', 'string', 'max:100'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_time' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string'],
        ]);

        $id = DB::table('client_activitie_comments')->insertGetId([
            'user_id' => $payload['user_id'],
            'store_id' => $payload['store_id'] ?? null,
            'short_comment' => $payload['client_status'],
            'follow_up_date' => $payload['follow_up_date'] ?? null,
            'follow_up_time' => $payload['follow_up_time'] ?? null,
            'comment' => $payload['comment'],
            'comment_by' => (string) ($request->user()->name ?? 'System'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Comment saved.',
            'id' => (int) $id,
        ]);
    }

    private function superadminStoreClientsList(Request $request, bool $onlyRegisterClients): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $page = max(1, (int) $request->query('page', 1));
        $status = trim((string) $request->query('status', ''));
        $fromDate = trim((string) $request->query('from_date', $request->query('formdate', '')));
        $toDate = trim((string) $request->query('to_date', $request->query('enddate', '')));
        $search = trim((string) $request->query('search', ''));

        $query = Store::query()->with(['getUser:id,name,phone,email', 'getPlan:id,name'])->groupBy('user_id');
        if ($onlyRegisterClients) $query->where('plan_id', 6);
        else $query->whereNotIn('plan_id', [6, 9]);

        if ($status !== '' && $status !== 'select') $query->where('status', $status);

        if ($fromDate !== '' || $toDate !== '') {
            $from = $fromDate !== '' ? Carbon::parse($fromDate)->startOfDay() : null;
            $to = $toDate !== '' ? Carbon::parse($toDate)->endOfDay() : null;
            $column = $onlyRegisterClients ? 'created_at' : 'expiry_date';
            if ($from && $to) $query->whereBetween($column, [$from, $to]);
            elseif ($from) $query->where($column, '>=', $from);
            elseif ($to) $query->where($column, '<=', $to);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('user_id', $search)
                    ->orWhereHas('getUser', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        $storeIds = collect($paginator->items())->pluck('id')->filter()->values();
        $latestComments = $this->latestCommentsByStore($storeIds);

        $items = collect($paginator->items())->map(function ($store, $idx) use ($paginator, $latestComments) {
            $comment = $latestComments[$store->id] ?? null;
            return [
                'sl' => (($paginator->currentPage() - 1) * $paginator->perPage()) + $idx + 1,
                'store_id' => (int) $store->id,
                'store_name' => (string) ($store->name ?? ''),
                'store_url' => (string) ($store->url ?? ''),
                'user_id' => (int) ($store->getUser->id ?? 0),
                'name' => (string) ($store->getUser->name ?? ''),
                'phone' => (string) ($store->getUser->phone ?? ''),
                'email' => (string) ($store->getUser->email ?? ''),
                'plan_name' => (string) ($store->getPlan->name ?? ''),
                'active_date' => $store->purchase_date ? Carbon::parse($store->purchase_date)->format('j M, Y') : '',
                'created_at' => $store->created_at ? Carbon::parse($store->created_at)->format('j M, Y h:i:s A') : '',
                'setup_status' => (int) ($store->setup_status ?? 0),
                'follow_up_status' => (string) ($comment->short_comment ?? ''),
                'follow_up_date' => $comment?->follow_up_date,
                'follow_up_time' => $comment?->follow_up_time,
                'comment' => (string) ($store->getUser->comment ?? ''),
            ];
        })->values();

        return response()->json(['items' => $items, 'pagination' => $this->paginationPayload($paginator)]);
    }

    private function superadminActivityList(Request $request, bool $followUpMode): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) return $error;
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('search', ''));
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $followUpDate = trim((string) $request->query('follow_up_date', ''));

        $query = DB::table('admin_user_analytics as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('stores as s', 's.id', '=', 'a.store_id')
            ->selectRaw('MAX(a.id) as id, a.store_id, MAX(a.user_id) as user_id, MAX(a.url) as latest_url, MAX(a.number_of_visits) as number_of_visits, MAX(a.created_at) as created_at')
            ->groupBy('a.store_id');

        if ($fromDate !== '' || $toDate !== '') {
            $from = $fromDate !== '' ? Carbon::parse($fromDate)->startOfDay() : null;
            $to = $toDate !== '' ? Carbon::parse($toDate)->endOfDay() : null;
            if ($from && $to) $query->whereBetween('a.updated_at', [$from, $to]);
            elseif ($from) $query->where('a.updated_at', '>=', $from);
            elseif ($to) $query->where('a.updated_at', '<=', $to);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.id', 'like', "%{$search}%")
                    ->orWhere('u.name', 'like', "%{$search}%")
                    ->orWhere('u.phone', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('s.url', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
        $storeIds = collect($rows->items())->pluck('store_id')->filter()->values();
        $latestComments = $this->latestCommentsByStore($storeIds);

        $items = collect($rows->items())->map(function ($row, $idx) use ($rows, $latestComments, $followUpMode, $followUpDate) {
            $comment = $latestComments[$row->store_id] ?? null;
            if ($followUpMode && $followUpDate !== '' && (string) ($comment->follow_up_date ?? '') !== $followUpDate) {
                return null;
            }
            $user = DB::table('users')->where('id', $row->user_id)->first();
            $store = DB::table('stores')->where('id', $row->store_id)->first();
            return [
                'sl' => (($rows->currentPage() - 1) * $rows->perPage()) + $idx + 1,
                'id' => (int) ($row->id ?? 0),
                'store_id' => (int) ($row->store_id ?? 0),
                'user_id' => (int) ($row->user_id ?? 0),
                'visitor_name' => (string) ($user->name ?? ''),
                'visitor_phone' => (string) ($user->phone ?? ''),
                'store_name' => (string) ($store->name ?? ''),
                'store_url' => (string) ($store->url ?? ''),
                'number_of_visits' => (int) ($row->number_of_visits ?? 0),
                'latest_url' => (string) ($row->latest_url ?? ''),
                'comment' => (string) ($comment->comment ?? ''),
                'follow_up_status' => (string) ($comment->short_comment ?? ''),
                'follow_up_date' => $comment?->follow_up_date,
                'follow_up_time' => $comment?->follow_up_time,
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y, h:i:s A') : '',
            ];
        })->filter()->values();

        return response()->json([
            'items' => $items,
            'pagination' => $this->paginationPayload($rows),
            'last_30_days' => DB::table('admin_user_analytics')
                ->where('updated_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('store_id')
                ->get()
                ->count(),
        ]);
    }

    private function latestCommentsByStore($storeIds)
    {
        if (count($storeIds) === 0) return [];
        $comments = DB::table('client_activitie_comments')
            ->whereIn('store_id', $storeIds)
            ->orderByDesc('updated_at')
            ->get();
        $latest = [];
        foreach ($comments as $comment) {
            if (!isset($latest[$comment->store_id])) $latest[$comment->store_id] = $comment;
        }
        return $latest;
    }

    private function resolvePlanOrderName(AddonsOrder $row): string
    {
        if ($row->plan_id && $row->pos_plan_id && $row->digital_plan_id) return "POS+WEB+SMM";
        if ($row->plan_id && $row->pos_plan_id) return "WEB+POS";
        if ($row->pos_plan_id && $row->digital_plan_id) return "POS+SMM";
        if ($row->plan_id && $row->digital_plan_id) return "WEB+SMM";
        if ($row->plan_id) return "Website";
        if ($row->pos_plan_id) return "POS";
        if ($row->digital_plan_id) return "Digital";
        return "Package";
    }

    private function resolvePlanPackageName(AddonsOrder $row): string
    {
        $package = is_array($row->package) ? $row->package : (json_decode((string) $row->package, true) ?: []);
        if (is_array($package) && isset($package['name'])) {
            return (string) $package['name'];
        }
        return $this->resolvePlanOrderName($row);
    }

    private function resolvePlanOrderMonths(AddonsOrder $row): string
    {
        $months = [];
        if ($row->total_month) $months[] = (string) $row->total_month;
        if ($row->pos_plan_month) $months[] = (string) $row->pos_plan_month;
        if ($row->digital_plan_month) $months[] = (string) $row->digital_plan_month;
        if (!empty($months)) return implode("+", $months);

        $package = is_array($row->package) ? $row->package : (json_decode((string) $row->package, true) ?: []);
        if (is_array($package) && isset($package['month'])) return (string) $package['month'];
        return "-";
    }

    private function saveSuperadminAddon(Request $request, AddonsApi $addon): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:oneTime,monthly,counter'],
            'position' => ['nullable', 'integer'],
            'price' => ['nullable', 'array'],
            'price.*' => ['nullable', 'numeric', 'min:0'],
            'offerprice' => ['nullable', 'array'],
            'offerprice.*' => ['nullable', 'numeric', 'min:0'],
            'monthorvalue' => ['nullable', 'array'],
            'monthorvalue.*' => ['nullable'],
            'name' => ['nullable', 'array'],
            'name.*' => ['nullable', 'string', 'max:255'],
            'usd_price' => ['nullable', 'array'],
            'usd_price.*' => ['nullable', 'numeric', 'min:0'],
            'usd_offer_price' => ['nullable', 'array'],
            'usd_offer_price.*' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            if (!empty($addon->image)) {
                $oldPath = public_path('addons/' . $addon->image);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $addon->image = $this->storeUploadedPublicImage($request->file('image'), 'addons');
        }

        $addon->title = $payload['title'];
        $addon->type = $payload['type'];
        $addon->position = (int) ($payload['position'] ?? 0);
        $addon->status = 1;
        $addon->price = array_values(array_filter($payload['price'] ?? [0], static fn($v) => $v !== null && $v !== ''));
        $addon->offerprice = array_values(array_filter($payload['offerprice'] ?? [0], static fn($v) => $v !== null && $v !== ''));
        $addon->monthorvalue = array_values(array_filter($payload['monthorvalue'] ?? ['null'], static fn($v) => $v !== null && $v !== ''));
        $addon->name = array_values(array_filter($payload['name'] ?? [''], static fn($v) => $v !== null && $v !== ''));
        $addon->usd_price = json_encode(array_values(array_filter($payload['usd_price'] ?? [], static fn($v) => $v !== null && $v !== '')));
        $addon->usd_offer_price = json_encode(array_values(array_filter($payload['usd_offer_price'] ?? [], static fn($v) => $v !== null && $v !== '')));

        if ($addon->type === 'oneTime') {
            $addon->monthorvalue = ['null'];
            $addon->name = ['oneTime'];
        }

        $addon->save();
        return response()->json(['status' => true, 'message' => 'Addon saved successfully.', 'id' => (int) $addon->id]);
    }

    private function saveSuperadminModulus(Request $request, Modulus $row): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_usd' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'integer', 'min:0'],
            'no_of_rating' => ['nullable', 'integer', 'min:0'],
            'no_of_user' => ['nullable', 'integer', 'min:0'],
            'review' => ['nullable', 'integer', 'min:0'],
            'type' => ['nullable', 'integer'],
            'modulus_type' => ['nullable', 'integer'],
            'config_status' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer'],
            'status' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            if (!empty($row->image)) {
                $oldPath = public_path('modulus/' . $row->image);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $row->image = $this->storeUploadedPublicImage($request->file('image'), 'modulus');
        }

        $row->name = $payload['name'];
        $row->title = $payload['title'] ?? null;
        $row->price = (float) ($payload['price'] ?? 0);
        $row->price_usd = (float) ($payload['price_usd'] ?? 0);
        $row->rating = (int) ($payload['rating'] ?? 0);
        $row->no_of_rating = (int) ($payload['no_of_rating'] ?? 0);
        $row->no_of_user = (int) ($payload['no_of_user'] ?? 0);
        $row->review = (int) ($payload['review'] ?? 0);
        $row->type = (int) ($payload['type'] ?? 0);
        $row->modulus_type = (int) ($payload['modulus_type'] ?? 0);
        $row->config_status = !empty($payload['config_status']) ? 1 : 0;
        $row->position = (int) ($payload['position'] ?? 0);
        $row->status = array_key_exists('status', $payload) ? (!empty($payload['status']) ? 1 : 0) : ((int) ($row->status ?? 0));
        $row->save();

        return response()->json(['status' => true, 'message' => 'Modulus saved successfully.', 'id' => (int) $row->id]);
    }

    private function saveSuperadminCurrency(Request $request, Currency $row): JsonResponse
    {
        $payload = $request->validate([
            'country' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10'],
            'symbol' => ['required', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'customize_rate_status' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $row->country = (string) $payload['country'];
        $row->code = strtoupper(trim((string) $payload['code']));
        $row->symbol = (string) $payload['symbol'];
        if ($this->tableHasColumn('currencies', 'rate')) {
            $row->rate = array_key_exists('rate', $payload) ? (float) ($payload['rate'] ?? 0) : (float) ($row->rate ?? 0);
        }
        if ($this->tableHasColumn('currencies', 'customize_rate_status')) {
            $row->customize_rate_status = !empty($payload['customize_rate_status']) ? 1 : 0;
        }
        $row->status = !empty($payload['status']) ? 1 : 0;
        $row->save();

        return response()->json(['status' => true, 'message' => 'Currency saved successfully.', 'id' => (int) $row->id]);
    }

    private function saveSuperadminDesign(Request $request, ?int $id = null): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'category' => ['required', 'array', 'min:1'],
            'category.*' => ['integer'],
            'value' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string'],
            'title_color' => ['nullable', 'string', 'max:50'],
            'subtitle' => ['nullable', 'string'],
            'subtitle_color' => ['nullable', 'string', 'max:50'],
            'button' => ['nullable', 'string'],
            'button_color' => ['nullable', 'string', 'max:50'],
            'button_bg_color' => ['nullable', 'string', 'max:50'],
            'button1' => ['nullable', 'string'],
            'button1_color' => ['nullable', 'string', 'max:50'],
            'button1_bg_color' => ['nullable', 'string', 'max:50'],
            'link' => ['nullable', 'string'],
            'image_description' => ['nullable', 'string'],
            'ai_brand_colors' => ['nullable', 'string', 'max:255'],
            'ai_style_preset' => ['nullable', 'string', 'max:255'],
            'ai_tone_preset' => ['nullable', 'string', 'max:255'],
            'ai_primary_goal' => ['nullable', 'string', 'max:255'],
            'ai_meta_focus' => ['nullable', 'string', 'max:255'],
            'ai_brand_keywords' => ['nullable', 'string'],
            'status' => ['nullable', 'in:on,off,active,inactive'],
            'image' => ['nullable', 'image', 'max:5120'],
            'bg_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $query = Designlist::query()->where('value', $payload['value'])->where('type', $payload['type']);
        if ($id) $query->where('id', '!=', $id);
        if ($query->exists()) return response()->json(['message' => 'The combination of value and type exists.'], 422);

        $row = $id ? Designlist::query()->find($id) : new Designlist();
        if ($id && !$row) return response()->json(['message' => 'Design not found.'], 404);

        $row->name = $payload['name'];
        $row->type = $payload['type'];
        $row->category = implode(',', array_map('strval', $payload['category']));
        $row->value = $payload['value'];
        $row->title = $payload['title'] ?? null;
        $row->title_color = $payload['title_color'] ?? null;
        $row->subtitle = $payload['subtitle'] ?? null;
        $row->subtitle_color = $payload['subtitle_color'] ?? null;
        $row->button = $payload['button'] ?? null;
        $row->button_color = $payload['button_color'] ?? null;
        $row->button_bg_color = $payload['button_bg_color'] ?? null;
        $row->button1 = $payload['button1'] ?? null;
        $row->button1_color = $payload['button1_color'] ?? null;
        $row->button1_bg_color = $payload['button1_bg_color'] ?? null;
        $row->link = $payload['link'] ?? null;
        $row->image_description = $payload['image_description'] ?? null;
        if (Schema::hasColumn('designlists', 'ai_preferences')) {
            $row->ai_preferences = json_encode([
                'brand_colors' => $payload['ai_brand_colors'] ?? '',
                'style_preset' => $payload['ai_style_preset'] ?? '',
                'tone_preset' => $payload['ai_tone_preset'] ?? '',
                'primary_goal' => $payload['ai_primary_goal'] ?? '',
                'meta_focus' => $payload['ai_meta_focus'] ?? '',
                'brand_keywords' => $payload['ai_brand_keywords'] ?? '',
                'section_type' => $payload['type'] ?? '',
            ]);
        }
        $status = $payload['status'] ?? ($row->status ?? 'active');
        $row->status = in_array($status, ['on', 'active'], true) ? 'active' : 'inactive';

        if ($request->hasFile('image')) {
            if (!empty($row->image)) {
                $oldPath = public_path('assets/images/design/' . $row->image);
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $row->image = $this->storeUploadedPublicImage($request->file('image'), 'assets/images/design');
        }
        if ($request->hasFile('bg_image')) {
            if (!empty($row->bg_image)) {
                $oldPath = public_path('assets/images/design/' . $row->bg_image);
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $row->bg_image = $this->storeUploadedPublicImage($request->file('bg_image'), 'assets/images/design');
        }

        $row->save();
        return response()->json(['status' => true, 'id' => (int) $row->id]);
    }

    private function saveSuperadminTemplate(Request $request, ?int $id = null): JsonResponse
    {
        $isCreate = $id === null;
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'array', 'min:1'],
            'category.*' => ['integer'],
            'value' => ['required', 'string', 'max:255'],
            'short_description' => ['required', 'string'],
            'mainposition' => ['required', 'integer'],
            'status' => ['nullable', 'in:on,off,active,inactive'],
            'link' => ['nullable', 'string'],
            'feature_image' => [$isCreate ? 'required' : 'nullable', 'image', 'max:10240'],
            'main_image' => [$isCreate ? 'required' : 'nullable', 'image', 'max:10240'],
            'header' => ['required', 'string'], 'slider' => ['required', 'string'], 'banner' => ['required', 'string'],
            'banner_bottom' => ['nullable', 'string'], 'feature_category' => ['required', 'string'], 'product' => ['required', 'string'],
            'feature_product' => ['required', 'string'], 'best_sell_product' => ['required', 'string'], 'new_arrival' => ['required', 'string'],
            'testimonial' => ['required', 'string'], 'youtube' => ['required', 'string'], 'footer' => ['required', 'string'], 'auth' => ['required', 'string'],
            'header_position' => ['nullable', 'integer'], 'slider_position' => ['nullable', 'integer'], 'banner_position' => ['nullable', 'integer'],
            'banner_bottom_position' => ['nullable', 'integer'], 'feature_category_position' => ['nullable', 'integer'], 'product_position' => ['nullable', 'integer'],
            'feature_product_position' => ['nullable', 'integer'], 'best_sell_product_position' => ['nullable', 'integer'], 'new_arrival_position' => ['nullable', 'integer'],
            'new_arrival_product_position' => ['nullable', 'integer'], 'testimonial_position' => ['nullable', 'integer'], 'youtube_position' => ['nullable', 'integer'],
            'footer_position' => ['nullable', 'integer'],
            'is_premium' => ['nullable', 'string'], 'price' => ['nullable', 'numeric'], 'review' => ['nullable', 'string'],
            'reviewer' => ['nullable', 'string'], 'downlad' => ['nullable', 'string'],
        ]);

        $query = Template::query()->where('value', $payload['value']);
        if ($id) $query->where('id', '!=', $id);
        if ($query->exists()) return response()->json(['message' => 'Template value already exists.'], 422);

        $row = $id ? Template::query()->find($id) : new Template();
        if ($id && !$row) return response()->json(['message' => 'Template not found.'], 404);

        $row->name = $payload['name'];
        $row->liveurl = $payload['link'] ?? null;
        $row->value = $payload['value'];
        $row->category = implode(',', array_map('strval', $payload['category']));
        $row->short_description = $payload['short_description'];
        foreach (['header','slider','banner','banner_bottom','feature_category','product','feature_product','best_sell_product','new_arrival','testimonial','youtube','footer','auth'] as $key) {
            $row->{$key} = $payload[$key] ?? null;
        }
        $row->position = (int) $payload['mainposition'];
        $row->is_premium = $payload['is_premium'] ?? 'No';
        $row->price = (float) ($payload['price'] ?? 0);
        $row->review = $payload['review'] ?? 'Yes';
        $row->reviewer = $payload['reviewer'] ?? null;
        $row->downlad = $payload['downlad'] ?? null;
        $status = $payload['status'] ?? ($row->status ?? 'active');
        $row->status = in_array($status, ['on', 'active'], true) ? 'active' : 'inactive';

        if ($request->hasFile('feature_image')) {
            $row->feature_image = $this->storeUploadedPublicImage($request->file('feature_image'), 'assets/images/template');
        }
        if ($request->hasFile('main_image')) {
            $row->main_image = $this->storeUploadedPublicImage($request->file('main_image'), 'assets/images/template');
        }
        $row->save();

        Temposition::query()->where('template_id', $row->id)->delete();
        $positionMap = [
            'header' => 'header_position', 'hero_slider' => 'slider_position', 'banner' => 'banner_position', 'banner_bottom' => 'banner_bottom_position',
            'feature_category' => 'feature_category_position', 'product' => 'product_position', 'feature_product' => 'feature_product_position',
            'best_sell_product' => 'best_sell_product_position', 'new_arrival' => ($payload['new_arrival_position'] ?? $payload['new_arrival_product_position'] ?? 0),
            'testimonial' => 'testimonial_position', 'youtube' => 'youtube_position', 'footer' => 'footer_position',
        ];
        foreach ($positionMap as $name => $source) {
            $position = is_string($source) ? (int) ($payload[$source] ?? 0) : (int) $source;
            Temposition::query()->create(['template_id' => $row->id, 'name' => $name, 'position' => $position]);
        }

        return response()->json(['status' => true, 'id' => (int) $row->id]);
    }

    private function saveSuperadminIconPack(Request $request, ?int $id = null): JsonResponse
    {
        if ($id !== null) {
            $row = Iconpack::query()->find($id);
            if (!$row) return response()->json(['message' => 'Icon not found.'], 404);
            $payload = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'value' => ['required', 'string', 'max:255'],
                'image' => ['nullable', 'image', 'max:5120'],
            ]);
            $row->name = $payload['name'];
            $row->value = $payload['value'];
            if ($request->hasFile('image')) {
                if (!empty($row->image)) {
                    $oldPath = public_path('assets/images/icon/' . $row->image);
                    if (is_file($oldPath)) @unlink($oldPath);
                }
                $row->image = $this->storeUploadedPublicImage($request->file('image'), 'assets/images/icon');
            }
            $row->save();
            return response()->json(['status' => true, 'id' => (int) $row->id]);
        }

        $request->validate([
            'image' => ['required_without:images', 'nullable', 'image', 'max:5120'],
            'images' => ['required_without:image', 'nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'name' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
        ]);

        $ids = [];
        $files = [];
        if ($request->hasFile('images')) $files = $request->file('images');
        elseif ($request->hasFile('image')) $files = [$request->file('image')];

        foreach ($files as $index => $file) {
            $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
            $autoName = trim((string) $original) !== '' ? trim((string) $original) : ('icon_' . time() . '_' . $index);
            $icon = new Iconpack();
            $icon->name = trim((string) ($request->input('name') ?: $autoName));
            $icon->value = trim((string) ($request->input('value') ?: (Str::slug($autoName, '-') . '_' . rand(100, 999))));
            $icon->image = $this->storeUploadedPublicImage($file, 'assets/images/icon');
            $icon->save();
            $ids[] = (int) $icon->id;
        }

        return response()->json(['status' => true, 'ids' => $ids]);
    }

    private function saveSuperadminStaff(Request $request, ?int $id = null): JsonResponse
    {
        $isCreate = $id === null;
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'phone' => AdminContactValidation::phoneRules(true, 50),
            'email' => AdminContactValidation::emailRules(false, 255),
            'address' => ['nullable', 'string', 'max:500'],
            'new_commission' => ['nullable', 'numeric'],
            'renew_commission' => ['nullable', 'numeric'],
            'setup_commission' => ['nullable', 'numeric'],
            'role' => ['required', 'integer', 'exists:superroles,id'],
            'status' => ['nullable', 'in:on,off,active,inactive'],
        ];
        $rules['password'] = $isCreate ? ['required', 'string', 'min:4'] : ['nullable', 'string', 'min:4'];
        if ($isCreate) {
            $rules['username'][] = 'unique:superstaffs,username';
        } else {
            $rules['username'][] = 'unique:superstaffs,username,' . $id;
        }
        $payload = $request->validate($rules);

        if ($isCreate) {
            $user = new User();
            $user->name = $payload['name'];
            $user->email = (string) (time() . 'gmail.com');
            $user->type = 'superstaff';
            $user->phone = (string) time();
            $user->password = Hash::make($payload['password']);
            $user->save();
            $staff = new Superstaff();
            $staff->uid = $user->id;
        } else {
            $staff = Superstaff::query()->find($id);
            if (!$staff) return response()->json(['message' => 'Staff not found.'], 404);
            $user = User::query()->find($staff->uid);
            if (!$user) return response()->json(['message' => 'Linked user not found.'], 404);
            $user->name = $payload['name'];
            if (!empty($payload['password'])) {
                $user->password = Hash::make($payload['password']);
            }
            $user->save();
        }

        $staff->name = $payload['name'];
        $staff->username = $payload['username'];
        $staff->phone = $payload['phone'];
        $staff->email = $payload['email'] ?? null;
        $staff->address = $payload['address'] ?? null;
        $staff->new_commission = $payload['new_commission'] ?? null;
        $staff->renew_commission = $payload['renew_commission'] ?? null;
        $staff->setup_commission = $payload['setup_commission'] ?? null;
        if (!empty($payload['password'])) {
            $staff->password = Hash::make($payload['password']);
        }
        $staff->role_id = (int) $payload['role'];
        $status = $payload['status'] ?? ($staff->status ?? 'active');
        $staff->status = in_array($status, ['on', 'active'], true) ? 'active' : 'inactive';
        $staff->save();

        return response()->json(['status' => true, 'id' => (int) $staff->id]);
    }

    private function superadminRolePermissionKeys(): array
    {
        $keys = [];

        try {
            $controllerPath = base_path('app/Http/Controllers/SuperAdminController.php');
            if (is_file($controllerPath)) {
                $content = file_get_contents($controllerPath);
                if ($content !== false) {
                    preg_match_all("/canSuperStaffAccess\\('([^']+)'\\)/", $content, $matches);
                    foreach (($matches[1] ?? []) as $rawKey) {
                        $key = trim((string) $rawKey);
                        if ($key !== '') {
                            $keys[] = $key;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep fallback behavior below if runtime file parsing fails.
        }

        try {
            $dbPermissions = Superrole::query()->pluck('permission')->filter()->all();
            foreach ($dbPermissions as $permissionString) {
                foreach (explode(',', (string) $permissionString) as $token) {
                    $token = trim((string) $token);
                    if ($token !== '') {
                        $keys[] = $token;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB parsing failures and continue with collected keys.
        }

        $fallback = [
            'branch_delete_request', 'customer', 'domain', 'domain_request', 'design', 'template', 'affiliate',
            'order', 'staff', 'role_and_permission', 'clients', 'paid_clients', 'clients_Activities',
            'clients_Follow_Up', 'notification', 'message', 'chatbot', 'chat_assign', 'plan_order',
            'plans', 'smm', 'blog', 'webSetup', 'pse', 'landing_page_clients', 'paid_clients_list',
        ];

        $keys = array_values(array_unique(array_merge($fallback, $keys)));
        sort($keys);
        return $keys;
    }

    private function paginationPayload($paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function ensureSuperadminClientAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        if (!in_array((string) $user->type, ['superadmin', 'superstaff'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        return null;
    }

    private function readAccessModeSetting(): array
    {
        $raw = (string) SuperAdminSetting::getValue(self::ACCESS_MODES_SETTING_KEY, '');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $items = [];
        foreach (($decoded['items'] ?? []) as $key => $record) {
            if (!is_array($record)) {
                continue;
            }
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            $mode = (string) ($record['mode'] ?? 'active');
            if (!in_array($mode, ['active', 'deactive', 'dev', 'beta'], true)) {
                $mode = 'active';
            }
            $items[$normalizedKey] = [
                'mode' => $mode,
                'devIp' => trim((string) ($record['devIp'] ?? $record['dev_ip'] ?? '')),
            ];
        }

        return [
            'items' => $items,
            'updated_at' => (string) ($decoded['updated_at'] ?? ''),
        ];
    }

    public function csrfToken(Request $request): JsonResponse
    {
        $request->session()->regenerateToken();

        return response()->json([
            'csrf_token' => $request->session()->token(),
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        return response()->json([
            'user' => $this->formatUser($user),
            'active_store' => $this->activeStoreSummary($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => $this->formatUser($user),
            'active_store' => $this->activeStoreSummary($user),
        ]);
    }

    public function adminStaffDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $staff = Staff::where('uid', $user->id)->first();
        if (!$staff) {
            return response()->json(['message' => 'Staff record not found.'], 404);
        }

        $roleMap = Role::where('store_id', (string) ($staff->store_id ?? ''))
            ->pluck('name', 'id');
        $roleName = (string) ($roleMap[(int) ($staff->role_id ?? 0)] ?? '');

        $store = Store::find($staff->store_id);

        $ordersBase = Order::query()
            ->where('staff_id', $user->id)
            ->where('store_id', (int) ($staff->store_id ?? 0));
        $totalOrders = (clone $ordersBase)->count();
        $pendingOrders = (clone $ordersBase)
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'on hold', 'hold', 'new'])
            ->count();
        $processingOrders = (clone $ordersBase)
            ->whereIn(DB::raw('LOWER(status)'), ['processing', 'shipping', 'confirmed'])
            ->count();

        return response()->json([
            'staff' => [
                'id' => (int) $staff->id,
                'name' => (string) ($staff->name ?? ''),
                'username' => (string) ($staff->username ?? ''),
                'email' => (string) ($staff->email ?? ''),
                'phone' => (string) ($staff->phone ?? ''),
                'status' => strtolower((string) ($staff->status ?? 'inactive')) === 'active' ? 'active' : 'inactive',
                'role_name' => $roleName,
                'joined' => $staff->created_at ? Carbon::parse($staff->created_at)->format('d M Y') : '',
            ],
            'store' => $store ? [
                'id' => (int) $store->id,
                'name' => (string) ($store->name ?? ''),
                'url' => (string) ($store->url ?? ''),
            ] : null,
            'stats' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'processing_orders' => $processingOrders,
            ],
        ]);
    }

    public function superstaffDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $staff = Superstaff::with('role:id,name')->where('uid', $user->id)->first();
        if (!$staff) {
            return response()->json(['message' => 'Superstaff record not found.'], 404);
        }

        $balance = (float) SuperstaffSalesCommissionBalance::getSellerCommissionBalance($staff->id);
        $managedSites = Websitesetup::where('editor', $staff->uid)->count();

        return response()->json([
            'staff' => [
                'id' => (int) $staff->id,
                'name' => (string) ($staff->name ?? ''),
                'username' => (string) ($staff->username ?? ''),
                'email' => (string) ($staff->email ?? ''),
                'phone' => (string) ($staff->phone ?? ''),
                'status' => (string) ($staff->status ?? 'inactive'),
                'role_name' => (string) ($staff->role?->name ?? ''),
                'new_commission' => (string) ($staff->new_commission ?? '0'),
                'renew_commission' => (string) ($staff->renew_commission ?? '0'),
                'setup_commission' => (string) ($staff->setup_commission ?? '0'),
                'joined' => $staff->created_at ? Carbon::parse($staff->created_at)->format('d M Y') : '',
            ],
            'stats' => [
                'managed_sites' => $managedSites,
                'commission_balance' => round($balance, 2),
            ],
        ]);
    }

    public function dashboardOverview(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        /** @var User $user */
        $user = $ctx['user'];
        /** @var Customer $customer */
        $customer = $ctx['customer'];
        /** @var Store $store */
        $store = $ctx['store'];

        $endDate = $request->query('end_date')
            ? Carbon::parse((string) $request->query('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();
        $startDate = $request->query('start_date')
            ? Carbon::parse((string) $request->query('start_date'))->startOfDay()
            : $endDate->copy()->subDays(6)->startOfDay();
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1)->startOfDay();
        $previousEnd = $startDate->copy()->subDay()->endOfDay();

        $headerSetting = Headersetting::query()->where('store_id', $store->id)->first();
        $currencySymbol = $this->dashboardCurrencySymbol($headerSetting);

        $ordersQuery = Order::query()->where('store_id', $store->id);
        $ordersInRange = (clone $ordersQuery)->whereBetween('created_at', [$startDate, $endDate]);
        $ordersPrevious = (clone $ordersQuery)->whereBetween('created_at', [$previousStart, $previousEnd]);

        $orderRows = $ordersInRange->get();
        $previousRevenue = $this->dashboardOrdersRevenue($ordersPrevious->get());
        $revenue = $this->dashboardOrdersRevenue($orderRows);
        $totalOrders = $orderRows->count();
        $averageOrderValue = $totalOrders > 0 ? round($revenue / $totalOrders, 2) : 0.0;
        $pendingOrders = $orderRows->filter(fn($order) => $this->dashboardOrderIsPending($order->status ?? null))->count();
        $processingOrders = $orderRows->filter(fn($order) => $this->dashboardOrderIsProcessing($order->status ?? null))->count();

        $uniqueCustomerBuckets = [];
        $newCustomers = 0;
        $returningCustomers = 0;
        $repeatCustomerIds = [];
        $ltvSum = 0.0;
        $ltvCount = 0;

        foreach ($orderRows as $order) {
            $bucket = $this->dashboardCustomerBucket($order);
            if ($bucket === null) {
                continue;
            }
            if (isset($uniqueCustomerBuckets[$bucket])) {
                continue;
            }

            $uniqueCustomerBuckets[$bucket] = true;
            $historyQuery = Order::query()->where('store_id', $store->id);
            $historyQuery = $this->dashboardApplyCustomerBucket($historyQuery, $order);
            $historyCount = (clone $historyQuery)->count();
            $historyRevenue = $this->dashboardOrdersRevenue((clone $historyQuery)->get());

            if ($historyCount > 1) {
                $returningCustomers++;
                $repeatCustomerIds[] = $bucket;
            } else {
                $newCustomers++;
            }

            if ($historyRevenue > 0) {
                $ltvSum += $historyRevenue;
                $ltvCount++;
            }
        }

        $totalKnownCustomers = max($newCustomers + $returningCustomers, 1);
        $repeatPurchaseRate = round(($returningCustomers / $totalKnownCustomers) * 100, 1);
        $customerLifetimeValue = $ltvCount > 0 ? round($ltvSum / $ltvCount, 2) : 0.0;

        $revenueTrend = $this->dashboardRevenueTrend($store->id, $startDate, $endDate);
        $topProducts = $this->dashboardTopProducts($store->id, $startDate, $endDate, $currencySymbol);
        $trafficSources = $this->dashboardTrafficSources($store->id, $startDate, $endDate);
        $packageInfo = $this->dashboardPackageInfo($store, $currencySymbol);
        $websiteSetup = $this->dashboardWebsiteCompleteness($store, $headerSetting);
        $smartAlerts = $this->dashboardSmartAlerts($store, $headerSetting, $startDate, $endDate, $currencySymbol);

        return response()->json([
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'previous_start_date' => $previousStart->toDateString(),
                'previous_end_date' => $previousEnd->toDateString(),
            ],
            'store' => [
                'id' => (int) $store->id,
                'name' => (string) ($store->name ?? ''),
                'url' => (string) ($store->url ?? ''),
                'user_name' => (string) ($user->name ?? ''),
                'currency_symbol' => $currencySymbol,
            ],
            'overview' => [
                'total_revenue' => round($revenue, 2),
                'previous_revenue' => round($previousRevenue, 2),
                'revenue_change_percent' => $this->dashboardPercentChange($previousRevenue, $revenue),
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'processing_orders' => $processingOrders,
                'average_order_value' => $averageOrderValue,
            ],
            'customers' => [
                'new_customers' => $newCustomers,
                'returning_customers' => $returningCustomers,
                'repeat_purchase_rate' => $repeatPurchaseRate,
                'customer_lifetime_value' => $customerLifetimeValue,
            ],
            'charts' => [
                'daily_revenue' => $revenueTrend,
                'top_products' => $topProducts,
                'traffic_sources' => $trafficSources,
            ],
            'packages' => $packageInfo,
            'website_setup' => $websiteSetup,
            'alerts' => $smartAlerts,
        ]);
    }

    /**
     * Stores owned by the authenticated user (same scope as StoreController::store).
     */
    public function stores(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $customer = Customer::where('uid', $user->id)->first();
        $customerId = $customer->id ?? null;

        $query = Store::query()->where('user_id', $user->id);
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        $rows = $query->with('headerSetting')->orderBy('name')->get();

        $setupLogos = WebsiteSetupDetails::query()
            ->whereIn('store_id', $rows->pluck('id'))
            ->pluck('logo', 'store_id');

        $stores = $rows->map(function (Store $store) use ($setupLogos) {
            $expiresAt = $store->expiry_date
                ? Carbon::parse($store->expiry_date)
                : null;
            $isActive = $expiresAt && $expiresAt->greaterThan(Carbon::now());

            $logoFile = optional($store->headerSetting)->logo;
            if ($logoFile === null || $logoFile === '' || strcasecmp((string) $logoFile, 'null') === 0) {
                $logoFile = $setupLogos[$store->id] ?? null;
            }

            return [
                'id' => $store->id,
                'name' => $store->name,
                'url' => $store->url,
                'slug' => $store->slug,
                'is_active' => $isActive,
                'status_label' => $isActive ? 'Active' : 'Deactive',
                'logo_url' => $this->resolveStoreLogoPublicUrl($logoFile),
            ];
        })->values();

        return response()->json([
            'stores' => $stores,
        ]);
    }

    public function entitlements(Request $request, EntitlementService $entitlementService): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];

        if (!$entitlementService->isEngineEnabled()) {
            return response()->json([
                'enabled' => false,
                'items' => [],
            ]);
        }

        $resolved = $entitlementService->resolveForStore($store);
        return response()->json([
            'enabled' => true,
            'items' => $entitlementService->toFrontendPayload($resolved),
            'plan_id' => (int) ($store->plan_id ?? 0),
            'store_id' => (int) $store->id,
        ]);
    }

    /**
     * Store-wise products for the React admin products page.
     */
    public function products(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $customer = Customer::where('uid', $user->id)->first();
        if (!$customer) {
            return response()->json([
                'message' => 'Customer record not found.',
            ], 422);
        }

        $requestedStore = trim((string) $request->query('store_id', ''));
        $activeStore = trim((string) ($customer->active_store ?? ''));
        $storeId = $requestedStore !== '' ? $requestedStore : $activeStore;

        if ($storeId === '' || $storeId === '0') {
            return response()->json([
                'products' => [],
                'store_id' => null,
            ]);
        }

        $store = Store::query()
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$store) {
            return response()->json([
                'message' => 'Store not found or access denied.',
            ], 404);
        }

        $perPage = (int) $request->query('per_page', 500);
        $perPage = max(1, min($perPage, 2000));

        $rows = Product::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('status', '!=', 'RecycleBin')
            ->latest('id')
            ->limit($perPage)
            ->get([
                'id',
                'name',
                'status',
                'SKU',
                'regular_price',
                'quantity',
                'images',
                'created_at',
            ]);

        $products = $rows->map(function (Product $product) {
            $imageUrl = $this->resolveProductImagePublicUrl($product->images);
            $createdAt = $product->created_at ? Carbon::parse($product->created_at) : null;

            return [
                'id' => $product->id,
                'productInfo' => [
                    'name' => (string) ($product->name ?? ''),
                    'status' => strtolower((string) $product->status) === 'active' ? 'Enable' : 'Disable',
                    'sku' => (string) ($product->SKU ?? ''),
                    'image' => $imageUrl,
                ],
                'date' => $createdAt ? $createdAt->format('d-m-Y, g:i A') : '',
                'quantity' => (string) ((int) ($product->quantity ?? 0)),
                'price' => 'BDT ' . number_format((float) ($product->regular_price ?? 0), 0),
                // The legacy products table has no explicit storefront display flag.
                'displayedOnStorefront' => strtolower((string) $product->status) === 'active',
            ];
        })->values();

        return response()->json([
            'store_id' => (int) $store->id,
            'count' => $products->count(),
            'products' => $products,
        ]);
    }

    public function productStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:fixed,percentage,no_discount'],
            'promotional_price' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'string', 'max:120'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'video_link' => ['nullable', 'string', 'max:1000'],
            'product_link' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'string', 'max:100'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'max:100'],
            'subcategory_id' => ['nullable', 'string', 'max:100'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['string', 'max:100'],
            'brand_id' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'is_storefront' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:10240'],
            'media_paths' => ['nullable', 'array'],
            'media_paths.*' => ['string', 'max:500'],
            'variant_payload' => ['nullable'],
        ]);

        $row = new Product();
        $row->name = $payload['name'];
        if ($this->tableHasColumn('products', 'description')) {
            $row->description = $payload['description'] ?? null;
        }
        if ($this->tableHasColumn('products', 'short_description')) {
            $row->short_description = $payload['short_description'] ?? null;
        }
        if ($this->tableHasColumn('products', 'regular_price')) {
            $row->regular_price = (string) ((float) ($payload['regular_price'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'cost')) {
            $row->cost = (string) ((float) ($payload['cost'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'discount_type')) {
            $row->discount_type = $payload['discount_type'] ?? 'no_discount';
        }
        if ($this->tableHasColumn('products', 'promotional_price')) {
            $row->promotional_price = (string) ((float) ($payload['promotional_price'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'quantity')) {
            $row->quantity = (string) ((int) ($payload['quantity'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'weight')) {
            $row->weight = $payload['weight'] ?? null;
        }
        if ($this->tableHasColumn('products', 'shipping_fee')) {
            $row->shipping_fee = (string) ((float) ($payload['shipping_fee'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'video_link')) {
            $row->video_link = $payload['video_link'] ?? null;
        }
        if ($this->tableHasColumn('products', 'product_link')) {
            $row->product_link = $payload['product_link'] ?? null;
        }
        if ($this->tableHasColumn('products', 'category')) {
            $categoryIds = array_values(array_filter(array_map('trim', (array) ($payload['category_ids'] ?? []))));
            $row->category = !empty($categoryIds) ? implode(',', $categoryIds) : (string) ($payload['category_id'] ?? '');
        }
        if ($this->tableHasColumn('products', 'subcategory')) {
            $subCategoryIds = array_values(array_filter(array_map('trim', (array) ($payload['subcategory_ids'] ?? []))));
            $row->subcategory = !empty($subCategoryIds) ? implode(',', $subCategoryIds) : (string) ($payload['subcategory_id'] ?? '');
        }
        if ($this->tableHasColumn('products', 'brand')) {
            $row->brand = (string) ($payload['brand_id'] ?? '');
        }
        if ($this->tableHasColumn('products', 'tags')) {
            $row->tags = $payload['tags'] ?? null;
        }
        if ($this->tableHasColumn('products', 'seo_keywords')) {
            $row->seo_keywords = $payload['seo_title'] ?? null;
        }
        if ($this->tableHasColumn('products', 'seo_description')) {
            $row->seo_description = $payload['seo_description'] ?? null;
        }
        if ($this->tableHasColumn('products', 'feature')) {
            $row->feature = !empty($payload['is_featured']) ? 1 : 0;
        }
        if ($this->tableHasColumn('products', 'storefront')) {
            $row->storefront = array_key_exists('is_storefront', $payload) ? (!empty($payload['is_storefront']) ? 1 : 0) : 1;
        }
        if ($this->tableHasColumn('products', 'status')) {
            $row->status = $payload['status'] ?? 'active';
        }
        if ($this->tableHasColumn('products', 'SKU')) {
            $row->SKU = trim((string) ($payload['sku'] ?? '')) !== ''
                ? trim((string) $payload['sku'])
                : 'SKU-' . strtoupper(Str::random(8));
        }
        if ($this->tableHasColumn('products', 'barcode')) {
            $barcode = trim((string) ($payload['barcode'] ?? ''));
            $row->barcode = $barcode !== '' ? $barcode : date('ym') . rand(1000, 99999);
        }
        if ($this->tableHasColumn('products', 'currency_id')) {
            $row->currency_id = (int) ($store->currency ?? 1);
        }

        $imagePaths = [];
        foreach (($payload['media_paths'] ?? []) as $mediaPath) {
            $copied = $this->copyMediaLibraryAssetToProductDirectory((string) $mediaPath, (string) $customer->id, (string) $store->id);
            if ($copied !== null) {
                $imagePaths[] = $copied;
            }
        }
        if ($request->hasFile('images')) {
            foreach ((array) $request->file('images') as $file) {
                if (!$file) continue;
                $imagePaths[] = $this->storeUploadedPublicImage($file, 'assets/images/product');
            }
        }
        if ($this->tableHasColumn('products', 'images')) {
            $row->images = implode(',', array_values(array_unique(array_filter($imagePaths))));
        }
        if ($this->tableHasColumn('products', 'variant_payload')) {
            $variantPayloadRaw = $request->input('variant_payload');
            $row->variant_payload = is_string($variantPayloadRaw) ? $variantPayloadRaw : json_encode($variantPayloadRaw ?: []);
            $this->syncProductQuantityFromVariantSources($row);
        }

        $row->uid = $user->id;
        $row->customer_id = $customer->id;
        $row->store_id = $store->id;
        if ($this->tableHasColumn('products', 'creator')) {
            $row->creator = $user->id;
        }
        if ($this->tableHasColumn('products', 'editor')) {
            $row->editor = $user->id;
        }
        $row->save();

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
            ],
        ], 201);
    }

    public function productShow(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Product::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $variantPayload = [
            'mode' => 'color_size',
            'colorSizeGroups' => [],
            'colorOnlyRows' => [],
            'sizeOnlyRows' => [],
            'unitRows' => [],
        ];
        if ($this->tableHasColumn('products', 'variant_payload')) {
            $decoded = json_decode((string) ($row->variant_payload ?? ''), true);
            if (is_array($decoded)) {
                $variantPayload = array_merge($variantPayload, $decoded);
            }
        }

        $imageTokens = collect(explode(',', (string) ($row->images ?? '')))
            ->map(fn($x) => trim((string) $x))
            ->filter(fn($x) => $x !== '' && !str_contains($x, '..'))
            ->values();

        $images = $imageTokens->map(function ($token, $idx) {
            return [
                'id' => 'existing-' . $idx,
                'file' => basename((string) $token),
                'name' => basename((string) $token),
                'url' => $this->resolveProductImageTokenPublicUrl((string) $token),
            ];
        })->filter(fn($img) => !empty($img['url']))->values();

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'title' => (string) ($row->name ?? ''),
                'shortDescription' => (string) ($row->short_description ?? ''),
                'description' => (string) ($row->description ?? ''),
                'sku' => (string) ($row->SKU ?? ''),
                'quantity' => (string) ($row->quantity ?? '0'),
                'regularPrice' => (string) ($row->regular_price ?? '0'),
                'productCost' => (string) ($row->cost ?? '0'),
                'discountType' => (string) ($row->discount_type ?? 'fixed'),
                'discountPrice' => (string) ($row->promotional_price ?? '0'),
                'weight' => (string) ($row->weight ?? ''),
                'shippingFees' => (string) ($row->shipping_fee ?? '0'),
                'barCode' => (string) ($row->barcode ?? ''),
                'youtubeLink' => (string) ($row->video_link ?? ''),
                'digitalProductLink' => (string) ($row->product_link ?? ''),
                'categoryIds' => collect(explode(',', (string) ($row->category ?? '')))->map(fn($x) => trim((string) $x))->filter()->values()->all(),
                'subCategoryIds' => collect(explode(',', (string) ($row->subcategory ?? '')))->map(fn($x) => trim((string) $x))->filter()->values()->all(),
                'brandId' => (string) ($row->brand ?? ''),
                'tags' => (string) ($row->tags ?? ''),
                'seoTitle' => (string) ($row->seo_keywords ?? ''),
                'seoDescription' => (string) ($row->seo_description ?? ''),
                'storefront' => (int) ($row->storefront ?? 1) === 1,
                'featured' => (int) ($row->feature ?? 0) === 1,
                'publish' => strtolower((string) ($row->status ?? 'active')) === 'active',
                'variant_payload' => $variantPayload,
                'images' => $images,
            ],
        ]);
    }

    public function productUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $row = Product::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'regular_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:fixed,percentage,no_discount'],
            'promotional_price' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'string', 'max:120'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'video_link' => ['nullable', 'string', 'max:1000'],
            'product_link' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'string', 'max:100'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'max:100'],
            'subcategory_id' => ['nullable', 'string', 'max:100'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['string', 'max:100'],
            'brand_id' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'is_storefront' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'max:10240'],
            'media_paths' => ['nullable', 'array'],
            'media_paths.*' => ['string', 'max:500'],
            'retain_images' => ['nullable', 'array'],
            'retain_images.*' => ['string', 'max:500'],
        ]);

        $row->name = $payload['name'];
        if ($this->tableHasColumn('products', 'description')) {
            $row->description = $payload['description'] ?? null;
        }
        if ($this->tableHasColumn('products', 'short_description')) {
            $row->short_description = $payload['short_description'] ?? null;
        }
        if ($this->tableHasColumn('products', 'regular_price')) {
            $row->regular_price = (string) ((float) ($payload['regular_price'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'cost')) {
            $row->cost = (string) ((float) ($payload['cost'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'discount_type')) {
            $row->discount_type = $payload['discount_type'] ?? 'no_discount';
        }
        if ($this->tableHasColumn('products', 'promotional_price')) {
            $row->promotional_price = (string) ((float) ($payload['promotional_price'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'quantity')) {
            $row->quantity = (string) ((int) ($payload['quantity'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'weight')) {
            $row->weight = $payload['weight'] ?? null;
        }
        if ($this->tableHasColumn('products', 'shipping_fee')) {
            $row->shipping_fee = (string) ((float) ($payload['shipping_fee'] ?? 0));
        }
        if ($this->tableHasColumn('products', 'video_link')) {
            $row->video_link = $payload['video_link'] ?? null;
        }
        if ($this->tableHasColumn('products', 'product_link')) {
            $row->product_link = $payload['product_link'] ?? null;
        }
        if ($this->tableHasColumn('products', 'category')) {
            $categoryIds = array_values(array_filter(array_map('trim', (array) ($payload['category_ids'] ?? []))));
            $row->category = !empty($categoryIds) ? implode(',', $categoryIds) : (string) ($payload['category_id'] ?? '');
        }
        if ($this->tableHasColumn('products', 'subcategory')) {
            $subCategoryIds = array_values(array_filter(array_map('trim', (array) ($payload['subcategory_ids'] ?? []))));
            $row->subcategory = !empty($subCategoryIds) ? implode(',', $subCategoryIds) : (string) ($payload['subcategory_id'] ?? '');
        }
        if ($this->tableHasColumn('products', 'brand')) {
            $row->brand = (string) ($payload['brand_id'] ?? '');
        }
        if ($this->tableHasColumn('products', 'tags')) {
            $row->tags = $payload['tags'] ?? null;
        }
        if ($this->tableHasColumn('products', 'seo_keywords')) {
            $row->seo_keywords = $payload['seo_title'] ?? null;
        }
        if ($this->tableHasColumn('products', 'seo_description')) {
            $row->seo_description = $payload['seo_description'] ?? null;
        }
        if ($this->tableHasColumn('products', 'feature')) {
            $row->feature = !empty($payload['is_featured']) ? 1 : 0;
        }
        if ($this->tableHasColumn('products', 'storefront')) {
            $row->storefront = array_key_exists('is_storefront', $payload) ? (!empty($payload['is_storefront']) ? 1 : 0) : 1;
        }
        if ($this->tableHasColumn('products', 'status')) {
            $row->status = $payload['status'] ?? 'active';
        }
        if ($this->tableHasColumn('products', 'SKU')) {
            $row->SKU = trim((string) ($payload['sku'] ?? '')) !== ''
                ? trim((string) $payload['sku'])
                : 'SKU-' . strtoupper(Str::random(8));
        }
        if ($this->tableHasColumn('products', 'barcode')) {
            $barcode = trim((string) ($payload['barcode'] ?? ''));
            $row->barcode = $barcode !== '' ? $barcode : date('ym') . rand(1000, 99999);
        }

        $imagePaths = collect((array) ($payload['retain_images'] ?? []))
            ->map(fn($x) => $this->normalizeStoredImageToken((string) $x))
            ->filter(fn($x) => $x !== '' && !str_contains($x, '..'))
            ->values()
            ->all();
        foreach (($payload['media_paths'] ?? []) as $mediaPath) {
            $copied = $this->copyMediaLibraryAssetToProductDirectory((string) $mediaPath, (string) $customer->id, (string) $store->id);
            if ($copied !== null) {
                $imagePaths[] = $copied;
            }
        }
        if ($request->hasFile('images')) {
            foreach ((array) $request->file('images') as $file) {
                if (!$file) continue;
                $imagePaths[] = $this->storeUploadedPublicImage($file, 'assets/images/product');
            }
        }
        if ($this->tableHasColumn('products', 'images')) {
            $row->images = implode(',', array_values(array_unique(array_filter($imagePaths))));
        }
        if ($this->tableHasColumn('products', 'variant_payload')) {
            $variantPayloadRaw = $request->input('variant_payload');
            $row->variant_payload = is_string($variantPayloadRaw) ? $variantPayloadRaw : json_encode($variantPayloadRaw ?: []);
            $row->load(['variant' => function ($q) {
                $q->select('id', 'pid', 'quantity');
            }]);
            $this->syncProductQuantityFromVariantSources($row);
        }
        if ($this->tableHasColumn('products', 'editor')) {
            $row->editor = $user->id;
        }
        $row->save();

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
            ],
        ]);
    }

    public function productDestroy(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Product::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        DB::transaction(function () use ($row, $store, $id) {
            Veriant::query()->where('pid', $id)->delete();
            if (Schema::hasTable('ai_seed_products')) {
                AiSeedProduct::query()
                    ->where('product_id', $id)
                    ->where('store_id', $store->id)
                    ->delete();
            }
            if ($this->tableHasColumn('products', 'status')) {
                $row->status = 'RecycleBin';
                $row->save();
            } else {
                $row->delete();
            }
        });

        return response()->json(['success' => true, 'id' => $id]);
    }

    private function normalizeVariantQuantity($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) floor((float) $value));
        }

        return 0;
    }

    private function sumVariantPayloadQuantities($variantPayload): int
    {
        if ($variantPayload === null || $variantPayload === '') {
            return 0;
        }

        $payload = is_array($variantPayload)
            ? $variantPayload
            : json_decode((string) $variantPayload, true);
        if (!is_array($payload)) {
            return 0;
        }

        $total = 0;
        $mode = (string) ($payload['mode'] ?? 'color_size');
        if ($mode === 'color_size') {
            foreach ((array) ($payload['colorSizeGroups'] ?? []) as $group) {
                foreach ((array) ($group['sizeRows'] ?? []) as $row) {
                    $total += $this->normalizeVariantQuantity($row['quantity'] ?? 0);
                }
            }
            return $total;
        }
        if ($mode === 'color_only') {
            foreach ((array) ($payload['colorOnlyRows'] ?? []) as $row) {
                $total += $this->normalizeVariantQuantity($row['quantity'] ?? 0);
            }
            return $total;
        }
        if ($mode === 'size_only') {
            foreach ((array) ($payload['sizeOnlyRows'] ?? []) as $row) {
                $total += $this->normalizeVariantQuantity($row['quantity'] ?? 0);
            }
            return $total;
        }
        if ($mode === 'unit') {
            foreach ((array) ($payload['unitRows'] ?? []) as $row) {
                $total += $this->normalizeVariantQuantity($row['quantity'] ?? 0);
            }
        }

        return $total;
    }

    private function buildInventoryVariantsFromDatabase(Product $product): \Illuminate\Support\Collection
    {
        return collect($product->variant ?? [])->map(function ($variant) {
            return [
                'id' => (int) ($variant->id ?? 0),
                'image_url' => !empty($variant->image) ? $this->resolveProductImageTokenPublicUrl((string) $variant->image) : null,
                'color' => (string) ($variant->color ?? ''),
                'size' => (string) ($variant->size ?? ''),
                'unit' => (string) ($variant->unit ?? ''),
                'volume' => (string) ($variant->volume ?? ''),
                'price' => (float) ($variant->additional_price ?? 0),
                'quantity' => $this->normalizeVariantQuantity($variant->quantity ?? 0),
            ];
        })->values();
    }

    private function buildInventoryVariantsFromPayload(
        $variantPayload,
        array $colorLookup,
        array $sizeLookup,
        array $unitLookup
    ): \Illuminate\Support\Collection {
        if ($variantPayload === null || $variantPayload === '') {
            return collect();
        }

        $pl = is_array($variantPayload)
            ? $variantPayload
            : json_decode((string) $variantPayload, true);
        if (!is_array($pl)) {
            return collect();
        }

        $plMode = (string) ($pl['mode'] ?? 'color_size');
        $plVariants = [];
        $tempId = -1;
        $resolveImg = function (string $imgId): ?string {
            if ($imgId === '') {
                return null;
            }
            $path = preg_replace('/^stored-/', '', $imgId);
            return $path !== '' ? $this->resolveProductImageTokenPublicUrl($path) : null;
        };

        if ($plMode === 'color_size') {
            foreach ($pl['colorSizeGroups'] ?? [] as $g) {
                $colorName = $colorLookup[(int) ($g['colorId'] ?? 0)] ?? '';
                $groupImg = $resolveImg((string) ($g['imageId'] ?? ''));
                foreach ($g['sizeRows'] ?? [] as $r) {
                    $plVariants[] = [
                        'id' => $tempId--,
                        'image_url' => $resolveImg((string) ($r['imageId'] ?? '')) ?? $groupImg,
                        'color' => $colorName,
                        'size' => $sizeLookup[(int) ($r['sizeId'] ?? 0)] ?? '',
                        'unit' => '',
                        'volume' => '',
                        'price' => (float) ($r['price'] ?? 0),
                        'quantity' => $this->normalizeVariantQuantity($r['quantity'] ?? 0),
                    ];
                }
            }
        } elseif ($plMode === 'color_only') {
            foreach ($pl['colorOnlyRows'] ?? [] as $r) {
                $plVariants[] = [
                    'id' => $tempId--,
                    'image_url' => $resolveImg((string) ($r['imageId'] ?? '')),
                    'color' => $colorLookup[(int) ($r['colorId'] ?? 0)] ?? '',
                    'size' => '',
                    'unit' => '',
                    'volume' => '',
                    'price' => (float) ($r['price'] ?? 0),
                    'quantity' => $this->normalizeVariantQuantity($r['quantity'] ?? 0),
                ];
            }
        } elseif ($plMode === 'size_only') {
            foreach ($pl['sizeOnlyRows'] ?? [] as $r) {
                $plVariants[] = [
                    'id' => $tempId--,
                    'image_url' => $resolveImg((string) ($r['imageId'] ?? '')),
                    'color' => '',
                    'size' => $sizeLookup[(int) ($r['sizeId'] ?? 0)] ?? '',
                    'unit' => '',
                    'volume' => '',
                    'price' => (float) ($r['price'] ?? 0),
                    'quantity' => $this->normalizeVariantQuantity($r['quantity'] ?? 0),
                ];
            }
        } elseif ($plMode === 'unit') {
            foreach ($pl['unitRows'] ?? [] as $r) {
                $plVariants[] = [
                    'id' => $tempId--,
                    'image_url' => $resolveImg((string) ($r['imageId'] ?? '')),
                    'color' => '',
                    'size' => '',
                    'unit' => $unitLookup[(int) ($r['unitId'] ?? 0)] ?? '',
                    'volume' => (string) ($r['volume'] ?? ''),
                    'price' => (float) ($r['price'] ?? 0),
                    'quantity' => $this->normalizeVariantQuantity($r['quantity'] ?? 0),
                ];
            }
        }

        return collect($plVariants);
    }

    private function buildInventoryVariants(
        Product $product,
        array $colorLookup,
        array $sizeLookup,
        array $unitLookup,
        bool $hasVariantPayloadColumn
    ): \Illuminate\Support\Collection {
        $dbVariants = $this->buildInventoryVariantsFromDatabase($product);
        $dbTotal = (int) $dbVariants->sum(fn ($row) => (int) ($row['quantity'] ?? 0));

        if (!$hasVariantPayloadColumn || empty($product->variant_payload)) {
            return $dbVariants;
        }

        $payloadVariants = $this->buildInventoryVariantsFromPayload(
            $product->variant_payload,
            $colorLookup,
            $sizeLookup,
            $unitLookup,
        );
        if ($payloadVariants->isEmpty()) {
            return $dbVariants;
        }

        $payloadTotal = (int) $payloadVariants->sum(fn ($row) => (int) ($row['quantity'] ?? 0));
        if ($dbVariants->isEmpty() || $payloadTotal > $dbTotal) {
            return $payloadVariants;
        }

        return $dbVariants;
    }

    private function resolveInventoryProductQuantity(int $baseQuantity, $variants, $variantPayload = null): int
    {
        $resolved = max(0, $this->normalizeVariantQuantity($baseQuantity));

        if ($variants instanceof \Illuminate\Support\Collection && $variants->isNotEmpty()) {
            $variantTotal = (int) $variants->sum(fn ($row) => (int) ($row['quantity'] ?? 0));
            $resolved = max($resolved, $variantTotal);
        }

        $payloadTotal = $this->sumVariantPayloadQuantities($variantPayload);
        if ($payloadTotal > 0) {
            $resolved = max($resolved, $payloadTotal);
        }

        return $resolved;
    }

    private function syncProductQuantityFromVariantSources(Product $row): void
    {
        if (!$this->tableHasColumn('products', 'quantity')) {
            return;
        }

        $baseQuantity = $this->normalizeVariantQuantity($row->quantity ?? 0);
        $variantTableQty = 0;
        if ($row->relationLoaded('variant')) {
            $variantTableQty = (int) collect($row->variant ?? [])->sum(fn ($variant) => $this->normalizeVariantQuantity($variant->quantity ?? 0));
        }
        $payloadQty = $this->sumVariantPayloadQuantities($row->variant_payload ?? null);
        $row->quantity = (string) max($baseQuantity, $variantTableQty, $payloadQty);
    }

    public function inventoryStockList(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', '')));
        $stockState = strtolower(trim((string) $request->query('stock_state', 'all')));
        $sellingType = strtolower(trim((string) $request->query('selling_type', '')));
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $expiryDate = trim((string) $request->query('expiry_date', ''));
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $headerSetting = Headersetting::query()->where('store_id', (string) $store->id)->first();
        $stockOutQty = max(1, (int) ($headerSetting->stock_out_qty ?? 5));
        $module118Enabled = function_exists('ModulusStatus') ? (bool) ModulusStatus((int) $store->id, 118) : false;

        $query = Product::query()
            ->with(['variant' => function ($q) {
                $q->select('id', 'pid', 'image', 'color', 'size', 'unit', 'volume', 'additional_price', 'quantity');
            }])
            ->leftJoinSub(
                DB::table('veriants')
                    ->selectRaw('pid as product_id, SUM(CAST(COALESCE(quantity, 0) AS SIGNED)) as variant_qty')
                    ->groupBy('pid'),
                'inventory_variant_stock',
                function ($join) {
                    $join->on('inventory_variant_stock.product_id', '=', 'products.id');
                }
            )
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('status', '!=', 'RecycleBin')
            ->select('products.*')
            ->selectRaw('COALESCE(inventory_variant_stock.variant_qty, 0) as variant_stock_qty')
            ->orderByDesc('products.id');

        $effectiveQtyExpression = 'GREATEST(CAST(COALESCE(products.quantity, 0) AS SIGNED), CAST(COALESCE(inventory_variant_stock.variant_qty, 0) AS SIGNED))';

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('SKU', 'like', "%{$search}%");
            });
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }
        if ($stockState === 'in') {
            $query->whereRaw("{$effectiveQtyExpression} > 0");
        } elseif ($stockState === 'out') {
            $query->whereRaw("{$effectiveQtyExpression} <= 0");
        } elseif ($stockState === 'low') {
            $query->whereRaw("{$effectiveQtyExpression} > 0");
            $query->whereRaw("{$effectiveQtyExpression} <= ?", [$stockOutQty]);
        } elseif ($stockState === 'alert') {
            $query->whereRaw("{$effectiveQtyExpression} <= ?", [$stockOutQty]);
        }

        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }
        if ($module118Enabled && $expiryDate !== '' && $this->tableHasColumn('products', 'expiry_date')) {
            $query->whereDate('expiry_date', '<=', $expiryDate);
        }

        if (in_array($sellingType, ['higher', 'lower'], true)) {
            $salesAgg = Orderitem::query()
                ->selectRaw('orderitems.product_id as product_id')
                ->selectRaw('SUM(CAST(COALESCE(orderitems.quantity, 0) AS SIGNED)) as sold_qty')
                ->join('orders', 'orders.id', '=', 'orderitems.order_id')
                ->where('orders.store_id', (string) $store->id)
                ->groupBy('orderitems.product_id');

            $query->leftJoinSub($salesAgg, 'sales_agg', function ($join) {
                $join->on('sales_agg.product_id', '=', 'products.id');
            });
            $query->select('products.*');
            $query->selectRaw('COALESCE(inventory_variant_stock.variant_qty, 0) as variant_stock_qty');
            $query->selectRaw('COALESCE(sales_agg.sold_qty, 0) as sold_qty');
            $query->orderBy('sold_qty', $sellingType === 'higher' ? 'desc' : 'asc');
        }

        $paginator = $query->paginate($perPage);

        // Preload color/size/unit name lookups for products that use variant_payload (React-admin created products)
        $colorLookup = [];
        $sizeLookup = [];
        $unitLookup = [];
        $hasVariantPayloadColumn = $this->tableHasColumn('products', 'variant_payload');
        if ($hasVariantPayloadColumn) {
            $allColorIds = [];
            $allSizeIds = [];
            $allUnitIds = [];
            foreach ($paginator->items() as $product) {
                if (empty($product->variant_payload)) continue;
                $pl = json_decode((string) $product->variant_payload, true);
                if (!is_array($pl)) continue;
                $plMode = $pl['mode'] ?? 'color_size';
                if ($plMode === 'color_size') {
                    foreach ($pl['colorSizeGroups'] ?? [] as $g) {
                        if (!empty($g['colorId'])) $allColorIds[] = (int) $g['colorId'];
                        foreach ($g['sizeRows'] ?? [] as $r) {
                            if (!empty($r['sizeId'])) $allSizeIds[] = (int) $r['sizeId'];
                        }
                    }
                } elseif ($plMode === 'color_only') {
                    foreach ($pl['colorOnlyRows'] ?? [] as $r) {
                        if (!empty($r['colorId'])) $allColorIds[] = (int) $r['colorId'];
                    }
                } elseif ($plMode === 'size_only') {
                    foreach ($pl['sizeOnlyRows'] ?? [] as $r) {
                        if (!empty($r['sizeId'])) $allSizeIds[] = (int) $r['sizeId'];
                    }
                } elseif ($plMode === 'unit') {
                    foreach ($pl['unitRows'] ?? [] as $r) {
                        if (!empty($r['unitId'])) $allUnitIds[] = (int) $r['unitId'];
                    }
                }
            }
            if (!empty($allColorIds)) {
                $colorLookup = Color::query()->whereIn('id', array_unique($allColorIds))
                    ->get(['id', 'name'])->keyBy('id')->map(fn($x) => (string) $x->name)->all();
            }
            if (!empty($allSizeIds)) {
                $sizeLookup = Size::query()->whereIn('id', array_unique($allSizeIds))
                    ->get(['id', 'name'])->keyBy('id')->map(fn($x) => (string) $x->name)->all();
            }
            if (!empty($allUnitIds)) {
                $unitLookup = Unit::query()->whereIn('id', array_unique($allUnitIds))
                    ->get(['id', 'name'])->keyBy('id')->map(fn($x) => (string) $x->name)->all();
            }
        }

        $items = collect($paginator->items())->map(function (Product $product) use ($stockOutQty, $module118Enabled, $colorLookup, $sizeLookup, $unitLookup, $hasVariantPayloadColumn) {
            $soldQty = (int) ($product->sold_qty ?? 0);

            $variants = $this->buildInventoryVariants(
                $product,
                $colorLookup,
                $sizeLookup,
                $unitLookup,
                $hasVariantPayloadColumn,
            );

            $quantity = $this->resolveInventoryProductQuantity(
                $this->normalizeVariantQuantity($product->quantity ?? 0),
                $variants,
                $hasVariantPayloadColumn ? ($product->variant_payload ?? null) : null,
            );
            $stockLabel = $quantity <= 0
                ? 'Out of stock'
                : ($quantity <= $stockOutQty ? 'Low stock' : 'In stock');

            return [
                'id' => (int) $product->id,
                'name' => (string) ($product->name ?? ''),
                'sku' => (string) ($product->SKU ?? ''),
                'image_url' => $this->resolveProductImagePublicUrl($product->images),
                'status' => strtolower((string) ($product->status ?? 'inactive')) === 'active' ? 'active' : 'inactive',
                'status_label' => strtolower((string) ($product->status ?? 'inactive')) === 'active' ? 'Enable' : 'Disable',
                'quantity' => $quantity,
                'stock_label' => $stockLabel,
                'sold_qty' => $soldQty,
                'regular_price' => (float) ($product->regular_price ?? 0),
                'price_label' => 'BDT ' . number_format((float) ($product->regular_price ?? 0), 0),
                'created_at' => $product->created_at ? Carbon::parse($product->created_at)->toISOString() : null,
                'created_label' => $product->created_at ? Carbon::parse($product->created_at)->format('d-m-Y') : '',
                'expiry_date' => $module118Enabled && $this->tableHasColumn('products', 'expiry_date')
                    ? ($product->expiry_date ? Carbon::parse($product->expiry_date)->toISOString() : null)
                    : null,
                'expiry_label' => $module118Enabled && $this->tableHasColumn('products', 'expiry_date')
                    ? ($product->expiry_date ? Carbon::parse($product->expiry_date)->format('d-m-Y') : '')
                    : '',
                'variants' => $variants,
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'stock_out_qty' => $stockOutQty,
            'module_118_enabled' => $module118Enabled,
            'selling_type' => $sellingType ?: 'all',
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function inventoryStockSetAlertLimit(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];

        $payload = $request->validate([
            'stock_out_qty' => ['required', 'integer', 'min:1', 'max:99999'],
        ]);

        $headerSetting = Headersetting::query()->where('store_id', (string) $store->id)->first();
        if (!$headerSetting) {
            $headerSetting = new Headersetting();
            $headerSetting->store_id = (string) $store->id;
            if ($this->tableHasColumn('headersettings', 'customer_id')) {
                $headerSetting->customer_id = (string) ($ctx['customer']->id ?? '');
            }
            if ($this->tableHasColumn('headersettings', 'uid')) {
                $headerSetting->uid = (string) ($ctx['user']->id ?? '');
            }
        }
        $headerSetting->stock_out_qty = (int) $payload['stock_out_qty'];
        $headerSetting->save();

        return response()->json([
            'message' => 'Stock alert quantity updated successfully.',
            'stock_out_qty' => (int) $headerSetting->stock_out_qty,
        ]);
    }

    public function inventoryStockToggleStatus(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Product::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('status', '!=', 'RecycleBin')
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $requestedStatus = strtolower((string) $request->input('status', ''));
        if (!in_array($requestedStatus, ['active', 'inactive'], true)) {
            $requestedStatus = strtolower((string) ($row->status ?? 'inactive')) === 'active' ? 'inactive' : 'active';
        }

        $row->status = $requestedStatus;
        $row->save();

        return response()->json([
            'message' => 'Status updated successfully.',
            'item' => [
                'id' => (int) $row->id,
                'status' => $requestedStatus,
                'status_label' => $requestedStatus === 'active' ? 'Enable' : 'Disable',
            ],
        ]);
    }

    public function inventoryStockBulkAction(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:active,deactive,delete'],
        ]);

        $ids = collect($payload['ids'])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return response()->json(['message' => 'No valid products selected.'], 422);
        }

        $query = Product::query()
            ->whereIn('id', $ids->all())
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id);

        $affected = 0;
        if ($payload['action'] === 'active') {
            $affected = $query->update(['status' => 'active']);
        } elseif ($payload['action'] === 'deactive') {
            $affected = $query->update(['status' => 'inactive']);
        } else {
            $affected = $query->update(['status' => 'RecycleBin']);
        }

        return response()->json([
            'message' => 'Bulk action applied successfully.',
            'affected' => (int) $affected,
        ]);
    }

    public function inventorySuppliers(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $query = Supplier::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $supplierIds = collect($paginator->items())->pluck('id')->filter()->values()->all();

        $productCounts = Product::query()
            ->whereIn('supplier', $supplierIds)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('status', '!=', 'RecycleBin')
            ->selectRaw('supplier, COUNT(*) as total')
            ->groupBy('supplier')
            ->pluck('total', 'supplier');

        $items = collect($paginator->items())->map(function (Supplier $row) use ($productCounts) {
            $supplierId = (string) ($row->id ?? '');
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'company_name' => (string) ($row->company_name ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'address' => (string) ($row->address ?? ''),
                'products_count' => (int) ($productCounts[$supplierId] ?? 0),
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null,
                'created_label' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
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

    public function inventorySupplierStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => AdminContactValidation::phoneRules(true, 120),
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = new Supplier();
        $row->name = $payload['name'];
        $row->company_name = $payload['company_name'] ?? null;
        $row->phone = $payload['phone'];
        $row->address = $payload['address'] ?? null;
        $row->uid = $user->id;
        $row->customer_id = $customer->id;
        $row->store_id = $store->id;
        $row->creator = $user->id;
        $row->editor = $user->id;
        $row->save();

        return response()->json([
            'message' => 'Supplier created successfully.',
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
            ],
        ], 201);
    }

    public function inventorySupplierUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => AdminContactValidation::phoneRules(true, 120),
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = Supplier::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $row->name = $payload['name'];
        $row->company_name = $payload['company_name'] ?? null;
        $row->phone = $payload['phone'];
        $row->address = $payload['address'] ?? null;
        $row->editor = $user->id;
        $row->save();

        return response()->json(['message' => 'Supplier updated successfully.']);
    }

    public function inventorySupplierDelete(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Supplier::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $row->delete();

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    public function inventorySupplierBulkDelete(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = collect($payload['ids'])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return response()->json(['message' => 'No valid suppliers selected.'], 422);
        }

        $affected = Supplier::query()
            ->whereIn('id', $ids->all())
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->delete();

        return response()->json([
            'message' => 'Selected suppliers deleted successfully.',
            'affected' => (int) $affected,
        ]);
    }

    public function inventorySupplierExport(Request $request)
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $search = trim((string) $request->query('search', ''));
        $query = Supplier::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $rows = $query->get();
        $fileName = 'suppliers-' . date('Ymd-His') . '.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Company Name', 'Phone', 'Address', 'Created Date']);
            foreach ($rows as $row) {
                fputcsv($file, [
                    (string) ($row->name ?? ''),
                    (string) ($row->company_name ?? ''),
                    (string) ($row->phone ?? ''),
                    (string) ($row->address ?? ''),
                    $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function inventorySupplierProducts(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $supplier = Supplier::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $search = trim((string) $request->query('search', ''));
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $limit = max(1, min((int) $request->query('limit', 50), 200));

        $query = Product::query()
            ->where('supplier', (string) $supplier->id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('status', '!=', 'RecycleBin')
            ->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('SKU', 'like', "%{$search}%");
            });
        }
        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $items = $query
            ->limit($limit)
            ->get(['id', 'name', 'SKU', 'regular_price', 'images', 'created_at'])
            ->map(function (Product $row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'sku' => (string) ($row->SKU ?? ''),
                    'regular_price' => (float) ($row->regular_price ?? 0),
                    'price_label' => 'BDT ' . number_format((float) ($row->regular_price ?? 0), 0),
                    'image_url' => $this->resolveProductImagePublicUrl($row->images),
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null,
                    'created_label' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
                ];
            })
            ->values();

        return response()->json([
            'supplier' => [
                'id' => (int) $supplier->id,
                'name' => (string) ($supplier->name ?? ''),
            ],
            'items' => $items,
        ]);
    }

    public function staffAccessStaffMeta(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $roles = Role::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($row) => ['id' => (int) $row->id, 'name' => (string) ($row->name ?? '')])
            ->values();

        $branches = Branch::query()
            ->where('store_id', (string) $store->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($row) => ['id' => (int) $row->id, 'name' => (string) ($row->name ?? '')])
            ->values();

        $storeRow = Store::query()->find((int) $store->id);
        $plan = $storeRow ? Plan::query()->find((int) ($storeRow->plan_id ?? 0)) : null;
        $limit = max(0, (int) ($plan->staff ?? 0));
        $current = (int) Staff::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->count();

        return response()->json([
            'roles' => $roles,
            'branches' => $branches,
            'limit' => $limit,
            'count' => $current,
            'permission_keys' => $this->staffAccessPermissionKeys(),
        ]);
    }

    public function staffAccessStaffList(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $query = Staff::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $roleMap = Role::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->pluck('name', 'id');

        $items = collect($paginator->items())->map(function (Staff $row) use ($roleMap) {
            $posIds = collect(explode(',', (string) ($row->pos ?? '')))
                ->map(fn($x) => (int) trim((string) $x))
                ->filter(fn($x) => $x > 0)
                ->values()
                ->all();

            return [
                'id' => (int) $row->id,
                'uid' => (int) ($row->uid ?? 0),
                'name' => (string) ($row->name ?? ''),
                'username' => (string) ($row->username ?? ''),
                'email' => (string) ($row->email ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'address' => (string) ($row->address ?? ''),
                'status' => strtolower((string) ($row->status ?? 'inactive')) === 'active' ? 'active' : 'inactive',
                'role_id' => (int) ($row->role_id ?? 0),
                'role_name' => (string) ($roleMap[(int) ($row->role_id ?? 0)] ?? ''),
                'pos_branch_ids' => $posIds,
                'created_label' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
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

    public function staffAccessStaffStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:4', 'max:255'],
            'email' => AdminContactValidation::emailRules(false, 255),
            'phone' => AdminContactValidation::phoneRules(false, 120),
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive'],
            'role_id' => ['nullable', 'integer'],
            'pos_branch_ids' => ['nullable', 'array'],
            'pos_branch_ids.*' => ['integer'],
        ]);

        $storeRow = Store::query()->find((int) $store->id);
        $plan = $storeRow ? Plan::query()->find((int) ($storeRow->plan_id ?? 0)) : null;
        $limit = max(0, (int) ($plan->staff ?? 0));
        $current = (int) Staff::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->count();
        if ($limit > 0 && $current >= $limit) {
            return response()->json(['message' => 'Staff limit reached for current package.'], 422);
        }

        $username = trim((string) $payload['username']);
        $usernameExists = User::query()->where('username', $username)->exists()
            || Staff::query()
                ->where('store_id', (string) $store->id)
                ->where('customer_id', (string) $customer->id)
                ->where('username', $username)
                ->exists();
        if ($usernameExists) {
            return response()->json(['message' => 'Username already exists.'], 422);
        }

        $roleId = (int) ($payload['role_id'] ?? 0);
        if ($roleId > 0) {
            $roleExists = Role::query()
                ->where('id', $roleId)
                ->where('store_id', (string) $store->id)
                ->where('customer_id', (string) $customer->id)
                ->exists();
            if (!$roleExists) {
                return response()->json(['message' => 'Selected role is invalid.'], 422);
            }
        }

        $user = new User();
        $user->name = $payload['name'];
        $user->username = $username;
        $user->password = Hash::make((string) $payload['password']);
        $user->type = 'staff';
        $user->role_id = $roleId > 0 ? $roleId : null;
        $user->otp = 'NULL';
        $user->store_id = (int) $store->id;
        $user->customer_id = (int) $customer->id;
        $user->save();

        $staff = new Staff();
        $staff->name = $payload['name'];
        $staff->username = $username;
        $staff->password = Hash::make((string) $payload['password']);
        $staff->phone = $payload['phone'] ?? null;
        $staff->email = $payload['email'] ?? null;
        $staff->address = $payload['address'] ?? null;
        $staff->uid = $user->id;
        $staff->customer_id = (int) $customer->id;
        $staff->store_id = (int) $store->id;
        $staff->creator = (int) ($ctx['user']->id ?? 0);
        $staff->editor = (int) ($ctx['user']->id ?? 0);
        $staff->status = $payload['status'] ?? 'active';
        $staff->role_id = $roleId > 0 ? $roleId : null;
        $staff->pos = implode(',', collect((array) ($payload['pos_branch_ids'] ?? []))->map(fn($x) => (int) $x)->filter(fn($x) => $x > 0)->values()->all());
        $staff->save();

        return response()->json([
            'message' => 'Staff created successfully.',
            'item' => ['id' => (int) $staff->id],
        ], 201);
    }

    public function staffAccessStaffUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'email' => AdminContactValidation::emailRules(false, 255),
            'phone' => AdminContactValidation::phoneRules(false, 120),
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive'],
            'role_id' => ['nullable', 'integer'],
            'pos_branch_ids' => ['nullable', 'array'],
            'pos_branch_ids.*' => ['integer'],
        ]);

        $staff = Staff::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        $username = trim((string) $payload['username']);
        $usernameExists = User::query()
            ->where('username', $username)
            ->where('id', '!=', (int) ($staff->uid ?? 0))
            ->exists()
            || Staff::query()
                ->where('store_id', (string) $store->id)
                ->where('customer_id', (string) $customer->id)
                ->where('username', $username)
                ->where('id', '!=', $staff->id)
                ->exists();
        if ($usernameExists) {
            return response()->json(['message' => 'Username already exists.'], 422);
        }

        $roleId = (int) ($payload['role_id'] ?? 0);
        if ($roleId > 0) {
            $roleExists = Role::query()
                ->where('id', $roleId)
                ->where('store_id', (string) $store->id)
                ->where('customer_id', (string) $customer->id)
                ->exists();
            if (!$roleExists) {
                return response()->json(['message' => 'Selected role is invalid.'], 422);
            }
        }

        $userRow = User::query()->find((int) ($staff->uid ?? 0));
        if ($userRow) {
            $userRow->name = $payload['name'];
            $userRow->username = $username;
            $userRow->role_id = $roleId > 0 ? $roleId : null;
            if (!empty($payload['password'])) {
                $userRow->password = Hash::make((string) $payload['password']);
            }
            $userRow->save();
        }

        $staff->name = $payload['name'];
        $staff->username = $username;
        $staff->phone = $payload['phone'] ?? null;
        $staff->email = $payload['email'] ?? null;
        $staff->address = $payload['address'] ?? null;
        $staff->status = $payload['status'] ?? $staff->status ?? 'active';
        $staff->role_id = $roleId > 0 ? $roleId : null;
        if (!empty($payload['password'])) {
            $staff->password = Hash::make((string) $payload['password']);
        }
        $staff->pos = implode(',', collect((array) ($payload['pos_branch_ids'] ?? []))->map(fn($x) => (int) $x)->filter(fn($x) => $x > 0)->values()->all());
        $staff->editor = (int) ($ctx['user']->id ?? 0);
        $staff->save();

        return response()->json(['message' => 'Staff updated successfully.']);
    }

    public function staffAccessStaffDelete(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $staff = Staff::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        $uid = (int) ($staff->uid ?? 0);
        $staff->delete();
        if ($uid > 0) {
            User::query()->where('id', $uid)->delete();
        }

        return response()->json(['message' => 'Staff deleted successfully.']);
    }

    public function staffAccessStaffBulkDelete(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $ids = collect($payload['ids'])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return response()->json(['message' => 'No valid staff selected.'], 422);
        }

        $rows = Staff::query()
            ->whereIn('id', $ids->all())
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->get(['id', 'uid']);

        $uidList = $rows->pluck('uid')->map(fn($x) => (int) $x)->filter(fn($x) => $x > 0)->values()->all();
        $affected = Staff::query()->whereIn('id', $rows->pluck('id')->all())->delete();
        if (!empty($uidList)) {
            User::query()->whereIn('id', $uidList)->delete();
        }

        return response()->json(['message' => 'Selected staff deleted successfully.', 'affected' => (int) $affected]);
    }

    public function staffAccessStaffExport(Request $request)
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $search = trim((string) $request->query('search', ''));
        $query = Staff::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        $rows = $query->get();

        $fileName = 'staff-' . date('Ymd-His') . '.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Username', 'Email', 'Phone', 'Address', 'Status', 'Created Date']);
            foreach ($rows as $row) {
                fputcsv($file, [
                    (string) ($row->name ?? ''),
                    (string) ($row->username ?? ''),
                    (string) ($row->email ?? ''),
                    (string) ($row->phone ?? ''),
                    (string) ($row->address ?? ''),
                    (string) ($row->status ?? ''),
                    $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function staffAccessRoleMeta(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $catalog = $this->staffAccessPermissionCatalog();
        return response()->json([
            'permission_keys' => array_values(array_unique(array_map(fn ($item) => (string) ($item['key'] ?? ''), $catalog))),
            'permission_catalog' => $catalog,
        ]);
    }

    public function staffAccessRoleList(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $search = trim((string) $request->query('search', ''));
        $query = Role::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderByDesc('id');
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->get(['id', 'name', 'permission', 'created_at'])
            ->map(function (Role $row) {
                $permissions = collect(explode(',', (string) ($row->permission ?? '')))
                    ->map(fn($x) => trim((string) $x))
                    ->filter()
                    ->values()
                    ->all();
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'permission' => $permissions,
                    'permission_count' => count($permissions),
                    'created_label' => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '',
                ];
            })
            ->values();

        $catalog = $this->staffAccessPermissionCatalog();
        return response()->json([
            'items' => $items,
            'permission_keys' => array_values(array_unique(array_map(fn ($item) => (string) ($item['key'] ?? ''), $catalog))),
            'permission_catalog' => $catalog,
        ]);
    }

    public function staffAccessRoleStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permission' => ['nullable', 'array'],
            'permission.*' => ['string', 'max:80'],
        ]);

        $validKeys = collect($this->staffAccessPermissionCatalog())->pluck('key')->filter()->unique()->values()->all();
        $permissions = collect((array) ($payload['permission'] ?? []))
            ->map(fn($x) => trim((string) $x))
            ->filter(fn($x) => in_array($x, $validKeys, true))
            ->unique()
            ->values()
            ->all();

        $row = new Role();
        $row->name = $payload['name'];
        $row->permission = implode(',', $permissions);
        $row->uid = (int) ($ctx['user']->id ?? 0);
        $row->customer_id = (int) $customer->id;
        $row->store_id = (int) $store->id;
        $row->creator = (int) ($ctx['user']->id ?? 0);
        $row->editor = (int) ($ctx['user']->id ?? 0);
        $row->save();

        return response()->json(['message' => 'Role created successfully.', 'item' => ['id' => (int) $row->id]], 201);
    }

    public function staffAccessRoleUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $row = Role::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        $row->name = $payload['name'];
        $row->editor = (int) ($ctx['user']->id ?? 0);
        $row->save();

        return response()->json(['message' => 'Role updated successfully.']);
    }

    public function staffAccessRolePermissionsUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $payload = $request->validate([
            'permission' => ['nullable', 'array'],
            'permission.*' => ['string', 'max:80'],
        ]);

        $row = Role::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        $validKeys = collect($this->staffAccessPermissionCatalog())->pluck('key')->filter()->unique()->values()->all();
        $permissions = collect((array) ($payload['permission'] ?? []))
            ->map(fn($x) => trim((string) $x))
            ->filter(fn($x) => in_array($x, $validKeys, true))
            ->unique()
            ->values()
            ->all();

        $row->permission = implode(',', $permissions);
        $row->editor = (int) ($ctx['user']->id ?? 0);
        $row->save();

        return response()->json(['message' => 'Role permissions updated successfully.']);
    }

    public function staffAccessRoleDelete(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Role::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        Staff::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('role_id', $row->id)
            ->update(['role_id' => null]);
        User::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('role_id', $row->id)
            ->update(['role_id' => null]);

        $row->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    public function catalogCategories(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }

        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $query = trim((string) $request->query('q', ''));
        $parent = trim((string) $request->query('parent', '0'));
        $mass = trim((string) $request->query('scope', 'categories'));

        $rows = Category::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->when($parent === '0', fn($q) => $q->where('parent', '0'))
            ->when($parent === '1', fn($q) => $q->where('parent', '!=', '0'))
            ->when($query !== '', fn($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderByRaw('CAST(COALESCE(position, 0) AS SIGNED) ASC')
            ->orderBy('id', 'DESC')
            ->get(['id', 'name', 'status', 'position', 'parent', 'icon', 'banner']);

        $productRows = Product::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->get(['id', 'category', 'subcategory']);

        $mainCounts = [];
        $subCounts = [];
        foreach ($productRows as $product) {
            foreach (explode(',', (string) $product->category) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $mainCounts[$id] = ($mainCounts[$id] ?? 0) + 1;
                }
            }
            foreach (explode(',', (string) $product->subcategory) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $subCounts[$id] = ($subCounts[$id] ?? 0) + 1;
                }
            }
        }

        $parentNames = Category::query()
            ->whereIn('id', $rows->pluck('parent')->filter()->all())
            ->pluck('name', 'id');

        $childrenByParent = Category::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->where('parent', '!=', '0')
            ->orderByRaw('CAST(COALESCE(position, 0) AS SIGNED) ASC')
            ->orderBy('id', 'DESC')
            ->get(['id', 'name', 'parent'])
            ->groupBy('parent');

        $items = $rows->map(function (Category $row) use ($mainCounts, $subCounts, $parentNames, $childrenByParent, $mass) {
            $isSub = (string) $row->parent !== '0';
            $countSource = $isSub ? $subCounts : $mainCounts;
            $childNames = $isSub
                ? []
                : collect($childrenByParent->get((string) $row->id, []))
                    ->map(fn($x) => (string) ($x->name ?? ''))
                    ->filter()
                    ->values()
                    ->all();
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'status' => strtolower((string) $row->status) === 'active' ? 'Enable' : 'Disable',
                'position' => (int) ($row->position ?? 0),
                'products' => (int) ($countSource[(string) $row->id] ?? 0),
                'parentId' => (string) ($row->parent ?? '0'),
                'parentCategory' => $isSub ? (string) ($parentNames[$row->parent] ?? '') : '',
                'scope' => $mass === 'subcategories' ? 'sub' : 'main',
                'iconUrl' => $this->resolveCatalogAssetPublicUrl($row->icon ?? null, 'assets/images/icon'),
                'bannerUrl' => $this->resolveCatalogAssetPublicUrl($row->banner ?? null, 'assets/images/category'),
                'subCategoryNames' => implode(', ', array_slice($childNames, 0, 4)),
            ];
        })->values();

        return response()->json([
            'items' => $items,
        ]);
    }

    public function catalogCategoryStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'numeric'],
            'status' => ['nullable', 'in:active,inactive'],
            'icon' => ['nullable', 'string', 'max:255'],
            'icon_media_path' => ['nullable', 'string', 'max:500'],
            'banner_media_path' => ['nullable', 'string', 'max:500'],
            'icon_upload' => ['nullable', 'file', 'image', 'max:5120'],
            'banner_upload' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        $row = new Category();
        $row->name = $payload['name'];
        $row->parent = (string) ($payload['parent'] ?? '0');
        $row->position = (string) ((int) ($payload['position'] ?? 0));
        $row->status = $payload['status'] ?? 'active';
        $row->icon = $payload['icon'] ?? null;
        if ($request->hasFile('icon_upload')) {
            $row->icon = $this->storeUploadedPublicImage($request->file('icon_upload'), 'assets/images/icon');
        } elseif (!empty($payload['icon_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['icon_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/icon',
                'icon_',
            );
            if ($copied !== null) {
                $row->icon = $copied;
            }
        }
        if ($request->hasFile('banner_upload')) {
            $row->banner = $this->storeUploadedPublicImage($request->file('banner_upload'), 'assets/images/category');
        } elseif (!empty($payload['banner_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['banner_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/category',
                'category_',
            );
            if ($copied !== null) {
                $row->banner = $copied;
            }
        }
        $row->uid = $user->id;
        $row->customer_id = $customer->id;
        $row->store_id = $store->id;
        $row->creator = $user->id;
        $row->editor = $user->id;
        $row->save();

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'status' => strtolower((string) $row->status) === 'active' ? 'Enable' : 'Disable',
                'products' => 0,
                'parentCategory' => '',
            ],
        ], 201);
    }

    public function catalogCategoryUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return $ctx['error'];
        }
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $row = Category::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'numeric'],
            'status' => ['sometimes', 'in:active,inactive'],
            'parent' => ['sometimes', 'string', 'max:30'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon_media_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'banner_media_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'icon_upload' => ['sometimes', 'nullable', 'file', 'image', 'max:5120'],
            'banner_upload' => ['sometimes', 'nullable', 'file', 'image', 'max:10240'],
        ]);

        if (array_key_exists('name', $payload)) $row->name = $payload['name'];
        if (array_key_exists('position', $payload)) $row->position = (string) ((int) $payload['position']);
        if (array_key_exists('status', $payload)) $row->status = $payload['status'];
        if (array_key_exists('parent', $payload)) $row->parent = (string) $payload['parent'];
        if (array_key_exists('icon', $payload)) $row->icon = $payload['icon'];
        if ($request->hasFile('icon_upload')) {
            $row->icon = $this->storeUploadedPublicImage($request->file('icon_upload'), 'assets/images/icon');
        } elseif (!empty($payload['icon_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['icon_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/icon',
                'icon_',
            );
            if ($copied !== null) {
                $row->icon = $copied;
            }
        }
        if ($request->hasFile('banner_upload')) {
            $row->banner = $this->storeUploadedPublicImage($request->file('banner_upload'), 'assets/images/category');
        } elseif (!empty($payload['banner_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['banner_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/category',
                'category_',
            );
            if ($copied !== null) {
                $row->banner = $copied;
            }
        }
        $row->editor = $user->id;
        $row->save();

        return response()->json(['success' => true]);
    }

    public function catalogCategoryDelete(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Category::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Category not found.'], 404);
        }
        $row->delete();
        return response()->json(['success' => true]);
    }

    public function catalogBrands(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $query = trim((string) $request->query('q', ''));

        $rows = Brand::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->when($query !== '', fn($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('id', 'DESC')
            ->get(['id', 'name', 'image']);

        $counts = Product::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->get(['brand']);

        $brandCounts = [];
        foreach ($counts as $product) {
            foreach (explode(',', (string) ($product->brand ?? '')) as $token) {
                $token = trim((string) $token);
                if ($token === '') continue;
                $brandCounts[$token] = ($brandCounts[$token] ?? 0) + 1;
                $lowerToken = strtolower($token);
                if ($lowerToken !== $token) {
                    $brandCounts[$lowerToken] = ($brandCounts[$lowerToken] ?? 0) + 1;
                }
            }
        }

        $items = $rows->map(function (Brand $row) use ($brandCounts) {
            $idKey = (string) $row->id;
            $nameKey = trim((string) ($row->name ?? ''));
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'status' => 'Enable',
                'products' => (int) (
                    $brandCounts[$idKey]
                    ?? ($nameKey !== '' ? ($brandCounts[$nameKey] ?? $brandCounts[strtolower($nameKey)] ?? 0) : 0)
                ),
                'bannerUrl' => $this->resolveCatalogAssetPublicUrl($row->image ?? null, 'assets/images/brand'),
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function iconPack(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 200);
        $limit = max(1, min($limit, 1000));

        $rows = Iconpack::query()
            ->when($query !== '', fn($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get(['id', 'name', 'value', 'image']);

        $items = $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'value' => (string) ($row->value ?: $row->name),
                'image_url' => $this->resolveIconImagePublicUrl($row->image ?? null),
            ];
        })->values();

        return response()->json([
            'items' => $items,
        ]);
    }

    public function catalogBrandStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo_media_path' => ['nullable', 'string', 'max:500'],
            'logo_upload' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        $row = new Brand();
        $row->name = $payload['name'];
        if ($request->hasFile('logo_upload')) {
            $row->image = $this->storeUploadedPublicImage($request->file('logo_upload'), 'assets/images/brand');
        } elseif (!empty($payload['logo_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['logo_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/brand',
                'brand_',
            );
            if ($copied !== null) {
                $row->image = $copied;
            }
        }
        $row->uid = $user->id;
        $row->customer_id = $customer->id;
        $row->store_id = $store->id;
        $row->creator = $user->id;
        $row->editor = $user->id;
        $row->save();

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'status' => 'Enable',
                'products' => 0,
                'bannerUrl' => $this->resolveCatalogAssetPublicUrl($row->image ?? null, 'assets/images/brand'),
            ],
        ], 201);
    }

    public function catalogBrandUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $row = Brand::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Brand not found.'], 404);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'logo_media_path' => ['sometimes', 'nullable', 'string', 'max:500'],
            'logo_upload' => ['sometimes', 'nullable', 'file', 'image', 'max:10240'],
        ]);

        if (array_key_exists('name', $payload)) {
            $row->name = $payload['name'];
        }
        if ($request->hasFile('logo_upload')) {
            $row->image = $this->storeUploadedPublicImage($request->file('logo_upload'), 'assets/images/brand');
        } elseif (!empty($payload['logo_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['logo_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/brand',
                'brand_',
            );
            if ($copied !== null) {
                $row->image = $copied;
            }
        }
        if ($this->tableHasColumn('brands', 'editor')) {
            $row->editor = $user->id;
        }
        $row->save();

        return response()->json(['success' => true]);
    }

    public function catalogBrandDelete(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $row = Brand::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Brand not found.'], 404);
        }
        $row->delete();
        return response()->json(['success' => true]);
    }

    public function catalogVariants(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $colorColumns = ['id', 'name'];
        if ($this->tableHasColumn('colors', 'code')) {
            $colorColumns[] = 'code';
        }

        $colorHasPosition = $this->tableHasColumn('colors', 'position');
        $sizeHasPosition = $this->tableHasColumn('sizes', 'position');
        $unitHasPosition = $this->tableHasColumn('units', 'position');

        if ($colorHasPosition) {
            $colorColumns[] = 'position';
        }
        $sizeColumns = $sizeHasPosition ? ['id', 'name', 'position'] : ['id', 'name'];
        $unitColumns = $unitHasPosition ? ['id', 'name', 'position'] : ['id', 'name'];

        $colors = Color::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->when($colorHasPosition, fn($q) => $q->orderByRaw('CAST(COALESCE(position, 0) AS SIGNED) ASC'))
            ->when(!$colorHasPosition, fn($q) => $q->orderBy('id', 'DESC'))
            ->get($colorColumns)
            ->values()
            ->map(fn($x, $index) => [
                'id' => (int) $x->id,
                'value' => (string) $x->name,
                'hex' => (string) ($x->code ?? '#c24d2c'),
                'position' => (int) ($x->position ?? ($index + 1)),
            ])->values();

        $sizes = Size::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->when($sizeHasPosition, fn($q) => $q->orderByRaw('CAST(COALESCE(position, 0) AS SIGNED) ASC'))
            ->when(!$sizeHasPosition, fn($q) => $q->orderBy('id', 'DESC'))
            ->get($sizeColumns)
            ->values()
            ->map(fn($x, $index) => [
                'id' => (int) $x->id,
                'value' => (string) $x->name,
                'position' => (int) ($x->position ?? ($index + 1)),
            ])->values();

        $units = Unit::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->when($unitHasPosition, fn($q) => $q->orderByRaw('CAST(COALESCE(position, 0) AS SIGNED) ASC'))
            ->when(!$unitHasPosition, fn($q) => $q->orderBy('id', 'DESC'))
            ->get($unitColumns)
            ->values()
            ->map(fn($x, $index) => [
                'id' => (int) $x->id,
                'value' => (string) $x->name,
                'position' => (int) ($x->position ?? ($index + 1)),
            ])->values();

        return response()->json([
            'colors' => $colors,
            'sizes' => $sizes,
            'units' => $units,
        ]);
    }

    public function catalogVariantStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'type' => ['required', 'in:color,size,unit'],
            'value' => ['required', 'string', 'max:255'],
            'hex' => ['nullable', 'string', 'max:20'],
        ]);

        if ($payload['type'] === 'color') {
            $row = new Color();
            $row->name = $payload['value'];
            if ($this->tableHasColumn('colors', 'code')) {
                $row->code = $payload['hex'] ?? '#c24d2c';
            }
        } elseif ($payload['type'] === 'size') {
            $row = new Size();
            $row->name = $payload['value'];
        } else {
            $row = new Unit();
            $row->name = $payload['value'];
        }

        $row->uid = $user->id;
        $row->customer_id = $customer->id;
        $row->store_id = $store->id;
        $row->creator = $user->id;
        $row->editor = $user->id;
        $row->save();

        return response()->json([
            'item' => [
                'id' => (int) $row->id,
                'value' => (string) $row->name,
                'hex' => (string) ($row->code ?? '#c24d2c'),
            ],
        ], 201);
    }

    public function catalogVariantDelete(Request $request, string $type, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        if ($type === 'color') {
            $model = Color::class;
        } elseif ($type === 'size') {
            $model = Size::class;
        } elseif ($type === 'unit') {
            $model = Unit::class;
        } else {
            return response()->json(['message' => 'Invalid variant type.'], 422);
        }

        $row = $model::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Variant not found.'], 404);
        }
        $row->delete();
        return response()->json(['success' => true]);
    }

    public function mediaLibraryIndex(Request $request): JsonResponse
    {
        $library = $this->resolveMediaLibraryRequest($request);
        if ($library['error']) return $library['error'];

        $baseDir = $library['baseDir'];
        $folder = $this->sanitizeMediaFolder((string) $request->query('folder', ''));
        $dir = $this->resolveMediaLibraryFolderPath($baseDir, $folder);
        $disk = Storage::disk('public');
        $files = $folder !== ''
            ? ($disk->exists($dir) ? $disk->files($dir) : [])
            : ($disk->exists($baseDir) ? $disk->allFiles($baseDir) : []);
        $folders = $disk->exists($baseDir) ? $disk->directories($baseDir) : [];

        $items = collect($files)
            ->reject(function (string $path) {
                $name = basename($path);
                return str_starts_with($name, '.') || str_starts_with($name, '._');
            })
            ->map(function (string $path) use ($disk) {
                $name = basename($path);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                return [
                    'path' => $path,
                    'name' => $name,
                    'url' => $this->mediaLibraryFileUrl($path, $this->mediaStoreIdFromPath($path)),
                    'size' => (int) $disk->size($path),
                    'last_modified' => (int) $disk->lastModified($path),
                    'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        $folderItems = collect($folders)
            ->map(function (string $path) use ($baseDir) {
                $relative = trim(Str::after($path, trim($baseDir, '/') . '/'), '/');
                return [
                    'path' => $relative,
                    'name' => basename($path),
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json([
            'items' => $items,
            'folders' => $folderItems,
            'active_folder' => $folder,
            'scope' => $library['scope'],
            'base_dir' => $baseDir,
        ]);
    }

    public function mediaLibraryUpload(Request $request): JsonResponse
    {
        $library = $this->resolveMediaLibraryRequest($request);
        if ($library['error']) return $library['error'];

        $payload = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'max:10240'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $baseDir = $library['baseDir'];
        $folder = $this->sanitizeMediaFolder((string) ($payload['folder'] ?? ''));
        $dir = $this->resolveMediaLibraryFolderPath($baseDir, $folder);
        $disk = Storage::disk('public');
        $items = [];

        foreach ($payload['images'] as $file) {
            $originalName = method_exists($file, 'getClientOriginalName')
                ? (string) $file->getClientOriginalName()
                : 'file';
            $filename = $this->resolveUniqueMediaFilename($disk, $dir, $originalName);
            $stored = $file->storeAs($dir, $filename, 'public');
            $items[] = [
                'path' => $stored,
                'name' => basename($stored),
                'url' => $this->mediaLibraryFileUrl($stored, $this->mediaStoreIdFromPath($stored)),
                'size' => (int) $disk->size($stored),
                'last_modified' => (int) $disk->lastModified($stored),
                'is_image' => true,
            ];
        }

        return response()->json(['items' => $items], 201);
    }

    public function mediaLibraryCreateFolder(Request $request): JsonResponse
    {
        $library = $this->resolveMediaLibraryRequest($request);
        if ($library['error']) return $library['error'];

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $baseDir = $library['baseDir'];
        $folderName = preg_replace('/[^a-zA-Z0-9 _.-]/', '', trim((string) $payload['name']));
        $folderName = trim(str_replace('\\', '/', $folderName), '/');
        if ($folderName === '' || str_contains($folderName, '..')) {
            return response()->json(['message' => 'Invalid folder name.'], 422);
        }

        $folderPath = $this->resolveMediaLibraryFolderPath($baseDir, $folderName);
        Storage::disk('public')->makeDirectory($folderPath);

        return response()->json([
            'folder' => [
                'path' => $folderName,
                'name' => basename($folderName),
            ],
        ], 201);
    }

    public function mediaLibraryDelete(Request $request): JsonResponse
    {
        $library = $this->resolveMediaLibraryRequest($request);
        if ($library['error']) return $library['error'];

        $payload = $request->validate([
            'path' => ['nullable', 'string', 'max:500'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $dir = $library['baseDir'];
        $disk = Storage::disk('public');

        $folder = $this->sanitizeMediaFolder((string) ($payload['folder'] ?? ''));
        if ($folder !== '') {
            $folderPath = $this->resolveMediaLibraryFolderPath($dir, $folder);
            $prefix = trim($dir, '/') . '/';
            if (!str_starts_with($folderPath, $prefix)) {
                return response()->json(['message' => 'Invalid media folder.'], 422);
            }
            if (!$disk->exists($folderPath)) {
                return response()->json(['message' => 'Folder not found.'], 404);
            }
            $disk->deleteDirectory($folderPath);

            return response()->json(['success' => true]);
        }

        $path = $this->normalizeMediaLibraryDiskPath((string) ($payload['path'] ?? ''));
        if ($path === '') {
            return response()->json(['message' => 'Missing media path.'], 422);
        }
        $prefix = trim($dir, '/') . '/';
        if (!str_starts_with($path, $prefix)) {
            return response()->json(['message' => 'Invalid media path.'], 422);
        }

        if (!$disk->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $disk->delete($path);

        return response()->json(['success' => true]);
    }

    public function mediaLibraryFile(Request $request)
    {
        $library = $this->resolveMediaLibraryRequest($request);
        if ($library['error']) return $library['error'];

        $path = $this->normalizeMediaLibraryDiskPath((string) $request->query('path', ''));
        if ($path === '') {
            return response()->json(['message' => 'Missing file path.'], 422);
        }

        // ai-seed-library is a superadmin-only path not under image-library/
        if (Str::startsWith($path, 'ai-seed-library/')) {
            if ($library['scope'] !== 'superadmin') {
                return response()->json(['message' => 'Super admin media access required.'], 403);
            }
        } else {
            $dir = $this->legacyMediaLibraryBaseFromPath($path) ?: $library['baseDir'];
            $prefix = trim($dir, '/') . '/';
            if (!str_starts_with($path, $prefix)) {
                return response()->json(['message' => 'Invalid media path.'], 422);
            }
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $stream = $disk->readStream($path);
        if (!$stream) {
            return response()->json(['message' => 'Unable to read file.'], 500);
        }

        return Response::stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function catalogVariantUpdate(Request $request, string $type, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $resolved = $this->resolveVariantModelAndTable($type);
        if (!$resolved) {
            return response()->json(['message' => 'Invalid variant type.'], 422);
        }
        ['model' => $model, 'table' => $table] = $resolved;

        $payload = $request->validate([
            'value' => ['sometimes', 'string', 'max:255'],
            'hex' => ['sometimes', 'nullable', 'string', 'max:20'],
            'position' => ['sometimes', 'numeric', 'min:1'],
        ]);

        $row = $model::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Variant not found.'], 404);
        }

        if (array_key_exists('value', $payload)) {
            $row->name = $payload['value'];
        }
        if ($type === 'color' && array_key_exists('hex', $payload) && $this->tableHasColumn('colors', 'code')) {
            $row->code = $payload['hex'] ?? '#c24d2c';
        }
        if (array_key_exists('position', $payload) && $this->tableHasColumn($table, 'position')) {
            $row->position = (string) ((int) $payload['position']);
        }
        if ($this->tableHasColumn($table, 'editor')) {
            $row->editor = $user->id;
        }
        $row->save();

        return response()->json(['success' => true]);
    }

    public function catalogVariantReorder(Request $request, string $type): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $resolved = $this->resolveVariantModelAndTable($type);
        if (!$resolved) {
            return response()->json(['message' => 'Invalid variant type.'], 422);
        }
        ['model' => $model, 'table' => $table] = $resolved;
        if (!$this->tableHasColumn($table, 'position')) {
            return response()->json(['success' => true]);
        }

        $payload = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $rows = $model::query()
            ->whereIn('id', $payload['ordered_ids'])
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->get()
            ->keyBy('id');

        foreach (array_values($payload['ordered_ids']) as $index => $id) {
            $row = $rows->get((int) $id);
            if (!$row) continue;
            $row->position = (string) ($index + 1);
            if ($this->tableHasColumn($table, 'editor')) {
                $row->editor = $user->id;
            }
            $row->save();
        }

        return response()->json(['success' => true]);
    }

    private function resolveVariantModelAndTable(string $type): ?array
    {
        if ($type === 'color') return ['model' => Color::class, 'table' => 'colors'];
        if ($type === 'size') return ['model' => Size::class, 'table' => 'sizes'];
        if ($type === 'unit') return ['model' => Unit::class, 'table' => 'units'];
        return null;
    }

    private function resolveMediaLibraryRequest(Request $request): array
    {
        $scope = strtolower(trim((string) $request->query('scope', 'admin')));
        if (!in_array($scope, ['admin', 'superadmin'], true)) {
            $scope = 'admin';
        }

        $user = $request->user();
        if (!$user) {
            return ['error' => response()->json(['message' => 'Unauthenticated.'], 401)];
        }

        $userType = strtolower((string) ($user->type ?? ''));
        if ($scope === 'superadmin') {
            if (!in_array($userType, ['superadmin', 'superstaff'], true)) {
                return ['error' => response()->json(['message' => 'Super admin media access required.'], 403)];
            }

            return [
                'error' => null,
                'scope' => 'superadmin',
                'baseDir' => $this->superAdminMediaLibraryDirectory(),
                'customer' => null,
                'store' => null,
            ];
        }

        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) {
            return ['error' => $ctx['error']];
        }

        return [
            'error' => null,
            'scope' => 'admin',
            'baseDir' => $this->adminMediaLibraryDirectory($ctx['store'], $ctx['customer']),
            'customer' => $ctx['customer'],
            'store' => $ctx['store'],
        ];
    }

    private function superAdminMediaLibraryDirectory(?string $folder = null): string
    {
        return $this->appendMediaLibraryFolder('image-library/superadmin', $folder);
    }

    private function adminMediaLibraryDirectory(Store $store, Customer $customer, ?string $folder = null): string
    {
        $storeId = trim((string) $store->id);
        $storeName = trim((string) ($store->name ?? $store->url ?? $store->slug ?? 'store'));
        $slug = Str::slug($storeName);
        if ($slug === '') {
            $slug = 'store';
        }

        return $this->appendMediaLibraryFolder('image-library/admin/' . $slug . '-' . $storeId, $folder);
    }

    private function appendMediaLibraryFolder(string $base, ?string $folder = null): string
    {
        $base = trim(str_replace('\\', '/', $base), '/');
        $folder = $this->sanitizeMediaFolder((string) ($folder ?? ''));
        return $folder === '' ? $base : $base . '/' . $folder;
    }

    private function mediaLibraryDirectory(string $customerId, string $storeId): string
    {
        $store = Store::query()->find((int) $storeId);
        $customer = Customer::query()->find((int) $customerId);
        if ($store && $customer) {
            return $this->adminMediaLibraryDirectory($store, $customer);
        }

        return 'image-library/admin/store-' . trim($storeId, '/');
    }

    private function sanitizeMediaFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');
        if ($folder === '' || str_contains($folder, '..')) {
            return '';
        }
        return $folder;
    }

    private function resolveMediaLibraryFolderPath(string $baseDir, string $folder): string
    {
        $base = trim($baseDir, '/');
        if ($folder === '') {
            return $base;
        }
        return $base . '/' . trim($folder, '/');
    }

    private function sanitizeMediaFilename(string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'file';
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $basename = pathinfo($name, PATHINFO_FILENAME);

        $basename = preg_replace('/[^A-Za-z0-9 _.-]/', '', (string) $basename);
        $basename = preg_replace('/\s+/', ' ', (string) $basename);
        $basename = trim((string) $basename, " .\t\n\r\0\x0B");
        if ($basename === '') {
            $basename = 'file';
        }

        $extension = preg_replace('/[^A-Za-z0-9]/', '', (string) $extension);
        if ($extension === '') {
            return $basename;
        }

        return $basename . '.' . strtolower($extension);
    }

    private function resolveUniqueMediaFilename($disk, string $dir, string $filename): string
    {
        $filename = $this->sanitizeMediaFilename($filename);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;
        $counter = 2;

        while ($disk->exists(trim($dir, '/') . '/' . $candidate)) {
            $suffix = '-' . $counter;
            $candidate = $extension !== ''
                ? $basename . $suffix . '.' . $extension
                : $basename . $suffix;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Unique filename for files stored under a public disk directory (not the storage disk).
     */
    private function resolveUniquePublicDiskFilename(string $absoluteDir, string $filename): string
    {
        $filename = $this->sanitizeMediaFilename($filename);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;
        $counter = 2;

        while (file_exists(rtrim($absoluteDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate)) {
            $suffix = '-' . $counter;
            $candidate = $extension !== ''
                ? $basename . $suffix . '.' . $extension
                : $basename . $suffix;
            $counter++;
        }

        return $candidate;
    }

    private function mediaLibraryFileUrl(string $path, ?string $storeId = null): string
    {
        $cleanPath = $this->normalizeMediaLibraryDiskPath($path);
        $params = ['path' => $cleanPath];

        if (Str::startsWith($cleanPath, ['image-library/superadmin/', 'ai-seed-library/'])) {
            $params['scope'] = 'superadmin';
        }

        return rtrim($this->assetOrigin(), '/') . '/react-admin-api/media-library/file?' . http_build_query($params);
    }

    private function mediaStoreIdFromPath(string $path): ?string
    {
        $segments = explode('/', $this->normalizeMediaLibraryDiskPath($path));
        if (($segments[0] ?? '') === 'react-admin-media') {
            return !empty($segments[2]) ? (string) $segments[2] : null;
        }

        if (($segments[0] ?? '') !== 'image-library') {
            return null;
        }

        if (($segments[1] ?? '') !== 'admin') {
            return null;
        }

        $storeSegment = (string) ($segments[2] ?? '');
        if ($storeSegment === '') {
            return null;
        }

        if (preg_match('/-(\d+)$/', $storeSegment, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function legacyMediaLibraryBaseFromPath(string $path): ?string
    {
        $segments = explode('/', $this->normalizeMediaLibraryDiskPath($path));
        if (($segments[0] ?? '') !== 'react-admin-media') {
            return null;
        }

        $customerId = trim((string) ($segments[1] ?? ''));
        $storeId = trim((string) ($segments[2] ?? ''));
        if ($customerId === '' || $storeId === '') {
            return null;
        }

        return 'react-admin-media/' . $customerId . '/' . $storeId;
    }

    private function normalizeMediaLibraryDiskPath(string $path): string
    {
        $clean = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (Str::startsWith($clean, 'storage/')) {
            $clean = ltrim(substr($clean, strlen('storage/')), '/');
        }
        return $clean;
    }

    private function normalizeStoredImageToken(string $path): string
    {
        $clean = trim(str_replace('\\', '/', $path));
        if ($clean === '') {
            return '';
        }
        if (Str::startsWith($clean, ['http://', 'https://'])) {
            return $clean;
        }
        $clean = ltrim($clean, '/');
        if (Str::startsWith($clean, ['storage/', 'assets/', 'react-admin-media/', 'image-library/'])) {
            return $clean;
        }
        return basename($clean);
    }

    private function publicStoragePathUrl(string $path): string
    {
        $segments = array_map(
            static fn($segment) => rawurlencode($segment),
            array_filter(explode('/', trim(str_replace('\\', '/', $path), '/')), static fn($segment) => $segment !== '')
        );

        return '/storage/' . implode('/', $segments);
    }

    public function businessCategories(Request $request): JsonResponse
    {
        $hasPosition = Schema::hasColumn('business_categories', 'position');
        $rootsQuery = BusinessCategory::query()
            ->with(['subcategories' => static function ($q) use ($hasPosition) {
                $q->when($hasPosition, fn($query) => $query->orderByRaw('CASE WHEN position = 0 THEN 1 ELSE 0 END')->orderBy('position', 'asc'))
                    ->orderBy('name');
            }])
            ->where(function ($q) {
                $q->whereNull('parent_id')
                    ->orWhere('parent_id', '')
                    ->orWhere('parent_id', '0')
                    ->orWhere('parent_id', 0);
            })
            ->when($hasPosition, fn($q) => $q->orderByRaw('CASE WHEN position = 0 THEN 1 ELSE 0 END')->orderBy('position', 'asc'))
            ->orderBy('name');

        $parents = $rootsQuery->get();

        if ($parents->isEmpty()) {
            $all = BusinessCategory::query()
                ->when($hasPosition, fn($q) => $q->orderByRaw('CASE WHEN position = 0 THEN 1 ELSE 0 END')->orderBy('position', 'asc'))
                ->orderBy('name')
                ->get();
            $categories = $all->map(static function (BusinessCategory $row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'children' => [],
                ];
            })->values();

            return response()->json([
                'categories' => $categories,
            ]);
        }

        $categories = $parents->map(static function (BusinessCategory $parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'children' => $parent->subcategories->map(static function (BusinessCategory $child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function currencies(Request $request): JsonResponse
    {
        $rows = DB::table('currencies')->orderBy('code')->get(['id', 'code', 'country', 'symbol']);

        return response()->json([
            'currencies' => $rows,
        ]);
    }

    public function superadminStoreDefaultImageLibrary(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        $usageType = trim((string) $request->query('usage_type', ''));
        $categoryId = trim((string) $request->query('business_category_id', ''));
        $search = trim((string) $request->query('search', ''));
        $perPage = min(60, max(12, (int) $request->query('per_page', 24)));

        $query = AiSeedImageLibrary::query()->orderByDesc('id');

        if ($usageType !== '') {
            $query->where('usage_type', $usageType);
        }
        if ($categoryId !== '' && ctype_digit($categoryId)) {
            $filterCategoryId = (int) $categoryId;
            $query->where(function ($q) use ($filterCategoryId) {
                $q->where('business_category_id', $filterCategoryId);
                if (Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
                    $q->orWhereJsonContains('business_category_ids', $filterCategoryId)
                        ->orWhereJsonContains('business_category_ids', (string) $filterCategoryId);
                }
            });
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('business_category_name', 'like', "%{$search}%")
                    ->orWhere('category_slug', 'like', "%{$search}%")
                    ->orWhere('subcategory_slug', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'items' => collect($paginator->items())->map(fn(AiSeedImageLibrary $row) => $this->formatAiSeedImageLibraryRow($row))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'meta' => $this->aiSeedImageLibraryMeta(),
        ]);
    }

    public function superadminStoreDefaultImageLibraryStore(Request $request): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        $payload = $request->validate([
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'business_category_id' => ['nullable', 'integer', 'exists:business_categories,id'],
            'business_category_ids' => ['nullable', 'array', 'max:30'],
            'business_category_ids.*' => ['integer', 'exists:business_categories,id'],
            'usage_type' => ['required', 'string', 'in:product,category,slider,banner'],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'subcategory_slug' => ['nullable', 'string', 'max:120'],
            'ratio_key' => ['nullable', 'string', 'max:20'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'boolean'],
            'media_library_path' => ['nullable', 'string', 'max:512'],
            'media_library_paths' => ['nullable', 'array', 'max:50'],
            'media_library_paths.*' => ['string', 'max:512'],
        ]);

        $pathsRaw = $payload['media_library_paths'] ?? null;
        $paths = [];
        if (is_array($pathsRaw)) {
            $paths = array_values(array_unique(array_filter(array_map(static fn ($p) => trim((string) $p), $pathsRaw), static fn ($p) => $p !== '')));
        }
        $singlePath = trim((string) ($payload['media_library_path'] ?? ''));
        unset($payload['media_library_path'], $payload['media_library_paths']);

        $hasFile = $request->hasFile('image');
        if ($hasFile && ($singlePath !== '' || $paths !== [])) {
            return response()->json(['message' => 'Provide either an uploaded image or media library path(s), not both.'], 422);
        }
        if ($singlePath !== '' && $paths !== []) {
            return response()->json(['message' => 'Use either media_library_path or media_library_paths, not both.'], 422);
        }

        if ($paths !== []) {
            return $this->superadminStoreDefaultImageLibraryImportFromMediaPaths($payload, $paths);
        }
        if ($singlePath !== '') {
            return $this->superadminStoreDefaultImageLibraryImportFromMediaPaths($payload, [$singlePath]);
        }

        if (!$hasFile) {
            return response()->json(['message' => 'An image file or media library path is required.'], 422);
        }

        $businessCategoryIds = $this->normalizeAiSeedBusinessCategoryIds($payload);
        $category = !empty($businessCategoryIds)
            ? BusinessCategory::query()->find((int) $businessCategoryIds[0])
            : null;

        $file = $request->file('image');
        [$width, $height] = $this->imageDimensionsFromUpload($file);
        $usageType = $this->slugToken($payload['usage_type'] ?? 'product', 'product');
        $businessSlug = $this->slugToken($category?->slug ?: $category?->name ?: 'general', 'general');
        $categorySlug = $this->slugToken($payload['category_slug'] ?? '', 'general');
        $subcategorySlug = $this->slugToken($payload['subcategory_slug'] ?? '', 'all');
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg';
        $filename = 'seed_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $extension;
        $directory = "ai-seed-library/{$usageType}/{$businessSlug}/{$categorySlug}/{$subcategorySlug}";
        $path = $file->storeAs($directory, $filename, 'public');

        $rowPayload = [
            'business_category_id' => $category?->id,
            'business_category_name' => $category?->name,
            'category_slug' => $categorySlug,
            'subcategory_slug' => $subcategorySlug,
            'usage_type' => $usageType,
            'ratio_key' => trim((string) ($payload['ratio_key'] ?? '')),
            'width' => $width,
            'height' => $height,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'alt_text' => trim((string) ($payload['alt_text'] ?? '')),
            'tags' => trim((string) ($payload['tags'] ?? '')),
            'status' => array_key_exists('status', $payload) ? (bool) $payload['status'] : true,
        ];
        $this->setAiSeedBusinessCategoryIdsPayload($rowPayload, $businessCategoryIds);

        $row = AiSeedImageLibrary::query()->create($rowPayload);

        return response()->json([
            'message' => 'Seed image uploaded successfully.',
            'item' => $this->formatAiSeedImageLibraryRow($row),
        ], 201);
    }

    private function superadminStoreDefaultImageLibraryImportFromMediaPaths(array $payload, array $paths): JsonResponse
    {
        $businessCategoryIds = $this->normalizeAiSeedBusinessCategoryIds($payload);
        $category = !empty($businessCategoryIds)
            ? BusinessCategory::query()->find((int) $businessCategoryIds[0])
            : null;

        $items = [];
        $errors = [];
        foreach ($paths as $rawPath) {
            $cleanSource = $this->assertSuperadminMediaLibraryImagePath($rawPath);
            if ($cleanSource === null) {
                $errors[] = ['path' => $rawPath, 'message' => 'Invalid or inaccessible superadmin media library path.'];
                continue;
            }
            try {
                $row = $this->copySuperadminMediaLibraryIntoAiSeedLibrary($cleanSource, $payload, $category);
                $items[] = $this->formatAiSeedImageLibraryRow($row);
            } catch (\Throwable $e) {
                $errors[] = ['path' => $rawPath, 'message' => $e->getMessage()];
            }
        }

        $ok = count($items);
        $total = $ok + count($errors);
        if ($ok === 0) {
            return response()->json([
                'message' => 'No images could be imported from the media library.',
                'items' => [],
                'errors' => $errors,
            ], 422);
        }

        $message = $errors === []
            ? ($ok === 1 ? 'Seed image imported successfully.' : "Imported {$ok} seed images from the media library.")
            : "Imported {$ok} of {$total} image(s) from the media library.";

        $status = count($errors) === 0 ? 201 : 200;

        return response()->json([
            'message' => $message,
            'items' => $items,
            'errors' => $errors,
            'item' => $items[0] ?? null,
        ], $status);
    }

    private function assertSuperadminMediaLibraryImagePath(string $rawPath): ?string
    {
        $clean = $this->normalizeMediaLibraryDiskPath($rawPath);
        if ($clean === '' || str_contains($clean, '..')) {
            return null;
        }

        $base = trim($this->superAdminMediaLibraryDirectory(), '/');
        if ($clean === $base) {
            return null;
        }
        if (!str_starts_with($clean, $base . '/')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($clean)) {
            return null;
        }

        $fullPath = $disk->path($clean);
        if (!is_file($fullPath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($clean, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        return $clean;
    }

    private function imageDimensionsFromPublicDiskPath(string $cleanPath): array
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($cleanPath)) {
            return [null, null];
        }

        $fullPath = $disk->path($cleanPath);
        $size = @getimagesize($fullPath);
        if (!is_array($size)) {
            return [null, null];
        }

        return [(int) ($size[0] ?? 0), (int) ($size[1] ?? 0)];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function copySuperadminMediaLibraryIntoAiSeedLibrary(string $cleanSource, array $payload, ?BusinessCategory $category): AiSeedImageLibrary
    {
        $disk = Storage::disk('public');
        [$width, $height] = $this->imageDimensionsFromPublicDiskPath($cleanSource);
        $usageType = $this->slugToken($payload['usage_type'] ?? 'product', 'product');
        $businessSlug = $this->slugToken($category?->slug ?: $category?->name ?: 'general', 'general');
        $categorySlug = $this->slugToken($payload['category_slug'] ?? '', 'general');
        $subcategorySlug = $this->slugToken($payload['subcategory_slug'] ?? '', 'all');
        $extension = strtolower((string) pathinfo($cleanSource, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg';
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException('Unsupported image type.');
        }

        $filename = 'seed_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(8)) . '.' . $extension;
        $directory = "ai-seed-library/{$usageType}/{$businessSlug}/{$categorySlug}/{$subcategorySlug}";
        $relativeDest = trim($directory . '/' . $filename, '/');

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        if (!$disk->copy($cleanSource, $relativeDest)) {
            throw new \RuntimeException('Failed to copy file from media library.');
        }

        $originalName = basename($cleanSource);

        $rowPayload = [
            'business_category_id' => $category?->id,
            'business_category_name' => $category?->name,
            'category_slug' => $categorySlug,
            'subcategory_slug' => $subcategorySlug,
            'usage_type' => $usageType,
            'ratio_key' => trim((string) ($payload['ratio_key'] ?? '')),
            'width' => $width,
            'height' => $height,
            'path' => $relativeDest,
            'original_name' => $originalName,
            'alt_text' => trim((string) ($payload['alt_text'] ?? '')),
            'tags' => trim((string) ($payload['tags'] ?? '')),
            'status' => array_key_exists('status', $payload) ? (bool) $payload['status'] : true,
        ];
        $this->setAiSeedBusinessCategoryIdsPayload($rowPayload, $this->normalizeAiSeedBusinessCategoryIds($payload));

        return AiSeedImageLibrary::query()->create($rowPayload);
    }

    private function normalizeAiSeedBusinessCategoryIds(array $payload): array
    {
        $ids = [];
        foreach ((array) ($payload['business_category_ids'] ?? []) as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        $singleId = $payload['business_category_id'] ?? null;
        if (is_numeric($singleId) && (int) $singleId > 0) {
            array_unshift($ids, (int) $singleId);
        }

        return array_values(array_unique($ids));
    }

    private function setAiSeedBusinessCategoryIdsPayload(array &$payload, array $businessCategoryIds): void
    {
        if (Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
            $payload['business_category_ids'] = array_values(array_unique(array_filter(array_map('intval', $businessCategoryIds))));
        }
    }

    public function superadminStoreDefaultImageLibraryUpdate(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        $row = AiSeedImageLibrary::query()->find($id);
        if (!$row) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $payload = $request->validate([
            'business_category_id' => ['nullable', 'integer', 'exists:business_categories,id'],
            'business_category_ids' => ['nullable', 'array', 'max:30'],
            'business_category_ids.*' => ['integer', 'exists:business_categories,id'],
            'usage_type' => ['nullable', 'string', 'in:product,category,slider,banner'],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'subcategory_slug' => ['nullable', 'string', 'max:120'],
            'ratio_key' => ['nullable', 'string', 'max:20'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('business_category_id', $payload) || array_key_exists('business_category_ids', $payload)) {
            $businessCategoryIds = $this->normalizeAiSeedBusinessCategoryIds($payload);
            $category = !empty($businessCategoryIds) ? BusinessCategory::query()->find((int) $businessCategoryIds[0]) : null;
            $row->business_category_id = $category?->id;
            $row->business_category_name = $category?->name;
            if (Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
                $row->business_category_ids = $businessCategoryIds;
            }
        }

        if (array_key_exists('usage_type', $payload) && $payload['usage_type'] !== null && $payload['usage_type'] !== '') {
            $row->usage_type = (string) $payload['usage_type'];
        }

        if (array_key_exists('category_slug', $payload)) {
            $row->category_slug = $this->slugToken((string) ($payload['category_slug'] ?? ''), 'general');
        }

        if (array_key_exists('subcategory_slug', $payload)) {
            $row->subcategory_slug = $this->slugToken((string) ($payload['subcategory_slug'] ?? ''), 'all');
        }

        if (array_key_exists('ratio_key', $payload)) {
            $row->ratio_key = trim((string) ($payload['ratio_key'] ?? ''));
        }

        if (array_key_exists('alt_text', $payload)) {
            $row->alt_text = trim((string) ($payload['alt_text'] ?? ''));
        }

        if (array_key_exists('tags', $payload)) {
            $row->tags = trim((string) ($payload['tags'] ?? ''));
        }

        if (array_key_exists('status', $payload)) {
            $row->status = (bool) $payload['status'];
        }

        $row->save();

        return response()->json([
            'message' => 'Seed image updated.',
            'item' => $this->formatAiSeedImageLibraryRow($row->fresh()),
        ]);
    }

    public function superadminStoreDefaultImageLibraryDelete(Request $request, int $id): JsonResponse
    {
        if ($error = $this->ensureSuperadminClientAccess($request)) {
            return $error;
        }

        $row = AiSeedImageLibrary::query()->find($id);
        if (!$row) {
            return response()->json(['message' => 'Image not found.'], 404);
        }

        $path = trim((string) $row->path);
        if ($path !== '' && !Str::startsWith($path, ['../', '/'])) {
            Storage::disk('public')->delete($path);
        }
        $row->delete();

        return response()->json(['message' => 'Seed image deleted successfully.']);
    }

    private function aiSeedImageLibraryMeta(): array
    {
        $categories = BusinessCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->map(fn(BusinessCategory $row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'slug' => (string) ($row->slug ?? Str::slug((string) ($row->name ?? ''))),
                'parent_id' => $row->parent_id,
            ])->values();

        return [
            'usage_types' => [
                ['key' => 'product', 'label' => 'Product'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'slider', 'label' => 'Slider'],
                ['key' => 'banner', 'label' => 'Banner'],
            ],
            'ratios' => [
                ['key' => '1:1', 'label' => 'Square 1:1'],
                ['key' => '4:5', 'label' => 'Portrait 4:5'],
                ['key' => '3:4', 'label' => 'Portrait 3:4'],
                ['key' => '16:9', 'label' => 'Wide 16:9'],
                ['key' => '16:6', 'label' => 'Slider 16:6'],
            ],
            'business_categories' => $categories,
        ];
    }

    private function formatAiSeedImageLibraryRow(AiSeedImageLibrary $row): array
    {
        $path = trim((string) $row->path);
        $normalized = $path !== '' ? ltrim(str_replace('\\', '/', $path), '/') : '';
        $publicUrl = $normalized !== '' ? '/storage/' . $normalized : null;

        return [
            'id' => (int) $row->id,
            'business_category_id' => $row->business_category_id,
            'business_category_ids' => Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')
                ? array_values(array_filter(array_map('intval', (array) ($row->business_category_ids ?: []))))
                : ($row->business_category_id ? [(int) $row->business_category_id] : []),
            'business_category_name' => (string) ($row->business_category_name ?? ''),
            'category_slug' => (string) ($row->category_slug ?? ''),
            'subcategory_slug' => (string) ($row->subcategory_slug ?? ''),
            'usage_type' => (string) ($row->usage_type ?? 'product'),
            'ratio_key' => (string) ($row->ratio_key ?? ''),
            'width' => $row->width,
            'height' => $row->height,
            'path' => $path,
            'url' => $publicUrl,
            'original_name' => (string) ($row->original_name ?? ''),
            'alt_text' => (string) ($row->alt_text ?? ''),
            'tags' => (string) ($row->tags ?? ''),
            'status' => (bool) $row->status,
            'created_at' => optional($row->created_at)->toDateTimeString(),
        ];
    }

    private function imageDimensionsFromUpload($file): array
    {
        if (!$file || !$file->getRealPath()) {
            return [null, null];
        }

        $size = @getimagesize($file->getRealPath());
        if (!is_array($size)) {
            return [null, null];
        }

        return [(int) ($size[0] ?? 0), (int) ($size[1] ?? 0)];
    }

    private function slugToken(string $value, string $fallback): string
    {
        $slug = Str::slug(trim($value));
        return $slug !== '' ? $slug : $fallback;
    }

    public function storeCreateMeta(Request $request): JsonResponse
    {
        return response()->json([
            'store_subdomain' => env('STORE_SUB_DOMAIN', 'ebitans.com'),
        ]);
    }

    public function storeCreateAiMeta(Request $request): JsonResponse
    {
        $resourceCounts = [
            'header' => (int) DemoStoreData::where('type', 'header')->count(),
            'slider' => (int) DemoStoreData::where('type', 'slider')->count(),
            'banner' => (int) DemoStoreData::where('type', 'banner')->count(),
            'feature_category' => (int) DemoStoreData::where('type', 'category')->count(),
            'feature_product' => (int) DemoStoreData::where('type', 'product')->count(),
            'testimonial' => (int) Testimonial::count(),
            'footer' => (int) DemoStoreData::where('type', 'theme')->count(),
        ];

        return response()->json([
            'style_presets' => [
                ['key' => 'modern-clean', 'label' => 'Modern clean', 'description' => 'Balanced layout with a calm and trusted look.'],
                ['key' => 'bold-sales', 'label' => 'Bold sales', 'description' => 'High-contrast sections for offers, urgency, and conversion.'],
                ['key' => 'premium-brand', 'label' => 'Premium brand', 'description' => 'More spacious and polished for boutique presentation.'],
                ['key' => 'minimal-fast', 'label' => 'Minimal fast', 'description' => 'A simple structure for quick launch and easy browsing.'],
            ],
            'tone_presets' => [
                ['key' => 'friendly', 'label' => 'Friendly', 'description' => 'Approachable and welcoming storefront language.'],
                ['key' => 'professional', 'label' => 'Professional', 'description' => 'Structured and business-like presentation.'],
                ['key' => 'trendy', 'label' => 'Trendy', 'description' => 'Youthful and visually active sections.'],
                ['key' => 'trust-first', 'label' => 'Trust first', 'description' => 'More emphasis on clarity, proof, and credibility.'],
            ],
            'primary_goals' => [
                ['key' => 'conversion', 'label' => 'Boost conversion'],
                ['key' => 'branding', 'label' => 'Build brand value'],
                ['key' => 'catalog', 'label' => 'Show a large catalog clearly'],
                ['key' => 'fast-launch', 'label' => 'Launch as fast as possible'],
            ],
            'meta_focus' => [
                ['key' => 'seo', 'label' => 'SEO-ready structure'],
                ['key' => 'ads', 'label' => 'Paid ads landing clarity'],
                ['key' => 'social', 'label' => 'Social-first product discovery'],
                ['key' => 'trust', 'label' => 'Trust and repeat purchase'],
            ],
            'section_options' => [
                ['key' => 'header', 'label' => 'Header', 'description' => 'Top navigation and store identity area.', 'resource_count' => $resourceCounts['header'], 'default' => true],
                ['key' => 'slider', 'label' => 'Slider', 'description' => 'Main hero banner for first impression.', 'resource_count' => $resourceCounts['slider'], 'default' => true],
                ['key' => 'banner', 'label' => 'Banner', 'description' => 'Promo block for offers or featured products.', 'resource_count' => $resourceCounts['banner'], 'default' => true],
                ['key' => 'feature_category', 'label' => 'Feature categories', 'description' => 'Highlight important product groups early.', 'resource_count' => $resourceCounts['feature_category'], 'default' => true],
                ['key' => 'feature_product', 'label' => 'Feature products', 'description' => 'Push curated or high-margin products.', 'resource_count' => $resourceCounts['feature_product'], 'default' => true],
                ['key' => 'testimonial', 'label' => 'Testimonials', 'description' => 'Show social proof and customer trust.', 'resource_count' => $resourceCounts['testimonial'], 'default' => false],
                ['key' => 'footer', 'label' => 'Footer', 'description' => 'Support links and final trust area.', 'resource_count' => $resourceCounts['footer'], 'default' => true],
            ],
        ]);
    }

    public function storeCreateAiPreview(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'storeName' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer', 'exists:business_categories,id'],
            'currency' => ['nullable', 'integer', 'exists:currencies,id'],
            'launch_mode' => ['nullable', 'string', 'in:auto,ai'],
            'ai_preferences' => ['nullable', 'array'],
        ]);

        $blueprint = app(AiStoreSeedService::class)->preview(
            trim((string) $payload['storeName']),
            (int) $payload['type'],
            (int) ($payload['currency'] ?? 1),
            (string) ($payload['launch_mode'] ?? 'ai'),
            $payload['ai_preferences'] ?? null
        );

        $catalog = $blueprint['catalog_blueprint'] ?? [];
        return response()->json([
            'source' => (string) ($blueprint['source'] ?? 'static'),
            'blueprint' => $blueprint,
            'summary' => [
                'source' => (string) ($blueprint['source'] ?? 'static'),
                'style_profile' => (string) ($blueprint['style_profile'] ?? ''),
                'product_image_profile' => $blueprint['product_image_profile'] ?? null,
                'categories_count' => count((array) ($catalog['categories'] ?? [])),
                'subcategories_count' => count((array) ($catalog['subcategories'] ?? [])),
                'products_count' => count((array) ($catalog['products'] ?? [])),
                'slider_banners_count' => count((array) ($catalog['slider_banners'] ?? [])),
                'notes' => $blueprint['notes'] ?? null,
            ],
        ]);
    }

    public function storeNameAvailability(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));
        if ($name === '') {
            return response()->json([
                'available' => false,
                'message' => 'Enter a store name to check availability.',
            ]);
        }

        $exists = Store::where('name', $name)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists
                ? 'This store name is already in use. Choose a different name.'
                : 'This store name is available.',
        ]);
    }

    /**
     * Subdomain slug from business name (same helper as store creation).
     */
    public function suggestStoreSlug(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));
        if ($name === '') {
            return response()->json(['slug' => '']);
        }

        $slug = function_exists('generateSlug') ? generateSlug($name) : Str::slug($name);
        $slug = is_string($slug) ? strtolower(trim($slug, '-')) : '';

        if ($slug === '') {
            $slug = 'store-' . strtolower(Str::random(8));
        }

        return response()->json([
            'slug' => $slug,
        ]);
    }

    public function storeDomainAvailability(Request $request): JsonResponse
    {
        $slug = strtolower(trim((string) $request->query('slug', '')));
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return response()->json([
                'available' => false,
                'message' => 'Use lowercase letters, numbers, and single hyphens only.',
                'full_domain' => null,
            ]);
        }

        $full = $slug . '.' . env('STORE_SUB_DOMAIN');
        $slugTaken = Store::where('slug', $slug)->exists();
        $domainTaken = Domain::where('name', $full)->where('status', '!=', 'Rejected')->exists();
        $available = !$slugTaken && !$domainTaken;

        return response()->json([
            'available' => $available,
            'full_domain' => $full,
            'message' => $available
                ? 'This domain is available.'
                : ($slugTaken ? 'This domain slug is already used.' : 'This domain is already registered.'),
        ]);
    }

    public function createStore(Request $request)
    {
        $validated = $request->validate([
            'storeName' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer', 'exists:business_categories,id'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'integer', 'exists:currencies,id'],
            'package_type' => ['nullable', 'string', 'max:20'],
            'phone' => AdminContactValidation::phoneRules(false, 30),
        ]);

        $packageType = $validated['package_type'] ?? 'ecw';
        if ($packageType === '' || $packageType === null) {
            $packageType = 'ecw';
        }

        $merged = array_merge($request->request->all(), [
            'storeName' => $validated['storeName'],
            'type' => (string) $validated['type'],
            'slug' => $validated['slug'],
            'currency' => (string) $validated['currency'],
            'package_type' => $packageType,
            'phone' => $validated['phone'] ?? '',
        ]);

        $forward = $request->duplicate(null, $merged);
        $forward->headers->set('Accept', 'application/json');

        return $this->createStoreFromReactPayload($forward, $validated);
    }

    private function createStoreFromReactPayload(Request $request, array $validated): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $storeName = trim((string) ($validated['storeName'] ?? $request->input('storeName', '')));
        $slug = Str::slug((string) ($validated['slug'] ?? $request->input('slug', $storeName)));
        $currencyId = (int) ($validated['currency'] ?? $request->input('currency', 1));
        $typeId = (int) ($validated['type'] ?? $request->input('type', 0));
        $customDomain = trim((string) ($validated['custom_domain'] ?? $request->input('custom_domain', '')));
        $storeSubdomain = trim((string) env('STORE_SUB_DOMAIN', 'ebitans.com'), ". \t\n\r\0\x0B");
        $storeUrl = $storeSubdomain !== '' ? "{$slug}.{$storeSubdomain}" : $slug;

        if ($storeName === '' || $slug === '') {
            return response()->json(['message' => 'Store name and domain slug are required.'], 422);
        }

        $slugExists = Store::query()
            ->where('slug', $slug)
            ->orWhere('url', $storeUrl)
            ->exists();
        if ($slugExists) {
            return response()->json(['message' => 'This domain slug is already used.'], 422);
        }

        return DB::transaction(function () use ($request, $user, $storeName, $slug, $storeUrl, $currencyId, $typeId, $customDomain) {
            $trialDays = $this->superadminTrialPeriodDays();
            $trialPlanId = $this->currentWebsiteTrialPlanId();
            $trialStartsAt = Carbon::now();
            $trialExpiresAt = $trialStartsAt->copy()->addDays($trialDays);

            $customer = Customer::query()->firstOrNew(['uid' => (string) $user->id]);
            if (!$customer->exists) {
                if ($this->tableHasColumn('customers', 'name')) $customer->name = $user->name ?? $storeName;
                if ($this->tableHasColumn('customers', 'phone')) $customer->phone = $request->input('phone', $user->phone ?? '');
                if ($this->tableHasColumn('customers', 'template_id')) $customer->template_id = 1;
            }
            if ($this->tableHasColumn('customers', 'company_name')) $customer->company_name = $storeName;
            $currentCustomerPlanId = strtoupper(trim((string) ($customer->plan_id ?? '')));
            if ($trialPlanId && $this->tableHasColumn('customers', 'plan_id') && in_array($currentCustomerPlanId, ['', '0', 'NULL'], true)) {
                $customer->plan_id = $trialPlanId;
            }
            $currentCustomerPurchaseDate = strtoupper(trim((string) ($customer->purchase_date ?? '')));
            if ($this->tableHasColumn('customers', 'purchase_date') && in_array($currentCustomerPurchaseDate, ['', 'NULL'], true)) {
                $customer->purchase_date = $trialStartsAt->toDateString();
            }
            if ($this->tableHasColumn('customers', 'expiry_date')) {
                $customer->expiry_date = $trialExpiresAt->toDateString();
            }
            if ($this->tableHasColumn('customers', 'plan_status')) $customer->plan_status = 'active';
            $customer->save();

            $store = new Store();
            if ($this->tableHasColumn('stores', 'name')) $store->name = $storeName;
            if ($this->tableHasColumn('stores', 'slug')) $store->slug = $slug;
            if ($this->tableHasColumn('stores', 'url')) $store->url = $storeUrl;
            if ($this->tableHasColumn('stores', 'type')) $store->type = (string) $typeId;
            if ($this->tableHasColumn('stores', 'category_id')) $store->category_id = (string) $typeId;
            if ($this->tableHasColumn('stores', 'user_id')) $store->user_id = (string) $user->id;
            if ($this->tableHasColumn('stores', 'customer_id')) $store->customer_id = (string) $customer->id;
            if ($this->tableHasColumn('stores', 'status')) $store->status = 'active';
            if ($this->tableHasColumn('stores', 'store_status')) $store->store_status = 1;
            if ($this->tableHasColumn('stores', 'paid_registration')) $store->paid_registration = (int) ($user->paid_registration ?? 0);
            if ($this->tableHasColumn('stores', 'template_id')) $store->template_id = 1;
            if ($this->tableHasColumn('stores', 'currency')) $store->currency = $currencyId ?: 1;
            if ($trialPlanId && $this->tableHasColumn('stores', 'plan_id')) $store->plan_id = $trialPlanId;
            if ($this->tableHasColumn('stores', 'plan_status')) $store->plan_status = 'active';
            if ($this->tableHasColumn('stores', 'purchase_date')) $store->purchase_date = $trialStartsAt->toDateString();
            if ($this->tableHasColumn('stores', 'expiry_date')) $store->expiry_date = $trialExpiresAt->toDateString();
            if ($this->tableHasColumn('stores', 'auth_type')) $store->auth_type = 'EmailEasyOrder';
            $store->save();

            if ($this->tableHasColumn('customers', 'active_store')) {
                $customer->active_store = $store->id;
            }
            if ($this->tableHasColumn('customers', 'template_id') && empty($customer->template_id)) {
                $customer->template_id = 1;
            }
            $customer->save();

            if ($this->tableHasColumn('users', 'store_id')) $user->store_id = $store->id;
            if ($this->tableHasColumn('users', 'customer_id')) $user->customer_id = $customer->id;
            if ($this->tableHasColumn('users', 'domain')) $user->domain = $storeUrl;
            $user->save();

            if ($customDomain !== '') {
                $this->saveCreateStoreDomainRecord($customDomain, $store, $customer, $user);
            }

            $launchMode = trim((string) $request->input('launch_mode', 'auto')) ?: 'auto';
            $aiPreferences = $request->input('ai_preferences');
            if (is_string($aiPreferences)) {
                $decodedPreferences = json_decode($aiPreferences, true);
                $aiPreferences = is_array($decodedPreferences) ? $decodedPreferences : null;
            } elseif (!is_array($aiPreferences)) {
                $aiPreferences = null;
            }
            app(AiStoreSeedService::class)->seed($store, $customer, $user, $launchMode, $aiPreferences);
            $store->refresh();

            return response()->json([
                'status' => true,
                'message' => 'Store created successfully.',
                'redirect_url' => '/dashboard',
                'store' => [
                    'id' => $store->id,
                    'name' => $storeName,
                    'slug' => $slug,
                    'url' => $storeUrl,
                    'custom_domain' => $customDomain,
                    'template_id' => $store->template_id ?? null,
                    'trial_plan_id' => $trialPlanId,
                    'trial_period_days' => $trialDays,
                    'purchase_date' => $trialStartsAt->toDateString(),
                    'expiry_date' => $trialExpiresAt->toDateString(),
                ],
            ]);
        });
    }

    private function saveCreateStoreDomainRecord(string $domain, Store $store, Customer $customer, User $user): void
    {
        if (!Schema::hasTable('domains')) {
            return;
        }

        $domain = strtolower(trim(preg_replace('#^https?://#i', '', $domain)));
        $domain = preg_replace('#/.*$#', '', $domain);
        if ($domain === '') {
            return;
        }

        $row = Domain::query()->firstOrNew(['name' => $domain]);
        if ($this->tableHasColumn('domains', 'status') && empty($row->status)) $row->status = 'Pending';
        if ($this->tableHasColumn('domains', 'connect_status') && empty($row->connect_status)) $row->connect_status = 'Pending';
        if ($this->tableHasColumn('domains', 'uid')) $row->uid = $user->id;
        if ($this->tableHasColumn('domains', 'store_id')) $row->store_id = $store->id;
        if ($this->tableHasColumn('domains', 'customer_id')) $row->customer_id = $customer->id;
        if ($this->tableHasColumn('domains', 'email')) $row->email = $user->email ?? '';
        if ($this->tableHasColumn('domains', 'creator') && empty($row->creator)) $row->creator = $user->id;
        if ($this->tableHasColumn('domains', 'editor')) $row->editor = $user->id;
        $row->save();
    }

    /**
     * Set the authenticated user's active store (same session effect as StoreController::activestore)
     * and return the SPA dashboard URL.
     */
    public function activateStore(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $customer = Customer::where('uid', $user->id)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer record not found.'], 422);
        }

        $query = Store::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('customer_id', $customer->id);

        $store = $query->first();

        if (!$store) {
            return response()->json(['message' => 'Store not found or access denied.'], 404);
        }

        $customer->active_store = $store->id;
        $customer->save();

        $store->status = 'active';
        $store->save();

        return response()->json([
            'success' => true,
            /** Path only so the SPA (e.g. Vite) stays on the current origin, not APP_URL. */
            'redirect_url' => '/dashboard',
        ]);
    }

    public function ownerProfileShow(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $imageUrl = null;
        if (!empty($user->image)) {
            $img = trim((string) $user->image);
            if (Str::startsWith($img, ['http://', 'https://'])) {
                $imageUrl = $img;
            } elseif (Str::startsWith($img, 'storage/')) {
                $imageUrl = $request->getSchemeAndHttpHost() . '/' . $img;
            } else {
                $imageUrl = $request->getSchemeAndHttpHost() . '/assets/images/img/' . $img;
            }
        }

        return response()->json([
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'address' => (string) ($user->address ?? ''),
            'image_url' => $imageUrl,
            'gender' => $this->tableHasColumn('users', 'gender') ? (string) ($user->gender ?? '') : '',
            'age' => $this->tableHasColumn('users', 'age') ? (string) ($user->age ?? '') : '',
            'verification_type' => $this->tableHasColumn('users', 'verification_type') ? (string) ($user->verification_type ?? '') : '',
            'identification_number' => $this->tableHasColumn('users', 'identification_number') ? (string) ($user->identification_number ?? '') : '',
            'voter_id_image_url' => $this->tableHasColumn('users', 'voter_id_image') && !empty($user->voter_id_image)
                ? $request->getSchemeAndHttpHost() . '/assets/images/img/' . $user->voter_id_image
                : null,
        ]);
    }

    public function ownerProfileUpdate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => AdminContactValidation::emailRules(true, 100),
            'phone' => AdminContactValidation::phoneRules(false, 30),
            'address' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'voter_id_image' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'voter_id_image_path' => ['nullable', 'string', 'max:500'],
        ];
        if ($this->tableHasColumn('users', 'gender')) {
            $rules['gender'] = ['nullable', 'string', 'in:male,female,other,'];
        }
        if ($this->tableHasColumn('users', 'age')) {
            $rules['age'] = ['nullable', 'string', 'max:10'];
        }
        if ($this->tableHasColumn('users', 'verification_type')) {
            $rules['verification_type'] = ['nullable', 'string', 'max:50'];
        }
        if ($this->tableHasColumn('users', 'identification_number')) {
            $rules['identification_number'] = ['nullable', 'string', 'max:100'];
        }

        $payload = $request->validate($rules);

        $user->name = $payload['name'];
        $user->email = $payload['email'];
        if ($this->tableHasColumn('users', 'phone')) {
            $user->phone = $payload['phone'] ?? null;
        }
        if ($this->tableHasColumn('users', 'address')) {
            $user->address = $payload['address'] ?? null;
        }
        if ($this->tableHasColumn('users', 'gender') && array_key_exists('gender', $payload)) {
            $user->gender = $payload['gender'] ?? null;
        }
        if ($this->tableHasColumn('users', 'age') && array_key_exists('age', $payload)) {
            $user->age = $payload['age'] ?? null;
        }
        if ($this->tableHasColumn('users', 'verification_type') && array_key_exists('verification_type', $payload)) {
            $user->verification_type = $payload['verification_type'] ?? null;
        }
        if ($this->tableHasColumn('users', 'identification_number') && array_key_exists('identification_number', $payload)) {
            $user->identification_number = $payload['identification_number'] ?? null;
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imgName = now()->timestamp . 'U.' . $file->extension();
            $file->storeAs('img', $imgName);
            $user->image = $imgName;
        } elseif (!empty($payload['image_path'])) {
            $user->image = basename(trim((string) $payload['image_path']));
        }

        if ($this->tableHasColumn('users', 'voter_id_image')) {
            if ($request->hasFile('voter_id_image')) {
                $file = $request->file('voter_id_image');
                $imgName = now()->timestamp . 'VID.' . $file->extension();
                $file->storeAs('img', $imgName);
                $user->voter_id_image = $imgName;
            } elseif (!empty($payload['voter_id_image_path'])) {
                $user->voter_id_image = basename(trim((string) $payload['voter_id_image_path']));
            }
        }

        $user->save();

        return response()->json(['message' => 'Profile updated successfully.']);
    }

    public function websiteSettings(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $store->loadMissing(['plan', 'current_currency']);

        $categoryRows = BusinessCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $categoryMap = $categoryRows->keyBy('id');
        $storeTypeRaw = trim((string) ($store->type ?? ''));
        $businessTypeId = '';
        $businessTypeName = '';
        if ($storeTypeRaw !== '' && ctype_digit($storeTypeRaw)) {
            $businessTypeId = $storeTypeRaw;
            $businessTypeName = (string) ($categoryMap->get((int) $storeTypeRaw)?->name ?? '');
        } elseif ($storeTypeRaw !== '') {
            $matched = $categoryRows->first(function ($row) use ($storeTypeRaw) {
                return strcasecmp((string) ($row->name ?? ''), $storeTypeRaw) === 0;
            });
            if ($matched) {
                $businessTypeId = (string) $matched->id;
                $businessTypeName = (string) ($matched->name ?? '');
            } else {
                $businessTypeName = $storeTypeRaw;
            }
        }

        $currencyRows = DB::table('currencies')->orderBy('code')->get(['id', 'code', 'country', 'symbol']);
        $currencyId = (string) ($store->currency ?? '');
        $currencyName = trim((string) (($store->current_currency?->code ?? '') . ' ' . ($store->current_currency?->symbol ?? '')));
        if ($currencyName === '' && $currencyId !== '') {
            $matchedCurrency = $currencyRows->firstWhere('id', (int) $currencyId);
            if ($matchedCurrency) {
                $currencyName = trim((string) (($matchedCurrency->code ?? '') . ' ' . ($matchedCurrency->symbol ?? '')));
            }
        }

        $header = Headersetting::query()
            ->where('store_id', (string) $store->id)
            ->latest('id')
            ->first();

        return response()->json([
            'settings' => [
                'business_name' => (string) ($store->name ?? ''),
                'short_description' => (string) ($header?->short_description ?? ''),
                'business_type' => $businessTypeName,
                'business_type_id' => $businessTypeId,
                'active_plan' => (string) ($store->plan?->name ?? $store->plan_id ?? ''),
                'active_plan_id' => (string) ($store->plan_id ?? ''),
                'store_currency' => $currencyName,
                'currency_id' => $currencyId,
                'phone' => (string) ($header?->phone ?? ''),
                'email' => (string) ($header?->email ?? ''),
                'address' => (string) ($header?->address ?? ''),
                'custom_writing' => (string) ($header?->custom_writing ?? ''),
                'tax' => (string) ($header?->tax ?? ''),
                'registration_mode' => $this->registrationModeFromAuthType((string) ($store->auth_type ?? 'phone')),
                'logo_url' => $this->resolveSettingAssetPublicUrl($header?->logo, 'assets/images/setting'),
                'favicon_url' => $this->resolveSettingAssetPublicUrl($header?->favicon, 'assets/images/setting/favicon'),
                'social_links' => [
                    'facebook' => (string) ($header?->facebook_link ?? ''),
                    'instagram' => (string) ($header?->instagram_link ?? ''),
                    'whatsapp' => (string) ($header?->whatsapp_phone ?? ''),
                    'linkedin' => (string) ($header?->lined_in_link ?? ''),
                    'pinterest' => (string) ($header?->pinterest_link ?? ''),
                    'twitter' => (string) ($header?->twitter_link ?? ''),
                    'tiktok' => (string) ($header?->tiktok_link ?? ''),
                    'map' => (string) ($header?->map_address ?? ''),
                    'website' => (string) ($store->url ?? ''),
                ],
                'shipping_methods' => $this->normalizeShippingMethods($header?->shipping_methods),
                'business_type_options' => $categoryRows
                    ->map(fn($row) => [
                        'id' => (string) $row->id,
                        'label' => (string) ($row->name ?? ''),
                    ])->values()->all(),
                'currency_options' => collect($currencyRows)
                    ->map(fn($row) => [
                        'id' => (string) $row->id,
                        'label' => trim((string) (($row->code ?? '') . ' ' . ($row->symbol ?? ''))) . (!empty($row->country) ? ' - ' . (string) $row->country : ''),
                    ])->values()->all(),
            ],
        ]);
    }

    public function domainSettings(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];

        $rows = Domain::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->orderBy('id', 'DESC')
            ->get(['id', 'name', 'status', 'created_at']);

        $activeDomain = trim((string) ($store->url ?? ''));

        return response()->json([
            'name_servers' => [
                'primary' => (string) env('DOMAIN_NS1', 'ns1.ebitans.com'),
                'secondary' => (string) env('DOMAIN_NS2', 'ns2.ebitans.com'),
            ],
            'domains' => $rows->map(function (Domain $row) use ($activeDomain) {
                $name = trim((string) ($row->name ?? ''));
                return [
                    'id' => (int) $row->id,
                    'name' => $name,
                    'status' => (string) ($row->status ?? 'Requested'),
                    'is_active' => $name !== '' && strcasecmp($name, $activeDomain) === 0,
                    'created_at' => optional($row->created_at)->toDateTimeString(),
                ];
            })->values(),
        ]);
    }

    public function paymentPackages(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $user = $ctx['user'];

        $store->loadMissing(['plan', 'current_currency']);
        $currencyCode = strtoupper(trim((string) ($store->current_currency?->code ?? 'BDT')));

        $plansQuery = Plan::query()
            ->with('details')
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('position', 'ASC')
            ->orderBy('id', 'ASC');

        if (($user->type ?? '') === 'dropshipper') {
            $plansQuery->whereIn('id', [8]);
        } else {
            $plansQuery->whereNotIn('id', [8, 9]);
        }

        $currentPlanId = (int) ($store->plan_id ?? 0);
        $packages = $plansQuery->get()->map(function (Plan $plan) use ($currencyCode, $currentPlanId) {
            $featureSource = $plan->details;
            $activeFeatures = $featureSource->where('status', true)->pluck('title')->filter();
            $featureTitles = ($activeFeatures->count() > 0 ? $activeFeatures : $featureSource->pluck('title'))
                ->map(fn($title) => trim((string) $title))
                ->filter()
                ->values();

            $quickFacts = collect([
                !empty($plan->category) ? ((int) $plan->category) . ' categories' : null,
                !empty($plan->product) ? ((int) $plan->product) . ' products' : null,
                !empty($plan->staff) ? ((int) $plan->staff) . ' staffs' : null,
                !empty($plan->order) ? ((int) $plan->order) . ' orders' : null,
            ])->filter()->values();

            $monthlyPrice = (float) ($plan->price ?? 0);
            $yearlyBase = $monthlyPrice * 12;
            $yearlyDiscount = (float) ($plan->twelvedis ?? 0);
            $discountType = strtolower(trim((string) ($plan->discount_type ?? '')));
            $yearlyPrice = $yearlyBase;
            if ($yearlyDiscount > 0) {
                if (in_array($discountType, ['percent', 'percentage'], true)) {
                    $yearlyPrice = max(0, $yearlyBase - (($yearlyBase * $yearlyDiscount) / 100));
                } else {
                    $yearlyPrice = max(0, $yearlyBase - $yearlyDiscount);
                }
            }
            $yearlySaveText = '';
            if ($yearlyBase > 0 && $yearlyPrice < $yearlyBase) {
                $yearlySaveText = 'Save ' . $currencyCode . ' ' . number_format($yearlyBase - $yearlyPrice, 0);
            }
            $isCurrent = $currentPlanId > 0 && $currentPlanId === (int) $plan->id;

            return [
                'id' => (int) $plan->id,
                'name' => (string) ($plan->name ?? ''),
                'subtitle' => (string) ($plan->subtitle ?? ''),
                'currency_code' => $currencyCode,
                'price_monthly' => $monthlyPrice,
                'price_yearly' => (float) $yearlyPrice,
                'yearly_save_text' => $yearlySaveText,
                'price_label' => $monthlyPrice > 0
                    ? $currencyCode . ' ' . number_format($monthlyPrice, 0) . ' / month'
                    : 'Contact sales',
                'best_for' => !empty($plan->subtitle) ? (string) $plan->subtitle : 'Best for growing stores',
                'quick_facts' => $quickFacts->all(),
                'features' => $featureTitles->all(),
                'is_current' => $isCurrent,
                'cta' => $isCurrent ? 'Current package' : 'Choose package',
                'checkout_url' => url('/admin/payment/packages/' . $plan->id),
            ];
        })->values();

        return response()->json([
            'current_plan_id' => $currentPlanId,
            'current_plan_name' => (string) ($store->plan?->name ?? ''),
            'packages' => $packages,
        ]);
    }

    public function paymentAddons(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $user = $ctx['user'];

        $store->loadMissing(['current_currency']);
        $currencyCode = strtoupper(trim((string) ($store->current_currency?->code ?? 'BDT')));
        $currencyType = $currencyCode === 'USD' ? 'USD' : 'BDT';

        $addons = AddonsApi::query()
            ->where('status', 1)
            ->orderBy('position', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->map(function (AddonsApi $row) use ($currencyType) {
                $titleRaw = $row->title ?? '';
                $headingRaw = $row->heading ?? '';
                $nameRaw = $row->name ?? '';
                $typeRaw = $row->type ?? '';

                $title = $this->extractAddonText($titleRaw);
                $heading = $this->extractAddonText($headingRaw);
                $type = strtolower(trim($this->extractAddonText($typeRaw)));

                $offerSource = $currencyType === 'USD' ? ($row->usd_offer_price ?? null) : ($row->offerprice ?? null);
                $priceSource = $currencyType === 'USD' ? ($row->usd_price ?? null) : ($row->price ?? null);

                $offerPrice = $this->extractAddonNumber($offerSource);
                $basePrice = $this->extractAddonNumber($priceSource);

                $nameOptions = $this->normalizeAddonVector($nameRaw);
                $monthOrValueOptions = $this->normalizeAddonVector($row->monthorvalue ?? []);
                $priceOptions = $this->normalizeAddonVector($row->price ?? []);
                $offerPriceOptions = $this->normalizeAddonVector($row->offerprice ?? []);
                $usdPriceOptions = $this->normalizeAddonVector($row->usd_price ?? []);
                $usdOfferPriceOptions = $this->normalizeAddonVector($row->usd_offer_price ?? []);

                $maxOptions = max(
                    count($nameOptions),
                    count($monthOrValueOptions),
                    count($priceOptions),
                    count($offerPriceOptions),
                    count($usdPriceOptions),
                    count($usdOfferPriceOptions),
                    1
                );

                $options = collect(range(0, $maxOptions - 1))->map(function ($index) use (
                    $nameOptions,
                    $monthOrValueOptions,
                    $priceOptions,
                    $offerPriceOptions,
                    $usdPriceOptions,
                    $usdOfferPriceOptions
                ) {
                    $price = $this->extractAddonNumber($priceOptions[$index] ?? ($priceOptions[0] ?? 0));
                    $offer = $this->extractAddonNumber($offerPriceOptions[$index] ?? ($offerPriceOptions[0] ?? $price));
                    $usdPrice = $this->extractAddonNumber($usdPriceOptions[$index] ?? ($usdPriceOptions[0] ?? 0));
                    $usdOffer = $this->extractAddonNumber($usdOfferPriceOptions[$index] ?? ($usdOfferPriceOptions[0] ?? $usdPrice));
                    return [
                        'index' => (int) $index,
                        'name' => trim((string) ($nameOptions[$index] ?? ($nameOptions[0] ?? ''))),
                        'month_or_value' => trim((string) ($monthOrValueOptions[$index] ?? ($monthOrValueOptions[0] ?? ''))),
                        'price' => (float) $price,
                        'offer_price' => (float) ($offer > 0 ? $offer : $price),
                        'usd_price' => (float) $usdPrice,
                        'usd_offer_price' => (float) ($usdOffer > 0 ? $usdOffer : $usdPrice),
                    ];
                })->values();

                $discountValue = max(0, $basePrice - $offerPrice);
                $discountPercent = $basePrice > 0 ? (int) round(($discountValue / $basePrice) * 100) : 0;

                return [
                    'id' => (int) $row->id,
                    'title' => $title !== '' ? $title : ($heading !== '' ? $heading : 'Addon'),
                    'subtitle' => $heading !== '' ? $heading : null,
                    'type' => $type !== '' ? $type : 'addon',
                    'price' => (float) $basePrice,
                    'offer_price' => (float) ($offerPrice > 0 ? $offerPrice : $basePrice),
                    'discount_percent' => $discountPercent,
                    'image_url' => $this->resolveAddonImagePublicUrl($row->image),
                    'raw_name' => $nameRaw,
                    'options' => $options,
                ];
            })
            ->values();

        $dueOrders = AddonsOrder::with(['paymentHistories.creator'])
            ->where('store_id', $store->id)
            ->whereIn('status', ['Complete', 'Processing'])
            ->where('due_amount', '>', 0)
            ->orderBy('updated_at', 'DESC')
            ->get()
            ->map(function (AddonsOrder $order) {
                return [
                    'id' => (int) $order->id,
                    'order_no' => (string) ($order->order_no ?? ''),
                    'total' => (float) ($order->total ?? 0),
                    'paid_amount' => (float) ($order->paid_amount ?? 0),
                    'due_amount' => (float) ($order->due_amount ?? 0),
                    'due_amount_status' => (string) ($order->due_amount_status ?? ''),
                    'payment_method' => (string) ($order->payment_method ?? ''),
                    'payment_number' => (string) ($order->payment_number ?? ''),
                    'transaction_id' => (string) ($order->transaction_id ?? ''),
                    'bank_name' => (string) ($order->bank_name ?? ''),
                    'account_number' => (string) ($order->account_number ?? ''),
                    'updated_at' => optional($order->updated_at)->toDateTimeString(),
                    'payment_histories' => $order->paymentHistories->map(function ($h) {
                        return [
                            'id' => (int) $h->id,
                            'due_amount_status' => (string) ($h->due_amount_status ?? ''),
                            'created_at' => optional($h->created_at)->toDateTimeString(),
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json([
            'currency_code' => $currencyType,
            'addons' => $addons,
            'user_type' => (string) ($user->type ?? ''),
            'due_orders' => $dueOrders,
        ]);
    }

    public function paymentDomainSearch(Request $request, DomainNameApiService $domainApi): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $payload = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $domain = $domainApi->normalizeDomain((string) $payload['domain']);
        if (!$domainApi->isValidDomain($domain)) {
            return response()->json(['message' => 'Please enter a valid domain name.'], 422);
        }

        $tld = $domainApi->tldFromDomain($domain);
        $exists = Domain::query()
            ->whereRaw('LOWER(name) = ?', [$domain])
            ->where('status', '!=', 'Rejected')
            ->exists();

        if ($exists) {
            return response()->json([
                'domain' => $domain,
                'tld' => $tld,
                'available' => false,
                'configured' => true,
                'price' => $domainApi->priceFor($tld),
                'message' => 'This domain is already in use.',
            ]);
        }

        $result = $domainApi->checkAvailability($domain);

        return response()->json([
            'domain' => $domain,
            'tld' => $tld,
            'available' => (bool) ($result['available'] ?? false),
            'configured' => (bool) ($result['configured'] ?? false),
            'price' => $domainApi->priceFor($tld),
            'message' => (string) ($result['message'] ?? ''),
            'source' => (string) ($result['source'] ?? 'domainnameapi'),
        ]);
    }

    public function paymentHistory(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];

        $addonsOrders = AddonsOrder::query()
            ->select('addons_orders.*', 'plans.name as plan_name', 'currencies.code as currency_code')
            ->leftJoin('plans', 'addons_orders.plan_id', '=', 'plans.id')
            ->leftJoin('currencies', 'currencies.id', '=', 'addons_orders.currency_id')
            ->where('addons_orders.store_id', (int) $store->id)
            ->orderByDesc('addons_orders.id')
            ->get()
            ->map(function ($row) {
                $currencyCode = strtoupper(trim((string) ($row->currency_code ?? 'BDT')));
                $statusRaw = trim((string) ($row->status ?? 'Processing'));
                $status = $statusRaw !== '' ? $statusRaw : 'Processing';
                return [
                    'id' => (int) $row->id,
                    'source' => 'addons',
                    'order_no' => (string) ($row->order_no ?? ('EBI-' . $row->id)),
                    'package_name' => (string) ($row->plan_name ?? 'Package'),
                    'total' => (float) ($row->total ?? 0),
                    'paid_amount' => (float) ($row->paid_amount ?? 0),
                    'due_amount' => (float) ($row->due_amount ?? 0),
                    'currency_code' => $currencyCode,
                    'payment_method' => (string) ($row->payment_method ?? ''),
                    'status' => $status,
                    'created_at' => optional($row->created_at)->toDateTimeString(),
                    'invoice_url' => route('payment.payments.invoice', ['id' => (int) $row->id]),
                ];
            });

        $modulusPayments = ModulusPayment::query()
            ->with('module')
            ->where('store_id', (int) $store->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (ModulusPayment $row) {
                $statusRaw = trim((string) ($row->status ?? ''));
                if ($statusRaw === '') {
                    $status = 'Processing';
                } elseif (is_numeric($statusRaw)) {
                    $status = ((int) $statusRaw === 1) ? 'Complete' : 'Failed';
                } else {
                    $lower = strtolower($statusRaw);
                    if (str_contains($lower, 'complete') || str_contains($lower, 'success')) {
                        $status = 'Complete';
                    } elseif (str_contains($lower, 'fail')) {
                        $status = 'Failed';
                    } else {
                        $status = 'Processing';
                    }
                }

                $price = (float) ($row->price ?? 0);
                return [
                    'id' => (int) $row->id,
                    'source' => 'modulus',
                    'order_no' => (string) ($row->order_no ?? ('PM-' . $row->id)),
                    'package_name' => (string) ($row->module?->name ?? $row->plan_name ?? 'Package'),
                    'total' => $price,
                    'paid_amount' => $price,
                    'due_amount' => (float) ($row->due_amount ?? 0),
                    'currency_code' => 'BDT',
                    'payment_method' => (string) ($row->payment_type ?? $row->payment_method ?? ''),
                    'status' => $status,
                    'created_at' => optional($row->created_at)->toDateTimeString(),
                    'invoice_url' => null,
                ];
            });

        $orders = $addonsOrders
            ->concat($modulusPayments)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function accountModulusList(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $storeId = (int) ($store->id ?? 0);
        $isTrialPlan = $this->isWebsiteTrialPlanId($store->plan_id ?? null);
        $trialAllowedModules = [107, 111, 114];
        $headerSetting = Headersetting::query()
            ->where('store_id', $storeId)
            ->first(['bkash', 'merchant_bkash', 'merchant_nagad']);

        $modules = Modulus::query()
            ->select(
                'moduluses.id',
                'moduluses.name',
                'moduluses.title',
                'moduluses.image',
                'moduluses.config_status',
                'moduluses.price',
                'moduluses.price_usd',
                'moduluses.modulus_type'
            )
            ->selectRaw('(CASE WHEN moduluses.price > 0 THEN 1 ELSE 0 END) AS priceStatus')
            ->selectRaw('moduluses.status AS modulusesStatus')
            ->selectRaw("COALESCE((SELECT MAX(modulus_payments.status) FROM modulus_payments WHERE modulus_payments.modulus_id = moduluses.id AND modulus_payments.store_id = ?), 0) AS paymentStatus", [$storeId])
            ->where('moduluses.status', 1)
            ->where('moduluses.modulus_type', 0)
            ->orderBy('moduluses.position', 'ASC')
            ->get()
            ->map(function ($row) use ($storeId, $isTrialPlan, $trialAllowedModules, $headerSetting) {
                $buyModulus = BuyModulus::query()
                    ->where('store_id', $storeId)
                    ->where('modulus_id', (int) $row->id)
                    ->first();
                $pendingPayment = ModulusPayment::query()
                    ->where('store_id', $storeId)
                    ->where('modulus_id', (int) $row->id)
                    ->whereNull('status')
                    ->latest('id')
                    ->first();

                $toggleEnabled = (int) ($buyModulus->status ?? 0) === 1;
                $paymentApproved = (int) ($row->paymentStatus ?? 0) === 1;
                $isFree = (int) ($row->priceStatus ?? 0) !== 1;
                $canUseInTrial = !$isTrialPlan || in_array((int) $row->id, $trialAllowedModules, true);
                $advanceBlocked = (int) $row->id === 106
                    && $paymentApproved
                    && (($headerSetting->bkash ?? '') !== 'active')
                    && (($headerSetting->merchant_bkash ?? '') !== 'active')
                    && (($headerSetting->merchant_nagad ?? '') !== 'active');
                $hasToggle = $paymentApproved || $isFree;
                if ((int) $row->id === 133) {
                    $hasToggle = false;
                }
                $requiresPayment = !$isFree;
                $showBuyModal = ($requiresPayment && !$paymentApproved) || ((int) $row->id === 104);

                $isActive = $isFree ? $toggleEnabled : ($paymentApproved && $toggleEnabled);

                return [
                    'id' => (int) $row->id,
                    'name' => trim((string) ($row->name ?? 'Modulus')),
                    'title' => trim(strip_tags((string) ($row->title ?? ''))),
                    'image_url' => !empty($row->image) ? url('modulus/' . ltrim((string) $row->image, '/')) : null,
                    'price' => (float) ($row->price ?? 0),
                    'price_usd' => (float) ($row->price_usd ?? 0),
                    'price_status' => (int) ($row->priceStatus ?? 0) === 1,
                    'payment_status' => $paymentApproved,
                    'is_active' => $isActive,
                    'status_label' => $isActive ? 'Active' : 'Inactive',
                    'config_status' => (int) ($row->config_status ?? 0) === 1,
                    'config_url' => url('/react-admin-api/account/modulus/' . (int) $row->id . '/config'),
                    'store_id' => $storeId,
                    'has_toggle' => $hasToggle,
                    'show_buy_modal' => $showBuyModal,
                    'can_use_in_trial' => $canUseInTrial,
                    'is_trial_plan' => $isTrialPlan,
                    'advance_blocked' => $advanceBlocked,
                    'has_pending_payment' => $pendingPayment !== null,
                    'external_url' => (int) $row->id === 133 ? 'https://ieditor.ebitans.com' : null,
                    'special_requires_product_count' => (int) $row->id === 121,
                ];
            })
            ->values();

        return response()->json([
            'modules' => $modules,
            'trial_allowed_modules' => $trialAllowedModules,
            'is_trial_plan' => $isTrialPlan,
            'upgrade_url' => '/account/payment/packages',
        ]);
    }

    public function accountMarketingModulusList(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $storeId = (int) ($store->id ?? 0);
        $isTrialPlan = $this->isWebsiteTrialPlanId($store->plan_id ?? null);
        $trialAllowedModules = [107, 111, 114];
        $headerSetting = Headersetting::query()
            ->where('store_id', $storeId)
            ->first(['bkash', 'merchant_bkash', 'merchant_nagad']);

        $modules = Modulus::query()
            ->select(
                'moduluses.id',
                'moduluses.name',
                'moduluses.title',
                'moduluses.image',
                'moduluses.config_status',
                'moduluses.price',
                'moduluses.price_usd',
                'moduluses.modulus_type'
            )
            ->selectRaw('(CASE WHEN moduluses.price > 0 THEN 1 ELSE 0 END) AS priceStatus')
            ->selectRaw('moduluses.status AS modulusesStatus')
            ->selectRaw("COALESCE((SELECT MAX(modulus_payments.status) FROM modulus_payments WHERE modulus_payments.modulus_id = moduluses.id AND modulus_payments.store_id = ?), 0) AS paymentStatus", [$storeId])
            ->where('moduluses.status', 1)
            ->where('moduluses.modulus_type', 1)
            ->orderBy('moduluses.position', 'ASC')
            ->get()
            ->map(function ($row) use ($storeId, $isTrialPlan, $trialAllowedModules, $headerSetting) {
                $buyModulus = BuyModulus::query()
                    ->where('store_id', $storeId)
                    ->where('modulus_id', (int) $row->id)
                    ->first();
                $pendingPayment = ModulusPayment::query()
                    ->where('store_id', $storeId)
                    ->where('modulus_id', (int) $row->id)
                    ->whereNull('status')
                    ->latest('id')
                    ->first();

                $toggleEnabled = (int) ($buyModulus->status ?? 0) === 1;
                $paymentApproved = (int) ($row->paymentStatus ?? 0) === 1;
                $isFree = (int) ($row->priceStatus ?? 0) !== 1;
                $canUseInTrial = !$isTrialPlan || in_array((int) $row->id, $trialAllowedModules, true);
                $advanceBlocked = (int) $row->id === 106
                    && $paymentApproved
                    && (($headerSetting->bkash ?? '') !== 'active')
                    && (($headerSetting->merchant_bkash ?? '') !== 'active')
                    && (($headerSetting->merchant_nagad ?? '') !== 'active');
                $hasToggle = $paymentApproved || $isFree;
                if ((int) $row->id === 133) {
                    $hasToggle = false;
                }
                $requiresPayment = !$isFree;
                $showBuyModal = ($requiresPayment && !$paymentApproved) || ((int) $row->id === 104);

                $isActive = $isFree ? $toggleEnabled : ($paymentApproved && $toggleEnabled);

                return [
                    'id' => (int) $row->id,
                    'name' => trim((string) ($row->name ?? 'Modulus')),
                    'title' => trim(strip_tags((string) ($row->title ?? ''))),
                    'image_url' => !empty($row->image) ? url('modulus/' . ltrim((string) $row->image, '/')) : null,
                    'price' => (float) ($row->price ?? 0),
                    'price_usd' => (float) ($row->price_usd ?? 0),
                    'price_status' => (int) ($row->priceStatus ?? 0) === 1,
                    'payment_status' => $paymentApproved,
                    'is_active' => $isActive,
                    'status_label' => $isActive ? 'Active' : 'Inactive',
                    'config_status' => (int) ($row->config_status ?? 0) === 1,
                    'config_url' => url('/react-admin-api/account/modulus/' . (int) $row->id . '/config'),
                    'store_id' => $storeId,
                    'has_toggle' => $hasToggle,
                    'show_buy_modal' => $showBuyModal,
                    'can_use_in_trial' => $canUseInTrial,
                    'is_trial_plan' => $isTrialPlan,
                    'advance_blocked' => $advanceBlocked,
                    'has_pending_payment' => $pendingPayment !== null,
                    'external_url' => (int) $row->id === 133 ? 'https://ieditor.ebitans.com' : null,
                    'special_requires_product_count' => (int) $row->id === 121,
                ];
            })
            ->values();

        return response()->json([
            'modules' => $modules,
            'trial_allowed_modules' => $trialAllowedModules,
            'is_trial_plan' => $isTrialPlan,
            'upgrade_url' => '/account/payment/packages',
        ]);
    }

    public function accountModulusToggle(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $storeId = (int) ($store->id ?? 0);

        $payload = $request->validate([
            'modulus_id' => ['required', 'integer', 'min:1'],
        ]);

        $modulusId = (int) $payload['modulus_id'];

        $isTrialPlan = $this->isWebsiteTrialPlanId($store->plan_id ?? null);
        $ALLOW_TRIAL_MODULUS_IDS = [107, 111, 114];
        if ($isTrialPlan && !in_array($modulusId, $ALLOW_TRIAL_MODULUS_IDS, true)) {
            return response()->json([
                'status' => false,
                'message' => 'Trial period এ এই module ব্যবহার করা যাবে না.',
            ], 403);
        }

        // Variant module (114) cannot be disabled if variants already used.
        if ($modulusId === 114) {
            $productIds = Product::query()
                ->where('store_id', $storeId)
                ->pluck('id')
                ->toArray();

            $veriants = Veriant::query()->whereIn('pid', $productIds)->get();

            $moduleStatus = BuyModulus::query()
                ->where('modulus_id', $modulusId)
                ->where('store_id', $storeId)
                ->first();

            $statusChange = false;
            if (is_null($moduleStatus)) {
                $statusChange = true;
            } elseif (isset($moduleStatus->status) && (int) $moduleStatus->status === 0) {
                $statusChange = true;
            }

            if ($veriants->count() === 0 || $statusChange) {
                $buyModulus = BuyModulus::query()->firstOrNew([
                    'modulus_id' => $modulusId,
                    'store_id' => $storeId,
                ]);
                $buyModulus->status = !$buyModulus->status;
                $buyModulus->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Status changed successfully',
                    'data' => $buyModulus,
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'You can not on/off Variant Modulus. If you set variant any product',
            ], 422);
        }

        $buyModulus = BuyModulus::query()->firstOrNew([
            'modulus_id' => $modulusId,
            'store_id' => $storeId,
        ]);
        $buyModulus->status = !$buyModulus->status;
        $buyModulus->save();

        return response()->json([
            'status' => true,
            'message' => 'Status changed successfully',
            'data' => $buyModulus,
        ]);
    }

    public function accountModulusConfig(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];
        $storeId = (int) ($store->id ?? 0);
        $modulusId = (int) $id;
        $supportedIds = [5, 6, 7, 10, 11, 106, 108, 112, 113, 119, 126, 127, 128, 129, 132];

        // Native modal config support (no iframe fallback).
        if (!in_array($modulusId, $supportedIds, true)) {
            return response()->json(['message' => 'Config form is not available for this module yet.'], 422);
        }

        $type = match ($modulusId) {
            5 => 'facebook',
            6 => 'google',
            7 => 'messenger',
            10 => 'Google Analytics & Search Console',
            11 => 'Facebook Pixel',
            106 => 'Pre-Payment',
            108 => 'booking',
            112 => 'stripe',
            113 => 'paypal',
            119 => 'order-sms',
            126 => 'uddoktapay',
            127 => 'facebook-catalog',
            128 => 'bkash',
            129 => 'nagad',
            132 => 'sslcommerz',
            default => 'module',
        };
        $gatewayCompany = match ($modulusId) {
            112 => 'stripe',
            113 => 'paypal',
            126 => 'uddoktapay',
            128 => 'bKash',
            129 => 'Nagad',
            132 => 'SSL',
            default => null,
        };

        if (strtoupper((string) $request->method()) === 'GET') {
            $credential = QuickLogin::query()
                ->where('modulus_id', $modulusId)
                ->where('store_id', $storeId)
                ->first();
            $header = Headersetting::query()
                ->where('store_id', $storeId)
                ->first(['id', 'prepayment', 'payment_type', 'payment_method']);
            $headerSms = Headersetting::query()
                ->where('store_id', $storeId)
                ->first(['id', 'order_sms']);
            $storeUrl = (string) ($store->url ?? '');
            $xmlUrl = '';
            if ($storeUrl !== '') {
                $xmlUrl = route('facebook.dataFeed.url', ['name' => $storeUrl]);
            }
            $gateway = $gatewayCompany
                ? Paymentgateway::query()
                    ->where('store_id', $storeId)
                    ->where('payment_company', $gatewayCompany)
                    ->first()
                : null;
            $bookingFieldRows = collect();
            if ($modulusId === 108) {
                $bookingFieldRows = BookingCustomerFiled::query()
                    ->where('modulus_id', 108)
                    ->where('store_id', $storeId)
                    ->orderBy('id')
                    ->get();
            }
            $bookingTags = $modulusId === 108
                ? BookingTag::query()->select('id', 'name')->orderBy('id')->get()
                : collect();
            $bookingIsSingle = (int) optional($bookingFieldRows->first())->is_single === 0 ? 0 : 1;

            return response()->json([
                'config' => [
                    'modulus_id' => $modulusId,
                    'type' => $type,
                    'app_id' => (string) ($credential->app_id ?? ''),
                    'client_id' => (string) ($credential->client_id ?? ''),
                    'client_secret' => (string) ($credential->client_secret ?? ''),
                    'google_analytics' => (string) ($credential->google_analytics ?? ''),
                    'google_search_console' => (string) ($credential->google_search_console ?? ''),
                    'google_tag_manager' => (string) ($credential->google_tag_manager ?? ''),
                    'facebook_pixel' => (string) ($credential->facebook_pixel ?? ''),
                    'general_access_token' => (string) ($credential->general_access_token ?? ''),
                    'test_event_code' => (string) ($credential->test_event_code ?? ''),
                    'domain_verification_code' => (string) ($credential->domain_verification_code ?? ''),
                    'prepayment' => (string) ($header->prepayment ?? ''),
                    'payment_type' => (string) ($header->payment_type ?? ''),
                    'payment_method' => (string) ($header->payment_method ?? ''),
                    'status' => (string) ($gateway->status ?? ''),
                    'app_key' => (string) ($gateway->app_key ?? ''),
                    'app_secret' => (string) ($gateway->app_secret ?? ''),
                    'api_username' => (string) ($gateway->api_username ?? ''),
                    'api_password' => (string) ($gateway->api_password ?? ''),
                    'merchant_id' => (string) ($gateway->merchant_id ?? ''),
                    'merchant_number' => (string) ($gateway->merchant_number ?? ''),
                    'public_key' => (string) ($gateway->public_key ?? ''),
                    'private_key' => (string) ($gateway->private_key ?? ''),
                    'store_id' => (string) ($gateway->ssl_store_id ?? ''),
                    'store_password' => (string) ($gateway->ssl_store_password ?? ''),
                    'order_sms' => (string) ($headerSms->order_sms ?? ''),
                    'facebook_catalog_xml_url' => (string) $xmlUrl,
                    'facebook_catalog_generate_url' => route('admin.facebook.dataFeed.file'),
                    'from_type' => $bookingIsSingle === 1 ? 'single' : 'double',
                    'booking_fields' => $bookingTags->map(function ($tag) use ($bookingFieldRows) {
                        $saved = $bookingFieldRows->firstWhere('tagId', (int) $tag->id);
                        return [
                            'tag_id' => (int) $tag->id,
                            'name' => (string) ($saved->name ?? $tag->name ?? ''),
                            'is_checked' => (bool) ((int) ($saved->is_checked ?? 0) === 1),
                            'is_required' => (bool) ((int) ($saved->is_required ?? 0) === 1),
                        ];
                    })->values(),
                ],
            ]);
        }

        $payload = $request->validate([
            'app_id' => ['nullable', 'string', 'max:1000'],
            'client_id' => ['nullable', 'string', 'max:1000'],
            'client_secret' => ['nullable', 'string', 'max:1000'],
            'google_analytics' => ['nullable', 'string', 'max:1000'],
            'google_search_console' => ['nullable', 'string', 'max:1000'],
            'google_tag_manager' => ['nullable', 'string', 'max:1000'],
            'facebook_pixel' => ['nullable', 'string', 'max:1000'],
            'general_access_token' => ['nullable', 'string', 'max:3000'],
            'test_event_code' => ['nullable', 'string', 'max:1000'],
            'domain_verification_code' => ['nullable', 'string', 'max:1000'],
            'prepayment' => ['nullable', 'numeric'],
            'payment_type' => ['nullable', 'in:0,1,2'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'status_enabled' => ['nullable', 'boolean'],
            'app_key' => ['nullable', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:1000'],
            'api_username' => ['nullable', 'string', 'max:1000'],
            'api_password' => ['nullable', 'string', 'max:1000'],
            'merchant_id' => ['nullable', 'string', 'max:1000'],
            'merchant_number' => ['nullable', 'string', 'max:1000'],
            'public_key' => ['nullable', 'string', 'max:2000'],
            'private_key' => ['nullable', 'string', 'max:3000'],
            'store_id' => ['nullable', 'string', 'max:255'],
            'store_password' => ['nullable', 'string', 'max:255'],
            'from_type' => ['nullable', 'in:single,double'],
            'booking_fields' => ['nullable', 'array'],
            'booking_fields.*.tag_id' => ['required_with:booking_fields', 'integer', 'min:1'],
            'booking_fields.*.name' => ['nullable', 'string', 'max:255'],
            'booking_fields.*.is_checked' => ['nullable', 'boolean'],
            'booking_fields.*.is_required' => ['nullable', 'boolean'],
            'order_sms' => ['nullable', 'string', 'max:5000'],
        ]);

        $credential = QuickLogin::query()->firstOrNew([
            'modulus_id' => $modulusId,
            'store_id' => $storeId,
            'type' => $type,
        ]);

        $credential->modulus_id = $modulusId;
        $credential->store_id = $storeId;
        $credential->type = $type;
        $credential->app_id = (string) ($payload['app_id'] ?? '');
        $credential->client_id = (string) ($payload['client_id'] ?? '');
        $credential->client_secret = (string) ($payload['client_secret'] ?? '');
        $credential->google_analytics = (string) ($payload['google_analytics'] ?? '');
        $credential->google_search_console = (string) ($payload['google_search_console'] ?? '');
        $credential->google_tag_manager = (string) ($payload['google_tag_manager'] ?? '');
        $credential->facebook_pixel = (string) ($payload['facebook_pixel'] ?? '');
        $credential->general_access_token = (string) ($payload['general_access_token'] ?? '');
        $credential->test_event_code = (string) ($payload['test_event_code'] ?? '');
        $credential->domain_verification_code = (string) ($payload['domain_verification_code'] ?? '');
        $credential->save();

        if ($modulusId === 108) {
            $fromType = (($payload['from_type'] ?? 'single') === 'double') ? 0 : 1;
            $bookingFields = collect($payload['booking_fields'] ?? []);
            $activeTagIds = [];
            foreach ($bookingFields as $row) {
                $tagId = (int) ($row['tag_id'] ?? 0);
                if ($tagId <= 0) continue;
                $activeTagIds[] = $tagId;
                $isChecked = !empty($row['is_checked']) ? 1 : 0;
                $isRequired = ($isChecked === 1 && !empty($row['is_required'])) ? 1 : 0;
                BookingCustomerFiled::query()->updateOrCreate(
                    ['modulus_id' => 108, 'store_id' => $storeId, 'tagId' => $tagId],
                    [
                        'uId' => (int) ($user->id ?? 0),
                        'customer_id' => (int) ($store->uid ?? 0),
                        'name' => (string) ($row['name'] ?? ''),
                        'is_checked' => $isChecked,
                        'is_required' => $isRequired,
                        'is_single' => $fromType,
                    ]
                );
            }
            if (!empty($activeTagIds)) {
                BookingCustomerFiled::query()
                    ->where('modulus_id', 108)
                    ->where('store_id', $storeId)
                    ->whereNotIn('tagId', $activeTagIds)
                    ->delete();
            }
        }

        // Keep legacy side-effects for modules 5 and 7.
        if (in_array($modulusId, [5, 7], true)) {
            $header = Headersetting::query()->where('store_id', $storeId)->first();
            if ($header) {
                if (array_key_exists('client_secret', $payload)) {
                    $header->messenger_link = (string) ($payload['client_secret'] ?? '');
                }
                if (array_key_exists('client_id', $payload)) {
                    $header->facebook_app_id = (string) ($payload['client_id'] ?? '');
                }
                if (array_key_exists('app_id', $payload)) {
                    $header->facebook_login = (string) ($payload['app_id'] ?? '');
                }
                $header->save();
            }
        }

        // Save payment-gateway credentials for related modules.
        if ($gatewayCompany) {
            $gateway = Paymentgateway::query()->firstOrNew([
                'store_id' => $storeId,
                'payment_company' => $gatewayCompany,
            ]);
            $gateway->store_id = $storeId;
            $gateway->user_id = (int) ($store->uid ?? 0);
            $gateway->payment_company = $gatewayCompany;
            if (array_key_exists('app_key', $payload)) $gateway->app_key = (string) ($payload['app_key'] ?? '');
            if (array_key_exists('app_secret', $payload)) $gateway->app_secret = (string) ($payload['app_secret'] ?? '');
            if (array_key_exists('client_id', $payload) && in_array($modulusId, [113, 126], true)) {
                $gateway->client_id = (string) ($payload['client_id'] ?? '');
            }
            if (array_key_exists('api_username', $payload)) $gateway->api_username = (string) ($payload['api_username'] ?? '');
            if (array_key_exists('api_password', $payload)) $gateway->api_password = (string) ($payload['api_password'] ?? '');
            if (array_key_exists('merchant_id', $payload)) $gateway->merchant_id = (string) ($payload['merchant_id'] ?? '');
            if (array_key_exists('merchant_number', $payload)) $gateway->merchant_number = (string) ($payload['merchant_number'] ?? '');
            if (array_key_exists('public_key', $payload)) $gateway->public_key = (string) ($payload['public_key'] ?? '');
            if (array_key_exists('private_key', $payload)) $gateway->private_key = (string) ($payload['private_key'] ?? '');
            if (array_key_exists('store_id', $payload)) $gateway->ssl_store_id = (string) ($payload['store_id'] ?? '');
            if (array_key_exists('store_password', $payload)) $gateway->ssl_store_password = (string) ($payload['store_password'] ?? '');
            if (array_key_exists('status_enabled', $payload)) {
                $gateway->status = !empty($payload['status_enabled']) ? 'Accepted' : 'Pending';
            }
            $gateway->save();
        }

        // Pre-payment config (module 106) lives in header settings.
        if ($modulusId === 106) {
            $header = Headersetting::query()->firstOrNew(['store_id' => $storeId]);
            if (array_key_exists('prepayment', $payload)) {
                $header->prepayment = $payload['prepayment'];
            }
            if (array_key_exists('payment_type', $payload)) {
                $header->payment_type = is_null($payload['payment_type']) ? null : (int) $payload['payment_type'];
            }
            if (array_key_exists('payment_method', $payload) && trim((string) $payload['payment_method']) !== '') {
                $header->payment_method = (string) $payload['payment_method'];
            }
            $header->save();
        }

        // Order SMS config (module 119) lives in header settings.
        if ($modulusId === 119) {
            $header = Headersetting::query()->firstOrNew(['store_id' => $storeId]);
            if (array_key_exists('order_sms', $payload)) {
                $header->order_sms = (string) ($payload['order_sms'] ?? '');
            }
            $header->save();
        }

        return response()->json([
            'message' => 'Configuration saved successfully.',
        ]);
    }

    public function accountOrders(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $query = Order::query()
            ->withCount('orderitems')
            ->where('store_id', (int) $store->id)
            ->whereNotIn('status', ['Restock', 'Returned'])
            ->orderByDesc('id');

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('order_no', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && strtolower($status) !== 'all status') {
            $query->where('status', $status);
        }

        $fromDate = trim((string) $request->query('from_date', ''));
        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        $toDate = trim((string) $request->query('to_date', ''));
        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());
        $orderIds = $rows->pluck('id')->all();

        // Resolve first product image per order (for list thumbnail).
        // Note: `products.images` is usually a comma-separated string, so we take the first token.
        $firstProductImageByOrderId = [];
        if (!empty($orderIds)) {
            $firstImageRows = DB::table('orderitems')
                ->join('products', 'products.id', '=', 'orderitems.product_id')
                ->select('orderitems.order_id', 'products.images as product_images')
                ->whereIn('orderitems.order_id', $orderIds)
                ->orderBy('orderitems.order_id')
                ->orderBy('orderitems.id')
                ->get();

            foreach ($firstImageRows as $row) {
                $orderId = (int) ($row->order_id ?? 0);
                if ($orderId <= 0) continue;
                if (array_key_exists($orderId, $firstProductImageByOrderId)) continue;

                $firstProductImageByOrderId[$orderId] = $this->resolveProductImagePublicUrl($row->product_images ?? null);
            }
        }

        $orders = $rows->map(function (Order $order) {
            $statusRaw = trim((string) ($order->status ?? 'Pending'));
            $statusLower = strtolower($statusRaw);
            $badgeTone = match (true) {
                str_contains($statusLower, 'shipping') => 'shipping',
                str_contains($statusLower, 'process') => 'processing',
                str_contains($statusLower, 'hold') => 'hold',
                str_contains($statusLower, 'deliver') || str_contains($statusLower, 'complete') => 'delivered',
                default => 'draft',
            };

            $paymentType = trim((string) ($order->type ?? ''));
            if ($paymentType === '') {
                $paymentType = ((float) ($order->paid ?? 0) > 0) ? 'Paid' : 'COD';
            }

            return [
                'id' => (int) $order->id,
                'payment' => strtoupper($paymentType) === 'COD' ? 'COD' : 'Paid',
                'status' => $statusRaw !== '' ? $statusRaw : 'Pending',
                'badge' => $statusRaw !== '' ? $statusRaw : 'Change Status',
                'badgeTone' => $badgeTone,
                'customer' => (string) ($order->name ?? 'Customer'),
                'phone' => (string) ($order->phone ?? ''),
                'code' => (string) ($order->reference_no ?? $order->order_no ?? ('ORD-' . $order->id)),
                'date' => optional($order->created_at)->toDateTimeString(),
                'total' => (float) ($order->total ?? 0),
                // "Item total" (subtotal) instead of full order total.
                'item_total' => (float) ($order->subtotal ?? 0),
                'items' => ((int) ($order->orderitems_count ?? 0)) . ' Item(s)',
                'location' => (string) ($order->city ?? ''),
                'delivery_address' => trim(collect([
                    $order->edited_address ?? '',
                    $order->address ?? '',
                    $order->area ?? '',
                    $order->city ?? '',
                ])->filter()->implode(', ')),
                'first_product_image' => $firstProductImageByOrderId[(int) $order->id] ?? null,
                'invoice_url' => url('/order/view/' . (int) $order->id),
            ];
        })->values();

        $statusOptions = OrderStatus::getOrderStatus()
            ->pluck('slug')
            ->filter(fn($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn($slug) => trim((string) $slug))
            ->values();

        if ($statusOptions->isEmpty()) {
            $statusOptions = collect(['Pending', 'Processing', 'Shipping', 'On Hold', 'Delivered', 'Completed', 'Cancelled']);
        }

        return response()->json([
            'orders' => $orders,
            'status_options' => $statusOptions,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function accountOrderCourierFraud(Request $request, string $phone): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $normalizedPhone = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($normalizedPhone, '880')) {
            $normalizedPhone = substr($normalizedPhone, 3);
        }
        if (strlen($normalizedPhone) === 10 && str_starts_with($normalizedPhone, '1')) {
            $normalizedPhone = '0' . $normalizedPhone;
        }

        if ($normalizedPhone === '') {
            return response()->json(['message' => 'Phone number is required.'], 422);
        }

        $hasMatchingOrder = Order::query()
            ->where('store_id', (int) $store->id)
            ->where('phone', 'like', '%' . $normalizedPhone . '%')
            ->exists();

        if (!$hasMatchingOrder) {
            return response()->json(['message' => 'No matching order found for this phone in the active store.'], 404);
        }

        try {
            /** @var \App\Http\Controllers\OrderController $orderController */
            $orderController = app(OrderController::class);
            $data = $orderController->buildCourierFraudPayload(
                $normalizedPhone,
                (int) $store->id,
                (int) $store->user_id
            );

            return response()->json([
                'phone' => $normalizedPhone,
                'couriers' => $data,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function accountOrderShow(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $query = Order::query()
            ->with('orderitems')
            ->where('id', $id)
            ->where('store_id', (int) $store->id);

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $order = $query->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $billing = User::query()
            ->where('id', (int) ($order->uid ?? 0))
            ->first();

        $transaction = Transaction::query()
            ->where('order_id', (int) $order->id)
            ->first();

        $headerSetting = Headersetting::query()
            ->where('store_id', (int) $store->id)
            ->first();

        $booking = Booking::query()
            ->where('order_id', (int) $order->id)
            ->where('store_id', (int) $store->id)
            ->first();

        $courierDelivery = CourierDelivery::query()
            ->where('merchant_order_id', (string) ($order->reference_no ?? ''))
            ->first();

        $courierOptions = Courier::query()
            ->where('store_id', (int) $store->id)
            ->where('status', 1)
            ->pluck('courier_name')
            ->filter(fn($name) => is_string($name) && trim($name) !== '')
            ->map(fn($name) => trim((string) $name))
            ->values();

        $itemRows = Orderitem::query()
            ->where('order_id', (int) $order->id)
            ->get();

        return response()->json([
            'order' => [
                'id' => (int) $order->id,
                'customer' => (string) ($order->name ?? 'Customer'),
                'phone' => (string) ($order->phone ?? ''),
                'email' => (string) ($order->email ?? ''),
                'note' => (string) ($order->note ?? ''),
                'order_comment' => (string) ($order->order_comment ?? ''),
                'address' => trim(collect([
                    $order->address ?? '',
                    $order->city ?? '',
                    $order->area ?? '',
                ])->filter()->implode(', ')),
                'edited_address' => (string) ($order->edited_address ?? ''),
                'district' => (string) ($order->district ?? ''),
                'reference_no' => (string) ($order->reference_no ?? ''),
                'order_no' => (string) ($order->order_no ?? ''),
                'status' => (string) ($order->status ?? 'Pending'),
                'status_options' => OrderStatus::getOrderStatus()
                    ->pluck('slug')
                    ->filter(fn($slug) => is_string($slug) && trim($slug) !== '')
                    ->map(fn($slug) => trim((string) $slug))
                    ->values(),
                'payment' => strtoupper(trim((string) ($order->type ?? ''))) === 'COD' ? 'COD' : 'Paid',
                'subtotal' => (float) ($order->subtotal ?? 0),
                'discount' => (float) ($order->discount ?? 0),
                'shipping' => (float) ($order->shipping ?? 0),
                'tax' => (float) ($order->tax ?? 0),
                'total' => (float) ($order->total ?? 0),
                'paid' => (float) ($order->paid ?? 0),
                'due' => (float) ($order->due ?? 0),
                'symbol' => (string) ($order->symbol ?? 'BDT'),
                'created_at' => optional($order->created_at)->toDateTimeString(),
                'invoice_url' => url('/order/view/' . (int) $order->id),
                'store' => [
                    'name' => (string) ($store->name ?? $headerSetting->website_name ?? 'Store'),
                    'url' => (string) ($store->url ?? ''),
                    'phone' => (string) ($headerSetting->phone ?? ''),
                    'email' => (string) ($headerSetting->email ?? ''),
                    'address' => (string) ($headerSetting->address ?? ''),
                    'logo_url' => !empty($headerSetting->logo) ? url('assets/images/setting/' . ltrim((string) $headerSetting->logo, '/')) : null,
                ],
                'billing' => [
                    'name' => (string) ($billing->name ?? ''),
                    'phone' => (string) ($billing->phone ?? ''),
                    'email' => (string) ($billing->email ?? ''),
                    'address' => (string) ($billing->address ?? ''),
                ],
                'transaction' => $transaction ? [
                    'status' => (string) ($transaction->status ?? ''),
                    'transaction_id' => (string) ($transaction->transaction_id ?? ''),
                    'mode' => (string) ($transaction->mode ?? ''),
                ] : null,
                'booking' => $booking ? [
                    'date' => (string) ($booking->date ?? ''),
                    'time' => (string) ($booking->time ?? ''),
                    'status' => (string) ($booking->status ?? ''),
                ] : null,
                'courier_delivery' => $courierDelivery ? [
                    'courier_name' => (string) ($courierDelivery->courier_name ?? ''),
                    'consignment_id' => (string) ($courierDelivery->consignment_id ?? ''),
                    'tracking_code' => (string) ($courierDelivery->tracking_code ?? ''),
                    'created_at' => optional($courierDelivery->created_at)->toDateTimeString(),
                ] : null,
                'courier_options' => $courierOptions,
                'items' => $itemRows->map(function ($item) {
                    $product = null;
                    if ((int) ($item->product_id ?? 0) > 0) {
                        $product = Product::query()->find((int) $item->product_id);
                    }

                    $snapshot = null;
                    if (!$product && !empty($item->product_snapshot)) {
                        $snapshot = json_decode((string) $item->product_snapshot);
                    }

                    $name = (string) ($product->name ?? $snapshot->name ?? $item->product_name ?? $item->name ?? 'Item');
                    $sku = (string) ($product->SKU ?? $snapshot->SKU ?? '');
                    $link = (string) ($product->product_link ?? $snapshot->product_link ?? '');
                    $imageUrl = $this->resolveProductImagePublicUrl($product->images ?? $snapshot->images ?? null);

                    return [
                        'id' => (int) ($item->id ?? 0),
                        'name' => $name,
                        'sku' => $sku,
                        'link' => $link,
                        'image_url' => $imageUrl,
                        'quantity' => (int) ($item->quantity ?? 1),
                        'price' => (float) ($item->price ?? $item->amount ?? 0),
                        'total' => (float) (($item->price ?? $item->amount ?? 0) * ($item->quantity ?? 1)),
                        'color' => (string) ($item->color ?? ''),
                        'size' => (string) ($item->size ?? ''),
                        'unit' => (string) ($item->unit ?? ''),
                        'volume' => (string) ($item->volume ?? ''),
                    ];
                })->values(),
            ],
        ]);
    }

    public function accountOrderAddressUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'edited_address' => ['nullable', 'string', 'max:5000'],
        ]);

        $query = Order::query()
            ->where('id', $id)
            ->where('store_id', (int) $store->id);

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $order = $query->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order->edited_address = (string) ($payload['edited_address'] ?? '');
        $order->save();

        return response()->json([
            'message' => 'Order address updated successfully.',
            'edited_address' => (string) ($order->edited_address ?? ''),
        ]);
    }

    public function accountOrderDetailsUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'due_pay' => ['nullable', 'numeric', 'min:0'],
        ]);

        $query = Order::query()
            ->where('id', $id)
            ->where('store_id', (int) $store->id);

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $order = $query->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if (array_key_exists('shipping', $payload) && $payload['shipping'] !== null) {
            $prevShipping = (float) ($order->shipping ?? 0);
            $prevTotal = (float) ($order->total ?? 0);
            $prevPaid = (float) ($order->paid ?? 0);
            $newShipping = (float) $payload['shipping'];
            $newTotal = ($prevTotal - $prevShipping) + $newShipping;
            $newDue = $newTotal - $prevPaid;

            $order->shipping = $newShipping;
            $order->total = $newTotal;
            $order->due = $newDue;
        }

        if (array_key_exists('due_pay', $payload) && $payload['due_pay'] !== null) {
            $duePay = (float) $payload['due_pay'];
            if ($duePay > (float) ($order->total ?? 0)) {
                return response()->json(['message' => 'You can not pay extra amount.'], 422);
            }

            $prevTotal = (float) ($order->total ?? 0);
            $prevPaid = (float) ($order->paid ?? 0);
            $newPaid = $prevPaid + $duePay;
            $newDue = $prevTotal - $newPaid;

            $order->paid = $newPaid;
            $order->due = $newDue;
        }

        $order->save();

        return response()->json([
            'message' => 'Order details updated successfully.',
            'order' => [
                'shipping' => (float) ($order->shipping ?? 0),
                'total' => (float) ($order->total ?? 0),
                'paid' => (float) ($order->paid ?? 0),
                'due' => (float) ($order->due ?? 0),
            ],
        ]);
    }

    public function accountOrderCommentUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'order_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $query = Order::query()
            ->where('id', $id)
            ->where('store_id', (int) $store->id);

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $order = $query->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order->order_comment = (string) ($payload['order_comment'] ?? '');
        $order->save();

        return response()->json([
            'message' => 'Comment updated successfully.',
            'order_comment' => (string) ($order->order_comment ?? ''),
        ]);
    }

    public function accountStaffList(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];

        $staff = Staff::query()
            ->where('store_id', (int) $store->id)
            ->get(['uid', 'name'])
            ->map(function ($s) {
                return [
                    'uid' => (int) $s->uid,
                    'name' => (string) ($s->name ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'staff' => $staff,
        ]);
    }

    public function accountOrdersBulkStatusUpdate(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'ids' => ['required'],
            'status' => ['required', 'string', 'max:100'],
        ]);

        $status = trim((string) $payload['status']);

        $idsRaw = $payload['ids'];
        $ids = is_array($idsRaw)
            ? $idsRaw
            : array_filter(explode(',', (string) $idsRaw));

        $ids = array_values(array_filter(array_map(function ($v) {
            $n = (int) $v;
            return $n > 0 ? $n : null;
        }, $ids)));

        if (empty($ids)) {
            return response()->json(['message' => 'No order IDs provided.'], 422);
        }

        $query = Order::query()
            ->whereIn('id', $ids)
            ->where('store_id', (int) $store->id);

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $orders = $query->get();
        $updatedCount = 0;

        $statusLower = strtolower($status);
        $badgeTone = match (true) {
            str_contains($statusLower, 'shipping') => 'shipping',
            str_contains($statusLower, 'process') => 'processing',
            str_contains($statusLower, 'hold') => 'hold',
            str_contains($statusLower, 'deliver') || str_contains($statusLower, 'complete') => 'delivered',
            default => 'draft',
        };

        foreach ($orders as $order) {
            $order->status = $status;
            $order->save();
            $updatedCount++;
        }

        return response()->json([
            'message' => 'Bulk order status updated successfully.',
            'updated_count' => $updatedCount,
            'badgeTone' => $badgeTone,
        ]);
    }

    public function accountReturnCancelOrders(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $allowed = ['Returned', 'Cancelled', 'Restock'];

        $query = Order::query()
            ->withCount('orderitems')
            ->where('store_id', (int) $store->id)
            ->whereIn('status', $allowed)
            ->orderByDesc('id');

        if ((string) ($user->type ?? '') === 'staff') {
            $query->where('staff_id', (int) $user->id);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('order_no', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $fromDate = trim((string) $request->query('from_date', ''));
        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        $toDate = trim((string) $request->query('to_date', ''));
        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && in_array($status, $allowed, true)) {
            $query->where('status', $status);
        }

        $rows = $query->limit(100)->get();
        $orderIds = $rows->pluck('id')->all();

        $firstProductImageByOrderId = [];
        if (!empty($orderIds)) {
            $firstImageRows = DB::table('orderitems')
                ->join('products', 'products.id', '=', 'orderitems.product_id')
                ->select('orderitems.order_id', 'products.images as product_images')
                ->whereIn('orderitems.order_id', $orderIds)
                ->orderBy('orderitems.order_id')
                ->orderBy('orderitems.id')
                ->get();

            foreach ($firstImageRows as $row) {
                $orderId = (int) ($row->order_id ?? 0);
                if ($orderId <= 0) continue;
                if (array_key_exists($orderId, $firstProductImageByOrderId)) continue;

                $firstProductImageByOrderId[$orderId] = $this->resolveProductImagePublicUrl($row->product_images ?? null);
            }
        }

        $orders = $rows->map(function (Order $order) use ($firstProductImageByOrderId) {
            $statusRaw = trim((string) ($order->status ?? 'Returned'));
            $statusLower = strtolower($statusRaw);

            $badgeTone = match (true) {
                str_contains($statusLower, 'return') => 'hold',
                str_contains($statusLower, 'cancel') => 'draft',
                str_contains($statusLower, 'restock') => 'delivered',
                default => 'draft',
            };

            $paymentType = trim((string) ($order->type ?? ''));
            if ($paymentType === '') {
                $paymentType = ((float) ($order->paid ?? 0) > 0) ? 'Paid' : 'COD';
            }

            return [
                'id' => (int) $order->id,
                'payment' => strtoupper($paymentType) === 'COD' ? 'COD' : 'Paid',
                'status' => $statusRaw,
                'badge' => $statusRaw,
                'badgeTone' => $badgeTone,
                'customer' => (string) ($order->name ?? 'Customer'),
                'phone' => (string) ($order->phone ?? ''),
                'code' => (string) ($order->reference_no ?? $order->order_no ?? ('ORD-' . $order->id)),
                'date' => optional($order->created_at)->toDateTimeString(),
                'total' => (float) ($order->total ?? 0),
                'item_total' => (float) ($order->subtotal ?? 0),
                'items' => ((int) ($order->orderitems_count ?? 0)) . ' Item(s)',
                'location' => (string) ($order->city ?? ''),
                'delivery_address' => trim(collect([
                    $order->edited_address ?? '',
                    $order->address ?? '',
                    $order->area ?? '',
                    $order->city ?? '',
                ])->filter()->implode(', ')),
                'first_product_image' => $firstProductImageByOrderId[(int) $order->id] ?? null,
                'invoice_url' => url('/order/view/' . (int) $order->id),
                'restock_url' => url('/order/restock/' . (int) $order->id),
            ];
        })->values();

        return response()->json([
            'orders' => $orders,
            'status_options' => collect($allowed)->values(),
        ]);
    }

    public function accountOrderRestock(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $order = Order::query()
            ->where('id', $id)
            ->where('store_id', (int) $store->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ((string) ($user->type ?? '') === 'staff' && (int) ($order->staff_id ?? 0) !== (int) $user->id) {
            return response()->json(['message' => 'You are not allowed to restock this order.'], 403);
        }

        $status = trim((string) ($order->status ?? ''));
        if (!in_array($status, ['Returned', 'Cancelled'], true)) {
            return response()->json(['message' => 'Only returned or cancelled orders can be restocked.'], 422);
        }

        DB::transaction(function () use ($order) {
            $orderItems = Orderitem::query()->where('order_id', (int) $order->id)->get();

            foreach ($orderItems as $item) {
                $quantity = (int) ($item->quantity ?? 0);
                if ($quantity <= 0) continue;

                $product = Product::query()->find($item->product_id);
                if ($product) {
                    $currentQty = is_numeric($product->quantity) ? (int) $product->quantity : 0;
                    $product->quantity = $currentQty + $quantity;
                    $product->save();
                }

                if (!empty($item->variant_id)) {
                    $variant = Veriant::query()->find($item->variant_id);
                    if ($variant) {
                        $currentVariantQty = is_numeric($variant->quantity) ? (int) $variant->quantity : 0;
                        $variant->quantity = $currentVariantQty + $quantity;
                        $variant->save();
                    }
                }
            }

            $order->status = 'Restock';
            $order->save();
        });

        return response()->json([
            'message' => 'Product quantity restored successfully.',
            'order' => [
                'id' => (int) $order->id,
                'status' => 'Restock',
            ],
        ]);
    }

    public function accountOrderStatusUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $store = $ctx['store'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'status' => ['required', 'string', 'max:100'],
        ]);

        $status = trim((string) $payload['status']);
        if ($status === '') {
            return response()->json(['message' => 'Status is required.'], 422);
        }

        $order = Order::query()
            ->where('id', $id)
            ->where('store_id', (int) $store->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ((string) ($user->type ?? '') === 'staff' && (int) ($order->staff_id ?? 0) !== (int) $user->id) {
            return response()->json(['message' => 'You are not allowed to update this order.'], 403);
        }

        $order->status = $status;
        $order->save();

        $statusLower = strtolower($status);
        $badgeTone = match (true) {
            str_contains($statusLower, 'shipping') => 'shipping',
            str_contains($statusLower, 'process') => 'processing',
            str_contains($statusLower, 'hold') => 'hold',
            str_contains($statusLower, 'deliver') || str_contains($statusLower, 'complete') => 'delivered',
            default => 'draft',
        };

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => [
                'id' => (int) $order->id,
                'status' => $status,
                'badge' => $status,
                'badgeTone' => $badgeTone,
            ],
        ]);
    }

    public function paymentHistoryInvoice(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $source = strtolower(trim((string) $request->query('source', 'addons')));

        if ($source === 'modulus') {
            $payment = ModulusPayment::query()
                ->with(['module'])
                ->where('id', $id)
                ->where('store_id', (int) $store->id)
                ->first();

            if (!$payment) {
                return response()->json(['message' => 'Invoice not found.'], 404);
            }

            $price = (float) ($payment->price ?? 0);
            $statusRaw = trim((string) ($payment->status ?? 'Processing'));
            $status = $statusRaw !== '' ? $statusRaw : 'Processing';

            return response()->json([
                'invoice' => [
                    'order_no' => (string) ($payment->order_no ?? ('PM-' . $payment->id)),
                    'store_name' => (string) ($store->name ?? ''),
                    'customer_name' => (string) ($ctx['user']->name ?? ''),
                    'phone' => (string) ($payment->number ?? ''),
                    'order_date' => optional($payment->created_at)->toDateTimeString(),
                    'status' => $status,
                    'currency_code' => 'BDT',
                    'package_rows' => [[
                        'name' => (string) ($payment->module?->name ?? 'Package'),
                        'plan' => (string) ($payment->payment_type ?? ''),
                        'price' => $price,
                    ]],
                    'addon_rows' => [],
                    'subtotal' => $price,
                    'grand_total' => $price,
                ],
            ]);
        }

        $order = AddonsOrder::query()
            ->with(['user'])
            ->where('id', $id)
            ->where('store_id', (int) $store->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        $packageRaw = $order->package;
        if (is_string($packageRaw)) {
            $decoded = json_decode($packageRaw, true);
            $packageRaw = is_array($decoded) ? $decoded : [];
        }
        $packageRows = [];
        if (is_array($packageRaw) && !empty($packageRaw)) {
            $packageRows[] = [
                'name' => (string) ($packageRaw['title'] ?? $packageRaw['name'] ?? 'Package'),
                'plan' => (string) ($packageRaw['monthorvalue'] ?? $packageRaw['month'] ?? ''),
                'price' => (float) ($packageRaw['offerprice'] ?? $packageRaw['price'] ?? 0),
            ];
        }

        $addonsRaw = $order->addons;
        if (is_string($addonsRaw)) {
            $decoded = json_decode($addonsRaw, true);
            $addonsRaw = is_array($decoded) ? $decoded : [];
        }
        $addonRows = collect(is_array($addonsRaw) ? $addonsRaw : [])
            ->map(function ($item) {
                return [
                    'name' => (string) ($item['title'] ?? 'Addon'),
                    'details' => (string) ($item['name'] ?? $item['monthorvalue'] ?? ''),
                    'price' => (float) ($item['offerprice'] ?? $item['price'] ?? 0),
                ];
            })
            ->values()
            ->all();

        $packageTotal = collect($packageRows)->sum('price');
        $addonsTotal = collect($addonRows)->sum('price');
        $subtotal = (float) ($packageTotal + $addonsTotal);
        $grandTotal = (float) ($order->total ?? $subtotal);

        return response()->json([
            'invoice' => [
                'order_no' => (string) ($order->order_no ?? ('EBI-' . $order->id)),
                'store_name' => (string) ($store->name ?? ''),
                'customer_name' => (string) ($order->user?->name ?? ''),
                'phone' => (string) ($order->payment_number ?? $order->user?->phone ?? ''),
                'order_date' => optional($order->created_at)->toDateTimeString(),
                'status' => (string) ($order->status ?? 'Processing'),
                'currency_code' => strtoupper(trim((string) ($order->currency_type ?? 'BDT'))),
                'package_rows' => $packageRows,
                'addon_rows' => $addonRows,
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
            ],
        ]);
    }

    public function paymentAddonsCoupon(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];

        $payload = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $store->loadMissing(['current_currency']);
        $currencyCode = strtoupper(trim((string) ($store->current_currency?->code ?? 'BDT')));
        $currencyType = $currencyCode === 'USD' ? 'USD' : 'BDT';

        $today = now()->toDateString();
        $subtotal = (float) $payload['subtotal'];

        $coupon = AdminCoupon::query()
            ->where('code', trim((string) $payload['code']))
            ->where('currency_type', $currencyType)
            ->where('min_purchase', '<=', $subtotal)
            ->where('max_purchase', '>=', $subtotal)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('status', 'active')
            ->first();

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found or expired.'], 422);
        }

        $discount = (float) ($coupon->discount_amount ?? 0);
        if (strtolower((string) ($coupon->discount_type ?? '')) === 'percent') {
            $discount = floor($subtotal * ($discount / 100));
        }
        $discount = min($subtotal, max(0, $discount));

        return response()->json([
            'code' => (string) ($coupon->code ?? ''),
            'discount' => (float) $discount,
            'discount_type' => (string) ($coupon->discount_type ?? ''),
            'discount_amount' => (float) ($coupon->discount_amount ?? 0),
        ]);
    }

    public function paymentAddonsCheckout(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $payload = $request->validate([
            'addons' => ['required', 'array', 'min:1'],
            'addons.*.id' => ['required', 'integer', 'min:1'],
            'addons.*.title' => ['required', 'string', 'max:255'],
            'addons.*.type' => ['nullable', 'string', 'max:50'],
            'addons.*.offerprice' => ['required', 'numeric', 'min:0'],
            'addons.*.price' => ['nullable', 'numeric', 'min:0'],
            'addons.*.monthorvalue' => ['nullable', 'string', 'max:100'],
            'addons.*.name' => ['nullable', 'string', 'max:255'],
            'addons.*.activeTime' => ['nullable', 'integer', 'in:0,1'],
            'addons.*.domain_name' => ['nullable', 'string', 'max:255'],
            'addons.*.domain_tld' => ['nullable', 'string', 'max:50'],
            'addons.*.domain_lookup_price' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'in:bkash,nagad,paypal,amarpay'],
        ]);

        $forwardPayload = [
            'addons' => array_map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'title' => (string) $item['title'],
                    'type' => (string) ($item['type'] ?? 'addon'),
                    'offerprice' => (float) $item['offerprice'],
                    'price' => (float) ($item['price'] ?? $item['offerprice']),
                    'monthorvalue' => (string) ($item['monthorvalue'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'activeTime' => (int) ($item['activeTime'] ?? 0),
                    'domain_name' => (string) ($item['domain_name'] ?? ''),
                    'domain_tld' => (string) ($item['domain_tld'] ?? ''),
                    'domain_lookup_price' => (float) ($item['domain_lookup_price'] ?? 0),
                ];
            }, (array) $payload['addons']),
            'subtotal' => (float) $payload['subtotal'],
            'discount' => (float) ($payload['discount'] ?? 0),
            'code' => (string) ($payload['code'] ?? ''),
            'payment_method' => (string) $payload['payment_method'],
        ];

        $forward = $request->duplicate(null, $forwardPayload);
        $forward->headers->set('Accept', 'application/json');

        return app(ChooseplanController::class)->buyAddons($forward);
    }

    public function paymentAddonsManualCheckout(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $payload = $request->validate([
            'addons' => ['required', 'array', 'min:1'],
            'addons.*.id' => ['required', 'integer', 'min:1'],
            'addons.*.title' => ['required', 'string', 'max:255'],
            'addons.*.type' => ['nullable', 'string', 'max:50'],
            'addons.*.offerprice' => ['required', 'numeric', 'min:0'],
            'addons.*.price' => ['nullable', 'numeric', 'min:0'],
            'addons.*.monthorvalue' => ['nullable', 'string', 'max:100'],
            'addons.*.name' => ['nullable', 'string', 'max:255'],
            'addons.*.activeTime' => ['nullable', 'integer', 'in:0,1'],
            'addons.*.domain_name' => ['nullable', 'string', 'max:255'],
            'addons.*.domain_tld' => ['nullable', 'string', 'max:50'],
            'addons.*.domain_lookup_price' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_type' => ['nullable', 'in:full,partial'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'manual_discount' => ['nullable', 'numeric', 'min:0'],
            'manual_discount_comment' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:191'],
            'phone' => AdminContactValidation::phoneRules(false, 100),
            'transaction' => ['nullable', 'string', 'max:191'],
        ]);

        $forward = $request->duplicate(null, $payload);
        $forward->headers->set('Accept', 'application/json');
        return app(ChooseplanController::class)->buyAddonsWithManual($forward);
    }

    public function paymentAddonsDueUpdate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];

        $payload = $request->validate([
            'additional_paid_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_number' => AdminContactValidation::phoneRules(false, 100),
            'transaction_id' => ['nullable', 'string', 'max:191'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $forward = $request->duplicate(null, $payload);
        $forward->headers->set('Accept', 'application/json');
        return app(ChooseplanController::class)->updateManualDuePayment($forward, $id);
    }

    public function domainSettingsStore(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $payload = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $raw = trim((string) $payload['domain']);
        $clean = function_exists('cleanDomain') ? cleanDomain($raw) : strtolower(preg_replace('/^https?:\/\//i', '', $raw));
        $clean = strtolower(trim((string) $clean, " \t\n\r\0\x0B./"));

        if ($clean === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $clean)) {
            return response()->json(['message' => 'Please enter a valid domain name.'], 422);
        }

        $existsForStore = Domain::query()
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->whereRaw('LOWER(name) = ?', [$clean])
            ->exists();
        if ($existsForStore) {
            return response()->json(['message' => 'Domain already exists for this store.'], 422);
        }

        $existsGlobal = Domain::query()
            ->whereRaw('LOWER(name) = ?', [$clean])
            ->where('status', '!=', 'Rejected')
            ->exists();
        if ($existsGlobal) {
            return response()->json(['message' => 'This domain is already in use.'], 422);
        }

        $row = new Domain();
        $row->name = $clean;
        if ($this->tableHasColumn('domains', 'status')) {
            $row->status = 'Requested';
        }
        if ($this->tableHasColumn('domains', 'uid')) {
            $row->uid = $user->id;
        }
        if ($this->tableHasColumn('domains', 'store_id')) {
            $row->store_id = $store->id;
        }
        if ($this->tableHasColumn('domains', 'customer_id')) {
            $row->customer_id = $customer->id;
        }
        if ($this->tableHasColumn('domains', 'creator')) {
            $row->creator = $user->id;
        }
        if ($this->tableHasColumn('domains', 'editor')) {
            $row->editor = $user->id;
        }
        $row->save();

        return $this->domainSettings($request);
    }

    public function domainSettingsActivate(Request $request, int $id): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        $row = Domain::query()
            ->where('id', $id)
            ->where('store_id', (string) $store->id)
            ->where('customer_id', (string) $customer->id)
            ->first();
        if (!$row) {
            return response()->json(['message' => 'Domain not found.'], 404);
        }

        $store->url = (string) ($row->name ?? '');
        if ($this->tableHasColumn('stores', 'editor')) {
            $store->editor = $user->id;
        }
        $store->save();

        return $this->domainSettings($request);
    }

    public function websiteSettingsUpdate(Request $request): JsonResponse
    {
        $ctx = $this->resolveReactStoreContext($request);
        if ($ctx['error']) return $ctx['error'];
        $store = $ctx['store'];
        $customer = $ctx['customer'];
        $user = $ctx['user'];

        // Multipart FormData sends "" for empty fields; Laravel's integer rule rejects those strings.
        foreach (['active_plan_id', 'currency_id', 'business_type_id'] as $emptyableId) {
            if ($request->has($emptyableId) && $request->input($emptyableId) === '') {
                $request->merge([$emptyableId => null]);
            }
        }

        $payload = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'business_type_id' => ['nullable', 'integer', 'exists:business_categories,id'],
            'active_plan' => ['nullable', 'string', 'max:255'],
            'active_plan_id' => ['nullable', 'integer', 'min:1'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'phone' => AdminContactValidation::phoneRules(false, 50),
            'email' => AdminContactValidation::emailRules(false, 255),
            'address' => ['nullable', 'string', 'max:255'],
            'custom_writing' => ['nullable', 'string', 'max:5000'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'registration_mode' => ['nullable', 'in:email,phone,easy,email_easy'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'max:1000'],
            'social_links.instagram' => ['nullable', 'string', 'max:1000'],
            'social_links.whatsapp' => ['nullable', 'string', 'max:1000'],
            'social_links.linkedin' => ['nullable', 'string', 'max:1000'],
            'social_links.pinterest' => ['nullable', 'string', 'max:1000'],
            'social_links.twitter' => ['nullable', 'string', 'max:1000'],
            'social_links.tiktok' => ['nullable', 'string', 'max:1000'],
            'social_links.map' => ['nullable', 'string', 'max:2000'],
            'social_links.website' => ['nullable', 'string', 'max:1000'],
            'shipping_methods' => ['nullable', 'array'],
            'shipping_methods.*.area' => ['nullable', 'string', 'max:255'],
            'shipping_methods.*.cost' => ['nullable', 'numeric', 'min:0'],
            'logo_upload' => ['nullable', 'file', 'max:10240', 'mimes:jpeg,jpg,png,gif,webp,svg,bmp'],
            'favicon_upload' => ['nullable', 'file', 'max:5120', 'mimes:jpeg,jpg,png,gif,webp,svg,bmp,ico'],
            'logo_media_path' => ['nullable', 'string', 'max:500'],
            'favicon_media_path' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->tableHasColumn('stores', 'name')) {
            $store->name = $payload['business_name'];
        }
        if ($this->tableHasColumn('stores', 'type') && array_key_exists('business_type_id', $payload)) {
            $store->type = (string) ($payload['business_type_id'] ?? '');
        }
        if ($this->tableHasColumn('stores', 'plan_id') && array_key_exists('active_plan_id', $payload)) {
            $store->plan_id = (int) ($payload['active_plan_id'] ?? 0);
        }
        if ($this->tableHasColumn('stores', 'currency') && array_key_exists('currency_id', $payload)) {
            $store->currency = (int) ($payload['currency_id'] ?? 1);
        }
        if ($this->tableHasColumn('stores', 'auth_type') && array_key_exists('registration_mode', $payload)) {
            $store->auth_type = $this->authTypeFromRegistrationMode((string) ($payload['registration_mode'] ?? 'phone'));
        }
        if ($this->tableHasColumn('stores', 'url') && isset($payload['social_links']) && array_key_exists('website', (array) $payload['social_links'])) {
            $store->url = (string) ($payload['social_links']['website'] ?? '');
        }
        if ($this->tableHasColumn('stores', 'editor')) {
            $store->editor = $user->id;
        }
        $store->save();

        $header = Headersetting::query()->where('store_id', (string) $store->id)->latest('id')->first();
        if (!$header) {
            $header = new Headersetting();
            if ($this->tableHasColumn('headersettings', 'store_id')) $header->store_id = $store->id;
            if ($this->tableHasColumn('headersettings', 'uid')) $header->uid = $user->id;
            if ($this->tableHasColumn('headersettings', 'customer_id')) $header->customer_id = $customer->id;
            if ($this->tableHasColumn('headersettings', 'creator')) $header->creator = $user->id;
        }

        if ($this->tableHasColumn('headersettings', 'website_name')) {
            $header->website_name = $payload['business_name'];
        }
        if ($this->tableHasColumn('headersettings', 'short_description')) {
            $header->short_description = $payload['short_description'] ?? '';
        }
        if ($this->tableHasColumn('headersettings', 'phone')) {
            $header->phone = $payload['phone'] ?? '';
        }
        if ($this->tableHasColumn('headersettings', 'email')) {
            $header->email = $payload['email'] ?? '';
        }
        if ($this->tableHasColumn('headersettings', 'address')) {
            $header->address = $payload['address'] ?? '';
        }
        if ($this->tableHasColumn('headersettings', 'custom_writing')) {
            $header->custom_writing = $payload['custom_writing'] ?? '';
        }
        if ($this->tableHasColumn('headersettings', 'tax')) {
            $header->tax = array_key_exists('tax', $payload) ? (string) ($payload['tax'] ?? '') : '';
        }
        if ($this->tableHasColumn('headersettings', 'facebook_link')) {
            $header->facebook_link = (string) data_get($payload, 'social_links.facebook', '');
        }
        if ($this->tableHasColumn('headersettings', 'instagram_link')) {
            $header->instagram_link = (string) data_get($payload, 'social_links.instagram', '');
        }
        if ($this->tableHasColumn('headersettings', 'whatsapp_phone')) {
            $header->whatsapp_phone = (string) data_get($payload, 'social_links.whatsapp', '');
        }
        if ($this->tableHasColumn('headersettings', 'lined_in_link')) {
            $header->lined_in_link = (string) data_get($payload, 'social_links.linkedin', '');
        }
        if ($this->tableHasColumn('headersettings', 'pinterest_link')) {
            $header->pinterest_link = (string) data_get($payload, 'social_links.pinterest', '');
        }
        if ($this->tableHasColumn('headersettings', 'twitter_link')) {
            $header->twitter_link = (string) data_get($payload, 'social_links.twitter', '');
        }
        if ($this->tableHasColumn('headersettings', 'tiktok_link')) {
            $header->tiktok_link = (string) data_get($payload, 'social_links.tiktok', '');
        }
        if ($this->tableHasColumn('headersettings', 'map_address')) {
            $header->map_address = (string) data_get($payload, 'social_links.map', '');
        }
        if ($this->tableHasColumn('headersettings', 'shipping_methods')) {
            $header->shipping_methods = json_encode($this->normalizeShippingMethods($payload['shipping_methods'] ?? []));
        }
        if ($this->tableHasColumn('headersettings', 'editor')) {
            $header->editor = $user->id;
        }
        if ($this->tableHasColumn('headersettings', 'currency_id') && empty($header->currency_id)) {
            $header->currency_id = (int) ($store->currency ?? 1);
        }

        if ($request->hasFile('logo_upload')) {
            $header->logo = $this->storeUploadedPublicImage($request->file('logo_upload'), 'assets/images/setting');
        } elseif (!empty($payload['logo_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['logo_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/setting',
                'logo_',
            );
            if ($copied !== null) {
                $header->logo = $copied;
            }
        }

        if ($request->hasFile('favicon_upload')) {
            $header->favicon = $this->storeUploadedPublicImage($request->file('favicon_upload'), 'assets/images/setting/favicon');
        } elseif (!empty($payload['favicon_media_path'])) {
            $copied = $this->copyMediaLibraryAssetToPublicDirectory(
                (string) $payload['favicon_media_path'],
                (string) $customer->id,
                (string) $store->id,
                'assets/images/setting/favicon',
                'favicon_',
            );
            if ($copied !== null) {
                // copyMediaLibraryAssetToPublicDirectory returns a path like "storage/image-library/...".
                // Do not prefix with "favicon/" — that breaks resolveSettingAssetPublicUrl().
                $header->favicon = str_starts_with((string) $copied, 'storage/')
                    ? $copied
                    : ('favicon/' . ltrim((string) $copied, '/'));
            }
        }

        $header->save();

        return $this->websiteSettings($request);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
        ]);
    }

    public function registerRequestOtp(Request $request): JsonResponse
    {
        $request->merge([
            'phone' => trim((string) $request->input('phone')) !== '' ? trim((string) $request->input('phone')) : null,
            'email' => trim((string) $request->input('email')) !== '' ? trim((string) $request->input('email')) : null,
        ]);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => AdminContactValidation::phoneRules(false),
            'email' => AdminContactValidation::emailRules(false, 255),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'in:admin,dropshipper'],
        ]);

        $phoneTarget = trim((string) ($payload['phone'] ?? ''));
        $emailTarget = trim((string) ($payload['email'] ?? ''));

        if ($phoneTarget === '' && $emailTarget === '') {
            throw ValidationException::withMessages([
                'message' => 'Phone number or email address is required.',
            ]);
        }

        $this->ensureRegistrationIsAvailable($phoneTarget !== '' ? $phoneTarget : null, $emailTarget !== '' ? $emailTarget : null);

        $otp = sixDigitRandCode();
        $otpTarget = $phoneTarget !== '' ? $phoneTarget : $emailTarget;

        $request->session()->put(self::REGISTRATION_SESSION_KEY, [
            'name' => $payload['name'],
            'phone' => $phoneTarget !== '' ? $phoneTarget : null,
            'email' => $emailTarget !== '' ? $emailTarget : null,
            'password_hash' => Hash::make($payload['password']),
            'user_type' => $payload['user_type'],
            'otp' => $otp,
            'target' => $otpTarget,
            'email_target' => $emailTarget !== '' ? $emailTarget : null,
        ]);

        $this->sendRegistrationOtp(
            $phoneTarget !== '' ? $phoneTarget : null,
            $emailTarget !== '' ? $emailTarget : null,
            $otp,
            $payload['name']
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'target' => $otpTarget,
            'channels' => $this->registrationOtpChannels($phoneTarget, $emailTarget),
            'flow' => 'registration',
            'user_type' => $payload['user_type'],
        ]);
    }

    public function registerResendOtp(Request $request): JsonResponse
    {
        $pending = $request->session()->get(self::REGISTRATION_SESSION_KEY);

        if (!$pending) {
            return response()->json([
                'message' => 'Registration session expired. Please register again.',
            ], 422);
        }

        $pending['otp'] = sixDigitRandCode();
        $request->session()->put(self::REGISTRATION_SESSION_KEY, $pending);

        $this->sendRegistrationOtp(
            $pending['phone'] ?? null,
            $pending['email_target'] ?? $pending['email'] ?? null,
            $pending['otp'],
            $pending['name'] ?? ''
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'target' => $pending['target'] ?? $pending['phone'] ?? $pending['email'] ?? null,
            'channels' => $this->registrationOtpChannels($pending['phone'] ?? null, $pending['email_target'] ?? $pending['email'] ?? null),
        ]);
    }

    public function registerVerifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = $request->session()->get(self::REGISTRATION_SESSION_KEY);

        if (!$pending) {
            return response()->json([
                'message' => 'Registration session expired. Please register again.',
            ], 422);
        }

        if ((string) ($pending['otp'] ?? '') !== (string) $validated['otp']) {
            return response()->json([
                'message' => 'Incorrect OTP.',
            ], 422);
        }

        $this->ensureRegistrationIsAvailable($pending['phone'] ?? null, $pending['email'] ?? null);

        $user = new User();
        $user->name = $pending['name'];
        $user->phone = $pending['phone'] ?? null;
        $user->email = $pending['email'] ?: null;
        $user->password = $pending['password_hash'];
        $user->type = $pending['user_type'];
        $user->otp = 'NULL';
        $user->save();

        $customer = new Customer();
        $customer->uid = $user->id;
        $customer->phone = $user->phone;
        $customer->plan_id = 'NULL';
        $customer->purchase_date = 'NULL';
        $customer->active_store = '0';
        $customer->ref_code = Str::random(8);
        $customer->points = '200';
        $customer->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(self::REGISTRATION_SESSION_KEY);

        return response()->json([
            'success' => true,
            'message' => 'Registration completed successfully.',
            'user' => $this->formatUser($user),
            'active_store' => $this->activeStoreSummary($user),
        ]);
    }

    public function passwordRequestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_or_phone' => AdminContactValidation::emailOrPhoneRules(true),
        ]);

        $user = User::query()
            ->where(function ($query) use ($validated) {
                $query
                    ->where('phone', $validated['email_or_phone'])
                    ->orWhere('email', $validated['email_or_phone']);
            })
            ->whereIn('type', ['admin', 'dropshipper'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No matching account was found.',
            ], 422);
        }

        $otp = sixDigitRandCode();
        $user->otp = $otp;
        $user->save();

        $target = filter_var($validated['email_or_phone'], FILTER_VALIDATE_EMAIL)
            ? ($user->email ?: $validated['email_or_phone'])
            : ($user->phone ?: $validated['email_or_phone']);

        $request->session()->put(self::PASSWORD_RESET_SESSION_KEY, [
            'user_id' => $user->id,
            'target' => $target,
            'verified' => false,
        ]);

        $this->sendOtp($target, $otp, 'Password Reset', $user->name ?? '');

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'target' => $target,
            'flow' => 'forgot-password',
        ]);
    }

    public function passwordResendOtp(Request $request): JsonResponse
    {
        $pending = $request->session()->get(self::PASSWORD_RESET_SESSION_KEY);

        if (!$pending || empty($pending['user_id'])) {
            return response()->json([
                'message' => 'Password reset session expired. Please start again.',
            ], 422);
        }

        $user = User::find($pending['user_id']);

        if (!$user) {
            $request->session()->forget(self::PASSWORD_RESET_SESSION_KEY);

            return response()->json([
                'message' => 'No matching account was found.',
            ], 422);
        }

        $otp = sixDigitRandCode();
        $user->otp = $otp;
        $user->save();

        $this->sendOtp($pending['target'], $otp, 'Password Reset', $user->name ?? '');

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'target' => $pending['target'],
        ]);
    }

    public function passwordVerifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = $request->session()->get(self::PASSWORD_RESET_SESSION_KEY);

        if (!$pending || empty($pending['user_id'])) {
            return response()->json([
                'message' => 'Password reset session expired. Please start again.',
            ], 422);
        }

        $user = User::find($pending['user_id']);

        if (!$user || (string) $user->otp !== $validated['otp']) {
            return response()->json([
                'message' => 'Incorrect OTP.',
            ], 422);
        }

        $user->otp = 'NULL';
        $user->save();

        $pending['verified'] = true;
        $request->session()->put(self::PASSWORD_RESET_SESSION_KEY, $pending);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    public function passwordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $pending = $request->session()->get(self::PASSWORD_RESET_SESSION_KEY);

        if (!$pending || empty($pending['user_id']) || empty($pending['verified'])) {
            return response()->json([
                'message' => 'Password reset session expired or not verified.',
            ], 422);
        }

        $user = User::find($pending['user_id']);

        if (!$user) {
            $request->session()->forget(self::PASSWORD_RESET_SESSION_KEY);

            return response()->json([
                'message' => 'No matching account was found.',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        $request->session()->forget(self::PASSWORD_RESET_SESSION_KEY);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type,
            'store_id' => $user->store_id,
        ];
    }

    /**
     * Active store for the session user (same scope as activateStore / stores).
     *
     * @return array{id:int,name:string,url:string,store_url:string,website_url:string,logo_url:?string}|null
     */
    private function activeStoreSummary($user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        $customer = Customer::where('uid', $user->id)->first();
        if (!$customer) {
            return null;
        }

        $raw = $customer->active_store;
        if ($raw === null || $raw === '' || $raw === '0' || $raw === 0) {
            return null;
        }

        $storeId = (int) $raw;
        if ($storeId < 1) {
            return null;
        }

        $store = Store::query()
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$store) {
            return null;
        }

        $logoFile = optional($store->headerSetting)->logo;
        if ($logoFile === null || $logoFile === '' || strcasecmp((string) $logoFile, 'null') === 0) {
            $logoFile = WebsiteSetupDetails::query()
                ->where('store_id', $store->id)
                ->value('logo');
        }

        return [
            'id' => $store->id,
            'name' => $store->name,
            'url' => (string) ($store->url ?? ''),
            'store_url' => (string) ($store->url ?? ''),
            'website_url' => (string) ($store->url ?? ''),
            'logo_url' => $this->resolveStoreLogoPublicUrl($logoFile),
        ];
    }

    /**
     * Public URL for a store logo file (headersettings / website setup; disk or public assets).
     */
    private function resolveStoreLogoPublicUrl($logo): ?string
    {
        if ($logo === null) {
            return null;
        }

        $logo = trim((string) $logo);
        if ($logo === '' || strcasecmp($logo, 'null') === 0) {
            return null;
        }

        if (str_contains($logo, '..')) {
            return null;
        }

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        $logo = str_replace('\\', '/', $logo);
        $logo = ltrim($logo, '/');

        // Paths written from media library or other flows (same rules as resolveSettingAssetPublicUrl).
        if (Str::startsWith($logo, ['storage/', 'assets/', 'image-library/'])) {
            return rtrim($this->assetOrigin(), '/') . '/' . $logo;
        }

        $storageKey = str_starts_with($logo, 'setting/')
            ? $logo
            : 'setting/' . $logo;

        if (Storage::disk('public')->exists($storageKey)) {
            return url(Storage::disk('public')->url($storageKey));
        }

        $publicPath = 'assets/images/setting/' . $logo;
        if (file_exists(public_path($publicPath))) {
            return asset($publicPath);
        }

        $base = basename($logo);
        if ($base !== $logo) {
            $publicBase = 'assets/images/setting/' . $base;
            if (file_exists(public_path($publicBase))) {
                return asset($publicBase);
            }
        }

        return null;
    }

    /**
     * Public URL for the first product image from comma-separated `products.images`.
     */
    private function resolveProductImagePublicUrl($images): ?string
    {
        if ($images === null) {
            return null;
        }

        $raw = trim((string) $images);
        if ($raw === '' || strcasecmp($raw, 'null') === 0) {
            return null;
        }

        $first = trim(explode(',', $raw)[0] ?? '');
        if ($first === '' || str_contains($first, '..')) {
            return null;
        }

        if (Str::startsWith($first, ['http://', 'https://'])) {
            return $first;
        }

        $first = ltrim(str_replace('\\', '/', $first), '/');
        $origin = $this->assetOrigin();

        if (Str::startsWith($first, 'storage/image-library/') || Str::startsWith($first, 'storage/ai-seed-library/')) {
            return $this->mediaLibraryFileUrl(Str::after($first, 'storage/'), $this->mediaStoreIdFromPath($first));
        }
        if (Str::startsWith($first, 'storage/')) {
            return $origin . '/' . $first;
        }
        if (Str::startsWith($first, ['react-admin-media/', 'image-library/', 'ai-seed-library/'])) {
            return $this->mediaLibraryFileUrl($first, $this->mediaStoreIdFromPath($first));
        }

        return $origin . '/assets/images/product/' . $first;
    }

    private function resolveProductImageTokenPublicUrl(string $token): ?string
    {
        $clean = trim((string) $token);
        if ($clean === '' || str_contains($clean, '..')) {
            return null;
        }
        if (Str::startsWith($clean, ['http://', 'https://'])) {
            return $clean;
        }
        $clean = ltrim(str_replace('\\', '/', $clean), '/');
        $origin = $this->assetOrigin();
        if (Str::startsWith($clean, 'storage/image-library/') || Str::startsWith($clean, 'storage/ai-seed-library/')) {
            return $this->mediaLibraryFileUrl(Str::after($clean, 'storage/'), $this->mediaStoreIdFromPath($clean));
        }
        if (Str::startsWith($clean, 'storage/')) {
            return $origin . '/' . $clean;
        }
        if (Str::startsWith($clean, ['react-admin-media/', 'image-library/', 'ai-seed-library/'])) {
            return $this->mediaLibraryFileUrl($clean, $this->mediaStoreIdFromPath($clean));
        }
        if (Str::startsWith($clean, 'assets/')) {
            return $origin . '/' . $clean;
        }
        return $origin . '/assets/images/product/' . $clean;
    }

    /**
     * Public URL for icon pack image (supports absolute URL, storage path, assets path, or filename).
     */
    private function resolveIconImagePublicUrl($image): ?string
    {
        if ($image === null) {
            return null;
        }

        $image = trim((string) $image);
        if ($image === '' || strcasecmp($image, 'null') === 0 || str_contains($image, '..')) {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        $path = ltrim(str_replace('\\', '/', $image), '/');
        $origin = $this->assetOrigin();

        if (Str::startsWith($path, 'storage/image-library/') || Str::startsWith($path, 'storage/ai-seed-library/')) {
            return $this->mediaLibraryFileUrl(Str::after($path, 'storage/'), $this->mediaStoreIdFromPath($path));
        }
        if (Str::startsWith($path, ['storage/', 'assets/'])) {
            return $origin . '/' . $path;
        }
        if (Str::startsWith($path, ['image-library/', 'ai-seed-library/'])) {
            return $this->mediaLibraryFileUrl($path, $this->mediaStoreIdFromPath($path));
        }

        return $origin . '/assets/images/icon/' . $path;
    }

    /**
     * Resolve category/subcategory media URL from absolute URL, storage/assets path, or filename.
     */
    private function resolveCatalogAssetPublicUrl($pathValue, string $defaultBasePath): ?string
    {
        if ($pathValue === null) {
            return null;
        }

        $value = trim((string) $pathValue);
        if ($value === '' || strcasecmp($value, 'null') === 0 || str_contains($value, '..')) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $path = ltrim(str_replace('\\', '/', $value), '/');
        $origin = $this->assetOrigin();

        if (Str::startsWith($path, 'storage/image-library/') || Str::startsWith($path, 'storage/ai-seed-library/')) {
            return $this->mediaLibraryFileUrl(Str::after($path, 'storage/'), $this->mediaStoreIdFromPath($path));
        }
        if (Str::startsWith($path, ['image-library/', 'ai-seed-library/'])) {
            return $this->mediaLibraryFileUrl($path, $this->mediaStoreIdFromPath($path));
        }
        if (Str::startsWith($path, ['storage/', 'assets/'])) {
            return $origin . '/' . $path;
        }

        return $origin . '/' . trim($defaultBasePath, '/') . '/' . $path;
    }

    private function normalizeShippingMethods($input): array
    {
        $items = [];
        foreach ((array) $input as $index => $item) {
            if (!is_array($item)) continue;
            $area = trim((string) ($item['area'] ?? ''));
            if ($area === '') continue;
            $items[] = [
                'id' => (int) ($item['id'] ?? ($index + 1)),
                'area' => $area,
                'cost' => (float) ($item['cost'] ?? 0),
            ];
        }

        if (empty($items)) {
            return [
                ['id' => 1, 'area' => 'Inside Dhaka', 'cost' => 60],
                ['id' => 2, 'area' => 'Outside Dhaka', 'cost' => 120],
            ];
        }

        return array_values(array_map(function ($row, $idx) {
            return [
                'id' => $idx + 1,
                'area' => (string) ($row['area'] ?? ''),
                'cost' => (float) ($row['cost'] ?? 0),
            ];
        }, $items, array_keys($items)));
    }

    private function registrationModeFromAuthType(string $authType): string
    {
        $normalized = strtolower(trim($authType));
        if ($normalized === 'email') return 'email';
        if ($normalized === 'emaileasyorder') return 'email_easy';
        if ($normalized === 'easyorder') return 'easy';
        return 'phone';
    }

    private function authTypeFromRegistrationMode(string $registrationMode): string
    {
        if ($registrationMode === 'email') return 'email';
        if ($registrationMode === 'email_easy') return 'EmailEasyOrder';
        if ($registrationMode === 'easy') return 'EasyOrder';
        return 'phone';
    }

    private function resolveSettingAssetPublicUrl($value, string $defaultBasePath): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'null') === 0 || str_contains($value, '..')) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $path = ltrim(str_replace('\\', '/', $value), '/');
        $origin = $this->assetOrigin();

        if (Str::startsWith($path, ['storage/', 'assets/'])) {
            return $origin . '/' . $path;
        }

        if (Str::startsWith($path, 'image-library/')) {
            return $origin . '/storage/' . $path;
        }

        $storagePath = 'setting/' . $path;
        if (Storage::disk('public')->exists($storagePath)) {
            return $origin . '/storage/' . $storagePath;
        }

        $libraryBase = trim(str_replace('\\', '/', Str::after($defaultBasePath, 'assets/images/')), '/');
        $libraryFolder = match ($libraryBase) {
            'setting/favicon' => 'settings/favicons',
            'setting' => 'settings',
            default => $libraryBase,
        };
        if ($libraryFolder !== '') {
            $libraryPath = 'image-library/superadmin/' . $libraryFolder . '/' . basename($path);
            if (Storage::disk('public')->exists($libraryPath)) {
                return $origin . '/storage/' . $libraryPath;
            }
        }

        return $origin . '/' . trim($defaultBasePath, '/') . '/' . basename($path);
    }

    private function assetOrigin(): string
    {
        $requestOrigin = request()->getSchemeAndHttpHost();
        if ($requestOrigin !== '') {
            return rtrim($requestOrigin, '/');
        }

        $configuredAppUrl = trim((string) config('app.url'));
        return rtrim($configuredAppUrl !== '' ? $configuredAppUrl : '', '/');
    }

    private function extractAddonText($value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $text = trim((string) $item);
                if ($text !== '') {
                    return $text;
                }
            }
            return '';
        }
        if ($value === null) {
            return '';
        }
        return trim((string) $value);
    }

    private function extractAddonNumber($value): float
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $num = $this->extractAddonNumber($item);
                if ($num > 0) {
                    return $num;
                }
            }
            return 0;
        }
        if ($value === null) {
            return 0;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0;
        }
        $normalized = preg_replace('/[^0-9.]+/', '', $raw);
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0;
        }
        return (float) $normalized;
    }

    private function normalizeAddonVector($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value === null) {
            return [];
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return array_values($decoded);
                }
            }
            return [$trimmed];
        }
        return [$value];
    }

    private function resolveAddonImagePublicUrl($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $path = trim((string) $value);
        if ($path === '' || strcasecmp($path, 'null') === 0 || str_contains($path, '..')) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        $clean = ltrim(str_replace('\\', '/', $path), '/');
        $origin = $this->assetOrigin();
        if (Str::startsWith($clean, 'storage/image-library/') || Str::startsWith($clean, 'storage/ai-seed-library/')) {
            return $this->mediaLibraryFileUrl(Str::after($clean, 'storage/'), $this->mediaStoreIdFromPath($clean));
        }
        if (Str::startsWith($clean, ['image-library/', 'ai-seed-library/'])) {
            return $this->mediaLibraryFileUrl($clean, $this->mediaStoreIdFromPath($clean));
        }
        if (Str::startsWith($clean, ['storage/', 'assets/', 'addons/', 'modulus/'])) {
            return $origin . '/' . $clean;
        }

        $addonsRelative = 'addons/' . basename($clean);
        if (file_exists(public_path($addonsRelative))) {
            return $origin . '/' . $addonsRelative;
        }

        $modulusRelative = 'modulus/' . basename($clean);
        if (file_exists(public_path($modulusRelative))) {
            return $origin . '/' . $modulusRelative;
        }

        return $origin . '/assets/images/addons/' . basename($clean);
    }

    private function copyMediaLibraryAssetToProductDirectory(string $path, string $customerId, string $storeId): ?string
    {
        $cleanPath = $this->normalizeMediaLibraryDiskPath($path);
        if ($cleanPath === '' || str_contains($cleanPath, '..')) {
            return null;
        }

        $baseDir = trim($this->mediaLibraryDirectory($customerId, $storeId), '/') . '/';
        $legacyBaseDir = trim((string) $this->legacyMediaLibraryBaseFromPath($cleanPath), '/') . '/';
        if (!str_starts_with($cleanPath, $baseDir) && ($legacyBaseDir === '/' || !str_starts_with($cleanPath, $legacyBaseDir))) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($cleanPath)) {
            return null;
        }

        return 'storage/' . $cleanPath;
    }

    private function copyMediaLibraryAssetToPublicDirectory(
        string $path,
        string $customerId,
        string $storeId,
        string $targetDirectory,
        string $namePrefix = 'media_'
    ): ?string {
        $cleanPath = $this->normalizeMediaLibraryDiskPath($path);
        if ($cleanPath === '' || str_contains($cleanPath, '..')) {
            return null;
        }

        $baseDir = trim($this->mediaLibraryDirectory($customerId, $storeId), '/') . '/';
        $legacyBaseDir = trim((string) $this->legacyMediaLibraryBaseFromPath($cleanPath), '/') . '/';
        if (!str_starts_with($cleanPath, $baseDir) && ($legacyBaseDir === '/' || !str_starts_with($cleanPath, $legacyBaseDir))) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($cleanPath)) {
            return null;
        }

        return 'storage/' . $cleanPath;
    }

    private function storeUploadedPublicImage($file, string $directory): string
    {
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'jpg'));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';

        $normalizedDirectory = trim(str_replace('\\', '/', $directory), '/');

        $originalLogical = $this->sanitizeMediaFilename((string) $file->getClientOriginalName());
        if ($originalLogical === 'file' || $originalLogical === '') {
            $originalLogical = 'upload.' . $ext;
        } elseif (!str_contains($originalLogical, '.')) {
            $originalLogical = $originalLogical . '.' . $ext;
        }

        if (in_array($normalizedDirectory, ['assets/images/setting', 'assets/images/setting/favicon'], true)) {
            $targetDir = public_path($normalizedDirectory);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            $filename = $this->resolveUniquePublicDiskFilename($targetDir, $originalLogical);
            $file->move($targetDir, $filename);

            return $filename;
        }

        $libraryDir = $this->mediaLibraryDirectoryForLegacyPublicDirectory($directory);
        if ($libraryDir !== null) {
            $disk = Storage::disk('public');
            $filename = $this->resolveUniqueMediaFilename($disk, $libraryDir, $originalLogical);
            $stored = $file->storeAs($libraryDir, $filename, 'public');

            return 'storage/' . ltrim((string) $stored, '/');
        }

        $targetDir = public_path(trim($directory, '/'));
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filename = $this->resolveUniquePublicDiskFilename($targetDir, $originalLogical);
        $file->move($targetDir, $filename);

        return $filename;
    }

    private function mediaLibraryDirectoryForLegacyPublicDirectory(string $directory): ?string
    {
        $folderMap = [
            'assets/images/product' => 'products',
            'assets/images/category' => 'categories',
            'assets/images/brand' => 'brands',
            'assets/images/icon' => 'icons',
            'assets/images/setting' => 'settings',
            'assets/images/setting/favicon' => 'settings/favicons',
            'assets/images/design' => 'themes',
            'assets/images/template' => 'templates',
            'addons' => 'addons',
            'modulus' => 'modulus',
            'BlogImages' => 'blogs',
        ];

        $normalized = trim(str_replace('\\', '/', $directory), '/');
        $folder = $folderMap[$normalized] ?? Str::after($normalized, 'assets/images/');
        $folder = $this->sanitizeMediaFolder((string) $folder);
        if ($folder === '') {
            return null;
        }

        $user = request()->user();
        $userType = strtolower((string) ($user->type ?? ''));
        if (in_array($userType, ['superadmin', 'superstaff'], true)) {
            return $this->superAdminMediaLibraryDirectory($folder);
        }

        $ctx = $this->resolveReactStoreContext(request());
        if (!empty($ctx['error']) || empty($ctx['store']) || empty($ctx['customer'])) {
            return null;
        }

        return $this->adminMediaLibraryDirectory($ctx['store'], $ctx['customer'], $folder);
    }

    /**
     * @return array{user:?User,customer:?Customer,store:?Store,error:?JsonResponse}
     */
    private function resolveReactStoreContext(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return ['user' => null, 'customer' => null, 'store' => null, 'error' => response()->json(['message' => 'Unauthenticated.'], 401)];
        }

        $customer = Customer::where('uid', $user->id)->first();
        if (!$customer) {
            return ['user' => $user, 'customer' => null, 'store' => null, 'error' => response()->json(['message' => 'Customer record not found.'], 422)];
        }

        $requestedStore = trim((string) $request->query('store_id', ''));
        $activeStore = trim((string) ($customer->active_store ?? ''));
        $storeId = $requestedStore !== '' ? $requestedStore : $activeStore;
        if ($storeId === '' || $storeId === '0') {
            return ['user' => $user, 'customer' => $customer, 'store' => null, 'error' => response()->json(['message' => 'No active store selected.'], 422)];
        }

        $store = Store::query()
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->where('customer_id', $customer->id)
            ->first();
        if (!$store) {
            return ['user' => $user, 'customer' => $customer, 'store' => null, 'error' => response()->json(['message' => 'Store not found or access denied.'], 404)];
        }

        return ['user' => $user, 'customer' => $customer, 'store' => $store, 'error' => null];
    }

    private function dashboardCurrencySymbol(?Headersetting $headerSetting): string
    {
        $symbol = trim((string) ($headerSetting->symbol ?? ''));
        return $symbol !== '' ? $symbol : '৳';
    }

    private function dashboardOrdersRevenue($orders): float
    {
        return collect($orders)->sum(function ($order) {
            foreach (['total', 'subtotal'] as $column) {
                if (isset($order->{$column}) && $order->{$column} !== null && $order->{$column} !== '') {
                    return (float) $order->{$column};
                }
            }
            return 0;
        });
    }

    private function dashboardOrderIsPending($status): bool
    {
        $value = strtolower(trim((string) $status));
        return in_array($value, ['pending', 'on hold', 'hold', 'new'], true);
    }

    private function dashboardOrderIsProcessing($status): bool
    {
        $value = strtolower(trim((string) $status));
        return in_array($value, ['processing', 'shipping', 'confirmed'], true);
    }

    private function dashboardCustomerBucket($order): ?string
    {
        $email = strtolower(trim((string) ($order->email ?? '')));
        if ($email !== '') return 'email:' . $email;

        $phone = preg_replace('/\D+/', '', (string) ($order->phone ?? ''));
        if ($phone !== '') return 'phone:' . $phone;

        $uid = trim((string) ($order->uid ?? ''));
        if ($uid !== '') return 'uid:' . $uid;

        return null;
    }

    private function dashboardApplyCustomerBucket($query, $order)
    {
        $email = strtolower(trim((string) ($order->email ?? '')));
        if ($email !== '') {
            return $query->whereRaw('LOWER(email) = ?', [$email]);
        }

        $phone = preg_replace('/\D+/', '', (string) ($order->phone ?? ''));
        if ($phone !== '') {
            return $query->where('phone', 'like', '%' . $phone . '%');
        }

        $uid = trim((string) ($order->uid ?? ''));
        if ($uid !== '') {
            return $query->where('uid', $uid);
        }

        return $query->whereRaw('1 = 0');
    }

    private function dashboardPercentChange(float $previous, float $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function dashboardRevenueTrend(int $storeId, Carbon $startDate, Carbon $endDate): array
    {
        $days = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $days[$cursor->toDateString()] = 0.0;
            $cursor->addDay();
        }

        $orders = Order::query()
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['created_at', 'total', 'subtotal']);

        foreach ($orders as $order) {
            $dayKey = Carbon::parse($order->created_at)->toDateString();
            if (!array_key_exists($dayKey, $days)) continue;
            $days[$dayKey] += isset($order->total) && $order->total !== null && $order->total !== ''
                ? (float) $order->total
                : (float) ($order->subtotal ?? 0);
        }

        return collect($days)->map(function ($amount, $dayKey) {
            return [
                'date' => $dayKey,
                'label' => Carbon::parse($dayKey)->format('d M'),
                'amount' => round((float) $amount, 2),
            ];
        })->values()->all();
    }

    private function dashboardTopProducts(int $storeId, Carbon $startDate, Carbon $endDate, string $currencySymbol): array
    {
        $items = Orderitem::query()
            ->join('orders', 'orders.id', '=', 'orderitems.order_id')
            ->leftJoin('products', 'products.id', '=', 'orderitems.product_id')
            ->where('orders.store_id', $storeId)
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('orderitems.product_id', 'products.name')
            ->selectRaw('orderitems.product_id as product_id, COALESCE(products.name, CONCAT("Product #", orderitems.product_id)) as product_name, SUM(CAST(orderitems.quantity AS DECIMAL(18,2))) as units_sold, SUM(CAST(orderitems.price AS DECIMAL(18,2)) * CAST(orderitems.quantity AS DECIMAL(18,2))) as revenue')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return $items->map(function ($item) use ($currencySymbol) {
            return [
                'product_id' => (int) ($item->product_id ?? 0),
                'name' => (string) ($item->product_name ?? 'Unnamed product'),
                'units_sold' => round((float) ($item->units_sold ?? 0), 2),
                'revenue' => round((float) ($item->revenue ?? 0), 2),
                'revenue_label' => $currencySymbol . number_format((float) ($item->revenue ?? 0), 2),
            ];
        })->values()->all();
    }

    private function dashboardTrafficSources(int $storeId, Carbon $startDate, Carbon $endDate): array
    {
        if (!Schema::hasTable('admin_user_analytics')) {
            return [];
        }

        $rows = AdminUserAnalytics::query()
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $buckets = [
            'Facebook Ads' => 0,
            'Facebook / Social' => 0,
            'Organic Search' => 0,
            'Direct' => 0,
            'Other' => 0,
        ];

        foreach ($rows as $row) {
            $sourceText = strtolower(trim((string) (($row->source ?? '') . ' ' . ($row->utm_source ?? '') . ' ' . ($row->referer ?? '') . ' ' . ($row->url ?? ''))));
            if (str_contains($sourceText, 'facebook') && (str_contains($sourceText, 'ads') || str_contains($sourceText, 'meta'))) {
                $buckets['Facebook Ads']++;
            } elseif (str_contains($sourceText, 'facebook') || str_contains($sourceText, 'instagram') || str_contains($sourceText, 'tiktok')) {
                $buckets['Facebook / Social']++;
            } elseif (str_contains($sourceText, 'google') || str_contains($sourceText, 'search') || str_contains($sourceText, 'organic')) {
                $buckets['Organic Search']++;
            } elseif ($sourceText === '') {
                $buckets['Direct']++;
            } else {
                $buckets['Other']++;
            }
        }

        $total = max(array_sum($buckets), 1);

        return collect($buckets)->map(function ($count, $label) use ($total) {
            return [
                'label' => $label,
                'visits' => $count,
                'share' => round(($count / $total) * 100, 1),
            ];
        })->values()->all();
    }

    private function dashboardPackageInfo(Store $store, string $currencySymbol): array
    {
        $websitePlan = null;
        if (!empty($store->plan_id)) {
            $plan = Plan::query()->find($store->plan_id);
            if ($plan) {
                $websitePlan = [
                    'name' => (string) ($plan->name ?? 'Website package'),
                    'price_label' => $currencySymbol . number_format((float) ($plan->price ?? 0), 2),
                    'expiry_date' => !empty($store->expiry_date) ? Carbon::parse($store->expiry_date)->toDateString() : null,
                    'status' => !empty($store->expiry_date) && Carbon::parse($store->expiry_date)->gte(Carbon::now()) ? 'Active' : 'Inactive',
                ];
            }
        }

        $smsAddon = AddonsExpired::query()
            ->with('addonsName')
            ->where('store_id', $store->id)
            ->where(function ($q) {
                $q->where('addons_id', 5)
                    ->orWhereHas('addonsName', function ($sub) {
                        $sub->where('title', 'like', '%sms%')
                            ->orWhere('heading', 'like', '%sms%');
                    });
            })
            ->latest('id')
            ->first();

        $mobileAddon = AddonsExpired::query()
            ->with('addonsName')
            ->where('store_id', $store->id)
            ->where(function ($q) {
                $q->whereHas('addonsName', function ($sub) {
                    $sub->where('title', 'like', '%mobile%')
                        ->orWhere('heading', 'like', '%mobile%')
                        ->orWhere('title', 'like', '%app%')
                        ->orWhere('heading', 'like', '%app%');
                });
            })
            ->latest('id')
            ->first();

        $digitalPlan = null;
        if ($this->tableHasColumn('stores', 'digital_plan_id') && !empty($store->digital_plan_id)) {
            $digital = Digitalplan::query()->find($store->digital_plan_id);
            if ($digital) {
                $digitalPlan = [
                    'name' => (string) ($digital->name ?? 'Mobile app package'),
                    'price_label' => $currencySymbol . number_format((float) ($digital->price ?? 0), 2),
                    'expiry_date' => $this->tableHasColumn('stores', 'digital_plan_end_date') && !empty($store->digital_plan_end_date)
                        ? Carbon::parse($store->digital_plan_end_date)->toDateString()
                        : null,
                    'status' => $this->tableHasColumn('stores', 'digital_plan_end_date') && !empty($store->digital_plan_end_date) && Carbon::parse($store->digital_plan_end_date)->gte(Carbon::now())
                        ? 'Active'
                        : 'Inactive',
                ];
            }
        }

        $posPlan = null;
        if ($this->tableHasColumn('stores', 'pos_plan_id') && !empty($store->pos_plan_id)) {
            $pos = Posplan::query()->find($store->pos_plan_id);
            if ($pos) {
                $posPlan = [
                    'name' => (string) ($pos->name ?? 'POS package'),
                    'price_label' => $currencySymbol . number_format((float) ($pos->price ?? 0), 2),
                    'expiry_date' => $this->tableHasColumn('stores', 'pos_plan_expiry_date') && !empty($store->pos_plan_expiry_date)
                        ? Carbon::parse($store->pos_plan_expiry_date)->toDateString()
                        : null,
                ];
            }
        }

        if (!$posPlan) {
            $posAddon = AddonsExpired::query()
                ->where('store_id', $store->id)
                ->whereNotNull('pos_plan_id')
                ->latest('id')
                ->first();

            if ($posAddon && !empty($posAddon->pos_plan_id)) {
                $pos = Posplan::query()->find($posAddon->pos_plan_id);
                if ($pos) {
                    $posPlan = [
                        'name' => (string) ($pos->name ?? 'POS package'),
                        'price_label' => $currencySymbol . number_format((float) ($pos->price ?? 0), 2),
                        'expiry_date' => null,
                    ];
                }
            }
        }

        return [
            'website_package' => $websitePlan,
            'sms_package' => $smsAddon ? [
                'name' => (string) (optional($smsAddon->addonsName)->title ?: optional($smsAddon->addonsName)->heading ?: 'SMS package'),
                'remaining' => max(0, (int) ($smsAddon->total ?? 0) - (int) ($smsAddon->used ?? 0)),
                'total' => (int) ($smsAddon->total ?? 0),
                'status' => (int) ($smsAddon->status ?? 0) === 1 ? 'Active' : 'Inactive',
            ] : null,
            'mobile_app_package' => $digitalPlan ?: ($mobileAddon ? [
                'name' => (string) (optional($mobileAddon->addonsName)->title ?: optional($mobileAddon->addonsName)->heading ?: 'Mobile app'),
                'remaining' => max(0, (int) ($mobileAddon->total ?? 0) - (int) ($mobileAddon->used ?? 0)),
                'total' => (int) ($mobileAddon->total ?? 0),
                'status' => (int) ($mobileAddon->status ?? 0) === 1 ? 'Active' : 'Inactive',
            ] : null),
            'pos_package' => $posPlan,
        ];
    }

    private function dashboardWebsiteCompleteness(Store $store, ?Headersetting $headerSetting): array
    {
        $categories = Category::query()->where('store_id', $store->id)->where('parent', 0)->count();
        $products = Product::query()->where('store_id', $store->id)->count();
        $menus = Schema::hasTable('menus') ? DB::table('menus')->where('store_id', $store->id)->count() : 0;
        $sliders = Schema::hasTable('sliders') ? Slider::query()->where('store_id', $store->id)->count() : 0;
        $pages = Schema::hasTable('pages') ? Page::query()->where('store_id', $store->id)->count() : 0;
        $domains = Schema::hasTable('domains') ? Domain::query()->where('store_id', $store->id)->count() : 0;
        $design = Schema::hasTable('designs') ? DB::table('designs')->where('store_id', $store->id)->first() : null;

        $headerReady = $headerSetting
            && !empty($headerSetting->short_description)
            && !empty($headerSetting->phone)
            && !empty($headerSetting->email)
            && !empty($headerSetting->address)
            && !empty($headerSetting->shipping_area_1)
            && !empty($headerSetting->shipping_area_1_cost);

        $steps = [
            ['label' => 'Categories', 'complete' => $categories > 4, 'value' => $categories, 'hint' => 'Add main categories'],
            ['label' => 'Products', 'complete' => $products > 10, 'value' => $products, 'hint' => 'Add product catalog'],
            ['label' => 'Store info', 'complete' => $headerReady, 'value' => $headerReady ? 1 : 0, 'hint' => 'Complete website information'],
            ['label' => 'Header menu', 'complete' => $menus > 0, 'value' => $menus, 'hint' => 'Create header navigation'],
            ['label' => 'Slider', 'complete' => $sliders > 0, 'value' => $sliders, 'hint' => 'Add homepage slider'],
            ['label' => 'Pages', 'complete' => $pages > 0, 'value' => $pages, 'hint' => 'Publish support pages'],
            ['label' => 'Domain', 'complete' => $domains > 1, 'value' => $domains, 'hint' => 'Connect custom domain'],
            ['label' => 'Theme', 'complete' => !empty($design?->template_id) && (string) $design->template_id !== '0', 'value' => (int) ($design->template_id ?? 0), 'hint' => 'Select a design template'],
        ];

        $completed = collect($steps)->where('complete', true)->count();
        $percentage = round(($completed / max(count($steps), 1)) * 100, 1);

        return [
            'completed_steps' => $completed,
            'total_steps' => count($steps),
            'percentage' => $percentage,
            'steps' => $steps,
        ];
    }

    private function dashboardSmartAlerts(Store $store, ?Headersetting $headerSetting, Carbon $startDate, Carbon $endDate, string $currencySymbol): array
    {
        $alerts = [];

        $stockThreshold = $headerSetting && isset($headerSetting->stock_out_qty)
            ? (float) $headerSetting->stock_out_qty
            : 5.0;

        $lowStockCount = Product::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->whereNotNull('quantity')
            ->whereRaw('CAST(quantity AS DECIMAL(18,2)) <= ?', [$stockThreshold])
            ->count();

        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Low stock warning',
                'message' => $lowStockCount . ' products are close to stock out.',
                'value' => $lowStockCount,
            ];
        }

        $abandonedCartValue = 0.0;
        $abandonedCartCount = 0;
        if (Schema::hasTable('carts')) {
            $cartQuery = DB::table('carts')->where('store_id', $store->id);
            if ($this->tableHasColumn('carts', 'created_at')) {
                $cartQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $abandonedCartCount = (clone $cartQuery)->count();
            if ($this->tableHasColumn('carts', 'total')) {
                $abandonedCartValue = (float) ((clone $cartQuery)->sum('total') ?? 0);
            } elseif ($this->tableHasColumn('carts', 'qty') && $this->tableHasColumn('carts', 'price')) {
                $abandonedCartValue = (clone $cartQuery)->get(['qty', 'price'])->sum(function ($row) {
                    return (float) ($row->qty ?? 0) * (float) ($row->price ?? 0);
                });
            }
        }

        if ($abandonedCartCount > 0 || $abandonedCartValue > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Abandoned cart alert',
                'message' => $abandonedCartCount . ' carts worth ' . $currencySymbol . number_format($abandonedCartValue, 2) . ' are waiting for recovery.',
                'value' => $abandonedCartCount,
            ];
        }

        $newReviewCount = Schema::hasTable('reviews')
            ? Review::query()->where('store_id', $store->id)->whereBetween('created_at', [$startDate, $endDate])->count()
            : 0;

        if ($newReviewCount > 0) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'New review notification',
                'message' => $newReviewCount . ' new reviews need a reply.',
                'value' => $newReviewCount,
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'No urgent alert',
                'message' => 'Stock, reviews, and carts look stable for this period.',
                'value' => 0,
            ];
        }

        return $alerts;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function staffAccessPermissionKeys(): array
    {
        return [
            'branch',
            'product',
            'category',
            'subcategory',
            'brand',
            'attribute',
            'supplier',
            'collection',
            'global_tab',
            'coupon',
            'campaign',
            'offer',
            'slider',
            'banner',
            'layouts',
            'template',
            'header',
            'homepage',
            'footer',
            'mobilemenu',
            'product_display',
            'product_grid',
            'shop_page',
            'pages',
            'customer',
            'staff',
            'invoice',
            'setting',
            'role_permission',
            'pos',
            'testimonials',
            'theme_customize',
            'activity_log',
            'inventory',
        ];
    }

    private function staffAccessPermissionCatalog(): array
    {
        $catalog = [];

        $legacyGroups = [
            'Catalog & Inventory' => ['branch', 'product', 'category', 'subcategory', 'brand', 'attribute', 'supplier', 'inventory'],
            'Marketing & Design' => ['campaign', 'offer', 'slider', 'banner', 'layouts', 'template', 'header', 'homepage', 'footer', 'mobilemenu', 'product_display', 'product_grid', 'shop_page', 'pages', 'testimonials', 'theme_customize'],
            'Operations' => ['customer', 'staff', 'invoice', 'role_permission', 'pos', 'collection', 'global_tab', 'coupon', 'setting', 'activity_log'],
        ];

        foreach ($legacyGroups as $group => $keys) {
            foreach ($keys as $key) {
                $catalog[$key] = [
                    'key' => $key,
                    'label' => $this->humanizeFeatureKey($key),
                    'group' => $group,
                    'type' => 'legacy',
                    'source' => 'laravel',
                ];
            }
        }

        $routesFile = base_path('../Admin_FrontEnd_React/src/routes.jsx');
        if (is_file($routesFile)) {
            $content = @file_get_contents($routesFile) ?: '';
            if ($content !== '') {
                preg_match_all('/feature="([^"]+)"/', $content, $featureMatches);
                foreach (($featureMatches[1] ?? []) as $rawKey) {
                    $rawKey = trim((string) $rawKey);
                    if ($rawKey === '') {
                        continue;
                    }
                    $catalog[$rawKey] = [
                        'key' => $rawKey,
                        'label' => $this->humanizeFeatureKey($rawKey),
                        'group' => 'Admin Pages',
                        'type' => 'page',
                        'source' => 'react-route-guard',
                    ];
                }

                preg_match_all('/can\("([^"]+)"\)/', $content, $routeActionMatches);
                foreach (($routeActionMatches[1] ?? []) as $rawKey) {
                    $rawKey = trim((string) $rawKey);
                    if ($rawKey === '') {
                        continue;
                    }
                    $catalog[$rawKey] = [
                        'key' => $rawKey,
                        'label' => $this->humanizeFeatureKey($rawKey),
                        'group' => 'Admin Functions',
                        'type' => 'action',
                        'source' => 'react-route',
                    ];
                }
            }
        }

        $reactSourceDir = base_path('../Admin_FrontEnd_React/src');
        if (is_dir($reactSourceDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($reactSourceDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (!preg_match('/\.(jsx|js|tsx|ts)$/', $path)) {
                    continue;
                }
                if (str_contains($path, '/superadmin/')) {
                    continue;
                }

                $content = @file_get_contents($path) ?: '';
                if ($content === '') {
                    continue;
                }

                preg_match_all('/can\("([^"]+)"\)/', $content, $actionMatches);
                foreach (($actionMatches[1] ?? []) as $rawKey) {
                    $rawKey = trim((string) $rawKey);
                    if ($rawKey === '') {
                        continue;
                    }
                    $catalog[$rawKey] = [
                        'key' => $rawKey,
                        'label' => $this->humanizeFeatureKey($rawKey),
                        'group' => str_starts_with($rawKey, 'actions.') ? 'Admin Functions' : 'Admin Options',
                        'type' => str_starts_with($rawKey, 'actions.') ? 'action' : 'option',
                        'source' => 'react-source',
                    ];
                }
            }
        }

        return array_values(collect($catalog)
            ->filter(fn ($item) => !empty($item['key']))
            ->sortBy([
                ['group', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all());
    }

    private function ensureRegistrationIsAvailable(?string $phone, ?string $email): void
    {
        $phone = trim((string) $phone);
        $email = trim((string) $email);

        if ($phone !== '') {
            $existingPhone = User::query()
                ->where('phone', $phone)
                ->whereIn('type', ['admin', 'dropshipper'])
                ->exists();

            if ($existingPhone) {
                abort(response()->json([
                    'message' => 'Phone number already exists. Please login your account.',
                ], 422));
            }
        }

        if ($email !== '') {
            $existingEmail = User::query()
                ->where('email', $email)
                ->whereIn('type', ['admin', 'dropshipper'])
                ->exists();

            if ($existingEmail) {
                abort(response()->json([
                    'message' => 'Email address already exists. Please login your account.',
                ], 422));
            }
        }
    }

    private function sendOtp(string $target, string $otp, string $subject, string $name = ''): void
    {
        if (filter_var($target, FILTER_VALIDATE_EMAIL)) {
            $data['name'] = $name ?: $target;
            $data['subject'] = $subject;
            $data['otp'] = $otp;
            $data['text'] = "Your eCommerceX OTP code is <strong style='font-size: 24px;letter-spacing:4px;'>{$otp}</strong>";
            $data['plainText'] = "Your eCommerceX OTP code is {$otp}. If you did not request this code, you can ignore this email.";
            $data['formEmail'] = config('mail.from.address');
            $data['fromName'] = config('mail.from.name', 'eCommerceX');

            Mail::to($target)->send(new OPTSendMail($data));

            return;
        }

        $text = "eCommerceX OTP code is {$otp}";
        $this->sendWhatsAppOtp($target, $text, $subject);
        smsLogger($target, $text, "{$subject} WhatsApp OTP Send");
    }

    private function sendRegistrationOtp(?string $phone, ?string $email, string $otp, string $name = ''): void
    {
        $phone = trim((string) $phone);
        $email = trim((string) $email);
        $errors = [];

        if ($phone !== '') {
            try {
                $this->sendOtp($phone, $otp, 'Registration', $name);
            } catch (\Throwable $exception) {
                $errors['phone'] = 'Unable to send OTP on WhatsApp. Please try again shortly.';
                Log::warning('React admin registration OTP WhatsApp failed.', [
                    'phone' => $phone,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $this->sendOtp($email, $otp, 'Registration', $name);
            } catch (\Throwable $exception) {
                $errors['email'] = 'Unable to send OTP by email. Please check mail settings and try again.';
                Log::warning('React admin registration OTP email failed.', [
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($phone === '' && $email === '') {
            $errors['message'] = 'Phone number or email address is required.';
        }

        if (!empty($errors)) {
            $errors['message'] = $errors['message'] ?? implode(' ', array_values($errors));
            throw ValidationException::withMessages($errors);
        }
    }

    private function registrationOtpChannels(?string $phone, ?string $email): array
    {
        return array_values(array_filter([
            trim((string) $phone) !== '' ? 'whatsapp' : null,
            trim((string) $email) !== '' ? 'email' : null,
        ]));
    }

    private function sendWhatsAppOtp(string $target, string $text, string $subject): void
    {
        try {
            $this->sendWhatsAppGatewayMessage(
                $target,
                $text,
                (string) config('whatsapp_automation.otp_source_type', 'otp')
            );
        } catch (\Throwable $exception) {
            Log::warning('React admin WhatsApp OTP failed.', [
                'target' => $target,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'message' => 'Unable to send OTP on WhatsApp. Please try again shortly.',
            ]);
        }
    }

    private function sendWhatsAppGatewayMessage(string $target, string $message, string $sourceType): array
    {
        $sessionId = $this->normalizeWhatsAppSessionId($target);

        if (!$sessionId) {
            throw new \InvalidArgumentException('Phone number is missing or invalid.');
        }

        $botApi = app(BotApiService::class);
        $queueError = null;

        try {
            $queued = $botApi->createOutbound([
                'session_id' => $sessionId,
                'bot_type' => (string) config('whatsapp_automation.otp_bot_type', 'support'),
                'source_type' => $sourceType,
                'message_type' => 'text',
                'message_text' => $message,
                'image_url' => '',
                'scheduled_for' => null,
            ]);

            $outboundId = (int) ($queued['outbound_id'] ?? 0);

            if ($outboundId > 0) {
                $dispatch = $botApi->dispatchOutbound($outboundId);
                if (($dispatch['success'] ?? false) !== true) {
                    throw new \RuntimeException((string) ($dispatch['error'] ?? 'WhatsApp dispatch failed.'));
                }
            }

            return $queued;
        } catch (\Throwable $exception) {
            $queueError = $exception;
            Log::warning('React admin WhatsApp OTP queue/dispatch failed.', [
                'target' => $target,
                'error' => $exception->getMessage(),
            ]);
        }

        $tenantId = $this->activeWhatsAppOtpTenantId();
        if ($tenantId !== '') {
            $direct = $botApi->sendGatewayTextMessage($tenantId, $sessionId, $message);
            if (($direct['success'] ?? false) === true) {
                return $direct;
            }

            throw new \RuntimeException((string) ($direct['message'] ?? 'Direct WhatsApp gateway send failed.'));
        }

        throw $queueError ?: new \RuntimeException('WhatsApp OTP dispatch failed.');
    }

    private function activeWhatsAppOtpTenantId(): string
    {
        return trim((string) (
            Cache::get(self::OTP_GATEWAY_TENANT_CACHE_KEY)
            ?: config('whatsapp_automation.otp_gateway_tenant_id', '')
        ));
    }

    private function normalizeWhatsAppSessionId(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '88' . $digits;
        }

        if (!str_starts_with($digits, '88') && strlen($digits) === 11) {
            $digits = '88' . $digits;
        }

        return $digits ?: null;
    }
}
