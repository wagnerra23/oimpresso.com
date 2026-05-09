---
date: 2026-05-07
slot: manhã
title: "Revisão CYCLE-01 órfão + fechamento + abertura CYCLE-02 + patch commit-discipline"
participants: [W, C]
duration_min: 60
tags: [governance, mcp, cycle-close, retro, commit-discipline, post-rename-jana]
---

# 2026-05-07 manhã — Revisão CYCLE-01 + abertura CYCLE-02

## Trajetória

Wagner abriu sessão com `my-work` e disse "não existe mais Jana, revise e descubra o que aconteceu, e aprenda". Diagnóstico → relatório → autorização ("ok faça obrigado") → execução completa.

## Diagnóstico (cronologia real recuperada via filesystem + git, NÃO MCP)

CYCLE-01 (29-abr → 12-mai, "Jana memória + MCP") ficou **órfão por 5 dias** após o pivot Constituição V2 (5-mai noite). MCP `cycles-active` mostrava cycle vivo com tasks zombie, mas o trabalho real estava em outros eixos:

| Janela | Eixo real | PRs |
|---|---|---|
| 29-abr → 02-mai | Memória Jana (no plano) | Sprint memória + Vizra rejeitada |
| 04-mai | Sprint 9b/9c retrieval + RAGAS + ADR 0069 | #93-100 |
| 05-mai noite | **PIVOT: Constituição V2 nasce** | Roteiro 7 camadas + 8 princípios |
| 06-mai dia | S3 Constituição V2 + Capterra + rename DB Jana | #111, #117, #119, #131-137 |
| 07-mai madrugada | S2.5 Repair MWART + topnav AppShellV2 | #138-148 |
| 07-mai manhã | Whatsapp ADR 0096 + esta sessão | `29bce4de` |

Goals 1+2 do CYCLE-01 ✅ batendo desde 29-abr. Goal 3 (dashboard custos) virou irrelevante pós-pivot.

## Lições aprendidas (estruturais)

1. **Cycle de 2 semanas com janela fixa não cabe num burst de 80h em 5 dias.** Pivot estratégico → cycle anterior vira morto-vivo.
2. **MCP cycles/tasks é write-once-then-stale.** PR mergeia mas ninguém roda `tasks-update`. → patch skill `commit-discipline` (esta sessão) com regra explícita de auto-update post-merge.
3. **Rename Jana→Jana foi PHP-only completo (ADR 0088) + DB completo (ADR 0092)** mas o module key MCP ficou COPI. Decisão: **NÃO renomear** — IDs históricos `COPI-NN` preservam rastro nos PRs.
4. **Pivots estratégicos sem fechar cycle anterior viram dark matter.** Falta regra: pivot → `cycles-close --rollover` no mesmo dia + `cycles-create` novo.
5. **brief-fetch + cycles-active deram dado errado.** Falta no brief: alerta "trabalho real vs cycle planejado" — se eixo do PR mergeado ≠ eixo do cycle ativo, alerta.

## Ações executadas

### 1. Triagem 5 tasks (DB-only)

| Task | Antes | Depois | Evidência |
|---|---|---|---|
| COPI-21 (MEM-KB-3 frontmatter YAML) | todo p0 | **done** | `f37c0832` US-COPI-078 backfill 56 ADRs |
| COPI-24 (F2 auto-mems P1) | blocked | **cancelled** | description já dizia "englobada por MEM-KB-3" |
| COPI-38 (Centrifugo CT 100) | todo p1 | **done** | sessão 2026-04-30 + ADR 0058 + Reverb superseded |
| COPI-42 (ProfileDistiller) | todo p1 | **done** | PR #124 fix Cache::tags + schedule diário |
| COPI-23 (HyDE+Reranker) | blocked | **manter blocked** | Sprint 9c reranker (US-COPI-087) ainda todo no SPEC |

### 2. Decisão NÃO renomear module COPI → JANA

`tasks-update` não aceita `module`. Renomear via SQL em massa quebra histórico de PRs (`COPI-XX` aparece em ~30 commits). Decisão: **preservar IDs como histórico**. Tasks novas usam código do módulo atual (`JANA-NN`, `MWART-NN`, `NFE-NN`).

### 3. CYCLE-02 criado via SQL CT 100

Tool `cycles-create` não existe no MCP (registrado como pendência no handoff 2026-05-06). Criação manual:

```sql
-- mcp_cycles
INSERT row id=3, project_id=1 (COPI), key='CYCLE-02',
  start=2026-05-13, end=2026-05-26,
  status=planning → active

-- mcp_cycle_goals (4 goals)
1. Repair MWART expansão (4+ telas com cockpit + topnav)
2. NfeBrasil emite NFe55 a partir de boleto pago em prod ROTA LIVRE
3. Constituição V2 health-check 0 alertas críticos por 7 dias
4. Skills V0.5 UI em prod /ads/admin/skills
```

Janela: **2026-05-13 → 2026-05-26** (2 semanas). Status `active`.

Note: chave `CYCLE-02` reutiliza key existente do project ADS (id=23) — uniq é composto `(project_id, key)`.

### 4. CYCLE-01 fechado com retro real

Via `cycles-close rollover_to=CYCLE-02`:

- **Sucessos** (15 itens) — incluindo Larissa OK, recall=190, sprints memória+9+9b, PiiRedactor, Centrifugo, Pivot Constituição V2, Capterra ADR 0089, rename Jana ADR 0088+0092, MWART 4 telas, NfeBrasil foundation
- **Falhas** (5 itens) — goal #3 dashboard custos não entregue (irrelevante pós-pivot), cycle órfão 5 dias, tasks stale 1-3 dias, module key COPI desatualizado, `cycles-create` não exposta
- **Lição:** pivot estratégico → `cycles-close --rollover` no MESMO dia. Hook commit→tasks-update auto. brief-fetch deve alertar eixo PR ≠ eixo cycle.

1 task rolou (COPI-23 blocked).

### 5. Patch skill commit-discipline

Adicionada seção "Auto-update tasks-update após commit/merge (regra MCP)" em `.claude/skills/commit-discipline/SKILL.md`. Regra:

- Após `git push` que avança trabalho de uma task → `tasks-comment` linkando commit
- Após `gh pr merge` → `tasks-update status=done`
- Bloqueio descoberto → `tasks-update status=blocked`
- Regex pra extrair task ID: `(?:Refs:\s*)?\b(?:US-)?(?:COPI|JANA|NFE|RB|REPAIR|FIN|CRM|GOV|ADS|MWART)-\d+\b`

Ainda **manual** (não automatizado via hook). Hook PostToolUse + extração automática fica como CYCLE-02 backlog.

## Pendências MCP (registrar no backlog)

1. **Tool `cycles-create`** exposta — atualmente CYCLE-02 só foi criado via SQL no CT 100. Próxima vez precisa ser tool MCP.
2. **Tool `tasks-update` aceitar `module`** — pra renomear projects no futuro sem SQL direto.
3. **Hook PostToolUse `Bash:gh pr merge`** que extrai task ID e roda `tasks-update status=done`.
4. **brief-fetch alerta** — se PR mergeado nas últimas 24h tem scope diferente do cycle ativo, mostrar warning.

## Estado pós-sessão

- CYCLE-01 fechado em COPI (id=1), retro registrado
- CYCLE-02 ativo em COPI (id=3, 19 dias restantes), 4 goals abertos
- 5 tasks reclassificadas (COPI-21, 24, 38, 42 + comment em COPI-23)
- Skill commit-discipline patcheada com regra auto-update
- Session log + handoff update + 1 PR

## Próximo passo

Retomar trabalho real do CYCLE-02:
1. **US-RB-044** (review hoje) → confirmar merge ou deploy
2. **MWART scaling** — próximas telas Repair / Project / Crm com cockpit pattern
3. **Skills V0.5** — Sprint A backend (proposta `memory/cycles/CYCLE-02-proposta.md`)
4. **Whatsapp foundation** — ADR 0096 SPEC

## Files tocados

- `.claude/skills/commit-discipline/SKILL.md` — +35 linhas seção auto-update
- `memory/sessions/2026-05-07-revisao-cycle-01-rollover-cycle-02.md` — este log
- `memory/08-handoff.md` — atualizado próxima sessão
