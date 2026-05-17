---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/ServiceOrders/Show
file: resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx
charter_present: true
charter_file: Show.charter.md
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-show.md
append_only: true
---

# Review estática — `OficinaAuto/ServiceOrders/Show.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader ✓ · charter+RUNBOOK ✓ (compliance MWART completo)
- Scaffold V0 minimal — só `order` + `vehicle nested`
- Sem `Deferred`, sem useForm (read-only)
- Sem FSM action panel visível — pode estar deferido ou ausente em V0

## Riscos Tier 0

1. **FSM/M2 — Sem FSM Panel visível**: charter prevê ações (ADR 0143)? Se não tem painel, é gap funcional V1.
2. **PII/L3 — Mileage + plate + chassis (vehicle)** visíveis em Show — verificar permissão view (role-based).
3. **PERF/L4 — Payload pequeno OK**, mas se evoluir pra incluir `transactions[]`, `parts[]`, defer.
4. **RUNBOOK/L4 — Cobertura compartilhada `RUNBOOK-show.md`** (Vehicles+ServiceOrders).
5. **CHARTER/L4 — Drift round 2**.

## Top 5 recomendações

1. P1 — Roadmap V1: incluir FSM Action Panel (`ServiceOrderFsmActionPanel` já existe em `_components/`).
2. P1 — Permission gate: `@can('oficina.servicos.show')` ou similar.
3. P2 — Quando adicionar `transactions`/`parts`, deferir.
4. P2 — RUNBOOK SoC: cobertura compartilhada documentada.
5. P3 — Charter drift round 2.
