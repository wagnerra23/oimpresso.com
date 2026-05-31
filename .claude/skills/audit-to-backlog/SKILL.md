---
name: audit-to-backlog
mission: "Fechar o loop audit → MCP backlog → ação. Gap catalogado em audit NUNCA vira órfão invisível pra próxima sessão."
description: ATIVAR quando user pedir "transformar audit em tasks", "levar audit X pro backlog", "criar tasks do audit", "/audit-to-backlog <doc>", OU quando agente escreve/lê audit doc em `memory/sessions/*-audit-*.md` ou `memory/requisitos/*/AUDIT-*.md` com itens `❌`/`🟡`/`TASK[owner](Px)` sem `TASK_CREATED` correspondente. Generaliza `comparativo-do-modulo` (que só cobre audits Capterra de módulo) pra QUALQUER audit interno — Sells/Create vs Blade, audit de processo, postmortem, bug investigation. Lê audit → cruza com MCP backlog (tasks-list) pra detectar duplicatas → propõe batch tasks-create com parent_audit metadata → Wagner confirma 1× → cria tasks + escreve cross-link no audit. NÃO cria tasks sem confirmação humana (publication-policy). Pareada com hook audit-creates-tasks.mjs (Mecanismo 2).
type: process-skill
status: active
version: 1.0.0
trust_level: L2
owner: wagner
created_at: 2026-05-28
parent_adr: 0213
parent_mission: meta-skill-roi-erp-autonomo
tier: B
---

# Audit-to-backlog — loop fechado audit → MCP task

> **Mecanismo 3 do [ADR 0213](../../memory/decisions/0213-audit-creates-tasks-loop-fechado.md).** Generaliza [`comparativo-do-modulo`](../comparativo-do-modulo/SKILL.md) (audits Capterra) pra QUALQUER audit interno.

## Por que existe (R10 raiz)

Sessão Larissa 2026-05-28: audit `2026-05-27-audit-sells-create-vs-blade-larissa.md` catalogou 11 gaps `❌`. R5/R6 atacou 5, **6 ficaram órfãos no doc** (fora do MCP backlog). 24h depois, diagnóstico Wagner-style "quais erros podem acontecer?" consultou o **snapshot estático** do audit e listou 2 gaps **já fechados** em commits do mesmo dia.

**Causa raiz:** audit doc é estático, MCP backlog é vivo. Sem ponte, gaps viram órfãos invisíveis. Esta skill é a ponte.

## Quando ATIVAR (3 modos)

| Modo | Gatilho | Output |
|---|---|---|
| **A. Explícito** | "transformar audit em tasks", "/audit-to-backlog <doc>", "levar audit pro backlog" | Executa fluxo completo |
| **B. Hook-triggered** | Hook `audit-creates-tasks.mjs` detectou tasks órfãs no Write → injetou system-reminder | Propõe batch ao Wagner |
| **C. Auto-detect** | Agente lê audit doc com `❌`/`🟡`/`TASK[...]` sem `TASK_CREATED` | Sugere rodar a skill |

## Fluxo (6 passos)

### 1. Ler audit doc completo
`Read memory/sessions/<doc>-audit-*.md` (ou path informado).

### 2. Extrair itens acionáveis
Prioriza convenção parseável `TASK[owner](Px): desc`. Se audit usa só `❌`/`🟡` (formato antigo), extrair manualmente:
- `❌ AUSENTE` → candidato P1/P2
- `🟡 PARCIAL` → candidato P2/P3
- `✅ APROVADO` → ignorar (já feito)

### 3. Cruzar com MCP backlog (detectar duplicatas)
```
mcp__oimpresso__tasks-list module:<X> status:todo
mcp__oimpresso__tasks-list module:<X> status:doing
```
Pra cada item do audit, checar se já existe task similar (título/descrição match). **Crítico** — evita o erro inverso (criar task duplicada de algo já no backlog OU já fechado).

### 4. Cruzar com PRs recentes (detectar já-fechados)
```
gh pr list --state merged --search "<keyword>" --limit 20
```
Item do audit que já foi fechado em PR mergeado → marcar `<!-- TASK_DONE: #PR -->` em vez de criar task. **Isto previne exatamente o R10** (listar gap já resolvido).

### 5. Propor batch `tasks-create` ao Wagner (confirmação 1×)
Apresentar tabela:
| # | Gap | Módulo | Prio | Esforço | Status detectado |
|---|---|---|---|---|---|
| 1 | ... | Sells | P1 | S | NOVO (criar) |
| 2 | ... | Sells | P2 | M | DUPLICATA US-SELL-099 (skip) |
| 3 | ... | Sells | P3 | L | JÁ-FECHADO PR #1832 (marcar DONE) |

Wagner confirma **1× batch** (não 1-a-1). Pra cada NOVO confirmado:
```
mcp__oimpresso__tasks-create module:<X> title:"..." priority:pN estimate_h:N
```
Com metadata `parent_audit:<slug-do-audit>` pra drill-down reverso.

### 6. Escrever cross-link no audit doc
Após criar, editar o audit:
- Item criado → `<!-- TASK_CREATED: US-MOD-NNN -->` ao lado
- Item duplicado → `<!-- TASK_CREATED: US-MOD-NNN (pré-existente) -->`
- Item já-fechado → `<!-- TASK_DONE: #PR -->`
- Gap dormente consciente (ADR 0105) → `<!-- TASK_IGNORED: razão -->`

Audit doc vira sincronizado com MCP backlog. Hook `audit-creates-tasks.mjs` não dispara mais (todos linkados).

## Anti-padrões

| ❌ Errado | ✅ Certo |
|---|---|
| Criar task de cada `❌` sem cruzar backlog | Passo 3+4 detecta duplicata/já-fechado primeiro |
| Criar tasks sem Wagner confirmar | Batch + confirmação 1× (publication-policy) |
| Deixar audit doc sem cross-link após criar | Passo 6 obrigatório — senão hook redispara |
| Forçar task em gap dormente | `TASK_IGNORED: razão` (respeitando ADR 0105 cliente-como-sinal) |

## Pareada com

- [ADR 0213](../../memory/decisions/0213-audit-creates-tasks-loop-fechado.md) — loop fechado (5 mecanismos)
- [Hook `audit-creates-tasks.mjs`](../../.claude/hooks/audit-creates-tasks.mjs) — Mecanismo 2 (PostToolUse Write)
- [Template `_TEMPLATE-audit.md`](../../memory/sessions/_TEMPLATE-audit.md) — Mecanismo 1 (convenção)
- [`comparativo-do-modulo`](../comparativo-do-modulo/SKILL.md) — irmã (audits Capterra de módulo)
- [ADR 0070](../../memory/decisions/0070-jira-style-task-management.md) — Jira-style task management
- [ADR 0105](../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente-como-sinal (justifica TASK_IGNORED)

## Origem

R10 sessão Larissa 2026-05-28. Wagner não pediu explicitamente esta skill, mas o dossier estado-da-arte 6 frentes (Frente 4 audit-to-backlog) + ADR 0213 catalogaram o gap. Esta skill + hook + template + (futuro) workflow CI + health-check = 5 mecanismos do loop fechado.

ROI: cada audit que vira backlog sincronizado evita "diagnose outdated" na próxima sessão (~10k tokens desperdiçados re-analisando gaps já fechados). Mais: time futuro (Felipe/Maiara/Eliana/Luiz) vê backlog vivo, não doc estático.
