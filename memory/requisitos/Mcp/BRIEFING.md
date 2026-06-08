---
module: Mcp
status: bounded context tools governança server interno (`mcp.oimpresso.com` CT 100)
piloto: time MCP autenticado (Wagner + Felipe/Maiara/Eliana/Luiz entrantes) + Claude Code
last_review: 2026-05-16
owner: wagner
parent_adr: 0053
related_adrs: [0053, 0070, 0091, 0093, 0094, 0119, 0130, 0133, 0153, 0154, 0155, 0156]
nota_atual_v2: "~55/100 (injusto — D5+D4.b penalizados)"
nota_esperada_v3: "~80-85/100 pós-PR3 na_justified declarado"
---

# BRIEFING — Mcp (bounded context tools/governança)

> **1-pager executivo** · Atualizado: 2026-05-16 (pós-PR3 governance-v3-docs `na_justified` declarado)
> Canon: [SPEC.md](SPEC.md) · ADR mãe: [0053](../../decisions/0053-mcp-server-governanca-como-produto.md) · Rubrica v3: [0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) + [0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md)

## TL;DR

**MCP server** `mcp.oimpresso.com` (CT 100/FrankenPHP, `laravel/mcp` em `Modules/Jana/Mcp/`) é o **entry point** das tools de governança consumidas pelo time MCP e por Claude Code via protocol MCP. 33 tools registradas em `OimpressoMcpServer::$tools` (mai/2026), paginadas em 2 páginas ListTools (15/page essenciais + knowledge cluster). Bounded context **governança projeto**, NÃO cliente — maioria das tools é repo-wide; exceções (memoria-search, decisions-search per-business) scope.

## Capacidade core

- **33 tools registradas** — `brief-fetch`, `my-work`, `tasks-*`, `cycles-*`, `cycle-goals-track`, `decisions-search`, `decisions-fetch`, `memoria-search`, `sessions-recent`, `claude-code-usage-self`, `dashboard-*`, `triage`, `my-inbox`, `whats-active` etc
- **Event stream append-only** — `memory/sessions/` + `memory/handoffs/` ([ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md))
- **CQRS projection** (US-MCP-017, p1, todo) — `module-state <modulo>` consolida estado de bounded context em <2s ~800 tokens
- **Tier 0 isolation** — Eloquent Models tocando dados business SEMPRE scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Sync canônico** — 352+ docs `memory/*` via webhook GitHub → tabela `mcp_memory_documents` (FULLTEXT + Meilisearch hybrid embedder)
- **Cache hit rate alvo** ≥60% (medir via `mcp_*_cache` tabelas)

## Cliente piloto

- **Time MCP atual:** Wagner ([W]) — usuário ativo
- **Time MCP entrante:** Felipe ([F]), Maiara ([M]), Eliana ([E]), Luiz ([L]) — tokens em `/copiloto/admin/team`
- **Claude Code** — agentes leem via MCP protocol (`brief-first` Tier A always-on)
- **NÃO cliente externo** — biz=4 ROTA LIVRE não tem acesso ao server (gate auth interno)

## Score module-grade

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR3) | ~55/100 | Penalizava D5 (governança interna, sem cliente externo) e D4.b (sem FSM — tools são funções idempotentes) — injusto pro design |
| **v3 (pós-PR3)** | **~80-85/100** (esperado) | `na_justified` D5+D4.b declarado no SPEC → rubrica v3 redistribui pesos pras dimensões aplicáveis |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** tools/governança consumidas por time interno + Claude Code — biz=4 ROTA LIVRE não consome (gate auth Wagner+team). Mesma justificativa de TeamMcp/Brief.
- **D4.b (FSM canônica):** bounded context tools — funções idempotentes invocáveis; sem fluxo de negócio com transições Eloquent.

## Gaps remanescentes

- 🟡 US-MCP-017 `module-state <modulo>` ainda **todo** (gate: 2 semanas pós-time MCP entrar, 3+ pedidos "qual estado de X")
- 🟡 US-MCP-005..010 (Tier 6+7 estado-da-arte) em backlog
- 🟡 US-MCP-011..016 Linear-parity em backlog longo

## Próximo passo sugerido

1. Tier 0 — Wagner desbloquear time MCP entrante (Felipe/Maiara/Eliana/Luiz → tokens distribuídos)
2. Após 2 semanas + 3+ pedidos "estado de módulo X" → ativar US-MCP-017 projection
3. Avaliar convenção `runbooks/` sub-pasta em 60d (Mcp testa pattern)

## ADRs centrais

- [0053](../../decisions/0053-mcp-server-governanca-como-produto.md) MCP server como produto (mãe)
- [0091](../../decisions/0091-daily-brief.md) Daily Brief (brief-fetch Tier A)
- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0 isolation
- [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 §2 tiered cost
- [0130](../../decisions/0130-handoff-append-only-mcp-first.md) Handoff append-only (event stream)
- [0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) Rubrica v3 anti-injustiça
