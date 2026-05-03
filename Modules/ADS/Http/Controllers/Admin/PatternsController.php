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

        $patterns = DB::table('mcp_decision_patterns')
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
            });

        $candidates = $service->listPromotionCandidates($businessId);
        $drifts     = $service->detectDrift($businessId);

        $kpis = [
            'total_patterns'  => $patterns->count(),
            'candidates'      => count($candidates),
            'drifts'          => count($drifts),
            'hardcoded'       => $patterns->where('is_hardcoded', true)->count(),
        ];

        return Inertia::render('ads/Admin/Patterns', [
            'patterns'   => $patterns->values(),
            'candidates' => $candidates,
            'drifts'     => $drifts,
            'kpis'       => $kpis,
        ]);
    }
}
