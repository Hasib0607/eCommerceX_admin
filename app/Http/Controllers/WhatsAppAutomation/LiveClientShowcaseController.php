<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LiveClientShowcaseController extends Controller
{
    private const FALLBACK_STORE = 'whatsapp/live-client-showcases.json';

    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->only([
            'limit',
            'page',
            'search',
            'status',
            'content_type',
            'is_active',
        ]);

        try {
            return response()->json($this->botApiService->getLiveClientShowcases($query));
        } catch (\Throwable $e) {
            if (!$this->shouldUseFallback($e)) {
                throw $e;
            }

            return response()->json($this->fallbackIndex($query));
        }
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        try {
            return response()->json($this->botApiService->createLiveClientShowcase($payload));
        } catch (\Throwable $e) {
            if (!$this->shouldUseFallback($e)) {
                throw $e;
            }

            $items = $this->fallbackItems();
            $nextId = ((int) collect($items)->max('id')) + 1;
            $item = $this->normalizeItem(array_merge($payload, [
                'id' => $nextId > 0 ? $nextId : 1,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ]));
            $items[] = $item;
            $this->saveFallbackItems($items);

            return response()->json([
                'success' => true,
                'item' => $item,
                'meta' => ['source' => 'local_fallback'],
            ], 201);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json($this->botApiService->getLiveClientShowcase($id));
        } catch (\Throwable $e) {
            if (!$this->shouldUseFallback($e)) {
                throw $e;
            }

            $item = $this->findFallbackItem($id);
            if (!$item) {
                return response()->json(['message' => 'Live client showcase not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'item' => $item,
                'meta' => ['source' => 'local_fallback'],
            ]);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $payload = $this->validatedPayload($request, true);

        try {
            return response()->json($this->botApiService->updateLiveClientShowcase($id, $payload));
        } catch (\Throwable $e) {
            if (!$this->shouldUseFallback($e)) {
                throw $e;
            }

            $items = $this->fallbackItems();
            $updated = null;
            $items = array_map(function (array $item) use ($id, $payload, &$updated) {
                if ((int) ($item['id'] ?? 0) !== $id) {
                    return $item;
                }

                $updated = $this->normalizeItem(array_merge($item, $payload, [
                    'id' => $id,
                    'updated_at' => now()->toISOString(),
                ]));

                return $updated;
            }, $items);

            if (!$updated) {
                return response()->json(['message' => 'Live client showcase not found.'], 404);
            }

            $this->saveFallbackItems($items);

            return response()->json([
                'success' => true,
                'item' => $updated,
                'meta' => ['source' => 'local_fallback'],
            ]);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            return response()->json($this->botApiService->deleteLiveClientShowcase($id));
        } catch (\Throwable $e) {
            if (!$this->shouldUseFallback($e)) {
                throw $e;
            }

            $items = $this->fallbackItems();
            $remaining = array_values(array_filter($items, static fn (array $item) => (int) ($item['id'] ?? 0) !== $id));
            $this->saveFallbackItems($remaining);

            return response()->json([
                'success' => true,
                'deleted' => count($remaining) !== count($items),
                'meta' => ['source' => 'local_fallback'],
            ]);
        }
    }

    private function fallbackIndex(array $query): array
    {
        $items = collect($this->fallbackItems());
        $search = strtolower(trim((string) ($query['search'] ?? '')));
        $status = (string) ($query['status'] ?? '');
        $contentType = (string) ($query['content_type'] ?? '');
        $isActive = $query['is_active'] ?? null;

        if ($search !== '') {
            $items = $items->filter(function (array $item) use ($search) {
                return str_contains(strtolower(implode(' ', [
                    $item['title'] ?? '',
                    $item['url'] ?? '',
                    $item['video_url'] ?? '',
                    $item['business_type'] ?? '',
                    $item['description'] ?? '',
                ])), $search);
            });
        }

        if ($status === 'active') {
            $items = $items->filter(static fn (array $item) => (bool) ($item['is_active'] ?? false));
        } elseif ($status === 'inactive') {
            $items = $items->filter(static fn (array $item) => !(bool) ($item['is_active'] ?? false));
        }

        if ($isActive !== null && $isActive !== '') {
            $active = filter_var($isActive, FILTER_VALIDATE_BOOL);
            $items = $items->filter(static fn (array $item) => (bool) ($item['is_active'] ?? false) === $active);
        }

        if ($contentType !== '') {
            $items = $items->filter(static fn (array $item) => (string) ($item['content_type'] ?? '') === $contentType);
        }

        $items = $items
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'desc'],
            ])
            ->values();

        $limit = max(1, (int) ($query['limit'] ?? 50));
        $page = max(1, (int) ($query['page'] ?? 1));
        $total = $items->count();

        return [
            'success' => true,
            'items' => $items->forPage($page, $limit)->values()->all(),
            'showcases' => $items->forPage($page, $limit)->values()->all(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
            ],
            'meta' => [
                'source' => 'local_fallback',
                'reason' => 'The configured WhatsApp bot API does not expose live-client-showcases yet.',
            ],
        ];
    }

    private function fallbackItems(): array
    {
        if (!Storage::disk('local')->exists(self::FALLBACK_STORE)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get(self::FALLBACK_STORE), true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(fn (array $item) => $this->normalizeItem($item), $decoded));
    }

    private function saveFallbackItems(array $items): void
    {
        Storage::disk('local')->put(
            self::FALLBACK_STORE,
            json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function findFallbackItem(int $id): ?array
    {
        foreach ($this->fallbackItems() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function normalizeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'title' => (string) ($item['title'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'video_url' => (string) ($item['video_url'] ?? ''),
            'content_type' => (string) ($item['content_type'] ?? 'live_client'),
            'feature_tag' => $item['feature_tag'] ?? null,
            'objection_type' => $item['objection_type'] ?? null,
            'business_type' => $item['business_type'] ?? null,
            'description' => $item['description'] ?? null,
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'is_active' => (bool) ($item['is_active'] ?? true),
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
        ];
    }

    private function shouldUseFallback(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'status code 404')
            || str_contains($message, '404 Not Found')
            || str_contains($message, 'Could not resolve host')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'Connection timed out')
            || str_contains($message, 'WHATSAPP_BOT_API_URL is not configured')
            || str_contains($message, 'WHATSAPP_BOT_ADMIN_TOKEN is not configured');
    }

    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'nullable', 'string'],
            'url' => ['sometimes', 'nullable', 'string'],
            'video_url' => ['sometimes', 'nullable', 'string'],
            'content_type' => [$required, 'string'],
            'feature_tag' => ['sometimes', 'nullable', 'string'],
            'objection_type' => ['sometimes', 'nullable', 'string'],
            'business_type' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
