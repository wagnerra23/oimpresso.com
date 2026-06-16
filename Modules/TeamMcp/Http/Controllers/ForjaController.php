<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ForjaController — cockpit do cowork loop (/forja).
 *
 * Absorção em TeamMcp (NÃO é módulo novo — kickoff Forja). As 6 abas projetam
 * estado que JÁ EXISTE (mcp_tasks + git/PR/ADR/sessão + gates/memory-health) —
 * sem dado fantasma. Todas renderizam o mesmo shell `team-mcp/Forja/Cockpit`
 * com a aba ativa via prop `tab`; o topnav de 6 abas vem de
 * config/core_topnavs.php['Forja'] (useAutoModuleNav casa por 1º segmento /forja
 * — por isso a raiz é /forja e não /team-mcp/forja, que colidiria com o hub Equipe).
 *
 * Onda Forja PR-A: SHELL navegável — abas ainda em construção (cada uma vira uma
 * PR própria com seu gate visual). Referência aprovada (F1.5 ADR 0114):
 * memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md.
 *
 * Multi-tenant Tier 0: cockpit é repo-wide (governança cross-business do loop)
 * — sem filtro business_id, INTENCIONAL (ADR 0093), igual Scorecard.
 *
 * Permissão: copiloto.mcp.usage.all (Wagner/superadmin), igual Scorecard/Team.
 */
class ForjaController extends Controller
{
    /**
     * Abas do cockpit (label + intro). Ordem = ordem do topnav.
     *
     * @var array<string,array{label:string,subtitle:string}>
     */
    private const TABS = [
        'triagem'   => ['label' => 'Triagem',   'subtitle' => 'Tickets propostos aguardando o analista [AN] e sua aprovação — é o F0 do protocolo, formalizado.'],
        'backlog'   => ['label' => 'Backlog',   'subtitle' => 'Issues agrupáveis por Onda / Fase / Papel / Prioridade / Módulo.'],
        'quadro'    => ['label' => 'Quadro',    'subtitle' => 'Fluxo do cowork loop por fase: F0 Brief → F1 Design → F1.5 Critique → F2 Screenshot → F3 Code → F3.5 A11y.'],
        'changelog' => ['label' => 'Changelog', 'subtitle' => 'O que shippou — PRs, ADRs, sessões e ondas.'],
        'mcp'       => ['label' => 'MCP',       'subtitle' => 'Contrato de ferramentas, tokens e auditoria — design; o enforce real é do servidor TeamMcp.'],
        'saude'     => ['label' => 'Saúde',     'subtitle' => 'Semáforo do loop — memory-health, baselines de gate e frescor. Cada métrica linka a uma ação.'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function triagem(): Response
    {
        return $this->renderTab('triagem');
    }

    public function backlog(): Response
    {
        return $this->renderTab('backlog');
    }

    public function quadro(): Response
    {
        return $this->renderTab('quadro');
    }

    public function changelog(): Response
    {
        return $this->renderTab('changelog');
    }

    public function mcp(): Response
    {
        return $this->renderTab('mcp');
    }

    public function saude(): Response
    {
        return $this->renderTab('saude');
    }

    /**
     * Renderiza o shell do cockpit com a aba ativa. Onda Forja PR-A: dados reais
     * entram aba a aba (cada PR). O shell + topnav + rotas já ficam de pé aqui.
     */
    private function renderTab(string $tab): Response
    {
        $meta = self::TABS[$tab] ?? self::TABS['triagem'];

        return Inertia::render('team-mcp/Forja/Cockpit', [
            'tab'      => $tab,
            'tabLabel' => $meta['label'],
            'subtitle' => $meta['subtitle'],
            'meta'     => [
                'generated_at' => now()->toIso8601String(),
                'onda'         => 'Forja',
            ],
        ]);
    }
}
