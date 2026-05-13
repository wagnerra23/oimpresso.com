<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp;

use Laravel\Mcp\Server;

/**
 * MEM-MCP-1.c (ADR 0053) — MCP server da empresa oimpresso.
 *
 * Expõe 5 tools + 2 resources + 1 prompt como entry-point pro Claude Code
 * (e Claude Desktop futuramente) consumir o conhecimento do projeto via
 * MCP protocol. Auth + audit log via middleware McpAuth (não duplicar).
 */
class OimpressoMcpServer extends Server
{
    protected string $name = 'oimpresso-mcp';

    protected string $version = '0.1';

    protected string $instructions = <<<'MARKDOWN'
        Servidor MCP da empresa oimpresso (ERP gráfico com IA).

        ⚡ CHAME `brief-fetch` PRIMEIRO em toda sessão (skill brief-first Tier A).
        Devolve estado consolidado em ~3k tokens (cycle ativo, HITL, decisões 24h,
        skills 7d, flags). Substitui exploração via cycles-active + sessions-recent
        + tasks-active + decisions-search. ADR 0091.

        Hierarquia Jira-style (ADR 0070): Project → Epic → Cycle → Story → Subtask.
        CURRENT.md/TASKS.md REMOVIDOS — tudo via tools MCP.

        Estado vivo:
          - `cycles-active`: cycle ativo + goals + tasks ativas
          - `my-work`: minhas tasks ativas (status doing/review/blocked)
          - `my-inbox`: notificações unread (mentions/assignments/reviews)
          - `triage`: tasks novas sem owner/prio
          - `cycle-goals-track`: trackear achieved_value de goals
          - `cycles-close --rollover`: fechar cycle e mover incompletas

        Backlog & detalhe:
          - `tasks-list module:X status:doing owner:Y`: filtros profissionais
          - `tasks-detail task_id:COPI-123`: detalhe + timeline + memory links
          - `tasks-create / tasks-update / tasks-comment`: mutação

        Conhecimento:
          - `decisions-search` / `decisions-fetch`: ADRs Nygard
          - `sessions-recent`: session logs cronológicos
          - `memoria-search`: fatos persistentes do business
          - `cc-search`: sessões Claude Code do time
          - `claude-code-usage-self`: meu consumo

        Stack: Laravel 13.6 + PHP 8.4 · Multi-tenant via business_id.
        ADRs canônicas: 0035 (stack IA), 0053 (este server), 0055 (Team plan equiv),
        0070 (Jira-style task management).
        MARKDOWN;

    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected array $tools = [
        // ADR 0091 — Daily Brief (camada L7 da Constituição V2). PRIMEIRA tool
        // em toda sessão (skill brief-first Tier A always-on). Substitui 5-8
        // chamadas exploratórias por 1 brief de ~3k tokens.
        \Modules\Brief\Mcp\Tools\BriefFetchTool::class,
        // ADR 0070 — Jira-style task management (CURRENT.md/TASKS.md removidos).
        // ⚠️ ListTools (laravel/mcp) PAGINA em 15 itens. 15 primeiras = essenciais.
        // Tools de leitura cycle/work/inbox:
        Tools\CyclesActiveTool::class,
        Tools\MyWorkTool::class,
        Tools\MyInboxTool::class,
        Tools\TriageTool::class,
        Tools\CycleGoalsTrackTool::class,
        Tools\CyclesCloseTool::class,
        Tools\CyclesCreateTool::class,
        // TaskRegistry CRUD (ADR 0070) — ESSENCIAIS pra agentes IA criarem/atualizarem:
        Tools\TasksListTool::class,
        Tools\TasksDetailTool::class,
        Tools\TasksUpdateTool::class,
        Tools\TasksCommentTool::class,
        Tools\TasksCreateTool::class,
        // Alias deprecated (redireciona pra cycles-active):
        Tools\TasksCurrentTool::class,
        // Knowledge — top 3 mais usadas:
        Tools\DecisionsSearchTool::class,
        Tools\DecisionsFetchTool::class,
        Tools\SessionsRecentTool::class,
        // ─── PAGE 2 (cliente precisa paginar pra carregar) ───
        Tools\ClaudeCodeUsageSelfTool::class,
        Tools\MemoriaSearchTool::class,
        Tools\CcSearchTool::class,
        // ADR 0119 Tier 1 — coordenação entre sessões Claude (alerta passivo,
        // não lock). Agregação derivada de mcp_cc_sessions + mcp_cc_messages.
        Tools\WhatsActiveTool::class,
        // ADR 0133 — System health audit canônico (5 dimensões: observability/evals/
        // ADR-stale/cost-agg/test-coverage). Wrapper sobre jana:system-audit --json.
        // Princípio 2 (tiered cost): SQL+FS only, ZERO LLM call.
        Tools\SystemHealthAuditTool::class,
        // Bug #4 BUGS-MCP-SYNC-2026-05-13 — staleness detection em mcp_tasks
        // (stale_todo >21d, stale_blocked >30d, stale_doing >7d sem commit, stale_review >5d).
        // Expõe o mesmo pipeline do command `mcp:tasks:health-check`.
        Tools\TasksHealthTool::class,
        // G3 (AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5) — Q&A natural sobre KB.
        // Hybrid retrieval (memoria-search + decisions-search) + síntese gpt-4o-mini.
        // Substitui fluxo manual "decisions-search → ler 3 ADRs → sintetizar".
        // Page 2 (knowledge cluster). Custo ~R$ [redacted Tier 0]/call.
        Tools\KbAnswerTool::class,
        // G4 (AUDITORIA-SESSION-HANDOFF-2026-05-13 §5 P0) — Resume handoffs via gpt-4o-mini
        // pra Wagner não reler 2000 linhas/dia (mediana 142 linhas, outlier 2151).
        // Cache MD5(filename+content) em `mcp_handoff_summaries`. ~R$ [redacted Tier 0] por handoff.
        // Page 2 (knowledge cluster).
        Tools\HandoffFetchSummarizedTool::class,
    ];

    /** @var array<int, class-string<\Laravel\Mcp\Server\Resource>> */
    protected array $resources = [
        Resources\HandoffResource::class,
        Resources\CurrentResource::class,
    ];

    /** @var array<int, class-string<\Laravel\Mcp\Server\Prompt>> */
    protected array $prompts = [
        Prompts\BriefingOimpressoPrompt::class,
    ];
}
