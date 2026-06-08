<!--
  TEMPLATE AUDIT — base canônica pra audits internos (ADR 0213).
  Copie pra `memory/sessions/{{YYYY-MM-DD}}-audit-{{slug-kebab}}.md`
  (filename DEVE conter `audit` pra hook `audit-creates-tasks.mjs` disparar).

  Diferença vs _TEMPLATE.md (session log normal):
    - Audit usa convenção parseável `TASK[owner](Px): desc` em vez de `❌` solto
    - Hook PostToolUse detecta as linhas TASK[...] e propõe `tasks-create` MCP em batch
    - Items que viram task ganham `<!-- TASK_CREATED: US-MOD-NNN -->` ao lado

  Por que: R10 da sessão Larissa 2026-05-28 — audit catalogou 11 gaps,
  6 ficaram órfãos no doc (fora do MCP backlog). 24h depois o diagnóstico
  consultou snapshot estático e listou 2 gaps JÁ fechados. Convenção +
  hook fecham o loop audit → backlog → ação.

  Override: `<!-- schema-allowlist: <razão> -->`.
-->
---
date: {{YYYY-MM-DD}}
hour: "{{HH:MM BRT}}"
duration: "{{2.5h}}"
topic: "Auditoria {{tema}} — {{escopo}}"
authors: [W, C]
outcomes:
  - "{{N gaps catalogados}}"
prs: []
us:  []
related_adrs: ["0213-audit-creates-tasks-loop-fechado"]
audit: true   # marca como audit (vs session log normal)
---

# Auditoria {{YYYY-MM-DD}} — {{título curto}}

## TL;DR

{{1-3 frases: o que foi auditado, quantos gaps, prioridade dominante}}

## Escopo

{{O que foi auditado e o que ficou de fora}}

## Inventário (3 buckets)

### ✅ APROVADO (paridade OK)
- {{item já implementado}}

### 🟡 PARCIAL (existe mas incompleto)
- {{item parcial}}

### ❌ AUSENTE (gap real)
- {{gap não implementado}}

---

## Tasks acionáveis (convenção ADR 0213 — parseável pelo hook)

> Cada gap que vira task usa o pattern abaixo. Hook `audit-creates-tasks.mjs`
> detecta no Write e propõe `tasks-create` MCP em batch (Wagner confirma 1×).
> Após criar, hook escreve `<!-- TASK_CREATED: US-MOD-NNN -->` ao lado.

- [ ] TASK[claude](P1): {{Descrição curta do gap acionável}}
  - Onde: {{path/arquivo.tsx:linha}}
  - Esforço: {{S | M | L}}
  - Impact: {{quem sofre + como}}

- [ ] TASK[wagner](P0): {{Gap que precisa decisão Wagner}}
  - Onde: {{path}}
  - Esforço: {{S/M/L}}
  - Impact: {{...}}

<!--
  Gaps que NÃO viram task (decisão consciente) devem usar:
  <!-- TASK_IGNORED: razão (ex: dormente até sinal qualificado ADR 0105) -->
  pra não disparar warning no workflow audit-orphan-check.yml.
-->

## Recomendação de ataque

{{Ordem priorizada — qual task primeiro e por quê}}

## Refs

- ADR 0213 — Audit docs criam MCP tasks (loop fechado)
- {{outras refs}}

---

**Arquivo:** `memory/sessions/{{YYYY-MM-DD}}-audit-{{slug}}.md`
**Status:** audit-only (nenhum código alterado durante a auditoria)
