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
        // RUNBOOK-inertia-defer-pattern.md — cada widget é Service call (DB/HTTP/cache).
        // Defer permite frontend renderizar shell instantâneo + skeletons; partial reload
        // posterior carrega cada widget independente. Preserva response shape (mesmas chaves).
        return Inertia::render('Admin/Index', [
            'widgets' => [
                'brief'        => Inertia::defer(fn () => $this->brief->fetch()),
                'health'       => Inertia::defer(fn () => $this->health->fetch()),
                'cycles'       => Inertia::defer(fn () => $this->cycles->fetch()),
                'adr_alerts'   => Inertia::defer(fn () => $this->adrAlerts->fetch()),
                'curador'      => Inertia::defer(fn () => $this->curador->fetch()),
                'mcp'          => Inertia::defer(fn () => $this->mcp->fetch()),
                'vaultwarden'  => Inertia::defer(fn () => $this->vaultwarden->fetch()),
                'sessions'     => Inertia::defer(fn () => $this->sessions->fetch()),
                'infra'        => Inertia::defer(fn () => $this->infra->fetch()),
                'brain_b_cost' => Inertia::defer(fn () => $this->brainBCost->fetch()),
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
