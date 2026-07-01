---
page: /oficina-auto/veiculos/{id}
component: resources/js/Pages/OficinaAuto/Vehicles/Show.tsx
owner: wagner
status: draft
last_validated: "2026-05-31"
parent_module: OficinaAuto
related_us: [US-OFICINA-001, US-OFICINA-017]
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0093-multi-tenant-isolation-tier-0
  - 0110-tipografia-canon-h1-subtitle
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
charter_version: 1
---

# Page Charter — /oficina-auto/veiculos/{id}

> **Status:** draft (V0 scaffold). Detalhe de um veículo do CLIENTE da oficina
> (sub-vertical 4 mecânica pesada caminhão basculante — Martinho LIVE prod, ADR 0194):
> dados de identificação + situação atual + histórico de OS.
>
> `status: draft` — Wagner aprova Non-Goals + Anti-hooks antes de `live`
> (skill `charter-write`).

## Mission

Dar ao mecânico/atendente uma visão única do veículo: dados de identificação +
situação atual (`current_status` via badge canon) + histórico de ordens de serviço,
com caminho direto pra abrir uma nova OS daquele veículo.

## Goals — Features (faz)

- `<PageHeader>` com placa + ações (Voltar · Nova OS · Editar).
- Badge de situação atual via `VehicleStatusBadge` canon (não string solta).
- Card "Dados do veículo" (placa, chassi, renavam, ano, cor, motor, KM, etc).
- Histórico de OS com status canon via `ServiceOrderStatusBadge`, cada linha
  linkando pro detalhe da OS.
- CTA "Nova OS" no header, pré-preenchendo o veículo
  (`/oficina-auto/ordens-servico/create?vehicle_id=…`).
- Multi-tenant Tier 0 — Policy `view` + global scope business_id.

## Non-Goals — Features (NÃO faz)

- Editar dados do veículo nesta tela (cadastro vive em Edit).
- Transição de FSM do veículo aqui (mudança de `current_status` é efeito de OS,
  não ação manual nesta tela).
- Métricas/agregados financeiros por veículo (fora de escopo do detalhe).

## UX Targets

- Detalhe em 2 colunas (Dados | Histórico) — pattern espelha ServiceOrders/Show.
- Header com ações agrupadas — PageHeader canon (`actions`).
- Status sempre via badge canon; nunca string solta nem cor crua.
- Cores só tokens (`text-muted-foreground`, `bg-card`, variantes de badge).

## UX Anti-patterns

- NÃO recriar badge de status — reusar `_components/VehicleStatusBadge` (default
  export) e `ServiceOrders/_components/ServiceOrderStatusBadge` (default export).
- NÃO usar `#hex`/`oklch`/`style` de cor inline.
- NÃO usar variante de Badge fora de `default|secondary|destructive|outline`.
- NÃO assumir prop/rota sem ler o `VehicleController::show` + entity `Vehicle`.

## Contrato de dados (props)

`vehicle` (Eloquent `Vehicle` + relation `serviceOrders` carregada no controller):
`{ id, plate, secondary_plate, chassis, secondary_chassis, vehicle_type,
manufacture_year, model_year, renavam, engine, mileage_at_entry, fuel_type,
color, notes, legacy_id, current_status, service_orders[] }`
`service_orders[]`: `{ id, status, entered_at, notes }`

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php)
- [Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php)

## Refs

- [SPEC.md US-OFICINA-001](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [ADR 0137 §"Escopo arquitetural V0"](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0093 multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
