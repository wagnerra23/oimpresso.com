<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConfidenceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $scores = DB::table('mcp_confidence_scores')
            ->orderByDesc('score')
            ->get()
            ->map(fn ($s) => [
                'domain'                => $s->domain,
                'event_type'            => $s->event_type,
                'score'                 => (float) $s->score,
                'sample_size'           => (int) $s->sample_size,
                'hitl_level'            => (int) $s->hitl_level,
                'last_outcome'          => $s->last_outcome,
                'consecutive_approvals' => (int) $s->consecutive_approvals,
                'consecutive_failures'  => (int) $s->consecutive_failures,
                'updated_at'            => $s->updated_at,
            ]);

        $kpis = [
            'total_pares'       => $scores->count(),
            'autonomos_brain_a' => $scores->where('hitl_level', 0)->count(),
            'media_score'       => $scores->isEmpty() ? 0 : round((float) $scores->avg('score'), 3),
            'sample_total'      => (int) $scores->sum('sample_size'),
        ];

        return Inertia::render('ads/Admin/Confidence', [
            'scores' => $scores->values(),
            'kpis'   => $kpis,
        ]);
    }
}
