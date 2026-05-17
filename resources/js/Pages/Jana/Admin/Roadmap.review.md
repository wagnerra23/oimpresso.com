---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Jana/Admin/Roadmap
file: resources/js/Pages/Jana/Admin/Roadmap.tsx
charter_present: true
charter_file: Roadmap.charter.md
runbook_present: false
runbook_notes: sem RUNBOOK-roadmap.md específico em memory/requisitos/Jana/
append_only: true
---

# Review estática — `Jana/Admin/Roadmap.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+Card+Sheet+Badge ✓ · charter ✓
- Onda 5 V1 + ADR 0070 (Jira-style) + ADR 0093 (multi-tenant) + ADR 0110 (Cockpit V2)
- **Dependência externa**: `@svar-ui/react-gantt` MIT v2.6.x — nova dependência registrada via charter?
- Tipos: Priority p0-p3 + TaskStatus 7 enum
- `useState`/`useMemo`/`useCallback` ✓
- Status: `draft` (Wagner aprova charter pra ir pra live)

## Riscos Tier 0

1. **MWART/M2 — SEM RUNBOOK**: hook bloquear ou `/mwart-override` (charter ✓ mas RUNBOOK ausente).
2. **DEP/M2 — `@svar-ui/react-gantt` MIT**: ADR de dependência? Bundle size 200kb+? Validar.
3. **PERF/L3 — Gantt render N tasks**: se cycle tem 50+ tasks, performance Gantt timeline.
4. **STATUS/L3 — `draft`**: Wagner não aprovou — Pest GUARD não bloqueia merge mas charter diz "draft".
5. **CHARTER/L4 — Drift round 2**.

## Top 5 recomendações

1. P0 — Criar `RUNBOOK-jana-roadmap.md`.
2. P0 — ADR ou justificativa pra `@svar-ui/react-gantt` (bundle + license + alternatives).
3. P1 — Roadmap live: Wagner aprovar charter → status `live`.
4. P2 — Lazy-load Gantt component (`React.lazy` se >50kb).
5. P3 — Charter drift round 2.
