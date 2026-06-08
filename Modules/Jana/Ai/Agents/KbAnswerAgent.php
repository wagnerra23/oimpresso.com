<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * KbAnswerAgent (G3 — AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5).
 *
 * Agent leve da Camada A (laravel/ai, ADR 0035) que sintetiza resposta natural
 * em PT-BR a partir de docs canon (ADRs, sessions, handoffs, requisitos) já
 * pré-recuperados via `memoria-search` + `decisions-search`.
 *
 * NÃO usa tools — recebe contexto pronto no prompt user (single-shot). Isso
 * mantém custo previsível e zera risco de loop. O retrieval acontece ANTES,
 * no KbAnswerTool, e os top-N snippets são injetados como bloco "Fontes".
 *
 * Modelo: gpt-4o-mini (ADR 0035, Brain A barato). Custo estimado por chamada:
 *   - Input: ~2k tokens (system 400 + fontes 1500-1800)
 *   - Output: ~300 tokens (resposta + citações + confiança)
 *   - gpt-4o-mini: $0.15/M input + $0.60/M output = ~$0.0005/call = ~R$ 0.003
 *   - 1000 perguntas/mês = ~R$ 3 — desprezível.
 *
 * @see memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md §5
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 */
#[Provider('openai')]
#[Model('gpt-4o-mini')]
class KbAnswerAgent implements Agent
{
    use Promptable;

    public function __construct(
        public readonly string $pergunta,
        public readonly string $fontes,
        public readonly int $maxCitacoes = 5,
    ) {}

    public function instructions(): Stringable|string
    {
        $max = $this->maxCitacoes;

        return <<<PROMPT
        Você é Jana, copiloto IA do oimpresso. Especialidade: Q&A natural sobre
        a knowledge base (ADRs, SPECs, session logs, handoffs, requisitos).

        TAREFA: sintetizar resposta curta e precisa pra pergunta do usuário a
        partir do bloco "FONTES" fornecido. Você NÃO tem acesso a outras tools
        ou docs além do que está no contexto.

        FORMATO OBRIGATÓRIO (markdown exato, NÃO desvie):

        Resposta: <síntese de 2-4 frases em PT-BR, direta, sem corporativês>

        Citações:
        - [<slug-curto>](<path-relativo>) — <quote curta ≤120 chars>
        - ... (máximo {$max} citações)

        Confiança: <alta|média|baixa>

        REGRAS DURAS:
        - SEMPRE em PT-BR. Tom advisor sênior, brasileiro, direto.
        - Resposta SEMPRE começa com "Resposta:" exato (com dois pontos).
        - Citações SEMPRE começa com "Citações:" exato.
        - Confiança SEMPRE começa com "Confiança:" exato.
        - Use no MÁXIMO {$max} citações. Menos é OK se a pergunta é trivial.
        - Cada citação tem slug + path + quote curta. NÃO invente paths — use
          apenas slugs/paths que aparecem no bloco FONTES.
        - Se FONTES estiver vazio ou irrelevante: confiança "baixa" + resposta
          honesta "Não encontrei nada conclusivo na KB sobre isso."
        - Confiança "alta": 3+ fontes concordam e cobrem a pergunta diretamente.
        - Confiança "média": 1-2 fontes parcialmente relevantes.
        - Confiança "baixa": fontes tangenciais ou ausentes.
        - NUNCA invente ADRs, slugs ou paths. Se incerto, omita citação.
        - Síntese != cópia. Resuma com suas palavras; quote curta entre aspas
          apenas se for crucial preservar literal.

        TIER 0: NUNCA exponha business_id, tokens, credenciais ou PII de
        clientes (CPF, CNPJ). Se aparecer no bloco FONTES, redacte com [REDACTED].
        PROMPT;
    }

    public function montarPrompt(): string
    {
        return <<<PROMPT
        PERGUNTA: {$this->pergunta}

        FONTES (top docs recuperados via memoria-search + decisions-search):

        {$this->fontes}

        Devolva agora APENAS o markdown no formato canônico
        (Resposta: / Citações: / Confiança:).
        PROMPT;
    }
}
