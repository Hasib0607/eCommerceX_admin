<?php

namespace App\Http\Controllers;

use App\Models\AdminBlog;
use App\Models\AdminBlogKeyword;
use App\Models\AdminBlogType;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminReactBlogController extends Controller
{
    private function resolveStoreId(): int
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return 0;
        }

        if (($authUser->type ?? '') === 'admin') {
            $customer = Customer::where('uid', $authUser->id)->first();
            return (int) ($customer->active_store ?? 0);
        }

        $staff = Staff::where('uid', $authUser->id)->first();
        return (int) ($staff->store_id ?? 0);
    }

    private function baseBlogQuery()
    {
        $query = AdminBlog::query();
        $user = Auth::user();
        $type = strtolower((string) ($user->type ?? ''));

        if ($type === 'superadmin') {
            return $query->whereNull('store_id');
        }
        if ($type === 'superstaff') {
            return $query->where('user_id', (int) $user->id)->whereNull('store_id');
        }

        return $query->where('store_id', $this->resolveStoreId());
    }

    private function ensureUniqueSlug(string $value, string $column, ?int $ignoreId = null): string
    {
        $slug = generateSlug($value, '-');
        $slug = $slug !== '' ? $slug : 'blog';
        $original = $slug;
        $counter = 1;

        while (true) {
            $query = AdminBlog::query()->where($column, $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function parseKeywordIds(string $keywords): array
    {
        $parts = array_filter(array_map('trim', explode(',', $keywords)));
        $ids = [];
        foreach ($parts as $keyword) {
            $name = strtolower($keyword);
            if ($name === '') {
                continue;
            }
            $record = AdminBlogKeyword::firstOrCreate(['name' => $name]);
            $ids[] = (string) $record->id;
        }
        return $ids;
    }

    private function uploadImageIfExists(Request $request, string $fieldName, ?string $previous = null): ?string
    {
        if (!$request->hasFile($fieldName)) {
            return $previous;
        }

        $file = $request->file($fieldName);
        $fileName = Auth::id() . '_blog_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $stored = $file->storeAs($this->blogImageDirectory(), $fileName, 'public');

        if ($previous) {
            $oldPath = ltrim(str_replace('\\', '/', (string) $previous), '/');
            if (Str::startsWith($oldPath, 'storage/')) {
                Storage::disk('public')->delete(ltrim(substr($oldPath, strlen('storage/')), '/'));
            } else {
                $legacyPath = public_path('BlogImages/' . $previous);
                if (file_exists($legacyPath)) {
                    @unlink($legacyPath);
                }
            }
        }

        return 'storage/' . $stored;
    }

    private function blogImageDirectory(): string
    {
        $authUser = Auth::user();
        $type = strtolower((string) ($authUser->type ?? ''));
        if (in_array($type, ['superadmin', 'superstaff'], true)) {
            return 'image-library/superadmin/blogs';
        }

        $storeId = $this->resolveStoreId();
        $store = $storeId > 0 ? Store::find($storeId) : null;
        $slug = Str::slug((string) ($store?->name ?? $store?->url ?? 'store')) ?: 'store';

        return 'image-library/admin/' . $slug . '-' . ($storeId ?: '0') . '/blogs';
    }

    private function blogImageUrl($image): ?string
    {
        $path = trim((string) ($image ?? ''));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (Str::startsWith($path, ['storage/', 'assets/'])) {
            return request()->getSchemeAndHttpHost() . '/' . $path;
        }
        return asset('BlogImages/' . basename($path));
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $search = trim((string) $request->query('search', ''));

        $query = $this->baseBlogQuery()->with('type:id,type')->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhere('sub_title', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items())->map(function (AdminBlog $blog) {
            return [
                'id' => (int) $blog->id,
                'title' => (string) ($blog->title ?? ''),
                'sub_title' => (string) ($blog->sub_title ?? ''),
                'position' => (int) ($blog->position ?? 0),
                'status' => (int) ($blog->status ?? 0),
                'popular' => (int) ($blog->popular ?? 0),
                'type_id' => $blog->type ? (int) $blog->type : null,
                'type_name' => (string) ($blog->type?->type ?? ''),
                'slug' => (string) ($blog->slug ?? ''),
                'permalink' => (string) ($blog->permalink ?? ''),
                'thumbnail_url' => $this->blogImageUrl($blog->thumbnail),
                'image_url' => $this->blogImageUrl($blog->image),
                'created_at' => optional($blog->created_at)->toISOString(),
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

    public function meta(): JsonResponse
    {
        $query = AdminBlogType::query()->where('status', 1)->orderBy('type');
        $user = Auth::user();
        $type = strtolower((string) ($user->type ?? ''));
        if ($type === 'superadmin') {
            $query->whereNull('store_id');
        } elseif ($type === 'superstaff') {
            $query->whereNull('store_id')->where('user_id', (int) $user->id);
        } else {
            $query->where('store_id', $this->resolveStoreId());
        }

        return response()->json([
            'types' => $query->get(['id', 'type'])->map(fn ($row) => [
                'id' => (int) $row->id,
                'type' => (string) ($row->type ?? ''),
            ]),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $blog = $this->baseBlogQuery()->findOrFail($id);

        $keywordIds = json_decode((string) ($blog->key_word ?? '[]'), true);
        $keywordIds = is_array($keywordIds) ? $keywordIds : [];
        $keywords = AdminBlogKeyword::query()
            ->whereIn('id', $keywordIds)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'item' => [
                'id' => (int) $blog->id,
                'title' => (string) ($blog->title ?? ''),
                'sub_title' => (string) ($blog->sub_title ?? ''),
                'description' => (string) ($blog->description ?? ''),
                'type' => $blog->type ? (int) $blog->type : null,
                'position' => (int) ($blog->position ?? 0),
                'slug' => (string) ($blog->slug ?? ''),
                'permalink' => (string) ($blog->permalink ?? ''),
                'website' => (string) ($blog->website ?? '0'),
                'canonical_url' => (string) ($blog->canonical_url ?? ''),
                'custom_script' => (string) ($blog->custom_script ?? ''),
                'status' => (int) ($blog->status ?? 0),
                'popular' => (int) ($blog->popular ?? 0),
                'seo' => implode(',', $keywords),
                'thumbnail_url' => $this->blogImageUrl($blog->thumbnail),
                'image_url' => $this->blogImageUrl($blog->image),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
            'permalink' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:20'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            'custom_script' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'popular' => ['nullable', 'boolean'],
            'seo' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image'],
            'image' => ['nullable', 'image'],
        ]);

        $blog = new AdminBlog();
        $blog->title = $payload['title'];
        $blog->sub_title = $payload['sub_title'] ?? null;
        $blog->description = $payload['description'] ?? null;
        $blog->type = $payload['type'] ?? null;
        $blog->position = (int) ($payload['position'] ?? 0);
        $blog->slug = $this->ensureUniqueSlug($payload['title'], 'slug');
        $blog->permalink = isset($payload['permalink']) && $payload['permalink'] !== ''
            ? $this->ensureUniqueSlug($payload['permalink'], 'permalink')
            : $blog->slug;
        $blog->website = $payload['website'] ?? null;
        $blog->canonical_url = $payload['canonical_url'] ?? null;
        $blog->custom_script = $payload['custom_script'] ?? null;
        $blog->status = (int) ($payload['status'] ?? false);
        $blog->popular = (int) ($payload['popular'] ?? false);
        $blog->key_word = json_encode($this->parseKeywordIds((string) ($payload['seo'] ?? '')));
        $blog->user_id = (int) (Auth::id() ?? 0);

        $type = strtolower((string) (Auth::user()->type ?? ''));
        if ($type === 'admin' || $type === 'staff' || $type === 'dropshipper') {
            $blog->store_id = $this->resolveStoreId();
        } else {
            $blog->store_id = null;
        }

        $blog->thumbnail = $this->uploadImageIfExists($request, 'thumbnail');
        $blog->image = $this->uploadImageIfExists($request, 'image');
        $blog->save();

        return response()->json(['id' => (int) $blog->id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $blog = $this->baseBlogQuery()->findOrFail($id);
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
            'permalink' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:20'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            'custom_script' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'popular' => ['nullable', 'boolean'],
            'seo' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image'],
            'image' => ['nullable', 'image'],
        ]);

        if ((string) $blog->title !== (string) $payload['title']) {
            $blog->slug = $this->ensureUniqueSlug($payload['title'], 'slug', $blog->id);
        }
        if (isset($payload['permalink']) && $payload['permalink'] !== '') {
            if ((string) $blog->permalink !== (string) $payload['permalink']) {
                $blog->permalink = $this->ensureUniqueSlug($payload['permalink'], 'permalink', $blog->id);
            }
        }

        $blog->title = $payload['title'];
        $blog->sub_title = $payload['sub_title'] ?? null;
        $blog->description = $payload['description'] ?? null;
        $blog->type = $payload['type'] ?? null;
        $blog->position = (int) ($payload['position'] ?? 0);
        $blog->website = $payload['website'] ?? null;
        $blog->canonical_url = $payload['canonical_url'] ?? null;
        $blog->custom_script = $payload['custom_script'] ?? null;
        $blog->status = (int) ($payload['status'] ?? false);
        $blog->popular = (int) ($payload['popular'] ?? false);
        $blog->key_word = json_encode($this->parseKeywordIds((string) ($payload['seo'] ?? '')));
        $blog->thumbnail = $this->uploadImageIfExists($request, 'thumbnail', $blog->thumbnail);
        $blog->image = $this->uploadImageIfExists($request, 'image', $blog->image);
        $blog->save();

        return response()->json(['success' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $blog = $this->baseBlogQuery()->findOrFail($id);
        $blog->delete();
        return response()->json(['success' => true]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $blog = $this->baseBlogQuery()->findOrFail($id);
        $blog->status = (int) !$blog->status;
        $blog->save();
        return response()->json(['success' => true, 'status' => (int) $blog->status]);
    }

    public function updatePosition(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'position' => ['required', 'integer'],
        ]);
        $blog = $this->baseBlogQuery()->findOrFail($id);
        $blog->position = (int) $payload['position'];
        $blog->save();
        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:active,deactive,delete'],
        ]);

        $query = $this->baseBlogQuery()->whereIn('id', $payload['ids']);
        if ($payload['action'] === 'active') {
            $query->update(['status' => 1]);
        } elseif ($payload['action'] === 'deactive') {
            $query->update(['status' => 0]);
        } else {
            $query->delete();
        }

        return response()->json(['success' => true]);
    }
}
