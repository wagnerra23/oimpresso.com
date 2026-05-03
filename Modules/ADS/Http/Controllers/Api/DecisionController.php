<?php

namespace Modules\ADS\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\ADS\Services\DecisionRouter;
use Modules\ADS\Services\RoutingInput;

/**
 * ARQ-0003 — Endpoint único de entrada de eventos no ADS.
 *
 * POST /api/ads/route
 * Auth: Bearer ADS_API_KEY
 * Body: { event_type, domain, event_source, files_affected[], metadata{}, business_id }
 */
class DecisionController extends Controller
{
    public function __construct(
        private readonly DecisionRouter $router,
    ) {}

    public function route(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type'      => 'required|string|max:80',
            'domain'          => 'required|string|max:50',
            'event_source'    => 'required|in:brain_a,evolution_agent,wagner,scheduler',
            'business_id'     => 'required|integer|min:1',
            'files_affected'  => 'sometimes|array',
            'files_affected.*' => 'string|max:500',
            'metadata'        => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'validation_failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $decision = $this->router->route(new RoutingInput(
                businessId:    (int) $data['business_id'],
                eventType:     $data['event_type'],
                eventSource:   $data['event_source'],
                domain:        $data['domain'],
                filesAffected: $data['files_affected'] ?? [],
                metadata:      $data['metadata'] ?? [],
            ));

            return response()->json([
                'decision_id'      => $decision->decisionId,
                'destination'      => $decision->destination,
                'risk_score'       => $decision->riskScore,
                'confidence_score' => $decision->confidenceScore,
                'policy_applied'   => $decision->policyApplied,
                'hitl_level'       => $decision->hitlLevel,
            ], 200);
        } catch (\Throwable $e) {
            Log::channel('single')->error('ads.route.failed', [
                'event_type' => $data['event_type'],
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'internal_error',
                'message' => app()->environment('production') ? 'erro interno' : $e->getMessage(),
            ], 500);
        }
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'ADS',
            'time'   => now()->toIso8601String(),
        ]);
    }
}
