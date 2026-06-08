---
module: Brief
na_justified:
  D3.b: "Brief não tem capacidades múltiplas pra um BRIEFING.md narrativo justificar. ADR 0091 já cumpre o papel de documento canônico do módulo."
related_adrs: [0091, 0153, 0154]
---

# BRIEFING — Modules/Brief

> **N/A justificado D3.b** — Brief é tool MCP atômica de infra. Documento canônico é [ADR 0091](../../decisions/0091-daily-brief.md). Este BRIEFING existe apenas como ponteiro pra cumprir governança Tier 0.

## O que é

Tool MCP **`brief-fetch`** — entrega snapshot consolidado do estado do projeto (~3k tokens) no início de cada sessão Claude Code, substituindo 5-8 chamadas exploratórias (cycles-active, sessions-recent, tasks-active, decisions-search).

## Por que existe

Economia ~27k tokens por sessão típica. Cache 5min trivial. Skill `brief-first` (Tier A always-on) força como primeira tool MCP.

## Diferencial

Não tem concorrente — é infra interna. ADR 0091 cataloga decisão de design (3 ângulos faturamento, cron daily 06:00 BRT, persistência `mcp_briefs`).

## Estado

LIVE em produção (CT 100 MCP server `mcp.oimpresso.com`). Cobertura Pest: 3 tests Wave B. Cron diário operacional desde 2026.

## Documento canônico

Toda a especificação técnica e arquitetural está em **[ADR 0091 — Daily Brief](../../decisions/0091-daily-brief.md)**. Este BRIEFING não duplica conteúdo (Tier 0: "não duplicar info entre sistemas").
