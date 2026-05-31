---
name: feedback-mcp-indexer-skips-meta-modules
description: Indexador SPEC→mcp_tasks NÃO sincroniza meta-módulos _-prefixados (_DesignSystem) → tasks git-canônicas ficam invisíveis no tasks-list/MCP
type: reference
created: 2026-05-31
authors: [wagner, claude-opus-4-8]
related_adr: [0070, 0144, 0053]
tags: [mcp, tasks, indexer, gotcha, design-system]
---

# Indexador MCP pula meta-módulos `_`-prefixados (SPEC→mcp_tasks)

## O fato (descoberto 2026-05-31)

`tasks-list module:_DesignSystem` retorna **vazio** — mesmo o `origin/main` tendo **US-_DESIGNSYSTEM-001..018** no `memory/requisitos/_DesignSystem/SPEC.md` (as 001-013 desde 2026-05-28, ~3 dias). Não é latência de cron: é **estrutural**.

**Causa provável:** o indexador git→`mcp_tasks` (server-side CT100, ADR 0070/0144) tem allowlist de módulos **reais** (`Modules/<X>` → `Financeiro`, `Jana`, `ProjectMgmt`, `PCP`…). `_DesignSystem` é pasta **meta** de `requisitos/` (não existe `Modules/_DesignSystem`), então é pulada. Resultado: **18 US de design-system git-canônicas mas invisíveis no MCP** esse tempo todo — ninguém notou porque ninguém consultou `tasks-list module:_DesignSystem`.

## Impacto

- Toda US criada em módulo `_`-prefixado (`_DesignSystem`, e qualquer outro meta-módulo) **não aparece** em `tasks-list`, `my-work`, brief, nem pro time via MCP.
- `tasks-create module:_DesignSystem` **aceita** e escreve o SPEC.md (git fica certo), mas a projeção MCP nunca recebe → falsa sensação de "criada".

## Fix (escolher um)

1. **Server-side (raiz):** incluir os meta-módulos `requisitos/_*` na allowlist do indexador SPEC→`mcp_tasks` no MCP server (CT100). Owner: time MCP (domínio Jana/Copiloto).
2. **Convenção (workaround):** tasks cross-cutting de design-system vão num módulo **indexado** — `ProjectMgmt` (onde o plano US-TR-309..314 já vive) em vez de `_DesignSystem`.

## Como aplicar

- Ao criar task de design/DS: prefira `module:ProjectMgmt` até a allowlist ser corrigida.
- Ao auditar "por que minha task não aparece no MCP": cheque se o módulo é `Modules/<X>` real OU meta `requisitos/_*`. Se meta → é este gotcha.
- Verificação: `git show origin/main:memory/requisitos/<X>/SPEC.md | grep US-` vs `tasks-list module:<X>` — divergência = módulo não-indexado.

**Refs:** worklist auditoria paralela 2026-05-31 (US-_DESIGNSYSTEM-014..018, [handoff 14:34](../handoffs/2026-05-31-1434-backlog-fixes-5-tasks-2-merges-mcp-sync-lag.md)) · ADR 0070 (MCP tasks) · ADR 0144 (tasks-db-canônico SPEC-template).
