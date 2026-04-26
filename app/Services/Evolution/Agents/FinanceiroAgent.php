<?php

declare(strict_types=1);

namespace App\Services\Evolution\Agents;

use App\Services\Evolution\Tools\GitDiffStatTool;
use App\Services\Evolution\Tools\ListAdrsTool;
use App\Services\Evolution\Tools\MemoryQueryTool;
use App\Services\Evolution\Tools\ModelSchemaTool;
use App\Services\Evolution\Tools\RankByRoiTool;

/**
 * FinanceiroAgent — sub-agent domain-sliced.
 *
 * Carrega APENAS chunks com scope_module='Financeiro' via MemoryQueryTool,
 * o que reduz ~7× tokens vs router monolítico.
 *
 * @see memory/requisitos/EvolutionAgent/adr/arq/0004-sub-agents-domain-sliced.md
 */
class FinanceiroAgent extends BaseAgent
{
    protected string $scope = 'Financeiro';

    public function __construct()
    {
        $this->model = (string) config('evolution.default_model', 'claude-sonnet-4-5');

        $this->withTool(new MemoryQueryTool)
            ->withTool(new ListAdrsTool)
            ->withTool(new RankByRoiTool)
            ->withTool(new ModelSchemaTool)
            ->withTool(new GitDiffStatTool);
    }

    public function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Você é o FinanceiroAgent — especialista no módulo Financeiro do oimpresso.

Contexto fixo:
- Onda 1 (contas a pagar/receber, contas bancárias) e Onda 2 (baixa automática, conciliação)
  estão mergeadas em 6.7-bootstrap.
- Pendência conhecida: backfill de purchases legadas em estado `due` (ver SPEC.md Financeiro).
- Numeração R/P000001 já implementada.
- ADRs em memory/requisitos/Financeiro/adr/.

Sua tarefa:
- Responda só sobre o módulo Financeiro.
- Use MemoryQuery com scope='Financeiro' pra carregar contexto.
- Cite SEMPRE arquivo + heading da fonte.
- Para perguntas de ranking/próximo passo, use RankByRoi com escopo Financeiro.

Regras invioláveis:
- Nada de mudar account_type → account_type_id sem migration explícita (já caído em produção, ver feedback_pattern_install_modulos.md).
- Nada de duplicar lógica de Util.php::format_date() — ela tem bug intencional de timezone preservado.
PROMPT;
    }
}
