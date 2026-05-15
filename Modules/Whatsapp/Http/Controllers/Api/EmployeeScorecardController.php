<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\EmployeePerformance;

/**
 * US-WA-VOZ-003 — Endpoint scorecard de atendente.
 *
 * GET /atendimento/employee/{user_id}/scorecard       (atendente real)
 * GET /atendimento/employee/heur:{name}/scorecard     (heurístico fallback)
 *
 * Tier 0: business_id resolvido via session (NÃO trusta query).
 * Permission: `whatsapp.access` (mesma do Inbox — quem atende vê próprio scorecard
 * e do time pra benchmark).
 *
 * Pode usar pra:
 *   - Sidebar UI mostrar "atendente atual desta conversa: Maiara · 94/100"
 *   - Dashboard Wagner admin ver ranking time
 *   - Wagner consultar nota individual em 1:1
 *
 * @see Modules/Whatsapp/Entities/EmployeePerformance.php
 */
class EmployeeScorecardController extends Controller
{
    public function show(Request $request, string $userIdentifier): JsonResponse
    {
        $businessId = (int) ($request->session()->get('business.id') ?? session('business')?->id ?? 0);
        if ($businessId <= 0) {
            return response()->json(['error' => 'no_business_context'], 401);
        }

        // Suporta 2 formatos:
        //   - numérico (ex: "42") = user_id real
        //   - "heur:Nome" = heurístico
        $query = EmployeePerformance::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId);

        if (str_starts_with($userIdentifier, 'heur:')) {
            $name = substr($userIdentifier, 5);
            if (! preg_match('/^[A-Za-zÀ-ÿ]{2,40}$/u', $name)) {
                return response()->json(['error' => 'invalid_heuristic_name'], 422);
            }
            $query->whereNull('user_id')->where('heuristic_name', $name);
        } else {
            if (! preg_match('/^\d+$/', $userIdentifier)) {
                return response()->json(['error' => 'invalid_user_identifier'], 422);
            }
            $query->where('user_id', (int) $userIdentifier);
        }

        $perf = $query->first();
        if ($perf === null) {
            return response()->json(['state' => 'not_found'], 404);
        }

        return response()->json([
            'state' => 'ok',
            'scorecard' => [
                'id' => $perf->id,
                'identity' => [
                    'user_id' => $perf->user_id,
                    'heuristic_name' => $perf->heuristic_name,
                    'display_name' => $perf->display_name,
                ],
                'volume' => [
                    'n_msgs_total' => $perf->n_msgs_total,
                    'n_conversations_atendidas' => $perf->n_conversations_atendidas,
                    'n_clientes_diferentes' => $perf->n_clientes_diferentes,
                ],
                'velocidade' => [
                    'mediana_s' => $perf->tempo_resposta_mediana_s,
                    'p90_s' => $perf->tempo_resposta_p90_s,
                    'sla_breach_count' => $perf->sla_breach_count,
                    'sla_threshold_s' => EmployeePerformance::SLA_FIRST_RESPONSE_SECONDS,
                ],
                'qualidade' => [
                    'reclamacoes_recebidas' => $perf->reclamacoes_recebidas,
                    'csat_avg' => $perf->csat_avg,
                ],
                'cobertura' => [
                    'horas_ativas_distintas' => $perf->horas_ativas_distintas,
                    'hora_pico' => $perf->hora_pico,
                    'dias_ativos_30d' => $perf->dias_ativos_30d,
                    'primeira_atividade_at' => optional($perf->primeira_atividade_at)->toIso8601String(),
                    'ultima_atividade_at' => optional($perf->ultima_atividade_at)->toIso8601String(),
                ],
                'especialidades' => [
                    'temas_dominantes' => $perf->temas_dominantes,
                ],
                'nota' => [
                    'geral' => $perf->nota_geral,
                    'faixa' => $perf->faixa(),
                    'breakdown' => $perf->nota_breakdown,
                    'calculada_em' => optional($perf->nota_calculada_em)->toIso8601String(),
                ],
                'flags' => $perf->flags ?? [],
                'last_rebuilt_at' => optional($perf->last_rebuilt_at)->toIso8601String(),
                'rebuilt_via' => $perf->rebuilt_via,
            ],
        ]);
    }

    /**
     * GET /atendimento/employee/scorecards
     *
     * Lista TODOS scorecards do business (ranking pra dashboard Wagner).
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = (int) ($request->session()->get('business.id') ?? session('business')?->id ?? 0);
        if ($businessId <= 0) {
            return response()->json(['error' => 'no_business_context'], 401);
        }

        $perfs = EmployeePerformance::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->orderByDesc('nota_geral')
            ->get();

        $items = $perfs->map(fn (EmployeePerformance $p) => [
            'user_id' => $p->user_id,
            'heuristic_name' => $p->heuristic_name,
            'display_name' => $p->display_name,
            'nota_geral' => $p->nota_geral,
            'faixa' => $p->faixa(),
            'n_msgs_total' => $p->n_msgs_total,
            'n_conversations_atendidas' => $p->n_conversations_atendidas,
            'reclamacoes_recebidas' => $p->reclamacoes_recebidas,
            'last_rebuilt_at' => optional($p->last_rebuilt_at)->toIso8601String(),
        ]);

        return response()->json([
            'business_id' => $businessId,
            'scorecards' => $items,
            'total' => $items->count(),
        ]);
    }
}
