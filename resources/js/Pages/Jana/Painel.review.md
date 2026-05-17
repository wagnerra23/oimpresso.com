---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Painel
file: resources/js/Pages/Jana/Painel.tsx
charter_present: true
charter_file: Painel.charter.md
runbook_present: false
runbook_notes: sem RUNBOOK-painel.md específico em memory/requisitos/Jana/
append_only: true
---

# Review estática — `Jana/Painel.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · charter ✓
- US-JANA-PAINEL-001 (Onda A1) + CYCLE-06 goal #4 (Jana V2 demo)
- Visual canon: `prototipo-ui/cowork-snapshot/chat-jana.jsx` (491 ln IIFE window.JanaCockpit)
- Status: `onda-a1-esqueleto` (mock data; sub-components + CSS canon + queries reais nas Ondas A2-C)
- Payload: BriefData (greeting/paragraphs/chips) + KpiData + AnaliseData + AcaoData + PainelPayload

## Riscos Tier 0

1. **MWART/M2 — SEM RUNBOOK**: hook bloquear ou `/mwart-override`.
2. **MOCK STATUS/M2 — `onda-a1-esqueleto` ainda mock**: validar transição A2-C pra real queries (Tier 0 risk se mock em prod sem flag).
3. **VISUAL-CANON/L3 — Cowork snapshot IIFE 491 lin**: gigante - paridade visual difícil verificar via análise estática.
4. **CHARTER/L4 — Drift round 2** (esqueleto evolui).
5. **FAB?/L4 — Painel é cockpit-like sem FabJana?**: confirmar UX consistency.

## Top 5 recomendações

1. P0 — Criar `RUNBOOK-painel.md` (Onda A1 spec) ou `/mwart-override`.
2. P0 — Flag `painel_mock=true` env-default — banner "modo demo" se ativo (evitar deploy prod sem real data).
3. P1 — Sub-componentes Ondas A2-C + CSS canon transition planner.
4. P2 — Charter drift round 2.
5. P3 — Validar consistency FabJana ou alternativa.
