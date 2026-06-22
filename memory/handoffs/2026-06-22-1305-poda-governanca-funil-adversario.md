---
date: "2026-06-22"
time: "13:05 BRT"
slug: "poda-governanca-funil-adversario"
tldr: "Wagner pediu MAP do sistema + desinchar a malha de CI. 3 fusões de workflows mergeadas via funil adversarial (#3202/#3203/#3204, -7 wf) — mas o sistema cresceu 81->85 no mesmo período (outras sessões +10/24h): a taxa de criação supera a poda. Proposta de ADR do TETO de governança criada."
cycle: null
prs: [3202, 3203, 3204]
us: []
decided_by: ["W"]
topic: "Poda de governança via funil adversarial (3 fusões) + achado: taxa de criação > poda → teto é a alavanca"
duration: "~4h"
authors: ["claude-code-wagner-laptop", "wagner"]
---

## Estado MCP no momento
- Cycle ativo: CYCLE-08 (Receita — Onda A), ~6d. Poda foi **off-cycle** (governança).
- my-work @wagner: 30 tasks (7 review, 8 blocked, 15 todo) — nenhuma de poda; o teto é item NOVO.
- Handoffs irmãos hoje: charter-refs (1223), shipped-log (0850), onda0-deconflict (0800) — a casa inteira mexendo em meta-governança em paralelo (parte do achado).

## O que aconteceu
"MAP do sistema + tem algo quebrado?" → mapa de saúde (runtime ok; **439 erros TS sem gate de typecheck**; PR Tier-0 #3162 aberto). → "desinchar a malha de CI" → escolheu podar a governança. Errei **3 propostas seguidas** (sempre "ausência de sinal = ausência de valor"); Wagner: "já errou, quero um adversário antes" → instituímos o **funil adversarial** (proposta → adversário refuta → executa o que sobrevive → adversário no diff → CI auto-valida). 3 fusões seguras mergeadas. No fim, o contador provou que **podar não vence a torneira**.

## Artefatos gerados
- PRs MERGED: **#3202** (guards-meta), **#3203** (xss-content), **#3204** (jana-logica-pura). −7 workflows das fusões; registry + memory-health Check G atualizados em cada.
- Session log: `memory/sessions/2026-06-22-poda-governanca-funil-adversario.md` (método + 5 lições).
- Proposta ADR: `memory/decisions/proposals/2026-06-22-teto-de-gate-governanca.md` (o roadmap item).
- Task MCP: US-GOV-* (teto), p2.

## Persistência
- git: este PR (handoff + session log + proposta + índice).
- MCP: task US-GOV-* via tasks-create (webhook ~2min).
- BRIEFING: n/a (não tocou módulo de produto, só CI/governança).

## Próximos passos pra retomar
1. Wagner decide a proposta do **teto** (`teto-de-gate-governanca.md`) — regra + dono do check (MVP = estender memory-health Check G).
2. Movimento 2 (rebaixar 4 advisory: handoff-integrity/plan-health/bundle-lint/reincidencia) só após **~5/jul** (14d ADR 0275 §5) + medir **catches reais**, não `conclusion`.
3. Bônus: promover `xss-content` a `required` (ganho anti-XSS) — branch protection, Wagner.

## Lições catalogadas (detalhe no session log)
- Ausência de sinal numa métrica cega ≠ ausência de valor (3 erros seguidos).
- Advisory não falha por design → `conclusion` é cega; cortar advisory exige catches + 14d (ADR 0275).
- **Taxa de criação de governança > poda manual** → o teto é a alavanca, não o balde.

## Pointers detalhados
- Session log — método do funil + as 5 lições + placar.
- Proposta teto — contexto + mecanismo (gates-registry +2 campos, memory-health Check G estendido).
