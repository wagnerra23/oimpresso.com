<?php

namespace Modules\Copiloto\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * SinteseSemanalAgent — gera síntese semanal automática (Fase 1 MemoriaAutonoma).
 *
 * Recebe o contexto bruto da semana (commits, arquivos memory/ novos, diffs)
 * e devolve markdown estruturado pra ser salvo em memory/sessions/SEMANA-YYYY-Www-resumo.md.
 *
 * Modelo: Haiku 4.5 (custo trivial pra tarefa de extração+resumo).
 *
 * Ver ADR `MemoriaAutonoma/adr/arq/0001-fase-1-sintese-semanal.md`.
 */
class SinteseSemanalAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string $semana,
        public string $rangeInicio,
        public string $rangeFim,
        public string $contextoBruto,
    ) {
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o sintetizador semanal do oimpresso ERP.

        Recebe os artefatos da semana (commits, ADRs novas, arquivos memory/ alterados, diffs de
        CURRENT/TASKS/TEAM) e gera uma síntese estruturada em PT-BR.

        Seja CONCISO: Wagner lê isso na segunda em menos de 2 minutos. Cada bullet 1 linha.

        NUNCA invente dados. Se não houver informação para alguma seção, escreva "—" no lugar.

        Cite SEMPRE paths/hashes/slugs quando referenciar algo (rastreabilidade).

        Tom: direto, técnico, sem floreio. Não use emoji.

        Estrutura obrigatória da resposta (markdown puro, sem frontmatter — eu adiciono depois):

        ## Decisões da semana
        - <decisão 1, citando ADR slug se houver>
        - <decisão 2>

        ## Implementações mergeadas
        - <feat/fix> — <descrição curta> (`<hash curto>`)

        ## Bloqueios identificados
        - <bloqueio + contexto>  *(ou "—" se nada)*

        ## Próximos passos sugeridos
        - <ação 1>
        - <ação 2>

        ## Referências
        - <path 1>
        - <path 2>
        PROMPT;
    }

    public function montarPromptUsuario(): string
    {
        return <<<USR
        Semana: {$this->semana} (range {$this->rangeInicio} a {$this->rangeFim})

        Contexto bruto da semana:

        {$this->contextoBruto}

        Gere a síntese seguindo a estrutura das instruções.
        USR;
    }
}
