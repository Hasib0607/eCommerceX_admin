<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    public function events(Request $request): JsonResponse
    {
        $query = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json(
            $this->botApiService->getRealtimeEvents(
                (int) ($query['after_id'] ?? 0),
                (int) ($query['limit'] ?? 50)
            )
        );
    }

    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        $payload = $request->attributes->get('whatsapp_auth', []);

        if (!is_array($payload) || empty($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 401);
        }

        $afterId = max((int) $request->query('after_id', 0), 0);
        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        return response()->stream(function () use ($payload, $afterId, $limit) {
            $cursor = $afterId;
            $startedAt = time();

            $sendEvent = static function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            };

            $sendEvent('ready', [
                'success' => true,
                'next_cursor' => $cursor,
                'user' => [
                    'id' => $payload['uid'] ?? null,
                    'name' => $payload['name'] ?? null,
                    'email' => $payload['email'] ?? null,
                ],
            ]);

            while (! connection_aborted() && (time() - $startedAt) < 25) {
                $result = $this->botApiService->getRealtimeEvents($cursor, $limit);
                $events = $result['events'] ?? [];

                if (is_array($events) && count($events) > 0) {
                    foreach ($events as $event) {
                        $sendEvent((string) ($event['event'] ?? 'message'), $event);
                        $cursor = max($cursor, (int) ($event['id'] ?? 0));
                    }
                } else {
                    $sendEvent('ping', [
                        'success' => true,
                        'next_cursor' => $cursor,
                        'timestamp' => now()->format('Y-m-d H:i:s'),
                    ]);
                }

                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
