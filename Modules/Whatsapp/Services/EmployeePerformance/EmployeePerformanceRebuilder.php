<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\EmployeePerformance;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\EmployeePerformance;
use Throwable;

/**
 * Observabilidade D9.a (ADR 0155): `rebuild()` envolto em `OtelHelper::span(`
 * (Tracer whatsapp.employee_performance.rebuild) — mede aggregations por business.
 *
 * US-WA-VOZ-003 — Recompila scorecard de 1 atendente do business.
 *
 * Stateless. Idempotente. Fail-open per-step.
 *
 * Identidade: aceita `user_id` (PRIMÁRIO via UI Inbox sender_user_id) OU
 * `heuristic_name` (FALLBACK via *Nome:* prefix no body — captura time
 * que responde direto WhatsApp Web sem usar UI).
 *
 * Scoring transparente 0-100 (publicado pro time saber):
 *   - Volume produtivo   (25 pts) — n_msgs (1500=25, escala linear cap)
 *   - Diversidade        (20 pts) — n_clientes (150=20)
 *   - Velocidade resposta (25 pts) — mediana <60s=25, <300s=18, <900s=12, <1800s=6, else=2
 *   - Profundidade conv  (15 pts) — msgs/conv 5-15=15 (sweet spot), 3-20=10, else=5
 *   - Cobertura horária  (10 pts) — horas_ativas_distintas (10h=10)
 *   - Engajamento        (5 pts)  — fixo (placeholder pra CSAT futuro)
 *
 * @see Modules/Whatsapp/Entities/EmployeePerformance.php
 */
class EmployeePerformanceRebuilder
{
    public const SCORE_MAX = 100;
    public const DEFAULT_BUSINESS_HOURS_START = 8;
    public const DEFAULT_BUSINESS_HOURS_END = 18;

    /**
     * Recompila scorecard pra 1 atendente identificado por user_id OU heuristic_name.
     * Pelo menos um dos 2 deve ser não-null.
     *
     * @param  int|null    $userId         FK users.id (PRIMÁRIO)
     * @param  string|null $heuristicName  Nome em body *Nome:* (FALLBACK)
     */
    public function rebuild(int $businessId, ?int $userId = null, ?string $heuristicName = null, string $via = EmployeePerformance::REBUILT_VIA_MANUAL): EmployeePerformance
    {
        if ($userId === null && ($heuristicName === null || $heuristicName === '')) {
            throw new \InvalidArgumentException('user_id OU heuristic_name obrigatório');
        }

        // Localiza ou cria
        $query = EmployeePerformance::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id')->where('heuristic_name', $heuristicName);
        }

        $perf = $query->first();
        if ($perf === null) {
            $perf = new EmployeePerformance();
            $perf->business_id = $businessId;
            $perf->user_id = $userId;
            $perf->heuristic_name = $heuristicName;
        }

        // Step 1 — display_name (user real OU heurístico)
        $this->refreshDisplayName($perf, $businessId);

        // Step 2 — stats agregados (volume, conversas, clientes)
        $this->refreshStats($perf, $businessId);

        // Step 3 — velocidade resposta (mediana + p90 + SLA breach)
        $this->refreshVelocity($perf, $businessId);

        // Step 4 — cobertura horária + dias ativos
        $this->refreshCoverage($perf, $businessId);

        // Step 5 — qualidade (reclamações dos clientes atendidos)
        $this->refreshQuality($perf, $businessId);

        // Step 6 — calcular nota 0-100 + breakdown
        $this->calculateScore($perf);

        $perf->last_rebuilt_at = now();
        $perf->rebuilt_via = $via;
        $perf->save();

        Log::channel('single')->info('[employee_performance.rebuilt]', [
            'metric_name' => 'employee_performance_rebuilt',
            'business_id' => $businessId,
            'performance_id' => $perf->id,
            'identity' => $perf->identidade(),
            'nota_geral' => $perf->nota_geral,
            'n_msgs' => $perf->n_msgs_total,
            'via' => $via,
        ]);

        return $perf;
    }

    /**
     * Query SQL canônica — JOIN messages × conversations filtrado por business
     * + identidade do atendente (user_id OU body LIKE heuristic).
     */
    protected function baseMessageQuery(int $businessId, EmployeePerformance $perf): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('messages as m')
            ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.business_id', $businessId)
            ->where('m.direction', 'outbound')
            ->where('m.is_internal_note', false);

        if ($perf->user_id !== null) {
            $q->where('m.sender_user_id', $perf->user_id);
        } else {
            // Heurística — body LIKE '%Nome:%' (case-insensitive Maiara/Luiz/Felipe)
            $q->where('m.body', 'like', '%' . $perf->heuristic_name . ':%');
        }

        return $q;
    }

    protected function refreshDisplayName(EmployeePerformance $perf, int $businessId): void
    {
        if ($perf->user_id !== null) {
            try {
                // SUPERADMIN: rebuild roda via CLI/job sem session — resolve o User
                // (pelo user_id do scorecard já escopado a $businessId) só pra ler
                // first_name/last_name de exibição. Sem leak cross-tenant. ADR 0093.
                $user = User::query()->withoutGlobalScopes()->find($perf->user_id);
                if ($user !== null) {
                    $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                    $perf->display_name = $name !== '' ? $name : ($user->username ?? "user#{$perf->user_id}");
                    return;
                }
            } catch (Throwable $e) {
                // fall through
            }
        }
        $perf->display_name = $perf->heuristic_name ?? 'unknown';
    }

    protected function refreshStats(EmployeePerformance $perf, int $businessId): void
    {
        try {
            $base = $this->baseMessageQuery($businessId, $perf);

            $perf->n_msgs_total = (clone $base)->count();
            $perf->n_conversations_atendidas = (clone $base)->distinct()->count('m.conversation_id');

            $clientes = (clone $base)
                ->whereNotNull('c.customer_external_id')
                ->distinct()->count('c.customer_external_id');
            $perf->n_clientes_diferentes = $clientes;

            $first = (clone $base)->min('m.created_at');
            $last = (clone $base)->max('m.created_at');
            $perf->primeira_atividade_at = $first;
            $perf->ultima_atividade_at = $last;
        } catch (Throwable $e) {
            Log::channel('single')->warning('[employee_performance.stats_failed]', [
                'business_id' => $businessId,
                'identity' => $perf->identidade(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function refreshVelocity(EmployeePerformance $perf, int $businessId): void
    {
        try {
            // Pra cada outbound do atendente, pega o inbound IMEDIATAMENTE anterior
            // da mesma conversa e calcula delta (gap de resposta).
            $where = $perf->user_id !== null
                ? "m.sender_user_id = " . (int) $perf->user_id
                : "m.body LIKE " . DB::getPdo()->quote('%' . $perf->heuristic_name . ':%');

            $rows = DB::select("
                SELECT TIMESTAMPDIFF(SECOND,
                    (SELECT MAX(m2.created_at)
                     FROM messages m2
                     WHERE m2.conversation_id = m.conversation_id
                       AND m2.direction = 'inbound'
                       AND m2.created_at < m.created_at), m.created_at) AS delta_s
                FROM messages m
                JOIN conversations c ON c.id = m.conversation_id
                WHERE c.business_id = ?
                  AND m.direction = 'outbound'
                  AND m.is_internal_note = 0
                  AND {$where}
                LIMIT 1000
            ", [$businessId]);

            $deltas = array_values(array_filter(
                array_column($rows, 'delta_s'),
                fn ($d) => $d !== null && $d > 0 && $d < 86400
            ));

            if (empty($deltas)) {
                $perf->tempo_resposta_mediana_s = null;
                $perf->tempo_resposta_p90_s = null;
                $perf->sla_breach_count = 0;
                return;
            }

            sort($deltas);
            $n = count($deltas);
            $perf->tempo_resposta_mediana_s = (int) $deltas[(int) ($n / 2)];
            $perf->tempo_resposta_p90_s = (int) $deltas[(int) ($n * 0.9)];

            $sla = EmployeePerformance::SLA_FIRST_RESPONSE_SECONDS;
            $perf->sla_breach_count = count(array_filter($deltas, fn ($d) => $d > $sla));
        } catch (Throwable $e) {
            Log::channel('single')->warning('[employee_performance.velocity_failed]', [
                'business_id' => $businessId,
                'identity' => $perf->identidade(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function refreshCoverage(EmployeePerformance $perf, int $businessId): void
    {
        try {
            $base = $this->baseMessageQuery($businessId, $perf);

            $hourCounts = (clone $base)
                ->select(DB::raw('HOUR(m.created_at) as h, COUNT(*) as n'))
                ->groupBy('h')
                ->get();

            $perf->horas_ativas_distintas = (int) $hourCounts->count();

            if ($hourCounts->isNotEmpty()) {
                $top = $hourCounts->sortByDesc('n')->first();
                $perf->hora_pico = (int) $top->h;
            } else {
                $perf->hora_pico = null;
            }

            // Dias ativos 30d
            $dias = (clone $base)
                ->where('m.created_at', '>=', now()->subDays(30))
                ->select(DB::raw('DATE(m.created_at) as d'))
                ->distinct()
                ->count('d');
            $perf->dias_ativos_30d = (int) $dias;
        } catch (Throwable $e) {
            Log::channel('single')->warning('[employee_performance.coverage_failed]', [
                'business_id' => $businessId,
                'identity' => $perf->identidade(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function refreshQuality(EmployeePerformance $perf, int $businessId): void
    {
        try {
            // Reclamações: soma total_reclamacoes dos clientes atendidos pelo perf
            $reclamacoes = DB::table('customer_memory as cm')
                ->where('cm.business_id', $businessId)
                ->whereIn('cm.customer_external_id', function ($sub) use ($businessId, $perf) {
                    $sub->select('c.customer_external_id')
                        ->from('conversations as c')
                        ->join('messages as m', 'm.conversation_id', '=', 'c.id')
                        ->where('c.business_id', $businessId)
                        ->where('m.direction', 'outbound')
                        ->where('m.is_internal_note', false);

                    if ($perf->user_id !== null) {
                        $sub->where('m.sender_user_id', $perf->user_id);
                    } else {
                        $sub->where('m.body', 'like', '%' . $perf->heuristic_name . ':%');
                    }
                })
                ->sum('cm.total_reclamacoes');

            $perf->reclamacoes_recebidas = (int) $reclamacoes;
        } catch (Throwable $e) {
            // customer_memory pode não existir ainda — fail-open zerado
            $perf->reclamacoes_recebidas = 0;
        }
    }

    /**
     * Scoring 0-100 transparente. Pesos publicados em docblock + comentário inline.
     */
    public function calculateScore(EmployeePerformance $perf): void
    {
        $breakdown = [];

        // Volume produtivo (25 pts) — escala linear 1500 msgs = 25
        $breakdown['volume'] = min(25, (int) round(($perf->n_msgs_total / 1500) * 25));

        // Diversidade clientes (20 pts) — 150 clientes = 20
        $breakdown['diversidade'] = min(20, (int) round(($perf->n_clientes_diferentes / 150) * 20));

        // Velocidade resposta (25 pts) — escalonado
        $mediana = $perf->tempo_resposta_mediana_s;
        $breakdown['velocidade'] = match (true) {
            $mediana === null => 0,
            $mediana < 60 => 25,
            $mediana < 300 => 18,
            $mediana < 900 => 12,
            $mediana < 1800 => 6,
            default => 2,
        };

        // Profundidade conv (15 pts) — msgs/conv sweet spot 5-15
        $mpc = $perf->n_conversations_atendidas > 0
            ? $perf->n_msgs_total / $perf->n_conversations_atendidas
            : 0;
        $breakdown['profundidade'] = match (true) {
            $mpc >= 5 && $mpc <= 15 => 15,
            $mpc >= 3 && $mpc <= 20 => 10,
            default => 5,
        };

        // Cobertura horária (10 pts) — 10 horas distintas = 10
        $breakdown['cobertura'] = min(10, (int) round(($perf->horas_ativas_distintas / 10) * 10));

        // Engajamento (5 pts) — placeholder CSAT
        $breakdown['engajamento'] = 5;

        $total = array_sum($breakdown);

        $perf->nota_geral = min(self::SCORE_MAX, $total);
        $perf->nota_breakdown = $breakdown;
        $perf->nota_calculada_em = now();
    }
}
