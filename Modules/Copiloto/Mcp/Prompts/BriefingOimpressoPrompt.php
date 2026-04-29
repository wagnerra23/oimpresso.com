<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Prompt briefing-oimpresso.
 *
 * Primer compacto (~300 tokens) substituindo CLAUDE.md inflado.
 * Retorna instructions + lista de skills + URIs MCP relevantes.
 *
 * Uso pelo cliente MCP:
 *   prompts/get name=briefing-oimpresso
 */
class BriefingOimpressoPrompt extends Prompt
{
    protected string $name = 'briefing-oimpresso';

    protected string $title = 'Primer compacto do oimpresso';

    protected string $description = 'Briefing curto (~300 tokens) com stack, padrões críticos, ADRs canônicas e como navegar via tools/resources MCP. Substitui CLAUDE.md inflado nas primeiras mensagens.';

    public function arguments(): array
    {
        return [
            new Argument(
                name: 'business_id',
                description: 'ID do business pra customizar briefing (default: agnóstico)',
                required: false,
            ),
        ];
    }

    public function handle(Request $request): Response
    {
        $businessId = $request->get('business_id');

        // Tenta puxar handoff do DB pra incluir contexto vivo
        $handoffDoc = McpMemoryDocument::where('type', 'handoff')->first();
        $handoffSnippet = $handoffDoc !== null
            ? mb_substr($handoffDoc->content_md, 0, 500) . '...'
            : '(handoff ainda não sincronizado)';

        $body = <<<MARKDOWN
# Primer oimpresso (ERP gráfico com IA)

Stack: Laravel 13.6 + PHP 8.4 + Inertia v3 + MySQL + Multi-tenant via business_id.
Sobre UltimatePOS v6 com módulos próprios em Modules/. Cliente principal: ROTA LIVRE (biz=4).

## Padrões duros (não negociar)
- **Multi-tenant** business_id global scope em TODA query (skill multi-tenant-patterns)
- **Append-only** pra Marcações Ponto (Portaria 671/2021) e mcp_audit_log
- **PT-BR** em tudo: texto, commit, comentário (código em inglês ok)
- **Antes de criar/mudar módulo**: olhar Modules/Jana e imitar (ADR 0011)
- **Delphi contrato IMUTÁVEL**: /connector/api/* nunca quebra request/response

## ADRs canônicas mais relevantes
- 0035 — Stack canônica IA (laravel/ai + MemoriaContrato)
- 0046 — Gap ChatCopilotoAgent (Caminho A: ContextoNegocio)
- 0047 — Wagner solo + sprint memória
- 0048 — Vizra ADK rejeitada (ficou laravel/ai)
- 0050 — 8 métricas obrigatórias + tabela copiloto_memoria_metricas
- 0051 — Schema próprio + adapter + OTel GenAI
- 0052 — ContextoNegocio expor múltiplos ângulos (não 1 número)
- 0053 — MCP server (este) governança como produto

## Como navegar via MCP

Tools disponíveis:
- `tasks-current` — cycle/sprint vivo (CURRENT.md)
- `decisions-search query=X` — busca FULLTEXT nos 53 ADRs
- `decisions-fetch slug=Y` — carrega 1 ADR completo
- `sessions-recent limit=N` — últimos session logs
- `claude-code-usage-self` — seu consumo IA 7 dias

Resources cacheáveis:
- `oimpresso://memory/handoff` — estado canônico vivo
- `oimpresso://memory/current` — cycle ativo

## Estado atual (snippet)
$handoffSnippet
MARKDOWN;

        if ($businessId !== null) {
            $body .= "\n\n## Customização\n_business_id requested: $businessId — use tools `business-snapshot` (em construção) pra dados vivos._";
        }

        return Response::text(trim($body));
    }
}
