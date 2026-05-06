<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard consolidado de governança (Constituição Art. 8 + Art. 9 UI).
 *
 * MVP: lê estado canônico do DB e exibe um painel agregado pra Wagner operar
 * 5min/dia. Versões futuras adicionam: edit policies inline, drill-down audit,
 * approval workflow, drift resolution, actor management.
 */
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        // ADRs status=proposto pendentes de aprovação Wagner
        // Schema mcp_memory_documents tem coluna `status` direta (não frontmatter_json)
        $pendingAdrs = DB::table('mcp_memory_documents')
            ->where('type', 'adr')
            ->whereNull('deleted_at')
            ->where('status', 'proposto')
            ->select('slug', 'title', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Policies ativas em mcp_governance_rules
        $activePoliciesCount = (int) DB::table('mcp_governance_rules')
            ->where('enabled', 1)
            ->count();

        // Skills approvals pendentes — versões em review aguardando approve/reject
        // Schema: mcp_skill_versions.status enum (draft|review|published|drift_pending|archived)
        // mcp_skill_approvals registra decision (approve/reject) — pending = sem approval row
        $skillApprovalsCount = (int) DB::table('mcp_skill_versions')
            ->where('status', 'review')
            ->count();

        // Audit highlights — últimas 24h, status != ok (denied/error/quota_exceeded)
        // Schema mcp_audit_log: endpoint é enum (tools/list, tools/call, etc.)
        // ações kernel/governance ainda não têm tag separada — Fase 5+1
        $auditHighlights = DB::table('mcp_audit_log')
            ->where('ts', '>', now()->subHours(24))
            ->where('status', '!=', 'ok')
            ->select('user_id', 'endpoint', 'tool_or_resource', 'status', 'ts as created_at', 'duration_ms')
            ->orderByDesc('ts')
            ->limit(20)
            ->get();

        // Actors registrados (Identity Mesh — Art. 6)
        $actorsCount = (int) DB::table('mcp_actors')
            ->whereNull('revoked_at')
            ->count();

        // Compliance score (heurístico — Constitution v1.1.0 Articles 1-10)
        // Plenamente compliant: 1, 2, 3, 5, 6, 9, 10 = 7 articles × 10pts = 70
        // Parcial: 4, 7 = 2 articles × 5pts = 10
        // Pendente: 8 (ActionGate Fase 5 ainda em warn) = 0
        // Total: 80/100 = 80%
        $compliancePct = (7 * 10) + (2 * 5) + 0; // = 80

        return Inertia::render('governance/Dashboard', [
            'kpis' => [
                'pending_adrs'         => $pendingAdrs->count(),
                'active_policies'      => $activePoliciesCount,
                'skill_approvals'      => $skillApprovalsCount,
                'actors_registered'    => $actorsCount,
                'audit_highlights'     => $auditHighlights->count(),
                'compliance_pct'       => $compliancePct,
            ],
            'pending_adrs'      => $pendingAdrs,
            'audit_highlights'  => $auditHighlights,
            'actiongate_mode'   => config('governance.actiongate_mode', 'warn'),
            'next_review_at'    => config('governance.next_review_at'),
        ]);
    }
}
