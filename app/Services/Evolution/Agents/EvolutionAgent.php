<?php

declare(strict_types=1);

namespace App\Services\Evolution\Agents;

use App\Services\Evolution\Tools\EvalGoldenSetTool;
use App\Services\Evolution\Tools\ExtractorTool;
use App\Services\Evolution\Tools\GitDiffStatTool;
use App\Services\Evolution\Tools\ListAdrsTool;
use App\Services\Evolution\Tools\MemoryQueryTool;
use App\Services\Evolution\Tools\ModelSchemaTool;
use App\Services\Evolution\Tools\PestRunTool;
use App\Services\Evolution\Tools\RankByRoiTool;
use App\Services\Evolution\Tools\RouteListTool;

/**
 * EvolutionAgent — router/triage. Decide qual sub-agent (Financeiro, etc.) responde,
 * ou responde direto se for cross-cutting.
 *
 * @see memory/requisitos/EvolutionAgent/adr/arq/0004-sub-agents-domain-sliced.md
 */
class EvolutionAgent extends BaseAgent
{
    protected string $scope = 'geral';

    public function __construct()
    {
        $this->model = (string) config('evolution.default_model', 'claude-sonnet-4-5');

        $this->withTool(new MemoryQueryTool)
            ->withTool(new ListAdrsTool)
            ->withTool(new RankByRoiTool)
            ->withTool(new PestRunTool)
            ->withTool(new RouteListTool)
            ->withTool(new ModelSchemaTool)
            ->withTool(new GitDiffStatTool)
            ->withTool(new EvalGoldenSetTool)
            ->withTool(new ExtractorTool);
    }

    public function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Você é o EvolutionAgent — router/triage do oimpresso (UltimatePOS v6.7).

Contexto hot-tier (sempre presente):
- Meta de negócio: R$ 5mi/ano (memory/11-metas-negocio.md).
- ADR 0026 posicionamento: PricingFpv + Copiloto v1 + CT-e são as features de maior ROI próximos 6 meses.
- Stack: Laravel 13.6, PHP 8.4, MySQL 8, nWidart/laravel-modules ^10, Inertia v2 + React, Pest v4.

Sub-agents disponíveis (Fase 1b: só Financeiro implementado; outros caem em ti):
- FinanceiroAgent — memory/requisitos/Financeiro/
- (futuro) PontoAgent, CmsAgent, CopilotoAgent

Sua tarefa:
1. Decida se a pergunta cabe em um sub-agent (escopo "Financeiro", "PontoWr2", "Cms", "Copiloto") OU se é cross-cutting.
2. Se for cross-cutting (roadmap geral, ROI cross-módulos, decisões arquiteturais), responda direto.
3. Sempre cite a fonte (arquivo + heading) extraída via MemoryQuery.
4. Não invente dados. Se MemoryQuery não retornar match, diga "sem evidência em memory/".

Regras invioláveis:
- NUNCA modificar app/Utils/Util.php::format_date() (ver memory/claude/feedback_carbon_timezone_bug.md).
- Não sugerir mexer em ponto_marcacoes (append-only por lei).
- Não sugerir migration que mexa em tabelas core UltimatePOS sem ADR.
PROMPT;
    }
}
