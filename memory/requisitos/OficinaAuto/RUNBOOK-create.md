# RUNBOOK — Pages OficinaAuto Create (V0 scaffold)

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN) — V0 placeholder
> **Status:** scaffold
> **Refs:** ADR 0137, SPEC.md US-OFICINA-001

## Telas cobertas

1. `resources/js/Pages/OficinaAuto/Vehicles/Create.tsx` — form criação de veículo
2. `resources/js/Pages/OficinaAuto/ServiceOrders/Create.tsx` — form criação de OS

## Contrato (V0)

### Vehicles/Create.tsx
- **Props:** `vehicleTypes: Record<string, string>`
- **Form fields obrigatórios:** `plate`, `vehicle_type` (default 'automovel')
- **Opcionais:** secondary_plate (Vargas case cavalo+reboque), chassis, secondary_chassis, year fields, renavam, engine, mileage, fuel, color, notes, contact_id
- **Submit:** POST `/oficina-auto/veiculos` (Inertia)
- **Redirect on success:** `/oficina-auto/veiculos/{id}`

### ServiceOrders/Create.tsx
- **Props:** `vehicles: Vehicle[]` (dropdown), `statuses: Record<string, string>`
- **Form fields obrigatórios:** vehicle_id, status (default 'aberta')
- **Opcionais:** transaction_id, mileage_at_service, entered_at (default now), expected_completion, notes
- **Submit:** POST `/oficina-auto/ordens-servico`

## Não-goals V0

- ❌ NÃO integrar API CRLV (US-AUTO-002, Sprint 5+)
- ❌ NÃO autocomplete placa via SerPro
- ❌ NÃO upload de foto/CRLV scan (Sprint 4 — US-AUTO-012)
- ❌ NÃO multi-mecânico (Sprint 3 — US-AUTO-006)

## Validação backend (canon)

Server-side authoritative — todas regras no `VehicleController::store` e `ServiceOrderController::store`. Frontend só facilita UX.
