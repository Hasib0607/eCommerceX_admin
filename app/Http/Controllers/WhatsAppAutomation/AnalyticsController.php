<?php

namespace App\Http\Controllers\WhatsAppAutomation;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppAutomation\BotApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected BotApiService $botApiService
    ) {
    }

    protected function analyticsResponse(string $method, Request $request): JsonResponse
    {
        $query = $request->only([
            'date_from',
            'date_to',
        ]);

        return response()->json(
            $this->botApiService->{$method}($query)
        );
    }

    public function leadSourceBehavior(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getLeadSourceBehaviorAnalytics', $request);
    }

    public function tagDistribution(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getTagDistributionAnalytics', $request);
    }

    public function conversionByTag(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getConversionByTagAnalytics', $request);
    }

    public function followupPerformance(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getFollowupPerformanceAnalytics', $request);
    }

    public function replySourceBreakdown(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getReplySourceBreakdownAnalytics', $request);
    }

    public function campaignPerformance(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getCampaignPerformanceAnalytics', $request);
    }

    public function unresolvedLearningTrends(Request $request): JsonResponse
    {
        return $this->analyticsResponse('getUnresolvedLearningTrendsAnalytics', $request);
    }
}
