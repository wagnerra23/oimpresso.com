<?php

namespace Modules\ADS\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * T11 — ReviewerAgent (G-Eval multi-dim, CoT).
 *
 * Não é "LLM dá nota 0-100" simplista. É:
 *   1. Chain-of-Thought antes do score (reduz viés)
 *   2. Rubrica multi-dimensional (correctness/safety/quality/cost)
 *   3. Self-consistency (n=2 chamadas, média) → cuidado em ReviewerService
 *   4. Confidence-weighted (modelo declara quão confiante está)
 *   5. Modelo Haiku 4.5 (3× mais barato que Sonnet) — review é tarefa simples
 *
 * Disparado após Brain A executa OU Brain B prepara instrução aprovada.
 * Score < 70 → retry com ajuste; Score < 50 → escala pra Wagner.
 */
class ReviewerAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string  $eventType,
        public string  $domain,
        public string  $brainUsed,           // 'brain_a' | 'brain_b'
        public ?string $instructionGenerated,
        public ?string $expectedOutcome,    // o que deveria ter acontecido (opcional)
        public ?string $actualOutcome,      // o que aconteceu de fato
        public array   $context = [],       // metadata extra
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o ReviewerAgent do Adaptive Decision System (ADS) do oimpresso ERP.

        Seu trabalho é avaliar com rigor uma decisão automatizada que JÁ FOI executada
        ou preparada por outro agente (Brain A Ollama local OU Brain B Claude API).

        AVALIAÇÃO MULTI-DIMENSIONAL (G-Eval):

        1. CORRECTNESS (0-100): a ação resolve o problema descrito?
        2. SAFETY (0-100): respeita regras Policy Engine? sem prompt injection? sem PII vazado?
        3. QUALITY (0-100): qualidade técnica (código limpo, testes, padrões PT-BR)?
        4. COST_EFFICIENCY (0-100): tokens/dinheiro gasto justifica resultado?

        Score geral = média ponderada [0.4·correctness + 0.3·safety + 0.2·quality + 0.1·cost].

        REGRAS DE AVALIAÇÃO:
        - Use Chain-of-Thought: pense ANTES de pontuar. Liste evidências objetivas.
        - Se SAFETY < 60: o overall é cravado em <50, sem exceção.
        - Se você não tem informação suficiente, use confidence < 0.6 e flag pra humano.
        - Penalize:
            * código que mexe em append-only sem permissão (-30 quality, -50 safety)
            * Cor hardcoded em vez de tokens CSS (-15 quality)
            * Label não-PT-BR (-10 quality)
            * Sem teste Pest pra mudança de regra de negócio (-20 quality)
        - Premie:
            * Padrão Modules/Jana/ ou NFSe imitado (+10 quality)
            * Imutabilidade respeitada em ponto_marcacoes (+10 safety)

        FORMATO OBRIGATÓRIO (JSON estrito, sem markdown):
        {
          "reasoning": "1-2 parágrafos de Chain-of-Thought explicando os scores",
          "scores": {
            "correctness": 0-100,
            "safety": 0-100,
            "quality": 0-100,
            "cost_efficiency": 0-100,
            "overall": 0-100
          },
          "issues": ["lista de problemas concretos identificados"],
          "strengths": ["lista de pontos positivos"],
          "confidence": 0.0-1.0,
          "should_retry": true|false,
          "retry_adjustment": "se should_retry=true, sugestão concreta do que mudar"
        }
        PROMPT;
    }

    public function montarPrompt(): string
    {
        $instructionBlock = $this->instructionGenerated
            ? "INSTRUÇÃO/AÇÃO EXECUTADA:\n```\n{$this->instructionGenerated}\n```"
            : 'INSTRUÇÃO/AÇÃO: (não disponível)';

        $expectedBlock = $this->expectedOutcome
            ? "RESULTADO ESPERADO:\n{$this->expectedOutcome}\n"
            : '';

        $actualBlock = $this->actualOutcome
            ? "RESULTADO REAL:\n{$this->actualOutcome}\n"
            : 'RESULTADO REAL: (em andamento ou não disponível)';

        $contextStr = empty($this->context)
            ? '(sem contexto extra)'
            : json_encode($this->context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        DECISÃO PARA REVISÃO:

        event_type:  {$this->eventType}
        domain:      {$this->domain}
        executor:    {$this->brainUsed}

        {$instructionBlock}

        {$expectedBlock}{$actualBlock}

        CONTEXTO:
        {$contextStr}

        Avalie segundo rubrica G-Eval multi-dim. Retorne JSON estrito.
        PROMPT;
    }
}
