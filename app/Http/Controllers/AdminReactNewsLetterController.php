<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NewsLetter;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReactNewsLetterController extends Controller
{
    private function resolveStoreId(): int
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return 0;
        }

        if (($authUser->type ?? '') === 'admin' || ($authUser->type ?? '') === 'dropshipper') {
            $customer = Customer::where('uid', $authUser->id)->first();
            return (int) ($customer->active_store ?? 0);
        }

        $staff = Staff::where('uid', $authUser->id)->first();
        return (int) ($staff->store_id ?? 0);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 50), 100));
        $search = trim((string) $request->query('search', ''));

        $query = NewsLetter::query()
            ->where('store_id', $this->resolveStoreId())
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where('email', 'like', "%{$search}%");
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'items' => collect($paginator->items())->map(fn ($row) => [
                'id' => (int) $row->id,
                'email' => (string) ($row->email ?? ''),
                'created_at' => optional($row->created_at)->toISOString(),
            ])->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = NewsLetter::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id);
        $row->delete();

        return response()->json(['success' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        NewsLetter::query()
            ->where('store_id', $this->resolveStoreId())
            ->whereIn('id', $payload['ids'])
            ->delete();

        return response()->json(['success' => true]);
    }

    public function exportCsv()
    {
        $rows = NewsLetter::query()
            ->where('store_id', $this->resolveStoreId())
            ->orderByDesc('id')
            ->get(['email', 'created_at']);

        $filename = 'newsletter-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Email', 'Created At']);
            foreach ($rows as $row) {
                fputcsv($file, [
                    (string) ($row->email ?? ''),
                    (string) ($row->created_at ?? ''),
                ]);
            }
            fclose($file);
        }, 200, $headers);
    }
}

