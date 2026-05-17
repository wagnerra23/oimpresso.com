<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\PatternLearningService;

/**
 * Conflicts Panel (Cognitive Control Panel #5).
 *
 * Detecta 3 tipos de conflito real do sistema:
 *   1. file_lock_concurrent: 2+ decisions tentaram modificar o mesmo arquivo
 *   2. drift_vs_pattern: padrão com taxa caiu mas Wilson ainda alto (decisão pendente)
 *   3. human_vs_ai: Wagner aprovou mas ReviewerAgent score < 50 (julgamentos discordam)
 */
class ConflictsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, PatternLearningService $patterns): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        // D6.a Wave 18 — Inertia::defer pra 3 detectores pesados (cada um
        // varre últimas 7 dias de mcp_dual_brain_decisions + faz Wilson calc).
        return Inertia::render('ads/Admin/Conflicts', [
            'file_lock_conflicts' => Inertia::defer(fn () => $this->detectFileLockConflicts($businessId)),
            'drift_conflicts'     => Inertia::defer(fn () => $patterns->detectDrift($businessId)),
            'judgment_conflicts'  => Inertia::defer(fn () => $this->detectHumanAiJudgmentConflicts($businessId)),
            'kpis'                => Inertia::defer(fn () => $this->buildKpisPayload($businessId, $patterns)),
        ]);
    }

    private function buildKpisPayload(int $businessId, PatternLearningService $patterns): array
    {
        $fileLock = $this->detectFileLockConflicts($businessId);
        $drift    = $patterns->detectDrift($businessId);
        $judg     = $this->detectHumanAiJudgmentConflicts($businessId);
        return [
            'file_lock' => count($fileLock),
            'drift'     => count($drift),
            'human_ai'  => count($judg),
            'total'     => count($fileLock) + count($drift) + count($judg),
        ];
    }

    /**
     * Detecta decisions que tentaram tocar o mesmo arquivo dentro de 1h.
     */
    private function detectFileLockConflicts(int $businessId): array
    {
        // Pega decisions com files_affected nas últimas 7 dias
        $decisions = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('files_affected')
            ->where('files_affected', '!=', '[]')
            ->get(['id', 'event_type', 'domain', 'files_affected', 'destination', 'created_at']);

        $byFile = [];
        foreach ($decisions as $d) {
            $files = json_decode($d->files_affected ?? '[]', true) ?: [];
            foreach ($files as $f) {
                $byFile[$f][] = $d;
            }
        }

        $conflicts = [];
        foreach ($byFile as $file => $arr) {
            if (count($arr) < 2) continue;

            // Verifica se há 2+ na mesma janela 1h
            usort($arr, fn ($a, $b) => strcmp($a->created_at, $b->created_at));
            for ($i = 0; $i < count($arr) - 1; $i++) {
                $a = strtotime($arr[$i]->created_at);
                $b = strtotime($arr[$i + 1]->created_at);
                if (($b - $a) < 3600) {
                    $conflicts[] = [
                        'file'        => $file,
                        'decision_a'  => ['id' => $arr[$i]->id,     'event_type' => $arr[$i]->event_type,     'destination' => $arr[$i]->destination],
                        'decision_b'  => ['id' => $arr[$i + 1]->id, 'event_type' => $arr[$i + 1]->event_type, 'destination' => $arr[$i + 1]->destination],
                        'gap_minutes' => round(($b - $a) / 60, 1),
                        'recommendation' => "Revisar se decisões #{$arr[$i]->id} e #{$arr[$i+1]->id} são duplicadas. File mutex evita execução simultânea.",
                    ];
                    break; // só 1 conflito por arquivo
                }
            }
        }

        return $conflicts;
    }

    /**
     * Decisions onde Wagner aprovou (success/wagner_modified) mas review_score < 50.
     */
    private function detectHumanAiJudgmentConflicts(int $businessId): array
    {
        $rows = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->whereIn('outcome', ['success', 'wagner_modified'])
            ->whereNotNull('review_score')
            ->where('review_score', '<', 50)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'event_type', 'domain', 'outcome', 'review_score', 'review_confidence', 'review_breakdown', 'created_at']);

        $conflicts = [];
        foreach ($rows as $r) {
            $breakdown = json_decode($r->review_breakdown ?? '{}', true) ?: [];
            $conflicts[] = [
                'decision_id'       => $r->id,
                'event_type'        => $r->event_type,
                'domain'            => $r->domain,
                'human_action'      => $r->outcome === 'success' ? 'Aprovou sem modificação' : 'Aprovou com modificação',
                'ai_score'          => (int) $r->review_score,
                'ai_confidence'     => (float) $r->review_confidence,
                'ai_issues'         => $breakdown['issues'] ?? [],
                'created_at'        => $r->created_at,
                'recommendation'    => "Wagner aprovou mas ReviewerAgent deu nota {$r->review_score}/100. Investigar discordância: ou Wagner sabe algo que IA não vê, ou padrão merece revisão de prompt.",
            ];
        }

        return $conflicts;
    }
}
