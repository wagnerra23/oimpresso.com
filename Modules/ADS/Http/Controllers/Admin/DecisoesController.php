<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UI Inbox — Wagner aprova/rejeita/modifica decisions pendentes.
 * ARQ-0008 HiTL-2/HiTL-3.
 *
 * Permissão futura: ads.decisoes.review (criar quando outras pessoas usarem).
 * Por ora, restringido a superadmin.
 */
class DecisoesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // V1: só superadmin Wagner. V2: criar permission `ads.decisoes.review`.
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $tab = $request->get('tab', 'pendentes'); // pendentes | em_andamento | historico

        $query = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId);

        if ($tab === 'pendentes') {
            $query->whereIn('destination', ['pending_wagner', 'blocked'])
                  ->where('outcome', 'cancelled');
        } elseif ($tab === 'em_andamento') {
            $query->where('destination', 'brain_b')
                  ->where('outcome', 'cancelled');
        } else { // historico
            $query->whereNotIn('outcome', ['cancelled']);
        }

        $decisions = $query->orderByDesc('id')->limit(50)->get()->map(function ($d) {
            return [
                'id'                => $d->id,
                'event_type'        => $d->event_type,
                'event_source'      => $d->event_source,
                'domain'            => $d->domain,
                'risk_score'        => (float) $d->risk_score,
                'confidence_score'  => (float) $d->confidence_score,
                'policy_applied'    => $d->policy_applied,
                'destination'       => $d->destination,
                'hitl_level'        => (int) $d->hitl_level,
                'brain_used'        => $d->brain_used,
                'outcome'           => $d->outcome,
                'instruction_short' => $d->instruction_generated
                    ? mb_strimwidth(strip_tags($d->instruction_generated), 0, 200, '…')
                    : null,
                'created_at'        => $d->created_at,
                'resolved_at'       => $d->resolved_at,
            ];
        });

        $kpis = [
            'pendentes'    => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->whereIn('destination', ['pending_wagner', 'blocked'])
                ->where('outcome', 'cancelled')
                ->count(),
            'em_andamento' => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->where('destination', 'brain_b')
                ->where('outcome', 'cancelled')
                ->count(),
            'concluidas_7d' => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->where('outcome', 'success')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'rejeitadas_7d' => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->where('outcome', 'wagner_rejected')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        return Inertia::render('ads/Admin/Decisoes', [
            'tab'       => $tab,
            'decisions' => $decisions,
            'kpis'      => $kpis,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $decision = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        $instruction = null;
        if ($decision->instruction_generated) {
            $raw = $decision->instruction_generated;
            if (preg_match('/```(?:json)?\s*(\{.+\})\s*```/s', $raw, $m)) {
                $raw = $m[1];
            }
            $parsed = json_decode($raw, true);
            $instruction = is_array($parsed) ? $parsed : ['raw' => $decision->instruction_generated];
        }

        return Inertia::render('ads/Admin/DecisaoShow', [
            'decision' => [
                'id'               => $decision->id,
                'event_type'       => $decision->event_type,
                'event_source'     => $decision->event_source,
                'domain'           => $decision->domain,
                'risk_score'       => (float) $decision->risk_score,
                'confidence_score' => (float) $decision->confidence_score,
                'policy_applied'   => $decision->policy_applied,
                'destination'      => $decision->destination,
                'hitl_level'       => (int) $decision->hitl_level,
                'brain_used'       => $decision->brain_used,
                'model_used'       => $decision->model_used,
                'outcome'          => $decision->outcome,
                'tokens_used'      => $decision->tokens_used,
                'execution_ms'     => $decision->execution_ms,
                'files_affected'   => json_decode($decision->files_affected ?? '[]', true) ?: [],
                'event_metadata'   => json_decode($decision->event_metadata ?? '{}', true) ?: [],
                'instruction'      => $instruction,
                'created_at'       => $decision->created_at,
                'resolved_at'      => $decision->resolved_at,
            ],
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        DB::table('mcp_dual_brain_decisions')
            ->where('id', $id)
            ->whereIn('outcome', ['cancelled'])
            ->update([
                'outcome'     => 'success',
                'resolved_at' => now(),
                'resolved_by' => 'wagner',
            ]);

        return back()->with('status', "Decision #{$id} aprovada. Instrução liberada para execução.");
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $reason = $request->input('reason', '');

        DB::table('mcp_dual_brain_decisions')
            ->where('id', $id)
            ->whereIn('outcome', ['cancelled'])
            ->update([
                'outcome'             => 'wagner_rejected',
                'wagner_modified_to'  => $reason !== '' ? "REJEITADO: {$reason}" : 'REJEITADO',
                'resolved_at'         => now(),
                'resolved_by'         => 'wagner',
            ]);

        return back()->with('status', "Decision #{$id} rejeitada. ConfidenceEngine vai aprender (-2.0).");
    }
}
