---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/ServiceOrders/Create
file: resources/js/Pages/OficinaAuto/ServiceOrders/Create.tsx
charter_present: true
charter_file: Create.charter.md
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-create.md
append_only: true
---

# Review estática — `OficinaAuto/ServiceOrders/Create.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader ✓ · charter+RUNBOOK ✓
- `useForm` ✓ · scaffold V0 (US-OFICINA-001) — pequeno e enxuto
- `status: 'aberta'` default hardcoded — string livre (não enum)
- `vehicles: Vehicle[]` EAGER (small dataset OK V0)
- POST `/oficina-auto/ordens-servico` direto

## Riscos Tier 0

1. **STATUS/M2 — `status: 'aberta'` hardcoded + free string**: ADR 0143 FSM canon — `current_stage_id` deveria substituir. V0 OK pra scaffold, mas validar roadmap V1 migrar pra FSM.
2. **MWART/L3 — Charter+RUNBOOK ✓ mas RUNBOOK genérico `create.md`**: 1 RUNBOOK cobrindo `ServiceOrders/Create` + `Vehicles/Create`? Validar SoC.
3. **VALIDATION/L3 — `vehicle_id: ''`**: client-side enforcement vazio? Backend valida required mas UX feedback?
4. **PERMISSIONS/L4 — Sem check de roles aparente**: assumir middleware do controller.
5. **FORM/L4 — `transaction_id` opcional**: link com venda nfe-de-boleto-pago futuro (US-RB-044)?

## Top 5 recomendações

1. P1 — Roadmap V1: migrar `status` free string pra FSM pipeline (ADR 0143). Documentar débito.
2. P1 — `vehicle_id` required visual indicator + disabled submit se vazio.
3. P2 — RUNBOOK específico OU justificar agrupado (`RUNBOOK-create.md` cobre 2 telas).
4. P2 — Toast erro `errors.vehicle_id` mais visível.
5. P3 — Charter drift round 2.
