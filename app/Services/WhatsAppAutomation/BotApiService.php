<?php

namespace App\Services\WhatsAppAutomation;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BotApiService
{
    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('whatsapp_automation.bot_api_url'), '/');
        $adminToken = (string) config('whatsapp_automation.bot_admin_token');

        if ($baseUrl === '') {
            throw new \RuntimeException('WHATSAPP_BOT_API_URL is not configured.');
        }

        if ($adminToken === '') {
            throw new \RuntimeException('WHATSAPP_BOT_ADMIN_TOKEN is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->withOptions([
                'connect_timeout' => 30,
            ])
            ->timeout(90)
            ->retry(3, 2000)
            ->acceptJson()
            ->withHeaders([
                'X-Admin-Token' => $adminToken,
                'X-API-Secret' => $adminToken,
                'X-Gateway-Secret' => $adminToken,
                'Authorization' => 'Bearer ' . $adminToken,
            ]);
    }

    protected function gatewayClient(): PendingRequest
    {
        $baseUrl = rtrim((string) config('whatsapp_automation.gateway_api_url'), '/');
        $apiSecret = (string) config('whatsapp_automation.gateway_api_secret');

        if ($baseUrl === '') {
            throw new \RuntimeException('WHATSAPP_GATEWAY_API_URL is not configured.');
        }

        if ($apiSecret === '') {
            throw new \RuntimeException('WHATSAPP_GATEWAY_API_SECRET is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->withOptions([
                'connect_timeout' => 30,
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->timeout(90)
            ->retry(3, 2000)
            ->acceptJson()
            ->withHeaders([
                'X-API-Secret' => $apiSecret,
                'X-Gateway-Secret' => $apiSecret,
                'Authorization' => 'Bearer ' . $apiSecret,
            ]);
    }

    public function getDashboard(bool $compact = false): array
    {
        $response = $this->client()->get('/operator/dashboard', [
            'compact' => $compact ? 1 : 0,
        ]);
        return $response->throw()->json();
    }

    public function getWorkingHours(): array
    {
        $response = $this->client()->get('/working-hours');
        return $response->throw()->json();
    }

    public function getLeads(array $query = []): array
    {
        $response = $this->client()->get('/leads', $query);
        return $response->throw()->json();
    }

    public function getLead(string $sessionId, bool $compact = false): array
    {
        $response = $this->client()->get("/leads/{$sessionId}", [
            'compact' => $compact ? 1 : 0,
        ]);
        return $response->throw()->json();
    }

    public function updateLeadStatus(string $sessionId, string $status): array
    {
        $response = $this->client()->post("/leads/{$sessionId}/status", [
            'status' => $status,
        ]);

        return $response->throw()->json();
    }

    public function getLeadTags(string $sessionId): array
    {
        $response = $this->client()->get("/leads/{$sessionId}/tags");
        return $response->throw()->json();
    }

    public function assignLeadTag(string $sessionId, string $tagName): array
    {
        $response = $this->client()->post("/leads/{$sessionId}/tags", [
            'tag_name' => $tagName,
        ]);

        return $response->throw()->json();
    }

    public function removeLeadTag(string $sessionId, string $tagName): array
    {
        $response = $this->client()->delete("/leads/{$sessionId}/tags/" . urlencode($tagName));
        return $response->throw()->json();
    }

    public function getAllTags(): array
    {
        $response = $this->client()->get('/tags');
        return $response->throw()->json();
    }

    public function createTag(string $name, string $description = ''): array
    {
        $response = $this->client()->post('/tags', [
            'name' => $name,
            'description' => $description,
        ]);

        return $response->throw()->json();
    }

    public function getFollowupPlans(array $query = []): array
    {
        $response = $this->client()->get('/followup-plans', $query);
        return $response->throw()->json();
    }

    public function createFollowupPlan(array $payload): array
    {
        $response = $this->client()->post('/followup-plans', $payload);
        return $response->throw()->json();
    }

    public function getFollowupPlanReasons(): array
    {
        $response = $this->client()->get('/followup-plans/reasons');
        return $response->throw()->json();
    }

    public function getHandoff(string $sessionId): array
    {
        $response = $this->client()->get("/handoff/{$sessionId}");
        return $response->throw()->json();
    }

    public function resolveHandoff(string $sessionId): array
    {
        $response = $this->client()->post("/handoff/{$sessionId}/resolve");
        return $response->throw()->json();
    }

    public function assignBotToHandoff(string $sessionId): array
    {
        $response = $this->client()->post("/handoff/{$sessionId}/assign-bot");
        return $response->throw()->json();
    }

    public function queueManualOutbound(
        string $sessionId,
        string $botType,
        string $messageText,
        ?string $imageUrl = null,
        string $sourceType = 'manual'
    ): array {
        $payload = [
            'session_id' => $sessionId,
            'bot_type' => $botType,
            'source_type' => $sourceType,
            'message_type' => $imageUrl ? 'image' : 'text',
            'message_text' => $messageText,
        ];

        if ($imageUrl) {
            $payload['image_url'] = $imageUrl;
        }

        $response = $this->client()->post('/outbound', $payload);
        return $response->throw()->json();
    }

    public function dispatchOutbound(int $outboundId): array
    {
        $response = $this->client()->post("/dispatch/outbound/{$outboundId}");
        return $response->throw()->json();
    }

    public function sendGatewayTextMessage(string $tenantId, string $phoneNumber, string $message): array
    {
        $response = $this->gatewayClient()->post('/api/messages/send', [
            'tenantId' => $tenantId,
            'phoneNumber' => $phoneNumber,
            'message' => $message,
        ]);

        $json = $response->json();
        $payload = is_array($json) ? $json : [];

        if ($response->successful()) {
            return $payload + ['success' => true];
        }

        return [
            'success' => false,
            'status_code' => $response->status(),
            'message' => $this->gatewayMessage($response, $payload),
            'details' => $payload,
        ];
    }

    public function getReviewQueue(string $queue, array $query = []): array
    {
        $response = $this->client()->get("/review/{$queue}", $query);
        return $response->throw()->json();
    }

    public function assignReviewToHuman(string $sessionId, string $assignedTo, ?string $queueType = null): array
    {
        $payload = ['assigned_to' => $assignedTo];
        if ($queueType) {
            $payload['queue_type'] = $queueType;
        }

        $response = $this->client()->post("/review/{$sessionId}/assign-human", $payload);
        return $response->throw()->json();
    }

    public function disableReviewBot(string $sessionId): array
    {
        $response = $this->client()->post("/review/{$sessionId}/disable-bot");
        return $response->throw()->json();
    }

    public function enableReviewBot(string $sessionId): array
    {
        $response = $this->client()->post("/review/{$sessionId}/enable-bot");
        return $response->throw()->json();
    }

    public function resolveReviewItem(string $sessionId, ?string $note = null, ?string $queueType = null): array
    {
        $payload = [];
        if ($note !== null && $note !== '') {
            $payload['note'] = $note;
        }
        if ($queueType) {
            $payload['queue_type'] = $queueType;
        }

        $response = $this->client()->post("/review/{$sessionId}/resolve", $payload);
        return $response->throw()->json();
    }

    public function addReviewNote(string $sessionId, string $note, ?string $queueType = null): array
    {
        $payload = ['note' => $note];
        if ($queueType) {
            $payload['queue_type'] = $queueType;
        }

        $response = $this->client()->post("/review/{$sessionId}/note", $payload);
        return $response->throw()->json();
    }

    public function updateLeadAutoReply(string $sessionId, bool $enabled): array
    {
        $response = $this->client()->post("/sessions/{$sessionId}/auto-reply", [
            'enabled' => $enabled,
        ]);

        return $response->throw()->json();
    }

    public function updateLeadPromisedPayment(string $sessionId, ?string $promisedPaymentAt): array
    {
        $response = $this->client()->post("/leads/{$sessionId}/promised-payment", [
            'promised_payment_at' => $promisedPaymentAt,
        ]);

        return $response->throw()->json();
    }

    public function refreshLeadTags(string $sessionId): array
    {
        $response = $this->client()->post("/leads/{$sessionId}/tags/refresh");
        return $response->throw()->json();
    }

    public function updateFollowupPlanStatus(int $planId, string $status): array
    {
        $response = $this->client()->post("/followup-plans/{$planId}/status", [
            'status' => $status,
        ]);

        return $response->throw()->json();
    }

    public function markFollowupPlanSent(int $planId): array
    {
        $response = $this->client()->post("/followup-plans/{$planId}/mark-sent");
        return $response->throw()->json();
    }

    public function deleteFollowupPlan(int $planId): array
    {
        $response = $this->client()->delete("/followup-plans/{$planId}");
        return $response->throw()->json();
    }

    public function getCampaigns(): array
    {
        $response = $this->client()->get('/campaigns');
        return $response->throw()->json();
    }

    public function getCampaignTypes(): array
    {
        $response = $this->client()->get('/campaigns/types');
        return $response->throw()->json();
    }

    public function createCampaign(array $payload): array
    {
        $response = $this->client()->post('/campaigns', $payload);
        return $response->throw()->json();
    }

    public function getCampaign(int $campaignId): array
    {
        $response = $this->client()->get("/campaigns/{$campaignId}");
        return $response->throw()->json();
    }

    public function getCampaignRecipients(int $campaignId): array
    {
        $response = $this->client()->get("/campaigns/{$campaignId}/recipients");
        return $response->throw()->json();
    }

    public function sendTagPromotion(array $payload): array
    {
        $response = $this->client()->post('/promotions/tag-send', $payload);
        return $response->throw()->json();
    }

    public function getOutbound(array $query = []): array
    {
        $response = $this->client()->get('/outbound', $query);
        return $response->throw()->json();
    }

    public function createOutbound(array $payload): array
    {
        $response = $this->client()->post('/outbound', $payload);
        return $response->throw()->json();
    }

    public function getOutboundDetail(int $outboundId): array
    {
        $response = $this->client()->get("/outbound/{$outboundId}");
        return $response->throw()->json();
    }

    public function getOutboundTypes(): array
    {
        $response = $this->client()->get('/outbound/types');
        return $response->throw()->json();
    }

    public function sendSupportChat(string $sessionId, string $userMessage): array
    {
        $response = $this->client()->post('/support/chat', [
            'session_id' => $sessionId,
            'user_message' => $userMessage,
        ]);

        return $response->throw()->json();
    }

    public function getLearningQuestions(array $query = []): array
    {
        $response = $this->client()->get('/learning/questions', $query);
        return $response->throw()->json();
    }

    public function getLearningQuestion(int $questionId): array
    {
        $response = $this->client()->get("/learning/questions/{$questionId}");
        return $response->throw()->json();
    }

    public function resolveLearningQuestion(int $questionId, array $payload): array
    {
        $response = $this->client()->post("/learning/questions/{$questionId}/resolve", $payload);
        return $response->throw()->json();
    }

    public function getLeadSourceBehaviorAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/lead-source-behavior', $query);
        return $response->throw()->json();
    }

    public function getTagDistributionAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/tag-distribution', $query);
        return $response->throw()->json();
    }

    public function getConversionByTagAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/conversion-by-tag', $query);
        return $response->throw()->json();
    }

    public function getFollowupPerformanceAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/followup-performance', $query);
        return $response->throw()->json();
    }

    public function getReplySourceBreakdownAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/reply-source-breakdown', $query);
        return $response->throw()->json();
    }

    public function getCampaignPerformanceAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/campaign-performance', $query);
        return $response->throw()->json();
    }

    public function getUnresolvedLearningTrendsAnalytics(array $query = []): array
    {
        $response = $this->client()->get('/analytics/unresolved-learning-trends', $query);
        return $response->throw()->json();
    }

    public function getTrainingItems(string $botType): array
    {
        $response = $this->client()->get("/{$botType}/training");
        return $response->throw()->json();
    }

    public function createTrainingItem(string $botType, string $content): array
    {
        $response = $this->client()->post("/{$botType}/train", [
            'content' => $content,
        ]);
        return $response->throw()->json();
    }

    public function deleteTrainingItem(string $botType, int $trainingId): array
    {
        $response = $this->client()->delete("/{$botType}/training/{$trainingId}");
        return $response->throw()->json();
    }

    public function getKnowledgeItems(array $query = []): array
    {
        $response = $this->client()->get('/knowledge/items', $query);
        return $response->throw()->json();
    }

    public function createKnowledgeItem(array $payload): array
    {
        $response = $this->client()->post('/knowledge/items', $payload);
        return $response->throw()->json();
    }

    public function getKnowledgeItem(int $itemId): array
    {
        $response = $this->client()->get("/knowledge/items/{$itemId}");
        return $response->throw()->json();
    }

    public function updateKnowledgeItem(int $itemId, array $payload): array
    {
        $response = $this->client()->patch("/knowledge/items/{$itemId}", $payload);
        return $response->throw()->json();
    }

    public function deleteKnowledgeItem(int $itemId): array
    {
        $response = $this->client()->delete("/knowledge/items/{$itemId}");
        return $response->throw()->json();
    }

    public function getLiveClientShowcases(array $query = []): array
    {
        $response = $this->client()->get('/live-client-showcases', $query);
        return $response->throw()->json();
    }

    public function getLiveClientShowcase(int $showcaseId): array
    {
        $response = $this->client()->get("/live-client-showcases/{$showcaseId}");
        return $response->throw()->json();
    }

    public function createLiveClientShowcase(array $payload): array
    {
        $response = $this->client()->post('/live-client-showcases', $payload);
        return $response->throw()->json();
    }

    public function updateLiveClientShowcase(int $showcaseId, array $payload): array
    {
        $response = $this->client()->patch("/live-client-showcases/{$showcaseId}", $payload);
        return $response->throw()->json();
    }

    public function deleteLiveClientShowcase(int $showcaseId): array
    {
        $response = $this->client()->delete("/live-client-showcases/{$showcaseId}");
        return $response->throw()->json();
    }

    public function runFollowupScheduler(bool $dispatchImmediately = false, int $limit = 50): array
    {
        $response = $this->client()->post('/scheduler/followups/run', [
            'dispatch_immediately' => $dispatchImmediately,
            'limit' => $limit,
        ]);

        return $response->throw()->json();
    }

    public function getLeadHistory(string $sessionId, string $botType): array
    {
        $endpoint = $botType === 'support'
            ? "/support/history/{$sessionId}"
            : "/sales/history/{$sessionId}";

        $response = $this->client()->get($endpoint);
        return $response->throw()->json();
    }

    public function getUnifiedLeadHistory(string $sessionId): array
    {
        $response = $this->client()->get("/leads/{$sessionId}/history");
        return $response->throw()->json();
    }

    public function getRealtimeEvents(int $afterId = 0, int $limit = 50): array
    {
        $response = $this->client()->get('/realtime/events', [
            'after_id' => $afterId,
            'limit' => $limit,
        ]);

        return $response->throw()->json();
    }

    protected function gatewayPayload(Response $response, string $tenantId): array
    {
        $json = $response->json();
        $payload = is_array($json) ? $json : [];

        if ($response->successful()) {
            return $payload + [
                'success' => true,
                'tenant_id' => $tenantId,
            ];
        }

        if ($response->status() === 404) {
            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'status' => 'not_found',
                'message' => 'Gateway session was not found.',
            ];
        }

        return [
            'success' => false,
            'tenant_id' => $tenantId,
            'status' => 'gateway_error',
            'status_code' => $response->status(),
            'message' => $this->gatewayMessage($response, $payload),
            'details' => $payload,
        ];
    }

    protected function gatewayMessage(Response $response, array $payload = []): string
    {
        $message = $payload['message'] ?? $payload['error'] ?? $payload['detail'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            $message = (string) $response->body();
        }

        $message = trim(strip_tags($message));

        if ($message === '' || str_contains(strtolower($message), 'doctype html')) {
            return 'WhatsApp gateway request failed.';
        }

        return mb_substr(preg_replace('/\s+/', ' ', $message) ?: $message, 0, 220);
    }

    protected function requestGatewayCandidates(array $candidates, string $tenantId, bool $sessionLookup = false): array
    {
        $notFound = null;
        $failed = null;

        foreach ($candidates as $candidate) {
            $method = strtolower($candidate['method'] ?? 'get');
            $url = $candidate['url'] ?? '/';
            $payload = $candidate['payload'] ?? [];
            $query = $candidate['query'] ?? [];

            $response = match ($method) {
                'post' => $this->gatewayClient()->post($url, $payload),
                'delete' => $this->gatewayClient()->delete($url, $payload),
                default => $this->gatewayClient()->get($url, $query),
            };

            if ($response->successful()) {
                return $this->gatewayPayload($response, $tenantId);
            }

            if ($response->status() === 404) {
                $notFound = $response;
                continue;
            }

            if ($response->status() === 405) {
                $failed = $response;
                continue;
            }

            return $this->gatewayPayload($response, $tenantId);
        }

        if ($notFound) {
            if ($sessionLookup) {
                return $this->gatewayPayload($notFound, $tenantId);
            }

            return [
                'success' => false,
                'tenant_id' => $tenantId,
                'status' => 'gateway_route_not_found',
                'status_code' => 404,
                'message' => 'WhatsApp gateway session endpoint was not found. Set WHATSAPP_GATEWAY_API_URL to the WhatsApp_GateWay service URL.',
            ];
        }

        return $failed
            ? $this->gatewayPayload($failed, $tenantId)
            : [
                'success' => false,
                'tenant_id' => $tenantId,
                'status' => 'gateway_error',
                'message' => 'WhatsApp gateway request failed.',
            ];
    }

    public function createGatewaySession(string $tenantId): array
    {
        $tenantPath = rawurlencode($tenantId);

        return $this->requestGatewayCandidates([
            ['method' => 'post', 'url' => '/api/sessions/create', 'payload' => ['tenantId' => $tenantId]],
            ['method' => 'post', 'url' => '/api/sessions/create', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'post', 'url' => '/gateway/sessions/create', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'post', 'url' => '/gateway/sessions/create', 'payload' => ['tenantId' => $tenantId]],
            ['method' => 'post', 'url' => '/sessions/create', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'post', 'url' => '/sessions/create', 'payload' => ['tenantId' => $tenantId]],
            ['method' => 'post', 'url' => '/gateway/session/create', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'post', 'url' => '/gateway/session/create', 'payload' => ['tenantId' => $tenantId]],
            ['method' => 'post', 'url' => '/sessions', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'post', 'url' => '/sessions', 'payload' => ['tenantId' => $tenantId]],
            ['method' => 'post', 'url' => "/sessions/{$tenantPath}/create"],
            ['method' => 'post', 'url' => "/gateway/sessions/{$tenantPath}/create"],
        ], $tenantId);
    }

    public function getGatewaySessionStatus(string $tenantId): array
    {
        $tenantPath = rawurlencode($tenantId);

        return $this->requestGatewayCandidates([
            ['method' => 'get', 'url' => "/api/sessions/{$tenantPath}/status"],
            ['method' => 'get', 'url' => "/gateway/sessions/{$tenantPath}/status"],
            ['method' => 'get', 'url' => "/sessions/{$tenantPath}/status"],
            ['method' => 'get', 'url' => "/gateway/session/{$tenantPath}/status"],
            ['method' => 'get', 'url' => '/api/sessions/status', 'query' => ['tenantId' => $tenantId]],
            ['method' => 'get', 'url' => '/gateway/sessions/status', 'query' => ['tenant_id' => $tenantId]],
            ['method' => 'get', 'url' => '/sessions/status', 'query' => ['tenant_id' => $tenantId]],
            ['method' => 'get', 'url' => '/sessions/status', 'query' => ['tenantId' => $tenantId]],
        ], $tenantId, true);
    }

    public function getGatewaySessionQr(string $tenantId): array
    {
        $tenantPath = rawurlencode($tenantId);

        return $this->requestGatewayCandidates([
            ['method' => 'get', 'url' => "/api/sessions/{$tenantPath}/qr"],
            ['method' => 'get', 'url' => "/gateway/sessions/{$tenantPath}/qr"],
            ['method' => 'get', 'url' => "/sessions/{$tenantPath}/qr"],
            ['method' => 'get', 'url' => "/gateway/session/{$tenantPath}/qr"],
            ['method' => 'get', 'url' => '/api/sessions/qr', 'query' => ['tenantId' => $tenantId]],
            ['method' => 'get', 'url' => '/gateway/sessions/qr', 'query' => ['tenant_id' => $tenantId]],
            ['method' => 'get', 'url' => '/sessions/qr', 'query' => ['tenant_id' => $tenantId]],
            ['method' => 'get', 'url' => '/sessions/qr', 'query' => ['tenantId' => $tenantId]],
        ], $tenantId, true);
    }

    public function logoutGatewaySession(string $tenantId): array
    {
        $tenantPath = rawurlencode($tenantId);

        return $this->requestGatewayCandidates([
            ['method' => 'post', 'url' => "/api/sessions/{$tenantPath}/logout"],
            ['method' => 'post', 'url' => "/gateway/sessions/{$tenantPath}/logout"],
            ['method' => 'post', 'url' => "/sessions/{$tenantPath}/logout"],
            ['method' => 'post', 'url' => "/gateway/session/{$tenantPath}/logout"],
            ['method' => 'post', 'url' => '/api/sessions/logout', 'payload' => ['tenantId' => $tenantId]],
            ['method' => 'post', 'url' => '/gateway/sessions/logout', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'post', 'url' => '/sessions/logout', 'payload' => ['tenant_id' => $tenantId]],
            ['method' => 'delete', 'url' => "/api/sessions/{$tenantPath}"],
            ['method' => 'delete', 'url' => "/gateway/sessions/{$tenantPath}"],
            ['method' => 'delete', 'url' => "/sessions/{$tenantPath}"],
        ], $tenantId);
    }
}
