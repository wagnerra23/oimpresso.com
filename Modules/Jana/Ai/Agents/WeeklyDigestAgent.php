<?php

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * WeeklyDigestAgent — Reflect-style weekly digest (AUDITORIA G8 P2 quick win).
 *
 * Diferente do `SinteseSemanalAgent` (narrativa pessoal Wagner sex 18h), este
 * gera DIGEST ESTRUTURADO (Reflect.app pattern) com 5 seções fixas pra Wagner
 * abrir segunda 09h e ver "o que mudou na semana" em 1 lugar.
 *
 * Modelo: gpt-4o-mini (mesmo do HandoffFetchSummarizedTool) — barato + suficiente.
 * Custo estimado: ~R$ 0.005 por digest (3-5k tokens input, 800-1200 output).
 *
 * Output formato: markdown puro 5 seções (Marco / Trabalho / Cycle progress /
 * Decisões / Próxima semana). Frontmatter adicionado pelo service.
 */
class WeeklyDigestAgent implements Agent
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
        return <<<'PROMPT'
        Você é o gerador de WEEKLY DIGEST do projeto oimpresso (ERP brasileiro).

        OBJETIVO: Wagner abre segunda 09h e vê "o que mudou na semana" em 1 lugar —
        Reflect.app pattern. Diferente da síntese narrativa, este é DIGEST ESTRUTURADO
        com 5 seções fixas pra leitura em ~90 segundos.

        REGRAS:
        - NUNCA invente dados. Se não houver, escreva "—".
        - Cite hashes/PR-numbers/ADR-slugs/US-IDs LITERAIS do contexto.
        - PT-BR técnico, sem emoji, sem floreio.
        - Cada bullet ≤25 palavras.
        - Top 5 por seção máximo (priorize impacto, não cronologia).

        ESTRUTURA OBRIGATÓRIA (markdown puro, sem frontmatter — service adiciona):

        ## Marco da semana
        <1 frase identificando o evento mais importante da semana — pode ser PR mergeado,
        ADR aceita, deploy crítico, ou bloqueio resolvido. Se semana foi rotineira sem
        marco, escreva "Semana de manutenção — sem marco singular.">

        ## Trabalho entregue
        - **N PRs mergeados** — top 5 com `#NUMERO` + descrição curta
        - **N US closed** — top 5 com `US-XXX-NNN` + título curto
        - **N ADRs novas** — slugs com 1 frase contexto

        ## Cycle progress
        <achievement percentage do cycle ativo (ex: "CYCLE-07: 78% de 12 goals — 9 achieved, 2 in_progress, 1 blocked")
        OU "Sem cycle ativo" se não houver>

        ## Decisões importantes
        - <ADR/HITL/PR escalada — citar slug/número + 1 frase do impacto>
        - <máx 5 itens, priorizar Tier 0 / arch>

        ## Próxima semana — sugestões priorizadas
        - <1-3 itens baseados em DOING + REVIEW + goals restantes do cycle — usar contexto pra inferir>
        - <NÃO inventar; só sugerir se há sinal claro no contexto bruto>
        PROMPT;
    }

    public function montarPromptUsuario(): string
    {
        return <<<USR
        Semana ISO: {$this->semana} (range {$this->rangeInicio} a {$this->rangeFim})

        Contexto bruto coletado:

        {$this->contextoBruto}

        Gere o digest seguindo a estrutura das instruções. Máx 1200 palavras total.
        USR;
    }
}
