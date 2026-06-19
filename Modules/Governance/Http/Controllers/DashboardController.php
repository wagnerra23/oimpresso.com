<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard consolidado de governança (Constituição Art. 8 + Art. 9 UI).
 *
 * MVP: lê estado canônico do DB e exibe um painel agregado pra Wagner operar
 * 5min/dia. Versões futuras adicionam: edit policies inline, drill-down audit,
 * approval workflow, drift resolution, actor management.
 *
 * Extensão Cockpit Saúde (epic US-COPI-095, charter v2 2026-05-09):
 * adiciona 3 fontes de saúde do ecossistema (failed_jobs Horizon, custo IA
 * Brain B 24h, narrativas Brain A horárias). Cada fonte degrada graciosamente
 * via Schema::hasTable — funciona com OR sem migrations dependentes mergeadas.
 */
class DashboardController extends Controller
{
    private const PRICING_USD_PER_1M_TOKENS_IN = 0.15;
    private const PRICING_USD_PER_1M_TOKENS_OUT = 0.60;
    private const USD_TO_BRL = 5.0;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        // Compliance score (heurístico — Constitution v1.1.0 Articles 1-10)
        // Plenamente compliant: 1, 2, 3, 5, 6, 9, 10 = 7 articles × 10pts = 70
        // Parcial: 4, 7 = 2 articles × 5pts = 10
        // Pendente: 8 (ActionGate Fase 5 ainda em warn) = 0
        // Total: 80/100 = 80%
        // Eager: trivial (escalar estático sem I/O).
        $compliancePct = (7 * 10) + (2 * 5) + 0; // = 80

        // ROLLBACK Wave W7 #953: Inertia::defer quebrava Pages que esperam props eager.
        // Pages frontend não foram atualizadas com <Deferred> wrapper — kpis undefined crashava.
        // Restaurado eager até Pages serem refatoradas (issue follow-up Wave W7).
        $health = $this->saudeEcosistema();
        return Inertia::render('governance/Dashboard', [
            'kpis'              => $this->buildKpisPayload(),
            'pending_adrs'      => $this->buildPendingAdrsPayload(),
            'audit_highlights'  => $this->buildAuditHighlightsPayload(),
            'health_kpis'       => $health['kpis'],
            'narratives'        => $health['narratives'],
            // GT-G7 — card SDD (ADR 0275). Deferred: Page TEM <Deferred> wrapper
            // (diferente do rollback W7 #953 — aqui o frontend já nasce preparado).
            'sdd'               => Inertia::defer(fn () => $this->buildSddPayload()),
            // Configs estáticas — eager (zero I/O).
            'actiongate_mode'   => config('governance.actiongate_mode', 'warn'),
            'next_review_at'    => config('governance.next_review_at'),
            'compliance_pct'    => $compliancePct,
        ]);
    }

    /**
     * ADRs status=proposto pendentes de aprovação Wagner.
     * Schema mcp_memory_documents tem coluna `status` direta (não frontmatter_json).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildPendingAdrsPayload(): \Illuminate\Support\Collection
    {
        return DB::table('mcp_memory_documents')
            ->where('type', 'adr')
            ->whereNull('deleted_at')
            ->where('status', 'proposto')
            ->select('slug', 'title', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();
    }

    /**
     * Audit highlights — últimas 24h, status != ok (denied/error/quota_exceeded).
     * Schema mcp_audit_log: endpoint é enum (tools/list, tools/call, etc.).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function buildAuditHighlightsPayload(): \Illuminate\Support\Collection
    {
        return DB::table('mcp_audit_log')
            ->where('ts', '>', now()->subHours(24))
            ->where('status', '!=', 'ok')
            ->select('user_id', 'endpoint', 'tool_or_resource', 'status', 'ts as created_at', 'duration_ms')
            ->orderByDesc('ts')
            ->limit(20)
            ->get();
    }

    /**
     * KPIs agregados (contagens DB) — 5 queries.
     *
     * @return array<string, int>
     */
    private function buildKpisPayload(): array
    {
        $pendingAdrsCount = (int) DB::table('mcp_memory_documents')
            ->where('type', 'adr')
            ->whereNull('deleted_at')
            ->where('status', 'proposto')
            ->count();

        $activePoliciesCount = (int) DB::table('mcp_governance_rules')
            ->where('enabled', 1)
            ->count();

        // mcp_skill_versions.status enum (draft|review|published|drift_pending|archived).
        // pending = versões em review aguardando approve/reject.
        $skillApprovalsCount = (int) DB::table('mcp_skill_versions')
            ->where('status', 'review')
            ->count();

        $actorsCount = (int) DB::table('mcp_actors')
            ->whereNull('revoked_at')
            ->count();

        $auditHighlightsCount = (int) DB::table('mcp_audit_log')
            ->where('ts', '>', now()->subHours(24))
            ->where('status', '!=', 'ok')
            ->count();

        // Compliance recalculado igual ao escalar do index (mantém shape consistente).
        $compliancePct = (7 * 10) + (2 * 5) + 0;

        return [
            'pending_adrs'         => $pendingAdrsCount,
            'active_policies'      => $activePoliciesCount,
            'skill_approvals'      => $skillApprovalsCount,
            'actors_registered'    => $actorsCount,
            'audit_highlights'     => $auditHighlightsCount,
            'compliance_pct'       => $compliancePct,
        ];
    }

    /**
     * GT-G7 — resumo SDD do último snapshot diário (mcp_sdd_scorecard_history)
     * + Δ da composta vs snapshot anterior. Degrada graciosamente (tabela
     * ausente ou sem rows → null; card mostra empty-state).
     *
     * @return array<string, mixed>|null
     */
    private function buildSddPayload(): ?array
    {
        if (! Schema::hasTable('mcp_sdd_scorecard_history')) {
            return null;
        }

        $rows = DB::table('mcp_sdd_scorecard_history')
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->get();

        $latest = $rows->first();
        if ($latest === null) {
            return null;
        }

        $previous = $rows->get(1);
        $payload = json_decode((string) $latest->payload, true) ?: [];

        $delta = null;
        if ($latest->composta !== null && $previous !== null && $previous->composta !== null) {
            $delta = round((float) $latest->composta - (float) $previous->composta, 1);
        }

        return [
            'snapshot_date' => (string) $latest->snapshot_date,
            'composta'      => $latest->composta !== null ? (float) $latest->composta : null,
            'composta_k'    => (int) ($payload['composta_k'] ?? 0),
            'delta'         => $delta,
            'vivas'         => (int) ($payload['vivas'] ?? 0),
            'metrics_total' => (int) ($payload['metrics_total'] ?? 10),
            'alerts'        => array_values((array) ($payload['alerts'] ?? [])),
        ];
    }

    /**
     * 3 fontes de saúde do ecossistema 24h (US-COPI-095 epic).
     * Cada fonte degrada graciosamente quando tabela ausente — array sempre
     * com shape estável pro componente Inertia.
     *
     * @return array{kpis: array<string, mixed>, narratives: array<int, array<string, mixed>>}
     */
    private function saudeEcosistema(): array
    {
        return [
            'kpis' => [
                'failed_jobs_24h' => $this->failedJobs24h(),
                'custo_ia_brl_24h' => $this->custoIa24h(),
                'last_narrative' => $this->ultimaNarrativa(),
            ],
            'narratives' => $this->narrativasRecentes(),
        ];
    }

    private function failedJobs24h(): ?int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        return (int) DB::table('failed_jobs')
            ->where('failed_at', '>', now()->subHours(24))
            ->count();
    }

    private function custoIa24h(): ?float
    {
        if (! Schema::hasTable('jana_mensagens')) {
            return null;
        }

        $base = DB::table('jana_mensagens')->where('created_at', '>', now()->subHours(24));
        $tokensIn = (int) (clone $base)->sum('tokens_in');
        $tokensOut = (int) (clone $base)->sum('tokens_out');

        $usd = ($tokensIn * self::PRICING_USD_PER_1M_TOKENS_IN / 1_000_000)
            + ($tokensOut * self::PRICING_USD_PER_1M_TOKENS_OUT / 1_000_000);

        return round($usd * self::USD_TO_BRL, 2);
    }

    /**
     * @return array{severity: string, message: string, generated_at: string}|null
     */
    private function ultimaNarrativa(): ?array
    {
        if (! Schema::hasTable('jana_health_narratives')) {
            return null;
        }

        $row = DB::table('jana_health_narratives')
            ->orderByDesc('generated_at')
            ->select('severity', 'narrative as message', 'generated_at')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function narrativasRecentes(): array
    {
        if (! Schema::hasTable('jana_health_narratives')) {
            return [];
        }

        return DB::table('jana_health_narratives')
            ->where('generated_at', '>', now()->subHours(24))
            ->orderByDesc('generated_at')
            ->limit(5)
            ->select('severity', 'narrative', 'generated_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
