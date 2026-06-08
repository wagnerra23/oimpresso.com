---
slug: session-2026-05-05-triagem-roadmap-mcp-audit
title: "Sessão 2026-05-05 — Triagem 135 tasks + roadmap 17 epics + auditoria tools MCP"
type: session
date: 2026-05-05
authors: [W, Claude]
related_adrs: [0070, 0071]
related_prs: []
---

# Sessão 2026-05-05 — Triagem + Roadmap + Auditoria MCP

## Sintomas iniciais

Wagner pediu `/continuar`. Estado lido:
- Cycle ativo CYCLE-01 (até 12-mai), 7 dias restantes, 2 goals ✅ + 1 🔲 (dashboard custos)
- `my-work`: 2 doing (COPI-22 driver MCP, COPI-21 frontmatter YAML), 5 blocked, 3 todo
- Handoff datado 29-abr — **stale por 6 dias** (4 sessões 04-mai não refletidas)

Wagner observou:
1. **"O roadmap está vazio"** — investigação revelou `mcp_epics` com 0 rows (Fase 1 ADR 0070 implementada mas nunca populada)
2. **"Minhas tarefas não aparecem em `my-work`?"** — 30 tasks PROJECT/COPI em `status:backlog` (default `my-work` filtra). Decisão: **(B) triagem em massa**.

## Trabalho executado

### 1. Triagem (135 tasks atualizadas, 17 canceladas)

Wagner aprovou (B) triagem rápida. Sem tool `tasks-bulk-update`, executei via curl JSON-RPC pra `tasks-update`:

| Categoria | Qtde | Ação |
|---|---|---|
| 36 tasks recém-criadas (8h) | 36 | priority + status=todo |
| 82 tasks legacy "sem owner" (4 dias) | 82 | owner=wagner + status=todo + priority |
| Duplicatas | 1 | COPI-32 cancelled (dup COPI-21) |
| Tools rejeitadas por ADR | 1 | EVO-1 cancelled (Vizra ADK rejeitada por 0048) |
| DOCVAULT obsoleto | 11 | cancelled (módulo renomeado pra MemCofre) |
| Placeholders sem título | 5 | US-CRM/ESSE/MANU/REPA/PROJ-001 cancelled |

**Total:** 135 atualizadas + 17 canceladas. Triage final: **0 backlog, 0 sem owner**.

### 2. Roadmap estruturado (17 Epics, 6 projects novos)

`mcp_epics` estava vazia. Criei 17 epics distribuídas em 3 quarters via script PHP (Tinker bootstrap):

**2026-Q2 (atual, foco execução) — 7 epics:**
- COPI-E1 Memória/Retrieval/KB (14 tasks)
- COPI-E2 Token Economy (5 tasks) ← inclui **COPI-40 cache semântico p0**
- COPI-E3 LGPD & Observability (10 tasks — após realocar US-AI-* aqui)
- COPI-E5 Driver MCP/Realtime/Permissions (4 tasks)
- INFRA-E1 Deploy + Backup + Sentry (5 tasks)
- PROJECT-E1 Jira-style Board Fase 7 ADR 0070 (10 tasks)
- GROW-E1 Grow MVP (2 tasks)

**2026-Q3 — 5 epics:**
- COPI-E4 Chat UX & Polish (33 tasks — inclui 24 US-COPI-001..075 legacy)
- FIN-E1 Financeiro evolução (21 tasks — inclui US-ACCO-* movidas)
- PONTO-E1 PontoWr2 retomada (17 tasks)
- NFSE-E1 NFSe @ Eliana (10 tasks) — **project NFSE criado**
- CMS-E1 Landing + Cms refactor (3 tasks)

**2026-Q4 — 5 epics:**
- NFE-E1 NFe Brasil + CT-e (12 tasks)
- REC-E1 RecurringBilling (15 tasks)
- ACCO-E1 Accounting MVP (10 tasks) — **project ACCO criado**
- AI-E1 AI permissions/auditoria (5 tasks) — **project AI criado**
- EVO-E1 Agente autônomo futuro (7 tasks)

**Projects novos criados em mcp_jira_projects:**
- `NFSE` (id 20) — owner Eliana, color orange
- `ACCO` (id 21) — Accounting, color teal
- `AI` (id 22) — AI permissions, color purple

### 3. Auditoria das 18 tools MCP — 5 bugs identificados

Smoke test JSON-RPC em todas as tools. Resultado: **13/18 OK**, 5 com problemas. Detalhe + workarounds em **ADR 0071**.

Findings novos (não documentados antes):
1. **`mcp_tasks` tem DUAS colunas de identifier**: `task_id` (varchar 40 — legacy `US-COPI-079`) e `identifier` (varchar 24 — Linear `COPI-22`). Tasks legacy backfill só preencheram `task_id`. Sempre filtrar pela coluna certa.
2. **`mcp_jira_projects` ≠ `mcp_projects`**: o segundo é entidade de planejamento de produto (viability/decision/custo_brl) — vazia. Tools usam `mcp_jira_projects`. Confusão de nomeação a corrigir.
3. **Backfill ADR 0070 Fase 4 incompleto**: 108 tasks legacy `US-*` tinham `project_id NULL`. Set por prefix mapping (US-COPI- → COPI, US-FIN- → FIN, etc).
4. **`tasks-create` retorna success mas NÃO escreve SPEC.md** — bug. Provável: MCP em CT 100, SPEC.md no Hostinger, write fail silencioso.
5. **5 tools auth-degradadas** (`my-inbox`, `claude-code-usage-self`, `memoria-search`, `cc-search`, `my-work` parcial) — token bound a user_id=1 em `mcp_tokens` mas tools-Pessoa não resolvem o user.

## Saída concreta

| Artefato | Status |
|---|---|
| 135 tasks com owner + status + priority + epic_id + project_id | ✅ DB prod atualizado |
| 17 Epics em 13 projects (3 novos) | ✅ |
| Tasks órfãs sem epic | 0 (todas linkadas exceto MEMCOFRE-1/2 e OFFICE-1 — projects sem Epic dedicada porque <3 tasks) |
| ADR 0071 — bugs + workarounds + schema canônico | ✅ |
| Session log | ✅ (este arquivo) |
| Handoff atualizado | (próxima etapa) |
| Commit + push + PR + merge | (próxima etapa) |

## Pendências saindo desta sessão

1. **Cache semântico (COPI-40 p0)** — Wagner liberou nesta sessão (`"e pode fazer o cache semantico"`) mas pivotou pra finalizar dia. **Próxima sessão:** ADR 0037 Sprint 8 — `SemanticCacheMiddleware` antes de `recallMemoria()`, similarity > 0.95, redução esperada -68.8% tokens.
2. **Fix B1** (`tasks-create` write fail silencioso) — abrir como US no SPEC TaskRegistry.
3. **Fix B2** (auth-degradação 5 tools MCP) — investigar `McpAuthMiddleware`.
4. **PROJECT-3** (frontmatter YAML SPECs) — escalar prioridade pra **p2** (era p3) — habilita re-rodar `mcp:tasks:sync` sem perder triagem desta sessão.
5. **Validar Larissa** (A4 rodada 2 — handoff antigo) — ainda pendente desde 29-abr.

## Lições

- "Roadmap vazio" pode significar `mcp_epics` literalmente vazia, não bug de UI.
- Triagem em massa via curl JSON-RPC funciona — mas cliente Claude Code (ToolSearch) nem sempre carrega todas as tools deferidas. Workaround: chamar tools/call HTTP direto.
- Fase de implementação (ADR 0070 Fase 1+4) ≠ Fase de população real. Ter schema NÃO significa ter dados. Próxima ADR que descrever sistema novo: incluir checklist de "schema rodado + dados seed".
