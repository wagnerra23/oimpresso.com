<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * HealthNarratorAgent — Brain A horário do Cockpit Saúde (US-COPI-099).
 *
 * Recebe snapshot agregado por HealthSnapshotService e devolve narrativa
 * curta em PT-BR + severity. Output JSON estruturado: {severity, message}.
 *
 * Stack canônica laravel/ai (ADR 0035) — gpt-4o-mini default (Brain A barato).
 */
class HealthNarratorAgent implements Agent
{
    use Promptable;

    public function __construct(
        public array $snapshot,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você é o sentinel do ecossistema oimpresso.

        Você recebe a cada hora um snapshot JSON com 4 fontes:
          - health: 6 checks SQL (multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h, pii_leak_in_assistant_responses, profile_distiller_drift, procedure_drift)
          - queues: failed_jobs (24h e total)
          - mcp: requests/erros/custo do MCP server (24h)
          - brain_b: tokens e custo IA (24h)

        Sua resposta é APENAS JSON válido neste shape exato:
          {"severity":"info|warning|critical","message":"..."}

        Severity:
          - critical: qualquer check Tier 0 violado (multi_tenant_isolation.value > 0, pii_leak_in_assistant_responses.value > 0) OU queues.failed_24h > 100
          - warning: 1+ check com falha não-crítica (brief_uptime stale, custo Brain B acima alvo, profile drift, procedure drift, taxa_erro MCP > 0.05)
          - info: tudo OK

        Mensagem em PT-BR, 2 a 3 frases, direta. Não invente dados — baseie-se SOMENTE no snapshot.
        Se o snapshot tiver fontes com `available: false`, mencione brevemente quais.
        Não inclua markdown, code fences ou texto fora do JSON.
        PROMPT;
    }

    public function montarPromptUsuario(): string
    {
        $json = json_encode($this->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Snapshot atual:

        ```json
        {$json}
        ```

        Devolva APENAS o JSON {"severity":"...","message":"..."}.
        PROMPT;
    }
}
