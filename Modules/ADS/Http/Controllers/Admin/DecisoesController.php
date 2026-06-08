<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\DecisionPresenter;

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
        $tab = $request->get('tab', 'pendentes'); // pendentes | em_andamento | subtarefas | historico

        // D6.a Wave 18 RETRY — `kpis` (5 COUNTs cumulativos por destination/outcome)
        // e `decisions` (50 rows + DecisionPresenter::explain por row) defer pra
        // pular re-render quando frontend troca de tab via partial reload.
        return Inertia::render('ads/Admin/Decisoes', [
            'tab'       => $tab,
            'decisions' => Inertia::defer(fn () => $this->buildDecisionsPayload($businessId, $tab)),
            'kpis'      => Inertia::defer(fn () => $this->buildKpisPayload($businessId)),
        ]);
    }

    /**
     * D6.a Wave 18 RETRY — extraído pra pular initial render quando partial reload.
     */
    private function buildDecisionsPayload(int $businessId, string $tab): array
    {
        $query = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId);

        if ($tab === 'pendentes') {
            $query->whereIn('destination', ['pending_wagner', 'blocked'])
                  ->where('outcome', 'cancelled')
                  ->whereNull('dismissed_at')
                  ->whereNull('parent_decision_id');
        } elseif ($tab === 'em_andamento') {
            $query->where('destination', 'brain_b')
                  ->where('outcome', 'cancelled')
                  ->whereNull('dismissed_at');
        } elseif ($tab === 'subtarefas') {
            $query->whereNotNull('parent_decision_id')
                  ->whereNull('dismissed_at');
        } else { // historico — inclui dispensados
            $query->where(function ($q) {
                $q->whereNotIn('outcome', ['cancelled'])
                  ->orWhereNotNull('dismissed_at');
            });
        }

        return $query->orderByDesc('id')->limit(50)->get()->map(function ($d) {
            $explained = DecisionPresenter::explain($d);
            return [
                'id'                  => $d->id,
                'parent_decision_id'  => $d->parent_decision_id,
                'event_type'          => $d->event_type,
                'event_source'        => $d->event_source,
                'domain'              => $d->domain,
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

                // Campos legíveis (DecisionPresenter)
                'one_line'          => $explained['one_line'],
                'why_badge'         => $explained['why_badge'],
                'status_label'      => $explained['status_label'],
                'actionable'        => $explained['actionable'],
                'action_hint'       => $explained['action_hint'],
                'risk_label'        => $explained['risk_label'],
            ];
        })->all();
    }

    /**
     * D6.a Wave 18 RETRY — 5 COUNTs por destination/outcome.
     */
    private function buildKpisPayload(int $businessId): array
    {
        return [
            'pendentes'    => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->whereIn('destination', ['pending_wagner', 'blocked'])
                ->where('outcome', 'cancelled')
                ->whereNull('dismissed_at')
                ->count(),
            'em_andamento' => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->where('destination', 'brain_b')
                ->where('outcome', 'cancelled')
                ->whereNull('dismissed_at')
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
            'subtarefas'    => DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $businessId)
                ->whereNotNull('parent_decision_id')
                ->whereNull('dismissed_at')
                ->where('outcome', 'cancelled')
                ->count(),
        ];
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

        // ─── Drill-down chain ───
        // 1. Decisão pai (se for subtarefa)
        $parent = null;
        if ($decision->parent_decision_id) {
            $p = DB::table('mcp_dual_brain_decisions')
                ->where('id', $decision->parent_decision_id)
                ->first();
            if ($p) {
                $parent = [
                    'id'         => $p->id,
                    'event_type' => $p->event_type,
                    'domain'     => $p->domain,
                    'destination' => $p->destination,
                    'outcome'    => $p->outcome,
                ];
            }
        }

        // 2. Subtarefas (se for decisão pai)
        $children = DB::table('mcp_dual_brain_decisions')
            ->where('parent_decision_id', $decision->id)
            ->orderBy('id')
            ->get(['id', 'event_type', 'domain', 'destination', 'outcome', 'review_score'])
            ->map(fn ($c) => [
                'id'           => $c->id,
                'event_type'   => $c->event_type,
                'domain'       => $c->domain,
                'destination'  => $c->destination,
                'outcome'      => $c->outcome,
                'review_score' => $c->review_score,
            ])
            ->all();

        // 3. Skill (pattern) relacionado a esse (domain × event_type)
        $skill = DB::table('mcp_decision_patterns')
            ->where('business_id', $businessId)
            ->where('domain', $decision->domain)
            ->where('event_type', $decision->event_type)
            ->first();
        $skillData = $skill ? [
            'id'            => $skill->id,
            'description'   => $skill->description,
            'success_count' => (int) $skill->success_count,
            'total_count'   => (int) $skill->total_count,
            'success_rate'  => (float) $skill->success_rate,
            'is_hardcoded'  => (bool) $skill->is_hardcoded,
        ] : null;

        // 4. Meta-skills que poderiam ter sido aplicadas (categoria correspondente ao destination/outcome)
        $applicableCategories = match ($decision->destination) {
            'pending_wagner' => ['escalation'],
            'brain_b'        => ['retry'],
            'blocked'        => [],
            default          => ['promotion'],
        };
        if ($decision->review_score !== null) {
            $applicableCategories[] = 'retry';
        }
        $metaSkills = DB::table('mcp_governance_rules')
            ->whereIn('category', $applicableCategories)
            ->where('enabled', true)
            ->get(['id', 'rule_key', 'name', 'category', 'condition', 'triggered_count'])
            ->map(fn ($r) => [
                'id'              => $r->id,
                'rule_key'        => $r->rule_key,
                'name'            => $r->name,
                'category'        => $r->category,
                'triggered_count' => (int) $r->triggered_count,
            ])
            ->all();

        // 5. Review breakdown (se já foi reviewed)
        $reviewBreakdown = $decision->review_breakdown
            ? json_decode($decision->review_breakdown, true)
            : null;

        return Inertia::render('ads/Admin/DecisaoShow', [
            'decision' => [
                'id'                  => $decision->id,
                'parent_decision_id'  => $decision->parent_decision_id,
                'event_type'          => $decision->event_type,
                'event_source'        => $decision->event_source,
                'domain'              => $decision->domain,
                'risk_score'          => (float) $decision->risk_score,
                'confidence_score'    => (float) $decision->confidence_score,
                'policy_applied'      => $decision->policy_applied,
                'destination'         => $decision->destination,
                'hitl_level'          => (int) $decision->hitl_level,
                'brain_used'          => $decision->brain_used,
                'model_used'          => $decision->model_used,
                'outcome'             => $decision->outcome,
                'tokens_used'         => $decision->tokens_used,
                'execution_ms'        => $decision->execution_ms,
                'files_affected'      => json_decode($decision->files_affected ?? '[]', true) ?: [],
                'event_metadata'      => json_decode($decision->event_metadata ?? '{}', true) ?: [],
                'instruction'         => $instruction,
                'created_at'          => $decision->created_at,
                'resolved_at'         => $decision->resolved_at,
                'review_score'        => $decision->review_score,
                'review_confidence'   => $decision->review_confidence !== null ? (float) $decision->review_confidence : null,
                'attempts'            => (int) ($decision->attempts ?? 0),
            ],
            'chain' => [
                'parent'      => $parent,
                'children'    => $children,
                'skill'       => $skillData,
                'meta_skills' => $metaSkills,
                'review_breakdown' => $reviewBreakdown,
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

    /**
     * Dispensa item não-acionável do inbox (ex: blocked pelo firewall).
     * Não muda outcome — só seta dismissed_at. Item vai pra Histórico.
     */
    public function dismiss(Request $request, int $id): RedirectResponse
    {
        DB::table('mcp_dual_brain_decisions')
            ->where('id', $id)
            ->whereNull('dismissed_at')
            ->update(['dismissed_at' => now()]);

        return back()->with('status', "Decision #{$id} dispensada. Movida pra Histórico.");
    }
}
