---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/Vehicles/Show
file: resources/js/Pages/OficinaAuto/Vehicles/Show.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-show.md
append_only: true
---

# Review estática — `OficinaAuto/Vehicles/Show.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader ✓ · RUNBOOK ✓ · **charter AUSENTE**
- Inclui `service_orders: ServiceOrder[]` (histórico OS do veículo)
- Sem `Deferred` — payload pode crescer com histórico longo
- Show simples — sem useForm/useState
- `legacy_id: string | null` presente (compat legacy import)

## Riscos Tier 0

1. **CHARTER/M2 — AUSENTE**: criar.
2. **PERF/M2 — `service_orders[]` EAGER**: veículo antigo pode ter 100+ OSs. Deferir + paginar.
3. **PII/L3 — chassis/renavam exibidos**: permission gate `@can`.
4. **HISTORICO/L3 — `ServiceOrder` simplificado** (só id+status+entered_at+notes) — sem link drill-down?
5. **RUNBOOK/L4 — Compartilhado** com ServiceOrders Show.

## Top 5 recomendações

1. P0 — Criar `Vehicles/Show.charter.md`.
2. P0 — Deferir `service_orders` + paginar (últimas 20).
3. P1 — Drill-down: `<Link href={\`/oficina-auto/ordens-servico/\${os.id}\`}>` em cada OS.
4. P2 — PII mask chassis/renavam com botão "revelar" + audit.
5. P3 — Charter drift round 2.
