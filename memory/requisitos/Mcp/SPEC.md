---
module: Mcp
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: ativo
na_justified:
  D5: "MCP server (`mcp.oimpresso.com`) é tools/governança consumida pelo TIME INTERNO (Wagner/Felipe/Maiara/Eliana/Luiz) e por Claude Code via protocol MCP — NÃO é módulo de features cliente. Cliente externo biz=4 ROTA LIVRE não consome tools MCP (não tem acesso ao server CT 100). D5 cliente real não aplica por design (mesma justificativa de TeamMcp/Brief — ADR 0094 Constituição §infraestrutura interna)."
  D4.b: "MCP server é bounded context de tools governança (entries em `OimpressoMcpServer::$tools`) — sem state machine FSM (ADR 0143) por design. Tools são funções idempotentes invocáveis; não há fluxo de negócio com transições Eloquent. D4.b FSM canônica N/A."
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0130-handoff-append-only-mcp-first
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
---

# Especificação funcional — MCP (bounded context tools/governança)

> **N/A justificado** D5 + D4.b — tools/governança internas (consumo time MCP + Claude Code, sem cliente externo) e sem state machine FSM por design. Detalhes em [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md).

> **Convenção do ID:** `US-MCP-NNN` para user stories de tools MCP do server `mcp.oimpresso.com`.
> **Origem:** consolidação 2026-05-15 — antes US-MCP-* estavam dispersas em [Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md](../Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) e [Jana/BUGS-MCP-SYNC-2026-05-13.md](../Jana/BUGS-MCP-SYNC-2026-05-13.md). Este SPEC vira a casa canônica do bounded context MCP.
> **Estimates:** recalibradas por [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x IA-pair + margem 2x.

## 1. Glossário

- **MCP server** — `mcp.oimpresso.com` (laravel/mcp em Modules/Jana/Mcp/) — entry point Claude Code do time
- **Tool MCP** — função invocável via protocol MCP; entry em `OimpressoMcpServer::$tools`
- **Event stream** — `memory/sessions/` + `memory/handoffs/` append-only ([ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md))
- **Projection (CQRS)** — read-side derivada on-demand do event stream (não nova storage)
- **Bounded context** — pasta `Modules/<X>/` + `memory/requisitos/<X>/` (DDD)
- **Tier 0** — multi-tenant isolation by default ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

## 2. User stories — índice

> **Convenção:** US-MCP-001 a 016 estão em outros arquivos (histórico). A partir de US-MCP-017 vivem aqui.

### Bugs/sync (US-MCP-001..004) — concluídas
- US-MCP-001..004 — ver [Jana/BUGS-MCP-SYNC-2026-05-13.md](../Jana/BUGS-MCP-SYNC-2026-05-13.md)

### Tier 6+7 estado-da-arte (US-MCP-005..010) — em backlog
- US-MCP-005..010 — ver [Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md](../Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) §Backlog

### Linear-parity (US-MCP-011..016) — em backlog longo
- US-MCP-011..016 — ver [Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md](../Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) §Linear-parity

### Active

#### US-MCP-017 · Tool `module-state <modulo>` (CQRS projection per bounded context)

> owner: wagner · priority: p1 · estimate: 14h IA-pair · status: todo · type: story · origin: dossier-2026-05-15
> blocked_by: aprovação Wagner áreas cinzentas (SPEC §10)
> gate: ver dossier §10 Q3 — esperar 2 semanas pós-time MCP entrar; ativar se 3+ pedidos "qual estado de X" sem brief responder ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) cliente como sinal qualificado)

**Spec completa:** [SPEC-US-MCP-017-module-state-projection.md](SPEC-US-MCP-017-module-state-projection.md)
**Runbook implementação:** [runbooks/RUNBOOK-module-state-tool.md](runbooks/RUNBOOK-module-state-tool.md)

**Resumo.** Read-side CQRS projection que consolida estado de um módulo (cycle ativo, tasks ativas, ADRs aplicáveis, handoffs recentes, PRs mergeados, charter, RUNBOOK, SPEC summary, CAPTERRA, drift) em <2s ~800 tokens. Não duplica storage do event stream — agrega on-demand via tools MCP existentes + git/gh + filesystem. Multi-tenant Tier 0. Cache 5min. Time MCP entrante (Felipe/Maiara/Eliana/Luiz) usa pra onboarding por módulo em <1min em vez de ler 50+ session logs.

**Refs:** [Dossier 2026-05-15 §6+§8](../../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) · [ADR 0130 event stream](../../decisions/0130-handoff-append-only-mcp-first.md) · [ADR 0093 Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0091 brief-fetch pattern cache](../../decisions/0091-daily-brief.md)

## 3. Métricas de saúde do bounded context

- Tools registradas em `OimpressoMcpServer::$tools`: 33 (mai/2026)
- Páginas MCP (paginação ListTools 15/page): 2 (Page 1 essenciais + Page 2 knowledge cluster)
- Cache hit rate alvo: ≥60% (medir via `mcp_*_cache` tabelas)
- Custo médio/call default: R$ [redacted Tier 0] (rule-based) — LLM opcional só em tools síntese

## 4. ADRs aplicáveis

- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server laravel/mcp como entry point
- [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style tasks via tools MCP
- [ADR 0091](../../decisions/0091-daily-brief.md) — Daily Brief (brief-fetch Tier A always-on)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 isolation
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio 2 tiered cost)
- [ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) — whats-active Tier 1
- [ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md) — handoff append-only (event stream)
- [ADR 0133](../../decisions/0133-system-health-audit-canonico.md) — System health audit canônico

## 5. Notas

Bounded context MCP é **governança projeto**, não cliente. Maioria das tools é repo-wide (sem `business_id`). Exceções: tools que tocam dados de business (ex: `memoria-search`, `decisions-search` quando ADR é per-business) — aí scope.

Pasta `runbooks/` segue convenção experimental — bounded contexts existentes (Infra, Jana, etc) usam `RUNBOOK-*.md` no root. Esta US-MCP-017 testa convenção sub-pasta. Avaliar em 60d se vale propagar.
