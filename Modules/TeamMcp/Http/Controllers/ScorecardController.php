<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\TeamMcp\Services\ScorecardBuilderService;

/**
 * ScorecardController — G1 FICHA Wave 22 esqueleto TeamMcp scorecard UI.
 *
 * Tela `/team-mcp/scorecard` apresenta:
 *   - **Facts** (factual, sem juízo): contagens diretas do MCP server
 *     (tokens ativos, calls 7d, cost 7d, top tools, drift detectado).
 *   - **Checks** (boolean ok/fail): saúde por dimensão (Tier 0 multi-tenant,
 *     governance gate verde, brief recente, audit log sem PII vazada).
 *
 * Pattern **Facts+Checks** (separar dado de juízo) reduz overhead cognitivo
 * — Wagner vê primeiro "tá tudo verde?" depois entra nos números se preciso.
 *
 * Permissão: `copiloto.mcp.usage.all` (Wagner/superadmin), igual TeamController.
 *
 * D6 Perf (Inertia::defer DEFAULT — rule pages.md):
 *   - `facts` e `checks` ambos defer (queries N×audit_log + N×schemata).
 *   - `meta` eager (config + timestamp, 0 query).
 *
 * D9 Obs: span `teammcp.scorecard.build` wrap nos builders (via Service).
 *
 * **Wave 25 D4 (2026-05-16):** lógica buildFacts/buildChecks/checkXxx extraída
 * pra `ScorecardBuilderService`. Controller fica thin (auth + render + proxies
 * pra preservar contrato Pest reflection de Wave 23).
 *
 * Multi-tenant Tier 0: scorecard é repo-wide (governance cross-business).
 * Sem business_id filter — INTENCIONAL pra superadmin enxergar saúde global.
 *
 * @see Modules\TeamMcp\Services\ScorecardBuilderService (lógica real — Wave 25)
 * @see Modules\TeamMcp\Http\Controllers\TeamController (irmão — team plan view)
 * @see memory/decisions/0091-daily-brief.md (facts pattern origem)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ScorecardController extends Controller
{
    /**
     * Wave 25 D4: builder extraído pra `ScorecardBuilderService` (thin Controller).
     * Service container injeta automaticamente — controller fica só auth + render.
     */
    public function __construct(private ScorecardBuilderService $builder)
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('team-mcp/Scorecard/Index', [
            // D6.a: defer Facts (queries DB caras)
            'facts'  => Inertia::defer(fn () => $this->builder->buildFacts()),
            // D6.a: defer Checks (consulta schema + audit_log)
            'checks' => Inertia::defer(fn () => $this->builder->buildChecks()),
            // Eager (config inline + agora)
            'meta'   => [
                'generated_at' => now()->toIso8601String(),
                'period_days'  => 7,
                'pattern'      => 'facts_checks',
                'source'       => 'mcp_audit_log + mcp_tokens + mcp_briefs',
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Proxies thin pra preservar contrato Pest (Wave 23 reflection tests).
    // Service ScorecardBuilderService é a fonte de verdade — controller delega.
    // ------------------------------------------------------------------

    /**
     * @internal Proxy pro `ScorecardBuilderService::buildFacts` (Pest contract).
     */
    protected function buildFacts(): array
    {
        return $this->builder->buildFacts();
    }

    /**
     * @internal Proxy pro `ScorecardBuilderService::buildChecks` (Pest contract).
     */
    protected function buildChecks(): array
    {
        return $this->builder->buildChecks();
    }
}
