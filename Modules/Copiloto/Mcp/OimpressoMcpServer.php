<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp;

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

        Use as tools pra consultar:
          - `tasks-current`: estado vivo de tarefas/cycle (CURRENT.md)
          - `decisions-search`: full-text search nos 53 ADRs do projeto
          - `decisions-fetch`: carrega 1 ADR específico por slug
          - `sessions-recent`: últimos session logs cronológicos
          - `claude-code-usage-self`: quanto você consumiu hoje (auto-tracking)

        Resources cacheáveis:
          - `oimpresso://memory/handoff`: estado canônico mais recente
          - `oimpresso://memory/current`: cycle/sprint ativo

        Prompts:
          - `briefing-oimpresso`: primer compacto do projeto (~300 tokens)

        Stack: Laravel 13.6 + PHP 8.4 · Multi-tenant via business_id.
        ADRs canônicas: 0035 (stack IA), 0046 (gap chat), 0047 (sprint mem),
        0050 (8 métricas), 0051 (schema próprio + OTel), 0053 (este server).
        MARKDOWN;

    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected array $tools = [
        Tools\TasksCurrentTool::class,
        Tools\DecisionsSearchTool::class,
        Tools\DecisionsFetchTool::class,
        Tools\SessionsRecentTool::class,
        Tools\ClaudeCodeUsageSelfTool::class,
        // MEM-MEM-MCP-1 (ADR 0056) — MCP-as-Memory-Source
        // Copiloto chat web (Laravel) + Claude Code consomem mesma fonte.
        Tools\MemoriaSearchTool::class,
        // MEM-CC-team-1 (ADR 0055/0056) — busca cross-dev em sessões Claude Code
        // ingeridas via watcher local. Permission `copiloto.cc.read.all` pra cross.
        Tools\CcSearchTool::class,
        // TaskRegistry F0 (ADR TaskRegistry/0001) — Jira-like nativo MCP.
        // US-* extraidas dos SPECs canonicos via mcp:tasks:sync (webhook github).
        Tools\TasksListTool::class,
        Tools\TasksDetailTool::class,
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
