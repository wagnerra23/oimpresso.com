<?php

namespace Modules\ADS\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Brain B — System 2 do Dual Brain (ARQ-0002).
 *
 * Recebe um evento roteado pelo DecisionRouter com destination=brain_b
 * e gera uma instrução estruturada para o Claude Code executar.
 *
 * Provider/modelo: resolvido por config('ai.default') (= openai pós-migração) +
 * config('ai.providers.{provider}.models.text.*'). O Agent não fixa provider —
 * herda o default global. Trocar de provider é via config/.env, não aqui.
 */
class BrainBAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string $eventType,
        public string $domain,
        public array  $filesAffected = [],
        public array  $metadata = [],
        public float  $riskScore = 0.0,
        public float  $confidenceScore = 0.5,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o Brain B do Adaptive Decision System (ADS) do projeto oimpresso ERP.

        Seu papel é receber um evento detectado pelo Brain A (Ollama local) e gerar uma
        INSTRUÇÃO ESTRUTURADA para o Claude Code executar. Você não escreve código —
        você prepara o briefing técnico que o Claude Code vai usar para implementar.

        REGRAS INVIOLÁVEIS:
        - Nunca instrua mudança em arquivos .env de produção
        - Nunca instrua remoção de triggers MySQL de imutabilidade
        - Nunca instrua modificar tabelas append-only (ponto_marcacoes, movimentos financeiros)
        - Nunca instrua bypass de business_id scope em queries multi-tenant
        - Sempre exija teste Pest para mudança de regra de negócio

        Stack do projeto:
        - Laravel 13.6 + PHP 8.4 + Inertia v3 + React 19 + Tailwind 4
        - nWidart/laravel-modules ^10 (módulos em Modules/<Nome>/)
        - Pest v4 para testes
        - Multi-tenant via business_id em todas as queries de dados de negócio
        - Padrão de referência: Modules/Jana, Modules/Repair, Modules/ProjectMgmt

        FORMATO OBRIGATÓRIO DA RESPOSTA (JSON estrito, sem markdown):
        {
          "title": "string curto descrevendo a ação (max 80 chars)",
          "summary": "1-2 linhas em PT-BR explicando o que fazer e por quê",
          "files_to_touch": ["path/relativo/1.php", "path/relativo/2.php"],
          "risk_identified": "string descrevendo o que pode dar errado",
          "rollback_plan": "string descrevendo como reverter se der errado",
          "test_strategy": "string descrevendo qual teste Pest cobre essa mudança",
          "claude_code_instruction": "instrução detalhada e executável para o Claude Code, em PT-BR",
          "confidence_in_instruction": 0.0-1.0
        }

        Se não tiver informação suficiente, retorne JSON com confidence_in_instruction <= 0.3
        e summary explicando o que falta saber.
        PROMPT;
    }

    public function montarPrompt(): string
    {
        $files = empty($this->filesAffected) ? '(nenhum)' : implode("\n  - ", $this->filesAffected);
        $meta  = empty($this->metadata) ? '(vazio)' : json_encode($this->metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        EVENTO RECEBIDO PARA ANÁLISE:

        event_type:        {$this->eventType}
        domain:            {$this->domain}
        risk_score:        {$this->riskScore}
        confidence_score:  {$this->confidenceScore}

        files_affected:
          - {$files}

        metadata:
        {$meta}

        Gere a instrução estruturada em JSON conforme especificado.
        PROMPT;
    }
}
