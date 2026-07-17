# BRIEFING — Modules/TeamMcp

> Estado consolidado 1-pager · Última atualização: 2026-05-16 · nota reconciliada 2026-07-17
> Owner: Wagner [W] · Trust: L1 · Status prod: ✅ live
> **Module grade: 79/100 (Bom) em 2026-07-17.** Dono do número: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) — recomputar com `php artisan module:grade TeamMcp`. Era **29/100 (Crítico)** em 2026-05-16, quando este 1-pager nasceu: a rubrica evoluiu pra v3 ([ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md)) e o módulo recebeu PRs desde então. Os gaps listados abaixo são de 2026-05-16 e podem estar fechados.

## O que faz (1 frase)

Painel administrativo do **MCP server canônico `mcp.oimpresso.com`**: gerencia tokens, Identity Mesh (actors humanos + IA), audit log append-only, sync webhooks git→DB, ingest Claude Code sessions, e Kanban Jira-style do time (5 pessoas: Wagner/Felipe/Maiara/Luiz/Eliana).

## Por que existe

Self-host equivalente ao Anthropic Team plan adaptado pra **LGPD + custo + custom** ([ADR 0059](../../decisions/0059-self-host-team-plan-anthropic-rejeitado.md)). Time MCP precisa de identidade rastreável (Constituição Art. 6 Identity Mesh) — cada ação no MCP server liga a humano físico via `actor_id`, com cadeia de delegação `parent_actor_id` pra IA pareada.

## Capacidades core (live em prod)

| Capacidade | Status | Owner US |
|---|---|---|
| Token MCP issue/revoke por Wagner | ✅ live | US-TEAM-002, US-TEAM-003 |
| Identity Mesh — 5 actors humanos seedados (Wagner L0, Felipe/Maira L2, Luiz/Eliana L3) | ✅ live | US-TEAM-001 |
| Audit log append-only (`mcp_audit_log` + trigger MySQL imutabilidade) | ✅ live | US-TEAM-007 |
| Webhook git→DB sync (`memory/*` → `mcp_memory_documents`, ~352 docs) | ✅ live | n/a |
| Ingest Claude Code sessions (`mcp_cc_sessions/messages/blobs`) | ✅ live | MEM-CC-UI-1 |
| Kanban Jira-style admin (`mcp_tasks` em `/team-mcp/tasks`) | ✅ live | TR-007 |
| ActionGate runtime enforce | ⏸️ Fase 5 (planejado) | US-TEAM-005 + ADR 0086 |
| UI dashboard audit log | ⏸️ vai pra `Modules/Governance` | per SCOPE.md |

## Tabelas DB owned

`mcp_actors` (cross-tenant ADR 0081) · `mcp_tokens` (bind actor+user) · `mcp_scopes` · `mcp_user_scopes` · `mcp_user_module_access` · `mcp_audit_log` (append-only trigger) · `mcp_cc_sessions/messages/blobs` · `mcp_tasks/epics/cycles/jira_projects` · `mcp_inbox_notifications` · `mcp_components/views/quotas/workflows`

## Diferenciais vs mercado

- **vs Anthropic Team plan (managed):** self-host LGPD compliant, custo zero per-seat, custom Identity Mesh com manifest YAML por actor, audit log local imutável
- **vs Vaultwarden/1Password (vault só):** governança per-tool + per-módulo (`modules_write/blocked`), cadeia delegação IA→humano, integração nativa com Kanban+Cycles do projeto
- **vs Jira/Linear (PM tools):** sincronia git canon ↔ MCP via webhook, ingest Claude Code sessions cross-dev (cc-search), trust tiers (L0-L4) operacionais
- **único custom:** `parent_actor_id` resolve IA pareada → humano responsável (audit trail `effectiveHumanSlug()`)

## Gaps conhecidos (priorizados)

| Bucket | Item | US |
|---|---|---|
| 🔴 P0 | Cobertura Pest: tests escassos (só McpActorsSeederTest existia até 2026-05-16) | US-TEAM-001..007 (resolvidos batch atual) |
| 🟡 P1 | UI dashboard audit log (mcp_audit_log queries via Modules/Governance Fase 5) | pendente ADR 0086 |
| 🟡 P1 | ActionGate runtime enforce (warn-only no momento) | ADR 0086 Fase 5 |
| 🟢 P2 | Self-service token rotation pra dev (hoje só Wagner cria) | backlog |
| 🟢 P2 | Métrica per-actor: quota usada, ações 24h, ban rate | backlog |

## Restrições Tier 0 IRREVOGÁVEIS

- ⛔ **`mcp_actors` NUNCA tem `business_id`** — cross-tenant by design ADR 0081 (Identity Mesh transcende tenants)
- ⛔ **`mcp_audit_log` NUNCA aceita UPDATE/DELETE** — trigger MySQL bloqueia (append-only by law-of-the-land)
- ⛔ **Token revogado NUNCA resolve** — `ActorResolver` retorna null pra `revoked_at != null`
- ⛔ **`Modules/TeamMcp` NÃO expõe MCP tools no Hostinger** — apenas CT 100 (Wagner regra 2026-05-07; `MCP_TOOLS_EXPOSED=false` em Hostinger)
- ⛔ **PII actor (email/CPF) NUNCA em PR/commit/log** — usar `display_name` curto

## Próximos passos (sprint atual)

1. ✅ **Cobertura Pest base** (US-TEAM-001..006) — 4 feature tests criados batch 2026-05-16
2. ⏳ **ActionGate Fase 5 enforce** — middleware bloqueia tool call se `canWriteModule=false` ou `isActionBlocked=true`
3. ⏳ **UI Wagner** consolidar `/team-mcp/team` (hoje espalhado em `/copiloto/admin/team` legacy)
4. ⏳ **Migration linkar `users.mcp_actor_id`** pra todos 5 humanos (hoje só seeder cria actors, link manual)

## Referências canon

- SPEC: [memory/requisitos/TeamMcp/SPEC.md](SPEC.md)
- SCOPE: [Modules/TeamMcp/SCOPE.md](../../../Modules/TeamMcp/SCOPE.md)
- ADRs: 0053 (MCP server), 0070 (Jira-style), 0079 (Constituição 7 camadas), 0080 (Trust tiers), **0081 (Identity Mesh — mãe)**, 0086 (ActionGate Fase 5), 0093 (Multi-tenant Tier 0)
- Testes Pest: `Modules/TeamMcp/Tests/Feature/` — 5 arquivos cobrindo seeder + isolamento + smoke + scaffold + matriz permissões

---
**v1.0.0** (2026-05-16) — BRIEFING inicial pós-batch cobertura Pest (Wave Massive 12-agents paralelos).

## Fusões absorvidas (KL-E2)

Este módulo **absorveu** (fusão FUNDIR, KL-E2) o **sistema** da pasta tombstoneada **TaskRegistry** — 15 US-TR + 8 US-UI ficaram `status: historical` in-place com ponteiro nuançado. ⚠️ Nuance: a **UI** (`SPEC-UI-FASE7`) segue a Fonte VIVA de **ProjectMgmt** (US-TR-309), não migra pra cá — só o sistema/backend consolida em TeamMcp. Ver [_TRIAGEM-IDENTIDADE-2026-06.md](../_TRIAGEM-IDENTIDADE-2026-06.md) §"Estado de execução E2/E3" (fusões FUNDIR, redirects #2750/#2757, fechamento #3653).
