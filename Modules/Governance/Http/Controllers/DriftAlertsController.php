<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Governance\Services\DriftAlertService;

/**
 * Drift alerts — Constituição Art. 7 (Module Charter).
 *
 * Lê SCOPE.md de cada módulo + filesystem real de Modules/<X>/Http/Controllers/
 * e detecta divergência (controllers fora do contains[] declarado).
 *
 * Mesma lógica do bin/check-scope.php mas em PHP runtime — pra UI.
 *
 * Drift detection cron (Enforcement #5) vai persistir em mcp_alertas;
 * esta tela lê tanto runtime scan quanto alertas históricos.
 *
 * Refator Wave H (#947): scan + parse YAML extraídos pra DriftAlertService —
 * Controller só responde HTTP + delega Service + render Inertia (mesma response shape).
 */
class DriftAlertsController extends Controller
{
    public function __construct(private readonly DriftAlertService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        // Service retorna Collection com report + modules_without_scope + modules_total + total_drift.
        $drifts = $this->service->getActiveDrifts(limit: 500);

        $report                = $drifts->get('report', []);
        $modulesWithoutScope   = $drifts->get('modules_without_scope', []);
        $modulesTotal          = (int) $drifts->get('modules_total', 0);
        $totalDrift            = (int) $drifts->get('total_drift', 0);

        // Alertas persistidos — drift detection cron job (Enforcement #5) ainda não roda;
        // tabela mcp_alertas é pra outras categorias (cota_excedida, tool_destrutiva,
        // ip_suspeito, taxa_errors, cliente_externo). Adicionar 'module_drift' ao enum
        // exige migration + ADR — fica pra Fase 5+1.
        $persistedAlerts = $this->service->persistedAlerts();

        return Inertia::render('governance/DriftAlerts', [
            'kpis' => [
                'total_drift'           => $totalDrift,
                'modules_with_drift'    => count($report),
                'modules_without_scope' => count($modulesWithoutScope),
                'modules_total'         => $modulesTotal,
            ],
            'report'                => $report,
            'modules_without_scope' => $modulesWithoutScope,
            'persisted_alerts'      => $persistedAlerts,
        ]);
    }
}
