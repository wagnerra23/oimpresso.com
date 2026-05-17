<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\PatternLearningService;

class PatternsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, PatternLearningService $service): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        // D6.a Wave 18 — Inertia::defer pra props caras.
        // Cada bloco invoca PatternLearningService (Wilson bound calc + drift
        // detection) ou query GROUP BY — defer evita block initial render.
        return Inertia::render('ads/Admin/Patterns', [
            'patterns'   => Inertia::defer(fn () => $this->buildPatternsPayload($businessId, $service)),
            'candidates' => Inertia::defer(fn () => $service->listPromotionCandidates($businessId)),
            'drifts'     => Inertia::defer(fn () => $service->detectDrift($businessId)),
            'kpis'       => Inertia::defer(fn () => $this->buildKpisPayload($businessId, $service)),
        ]);
    }

    private function buildPatternsPayload(int $businessId, PatternLearningService $service): array
    {
        return DB::table('mcp_decision_patterns')
            ->where('business_id', $businessId)
            ->orderByDesc('total_count')
            ->get()
            ->map(function ($p) use ($service) {
                $lb = $service->wilsonLowerBound((int) $p->success_count, (int) $p->total_count);
                return [
                    'id'                 => $p->id,
                    'domain'             => $p->domain,
                    'event_type'         => $p->event_type,
                    'description'        => $p->description,
                    'success_count'      => (int) $p->success_count,
                    'total_count'        => (int) $p->total_count,
                    'success_rate'       => (float) $p->success_rate,
                    'wilson_lower_bound' => $lb,
                    'is_promotion_ready' => $service->isPromotionCandidate($p),
                    'is_hardcoded'       => (bool) $p->is_hardcoded,
                    'updated_at'         => $p->updated_at,
                ];
            })
            ->values()
            ->toArray();
    }

    private function buildKpisPayload(int $businessId, PatternLearningService $service): array
    {
        $patterns   = $this->buildPatternsPayload($businessId, $service);
        $candidates = $service->listPromotionCandidates($businessId);
        $drifts     = $service->detectDrift($businessId);

        return [
            'total_patterns' => count($patterns),
            'candidates'     => count($candidates),
            'drifts'         => count($drifts),
            'hardcoded'      => collect($patterns)->where('is_hardcoded', true)->count(),
        ];
    }
}
