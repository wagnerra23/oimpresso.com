<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\AdrAlertReader;
use Modules\Admin\Services\BrainBCostReader;
use Modules\Admin\Services\BriefAdapter;
use Modules\Admin\Services\CuradorStatsReader;
use Modules\Admin\Services\CyclesAggregator;
use Modules\Admin\Services\HealthSnapshotReader;
use Modules\Admin\Services\InfraStatusReader;
use Modules\Admin\Services\McpServerHealthReader;
use Modules\Admin\Services\SessionsReader;
use Modules\Admin\Services\VaultwardenReader;

/**
 * IndexController — Admin Center painel principal (`GET /admin`).
 *
 * Sprint 1 dia 3-4 (US-ADM-004..008): renderiza Page Inertia
 * `Admin/Index` com 4 widgets read-mostly:
 *   W1 Brief diário (BriefAdapter)
 *   W2 Health checks 5 SQL (HealthSnapshotReader)
 *   W3 Cycles + tasks (CyclesAggregator)
 *   W4 ADRs Tier 0 violados (AdrAlertReader)
 *
 * Auth gate via middleware stack: tailscale-only -> auth -> is-wagner.
 * Em dev local com ADMIN_BYPASS_LOCAL=true, ambos os middlewares passam direto.
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class IndexController extends Controller
{
    public function __construct(
        protected BriefAdapter $brief,
        protected HealthSnapshotReader $health,
        protected CyclesAggregator $cycles,
        protected AdrAlertReader $adrAlerts,
        protected CuradorStatsReader $curador,
        protected McpServerHealthReader $mcp,
        protected VaultwardenReader $vaultwarden,
        protected SessionsReader $sessions,
        protected InfraStatusReader $infra,
        protected BrainBCostReader $brainBCost,
    ) {}

    public function __invoke(Request $request): Response
    {
        // ROLLBACK Wave L/W7 PR #963: Inertia::defer quebrava Pages (initial render undefined).
        return Inertia::render('Admin/Index', [
            'widgets' => [
                'brief'        => $this->brief->fetch(),
                'health'       => $this->health->fetch(),
                'cycles'       => $this->cycles->fetch(),
                'adr_alerts'   => $this->adrAlerts->fetch(),
                'curador'      => $this->curador->fetch(),
                'mcp'          => $this->mcp->fetch(),
                'vaultwarden'  => $this->vaultwarden->fetch(),
                'sessions'     => $this->sessions->fetch(),
                'infra'        => $this->infra->fetch(),
                'brain_b_cost' => $this->brainBCost->fetch(),
            ],
            'meta' => [
                'subdomain'    => config('admin.subdomain', 'admin.oimpresso.com'),
                'environment'  => app()->environment(),
                'bypass_local' => (bool) (config('admin.bypass_local') && app()->environment('local')),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
