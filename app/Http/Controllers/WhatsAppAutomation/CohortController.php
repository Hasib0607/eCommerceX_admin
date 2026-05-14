<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Models\RenewalCampaignBatch;
use App\Models\RenewalCampaignBatchDispatch;
use App\Models\RenewalCampaignBatchItem;
use App\Models\SMSLogger;
use App\Models\Store;
use App\Models\User;
use App\Services\WhatsAppAutomation\BotApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;

class CohortController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function expiredClients(Request $request): JsonResponse
    {
        return response()->json($this->buildExpiredClientsPayload($request));
    }

    public function unsubscribedRegistrations(Request $request): JsonResponse
    {
        return response()->json($this->buildUnsubscribedPayload($request));
    }

    public function createFollowups(Request $request, string $cohort): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'reason' => ['required', 'string'],
            'note' => ['nullable', 'string'],
            'scheduled_for' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
        ]);

        $items = $this->resolveCohortSelection($cohort, $validated['ids']);
        $created = [];
        $errors = [];

        foreach ($items as $item) {
            $sessionId = $this->normalizePhoneToSessionId($item['phone'] ?? '');
            if (!$sessionId) {
                $errors[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'error' => 'Phone number is missing or invalid.',
                ];
                continue;
            }

            try {
                $result = $this->botApiService->createFollowupPlan([
                    'session_id' => $sessionId,
                    'reason' => $validated['reason'],
                    'note' => trim(($validated['note'] ?? '') . ' ' . $this->cohortNoteSuffix($item)),
                    'scheduled_for' => $validated['scheduled_for'] ?? null,
                    'priority' => $validated['priority'] ?? 'normal',
                ]);

                $created[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'session_id' => $sessionId,
                    'result' => $result,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => empty($errors),
            'message' => sprintf(
                'Created %d follow-up plan(s).%s',
                count($created),
                count($errors) ? ' Some rows failed.' : ''
            ),
            'created_count' => count($created),
            'failed_count' => count($errors),
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    public function queueOutbound(Request $request, string $cohort): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'bot_type' => ['required', 'string', 'in:sales,support'],
            'message_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
            'scheduled_for' => ['nullable', 'string'],
        ]);

        $items = $this->resolveCohortSelection($cohort, $validated['ids']);
        $queued = [];
        $errors = [];

        foreach ($items as $item) {
            $sessionId = $this->normalizePhoneToSessionId($item['phone'] ?? '');
            if (!$sessionId) {
                $errors[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'error' => 'Phone number is missing or invalid.',
                ];
                continue;
            }

            try {
                $result = $this->botApiService->createOutbound([
                    'session_id' => $sessionId,
                    'bot_type' => $validated['bot_type'],
                    'source_type' => 'manual',
                    'message_type' => 'image' === $this->resolveMessageType($validated['image_url'] ?? '')
                        ? 'image'
                        : 'text',
                    'message_text' => $validated['message_text'],
                    'image_url' => $validated['image_url'] ?? '',
                    'scheduled_for' => $validated['scheduled_for'] ?? null,
                ]);

                $queued[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'session_id' => $sessionId,
                    'outbound_id' => $result['outbound_id'] ?? null,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => empty($errors),
            'message' => sprintf(
                'Queued %d WhatsApp message(s).%s',
                count($queued),
                count($errors) ? ' Some rows failed.' : ''
            ),
            'queued_count' => count($queued),
            'failed_count' => count($errors),
            'queued' => $queued,
            'errors' => $errors,
        ]);
    }

    public function sendSms(Request $request, string $cohort): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'message_text' => ['required', 'string'],
            'purpose' => ['nullable', 'string'],
        ]);

        $items = $this->resolveCohortSelection($cohort, $validated['ids']);
        $sent = [];
        $errors = [];
        $purpose = $validated['purpose'] ?? 'Renewal Follow-up';

        foreach ($items as $item) {
            $phone = trim((string) ($item['phone'] ?? ''));
            if ($phone === '') {
                $errors[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'error' => 'Phone number is missing.',
                ];
                continue;
            }

            try {
                SendSms($phone, $validated['message_text']);
                smsLogger($phone, $validated['message_text'], $purpose, 1, $item['store_id'] ?? null);

                $sent[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'phone' => $phone,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $item['id'],
                    'name' => $item['display_name'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => empty($errors),
            'message' => sprintf(
                'Sent %d SMS message(s).%s',
                count($sent),
                count($errors) ? ' Some rows failed.' : ''
            ),
            'sent_count' => count($sent),
            'failed_count' => count($errors),
            'sent' => $sent,
            'errors' => $errors,
        ]);
    }

    public function listBatches(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 20), 100));
        $status = trim((string) $request->query('status', ''));
        $cohortKey = trim((string) $request->query('cohort_key', ''));
        $search = trim((string) $request->query('search', ''));

        $batchesQuery = RenewalCampaignBatch::query()
            ->withCount('items')
            ->latest('id');

        if ($status !== '' && in_array($status, ['active', 'archived'], true)) {
            $batchesQuery->where('status', $status);
        }

        if ($cohortKey !== '' && in_array($cohortKey, ['expired-clients', 'unsubscribed-registrations'], true)) {
            $batchesQuery->where('cohort_key', $cohortKey);
        }

        if ($search !== '') {
            $batchesQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('message_text', 'like', "%{$search}%");
            });
        }

        $batches = $batchesQuery
            ->limit($limit)
            ->get();

        foreach ($batches as $batch) {
            $this->syncBatchDispatchStatuses($batch);
        }

        return response()->json([
            'success' => true,
            'batches' => $batches->map(fn (RenewalCampaignBatch $batch) => $this->serializeBatchSummary($batch))->values(),
        ]);
    }

    public function cloneBatch(int $id): JsonResponse
    {
        $sourceBatch = RenewalCampaignBatch::query()
            ->with('items')
            ->findOrFail($id);

        $clonedBatch = RenewalCampaignBatch::create([
            'name' => $this->buildClonedBatchName($sourceBatch->name),
            'cohort_key' => $sourceBatch->cohort_key,
            'bot_type' => $sourceBatch->bot_type,
            'message_text' => $sourceBatch->message_text,
            'image_url' => $sourceBatch->image_url,
            'scheduled_for' => $sourceBatch->scheduled_for,
            'status' => 'active',
            'created_by' => request()->attributes->get('whatsapp_auth', [])['uid'] ?? null,
            'total_recipients' => $sourceBatch->items->count(),
        ]);

        foreach ($sourceBatch->items as $item) {
            RenewalCampaignBatchItem::create([
                'batch_id' => $clonedBatch->id,
                'cohort_key' => $item->cohort_key,
                'entity_id' => $item->entity_id,
                'store_id' => $item->store_id,
                'user_id' => $item->user_id,
                'display_name' => $item->display_name,
                'phone' => $item->phone,
                'email' => $item->email,
                'store_name' => $item->store_name,
                'store_url' => $item->store_url,
                'status_label' => $item->status_label,
                'registration_date' => $item->registration_date,
                'expiry_date' => $item->expiry_date,
            ]);
        }

        $clonedBatch->loadCount('items');

        return response()->json([
            'success' => true,
            'message' => 'Batch duplicated successfully.',
            'batch' => $this->serializeBatchSummary($clonedBatch),
        ]);
    }

    public function exportBatchRecipients(int $id): StreamedResponse
    {
        $batch = RenewalCampaignBatch::query()
            ->with('items')
            ->findOrFail($id);

        $fileName = sprintf(
            'renewal-batch-%d-%s.csv',
            $batch->id,
            now()->format('Ymd-His')
        );

        return response()->streamDownload(function () use ($batch) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Batch ID',
                'Batch Name',
                'Cohort',
                'Recipient Name',
                'Phone',
                'Email',
                'Store Name',
                'Store URL',
                'Status',
                'Registration Date',
                'Expiry Date',
            ]);

            foreach ($batch->items as $item) {
                fputcsv($handle, [
                    $batch->id,
                    $batch->name,
                    $batch->cohort_key,
                    $item->display_name,
                    $item->phone,
                    $item->email,
                    $item->store_name,
                    $item->store_url,
                    $item->status_label,
                    $item->registration_date,
                    $item->expiry_date,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'cohort_key' => ['required', 'string', 'in:expired-clients,unsubscribed-registrations'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'bot_type' => ['required', 'string', 'in:sales,support'],
            'message_text' => ['required', 'string'],
            'image_url' => ['nullable', 'string'],
            'scheduled_for' => ['nullable', 'string'],
        ]);

        $items = $this->resolveCohortSelection($validated['cohort_key'], $validated['ids']);
        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No valid cohort rows found for this batch.',
            ], 422);
        }

        $auth = $request->attributes->get('whatsapp_auth', []);

        $batch = RenewalCampaignBatch::create([
            'name' => $validated['name'],
            'cohort_key' => $validated['cohort_key'],
            'bot_type' => $validated['bot_type'],
            'message_text' => $validated['message_text'],
            'image_url' => $validated['image_url'] ?? null,
            'scheduled_for' => $validated['scheduled_for'] ?: null,
            'status' => 'active',
            'created_by' => $auth['uid'] ?? null,
            'total_recipients' => $items->count(),
        ]);

        foreach ($items as $item) {
            RenewalCampaignBatchItem::create([
                'batch_id' => $batch->id,
                'cohort_key' => $validated['cohort_key'],
                'entity_id' => $item['id'] ?? null,
                'store_id' => $item['store_id'] ?? null,
                'user_id' => $item['user_id'] ?? null,
                'display_name' => $item['display_name'] ?? null,
                'phone' => $item['phone'] ?? null,
                'email' => $item['email'] ?? null,
                'store_name' => $item['store_name'] ?? null,
                'store_url' => $item['store_url'] ?? null,
                'status_label' => $item['status'] ?? null,
                'registration_date' => $item['registration_date'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
            ]);
        }

        $batch->loadCount('items');

        return response()->json([
            'success' => true,
            'message' => 'Renewal batch saved successfully.',
            'batch' => $this->serializeBatchSummary($batch),
        ]);
    }

    public function showBatch(int $id): JsonResponse
    {
        $batch = RenewalCampaignBatch::query()
            ->with(['items', 'dispatches.item'])
            ->findOrFail($id);

        $this->syncBatchDispatchStatuses($batch);

        return response()->json([
            'success' => true,
            'batch' => $this->serializeBatchDetail($batch),
        ]);
    }

    public function runBatch(int $id): JsonResponse
    {
        $batch = RenewalCampaignBatch::query()
            ->with('items')
            ->findOrFail($id);

        if ($batch->status === 'archived') {
            return response()->json([
                'success' => false,
                'error' => 'Archived batches must be restored before running.',
            ], 422);
        }

        $queued = [];
        $errors = [];

        foreach ($batch->items as $item) {
            $sessionId = $this->normalizePhoneToSessionId($item->phone ?? '');

            if (!$sessionId) {
                $errors[] = [
                    'id' => $item->id,
                    'name' => $item->display_name,
                    'error' => 'Phone number is missing or invalid.',
                ];
                continue;
            }

            try {
                $result = $this->botApiService->createOutbound([
                    'session_id' => $sessionId,
                    'bot_type' => $batch->bot_type,
                    'source_type' => 'manual',
                    'message_type' => $this->resolveMessageType($batch->image_url ?? ''),
                    'message_text' => $batch->message_text,
                    'image_url' => $batch->image_url ?? '',
                    'scheduled_for' => $batch->scheduled_for ? Carbon::parse($batch->scheduled_for)->toDateTimeString() : null,
                ]);

                $outboundId = $result['outbound_id'] ?? null;
                $outboundStatus = 'queued';
                $sentAt = null;
                $errorMessage = null;

                if ($outboundId) {
                    try {
                        $outboundDetail = $this->botApiService->getOutboundDetail((int) $outboundId);
                        $message = $outboundDetail['outbound_message'] ?? [];
                        $outboundStatus = $message['status'] ?? 'queued';
                        $sentAt = $message['sent_at'] ?? null;
                        $errorMessage = $message['error_message'] ?? null;
                    } catch (\Throwable) {
                        // Keep queued fallback if detail fetch is unavailable.
                    }
                }

                RenewalCampaignBatchDispatch::create([
                    'batch_id' => $batch->id,
                    'batch_item_id' => $item->id,
                    'outbound_id' => $outboundId,
                    'session_id' => $sessionId,
                    'status' => $outboundStatus,
                    'error_message' => $errorMessage,
                    'queued_at' => now(),
                    'sent_at' => $sentAt,
                ]);

                $queued[] = [
                    'item_id' => $item->id,
                    'name' => $item->display_name,
                    'session_id' => $sessionId,
                    'outbound_id' => $outboundId,
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'id' => $item->id,
                    'name' => $item->display_name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->refreshBatchAnalytics($batch, [
            'last_run_at' => now(),
            'last_run_success_count' => count($queued),
            'last_run_failed_count' => count($errors),
            'total_runs' => (int) $batch->total_runs + 1,
        ]);

        return response()->json([
            'success' => empty($errors),
            'message' => sprintf(
                'Queued %d outbound message(s) from saved batch.%s',
                count($queued),
                count($errors) ? ' Some rows failed.' : ''
            ),
            'queued_count' => count($queued),
            'failed_count' => count($errors),
            'queued' => $queued,
            'errors' => $errors,
            'batch' => $this->serializeBatchSummary($batch->fresh()->loadCount('items')),
        ]);
    }

    public function archiveBatch(int $id): JsonResponse
    {
        $batch = RenewalCampaignBatch::query()->findOrFail($id);
        $nextStatus = $batch->status === 'archived' ? 'active' : 'archived';

        $batch->update([
            'status' => $nextStatus,
            'archived_at' => $nextStatus === 'archived' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $nextStatus === 'archived'
                ? 'Batch archived successfully.'
                : 'Batch restored successfully.',
            'batch' => $this->serializeBatchSummary($batch->fresh()->loadCount('items')),
        ]);
    }

    public function destroyBatch(int $id): JsonResponse
    {
        $batch = RenewalCampaignBatch::query()
            ->with(['items', 'dispatches'])
            ->findOrFail($id);

        $batch->dispatches()->delete();
        $batch->items()->delete();
        $batch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully.',
            'id' => $id,
        ]);
    }

    protected function buildExpiredClientsPayload(Request $request): array
    {
        $limit = max(1, min((int) $request->integer('limit', 25), 100));
        $page = max(1, (int) $request->integer('page', 1));
        $search = trim((string) $request->query('search', ''));

        $query = Store::query()
            ->with(['user', 'plan'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', Carbon::today())
            ->whereNotIn('plan_id', [6, 9]);

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $total = (clone $query)->count();
        $stores = $query
            ->orderBy('expiry_date')
            ->forPage($page, $limit)
            ->get();

        $storeIds = $stores->pluck('id')->filter()->values()->all();
        $phones = $stores->pluck('user.phone')->filter()->values()->all();
        $smsMap = $this->buildSmsMap($storeIds, $phones);

        return [
            'success' => true,
            'cohort' => [
                'key' => 'expired_clients',
                'label' => 'Expired Clients',
            ],
            'items' => $stores->map(function (Store $store) use ($smsMap) {
                $user = $store->user;
                $lastSms = $this->resolveLastSms($smsMap, $store->id, $user->phone ?? null);

                return [
                    'id' => $store->id,
                    'store_id' => $store->id,
                    'user_id' => $user->id ?? null,
                    'name' => $user->name ?? null,
                    'display_name' => $user->name ?: ($store->name ?: 'Unknown'),
                    'phone' => $user->phone ?? null,
                    'email' => $user->email ?? null,
                    'store_name' => $store->name,
                    'store_url' => $store->url ?? null,
                    'plan_id' => $store->plan_id,
                    'plan_name' => $store->plan->name ?? null,
                    'status' => 'expired',
                    'registration_date' => optional($store->created_at)->toDateString(),
                    'purchase_date' => $store->purchase_date ? Carbon::parse($store->purchase_date)->toDateString() : null,
                    'expiry_date' => $store->expiry_date ? Carbon::parse($store->expiry_date)->toDateString() : null,
                    'renew_date' => $store->renew_date ? Carbon::parse($store->renew_date)->toDateString() : null,
                    'days_expired' => $store->expiry_date ? Carbon::parse($store->expiry_date)->diffInDays(Carbon::today()) : null,
                    'last_sms_status' => $lastSms['status'],
                    'last_sms_at' => $lastSms['at'],
                    'last_sms_purpose' => $lastSms['purpose'],
                ];
            })->values(),
            'pagination' => $this->pagination($page, $limit, $total),
        ];
    }

    protected function buildUnsubscribedPayload(Request $request): array
    {
        $limit = max(1, min((int) $request->integer('limit', 25), 100));
        $page = max(1, (int) $request->integer('page', 1));
        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->with(['storeInfo.plan'])
            ->where('type', 'admin')
            ->where(function ($subQuery) {
                $subQuery->doesntHave('storeInfo')
                    ->orWhereHas('storeInfo', function ($storeQuery) {
                        $storeQuery->whereIn('plan_id', [6, 9])
                            ->orWhereNull('expiry_date');
                    });
            });

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('storeInfo', function ($storeQuery) use ($search) {
                        $storeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('url', 'like', "%{$search}%");
                    });
            });
        }

        $total = (clone $query)->count();
        $users = $query
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get();

        $storeIds = $users->pluck('storeInfo.id')->filter()->values()->all();
        $phones = $users->pluck('phone')->filter()->values()->all();
        $smsMap = $this->buildSmsMap($storeIds, $phones);

        return [
            'success' => true,
            'cohort' => [
                'key' => 'unsubscribed_registrations',
                'label' => 'Registered Not Subscribed',
            ],
            'items' => $users->map(function (User $user) use ($smsMap) {
                $store = $user->storeInfo;
                $lastSms = $this->resolveLastSms($smsMap, $store->id ?? null, $user->phone ?? null);

                return [
                    'id' => $user->id,
                    'store_id' => $store->id ?? null,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'display_name' => $user->name ?: ($store->name ?? 'Unknown'),
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'store_name' => $store->name ?? null,
                    'store_url' => $store->url ?? null,
                    'plan_id' => $store->plan_id ?? null,
                    'plan_name' => $store->plan->name ?? null,
                    'status' => 'registered_not_subscribed',
                    'registration_date' => optional($user->created_at)->toDateString(),
                    'purchase_date' => $store && $store->purchase_date ? Carbon::parse($store->purchase_date)->toDateString() : null,
                    'expiry_date' => $store && $store->expiry_date ? Carbon::parse($store->expiry_date)->toDateString() : null,
                    'renew_date' => $store && $store->renew_date ? Carbon::parse($store->renew_date)->toDateString() : null,
                    'paid_registration' => (bool) ($user->paid_registration ?? false),
                    'last_sms_status' => $lastSms['status'],
                    'last_sms_at' => $lastSms['at'],
                    'last_sms_purpose' => $lastSms['purpose'],
                ];
            })->values(),
            'pagination' => $this->pagination($page, $limit, $total),
        ];
    }

    protected function resolveCohortSelection(string $cohort, array $ids): Collection
    {
        $idList = collect($ids)->filter()->values();

        if ($cohort === 'expired-clients') {
            $stores = Store::query()
                ->with(['user', 'plan'])
                ->whereIn('id', $idList->all())
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', Carbon::today())
                ->whereNotIn('plan_id', [6, 9])
                ->get();

            $smsMap = $this->buildSmsMap(
                $stores->pluck('id')->filter()->values()->all(),
                $stores->pluck('user.phone')->filter()->values()->all()
            );

            return $stores->map(function (Store $store) use ($smsMap) {
                $user = $store->user;
                $lastSms = $this->resolveLastSms($smsMap, $store->id, $user->phone ?? null);

                return [
                    'id' => $store->id,
                    'store_id' => $store->id,
                    'user_id' => $user->id ?? null,
                    'display_name' => $user->name ?: ($store->name ?: 'Unknown'),
                    'phone' => $user->phone ?? null,
                    'store_name' => $store->name,
                    'status' => 'expired',
                    'last_sms_status' => $lastSms['status'],
                    'last_sms_at' => $lastSms['at'],
                    'last_sms_purpose' => $lastSms['purpose'],
                ];
            })
                ->values();
        }

        if ($cohort === 'unsubscribed-registrations') {
            $users = User::query()
                ->with(['storeInfo.plan'])
                ->whereIn('id', $idList->all())
                ->where('type', 'admin')
                ->where(function ($subQuery) {
                    $subQuery->doesntHave('storeInfo')
                        ->orWhereHas('storeInfo', function ($storeQuery) {
                            $storeQuery->whereIn('plan_id', [6, 9])
                                ->orWhereNull('expiry_date');
                        });
                })
                ->get();

            $smsMap = $this->buildSmsMap(
                $users->pluck('storeInfo.id')->filter()->values()->all(),
                $users->pluck('phone')->filter()->values()->all()
            );

            return $users->map(function (User $user) use ($smsMap) {
                $store = $user->storeInfo;
                $lastSms = $this->resolveLastSms($smsMap, $store->id ?? null, $user->phone ?? null);

                return [
                    'id' => $user->id,
                    'store_id' => $store->id ?? null,
                    'user_id' => $user->id,
                    'display_name' => $user->name ?: ($store->name ?? 'Unknown'),
                    'phone' => $user->phone,
                    'store_name' => $store->name ?? null,
                    'status' => 'registered_not_subscribed',
                    'last_sms_status' => $lastSms['status'],
                    'last_sms_at' => $lastSms['at'],
                    'last_sms_purpose' => $lastSms['purpose'],
                ];
            })
                ->values();
        }

        abort(404, 'Invalid cohort.');
    }

    protected function buildSmsMap(array $storeIds, array $phones): array
    {
        $smsQuery = SMSLogger::query();

        if (empty($storeIds) && empty($phones)) {
            return [
                'by_store' => [],
                'by_phone' => [],
            ];
        }

        $smsQuery->where(function ($query) use ($storeIds, $phones) {
            if (!empty($storeIds)) {
                $query->whereIn('store_id', $storeIds);
            }

            if (!empty($phones)) {
                $method = !empty($storeIds) ? 'orWhereIn' : 'whereIn';
                $query->{$method}('phone', $phones);
            }
        });

        $smsRows = $smsQuery->orderByDesc('created_at')->get();

        $byStore = [];
        $byPhone = [];

        foreach ($smsRows as $row) {
            if ($row->store_id && !isset($byStore[$row->store_id])) {
                $byStore[$row->store_id] = $row;
            }

            if ($row->phone && !isset($byPhone[$row->phone])) {
                $byPhone[$row->phone] = $row;
            }
        }

        return [
            'by_store' => $byStore,
            'by_phone' => $byPhone,
        ];
    }

    protected function resolveLastSms(array $smsMap, ?int $storeId, ?string $phone): array
    {
        $row = null;

        if ($storeId && isset($smsMap['by_store'][$storeId])) {
            $row = $smsMap['by_store'][$storeId];
        } elseif ($phone && isset($smsMap['by_phone'][$phone])) {
            $row = $smsMap['by_phone'][$phone];
        }

        return [
            'status' => $row ? 'sent' : null,
            'at' => $row?->created_at?->toDateTimeString(),
            'purpose' => $row->purpose ?? null,
        ];
    }

    protected function normalizePhoneToSessionId(string $phone): ?string
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

    protected function cohortNoteSuffix(array $item): string
    {
        $storeName = trim((string) ($item['store_name'] ?? ''));
        $status = trim((string) ($item['status'] ?? ''));

        if ($storeName !== '') {
            return "[Cohort: {$status}; Store: {$storeName}]";
        }

        return "[Cohort: {$status}]";
    }

    protected function resolveMessageType(string $imageUrl): string
    {
        return trim($imageUrl) !== '' ? 'image' : 'text';
    }

    protected function pagination(int $page, int $limit, int $total): array
    {
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;

        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
        ];
    }

    protected function serializeBatchSummary(RenewalCampaignBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'cohort_key' => $batch->cohort_key,
            'status' => $batch->status,
            'bot_type' => $batch->bot_type,
            'message_text' => $batch->message_text,
            'image_url' => $batch->image_url,
            'scheduled_for' => $batch->scheduled_for ? Carbon::parse($batch->scheduled_for)->toDateTimeString() : null,
            'item_count' => $batch->items_count ?? (int) ($batch->total_recipients ?: $batch->items()->count()),
            'total_runs' => (int) $batch->total_runs,
            'total_recipients' => (int) $batch->total_recipients,
            'total_sent_count' => (int) $batch->total_sent_count,
            'total_failed_count' => (int) $batch->total_failed_count,
            'last_run_at' => $batch->last_run_at ? Carbon::parse($batch->last_run_at)->toDateTimeString() : null,
            'last_run_success_count' => (int) $batch->last_run_success_count,
            'last_run_failed_count' => (int) $batch->last_run_failed_count,
            'archived_at' => $batch->archived_at ? Carbon::parse($batch->archived_at)->toDateTimeString() : null,
            'created_at' => $batch->created_at?->toDateTimeString(),
        ];
    }

    protected function serializeBatchDetail(RenewalCampaignBatch $batch): array
    {
        return array_merge($this->serializeBatchSummary($batch), [
            'items' => $batch->items->map(function (RenewalCampaignBatchItem $item) {
                return [
                    'id' => $item->id,
                    'entity_id' => $item->entity_id,
                    'store_id' => $item->store_id,
                    'user_id' => $item->user_id,
                    'display_name' => $item->display_name,
                    'phone' => $item->phone,
                    'email' => $item->email,
                    'store_name' => $item->store_name,
                    'store_url' => $item->store_url,
                    'status_label' => $item->status_label,
                    'registration_date' => $item->registration_date,
                    'expiry_date' => $item->expiry_date,
                ];
            })->values(),
            'dispatches' => $batch->dispatches->map(function (RenewalCampaignBatchDispatch $dispatch) {
                return [
                    'id' => $dispatch->id,
                    'batch_item_id' => $dispatch->batch_item_id,
                    'outbound_id' => $dispatch->outbound_id,
                    'session_id' => $dispatch->session_id,
                    'status' => $dispatch->status,
                    'error_message' => $dispatch->error_message,
                    'queued_at' => $dispatch->queued_at ? Carbon::parse($dispatch->queued_at)->toDateTimeString() : null,
                    'sent_at' => $dispatch->sent_at ? Carbon::parse($dispatch->sent_at)->toDateTimeString() : null,
                    'display_name' => $dispatch->item->display_name ?? null,
                    'phone' => $dispatch->item->phone ?? null,
                ];
            })->values(),
        ]);
    }

    protected function refreshBatchAnalytics(RenewalCampaignBatch $batch, array $override = []): void
    {
        $dispatches = RenewalCampaignBatchDispatch::query()
            ->where('batch_id', $batch->id)
            ->get();

        $batch->update(array_merge([
            'total_recipients' => (int) ($batch->total_recipients ?: $batch->items()->count()),
            'total_sent_count' => $dispatches->where('status', 'sent')->count(),
            'total_failed_count' => $dispatches->whereIn('status', ['failed', 'cancelled'])->count(),
        ], $override));
    }

    protected function syncBatchDispatchStatuses(RenewalCampaignBatch $batch): void
    {
        $dispatches = $batch->dispatches()->whereNotNull('outbound_id')->get();

        foreach ($dispatches as $dispatch) {
            try {
                $detail = $this->botApiService->getOutboundDetail((int) $dispatch->outbound_id);
                $message = $detail['outbound_message'] ?? [];
                $dispatch->status = $message['status'] ?? $dispatch->status;
                $dispatch->sent_at = $message['sent_at'] ?? $dispatch->sent_at;
                $dispatch->error_message = $message['error_message'] ?? $dispatch->error_message;
                $dispatch->save();
            } catch (\Throwable) {
                // Ignore sync failures and keep the last known status.
            }
        }

        $this->refreshBatchAnalytics($batch);
    }

    protected function buildClonedBatchName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return 'Renewal Batch Copy';
        }

        return str_ends_with($trimmed, 'Copy') ? "{$trimmed} 2" : "{$trimmed} Copy";
    }
}
