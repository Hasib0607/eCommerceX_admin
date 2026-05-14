<?php

namespace App\Services\WhatsAppAutomation;

use Illuminate\Http\Client\PendingRequest;
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
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->timeout(90)
            ->retry(3, 2000)
            ->acceptJson()
            ->withHeaders([
                'X-Admin-Token' => $adminToken,
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
}
