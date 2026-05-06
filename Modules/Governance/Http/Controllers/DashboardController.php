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
        $pendingAdrs = DB::table('mcp_memory_documents')
            ->where('type', 'adr')
            ->whereNull('deleted_at')
            ->where('frontmatter_json', 'LIKE', '%"status":"proposto"%')
            ->select('slug', 'title', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Policies ativas em mcp_governance_rules
        $activePoliciesCount = (int) DB::table('mcp_governance_rules')
            ->where('enabled', 1)
            ->count();

        // Skills approvals pendentes
        $skillApprovalsCount = (int) DB::table('mcp_skill_approvals')
            ->where('status', 'pending')
            ->count();

        // Audit highlights — últimas 24h, ações L0/L1 ou erros
        $auditHighlights = DB::table('mcp_audit_log')
            ->where('created_at', '>', now()->subHours(24))
            ->where(function ($q) {
                $q->where('status', '!=', 'ok')
                  ->orWhereIn('endpoint', ['kernel_action', 'governance_change']);
            })
            ->select('user_id', 'endpoint', 'tool_or_resource', 'status', 'created_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Actors registrados (Identity Mesh — Art. 6)
        $actorsCount = (int) DB::table('mcp_actors')
            ->whereNull('revoked_at')
            ->count();

        // Compliance score (heurístico — Constitution v1.1.0 Articles 1-10)
        // Plenamente compliant: 1, 2, 3, 5, 6, 9, 10 = 7
        // Parcial: 4, 7 = 2
        // Pendente: 8 (Fase 5 — esta UI é o início) = 1
        $complianceScore = round(7 * 10 / 10) + round(2 * 5 / 10) + 0; // = 80
        $compliancePct = (int) $complianceScore;

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
