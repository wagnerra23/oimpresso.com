# RUNBOOK — Pages OficinaAuto Show (V0 scaffold)

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN) — V0 placeholder
> **Status:** scaffold
> **Refs:** ADR 0137, SPEC.md US-OFICINA-001

## Telas cobertas

1. `resources/js/Pages/OficinaAuto/Vehicles/Show.tsx` — detalhe veículo + histórico OS
2. `resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx` — detalhe OS

## Contrato (V0)

### Vehicles/Show.tsx
- **Props:** `vehicle: Vehicle` (com `serviceOrders` eager loaded)
- **Layout:** 2 colunas — campos veículo (esquerda) + lista compacta OS deste veículo (direita)
- **Ações:** Editar / Excluir (permissões `oficinaauto.vehicle.update|delete`)

### ServiceOrders/Show.tsx
- **Props:** `order: ServiceOrder` (com `vehicle` eager loaded)
- **Layout:** card com status, datas (entered/expected/completed/delivered), notes, vehicle info linked

## Não-goals V0

- ❌ NÃO timeline visual de histórico (US-AUTO-003 — Sprint 3)
- ❌ NÃO export PDF "passaporte do veículo" (Sprint 4)
- ❌ NÃO seção de fotos antes/depois (Sprint 5)
