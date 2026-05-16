---
page: /oficina-auto/vehicles
component: resources/js/Pages/OficinaAuto/Vehicles/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-16
parent_module: OficinaAuto
related_adrs: [0137, 0093, 0110]
tier: A
charter_version: 1
---

# Page Charter — /oficina-auto/vehicles

> **Status:** live (V0). CRUD da frota — veículos da oficina (caminhões, carros, caçambas, equipamentos).

## Mission

Cadastro-fonte único de veículos pra o módulo — atendente busca por placa antes de abrir OS, gerente vê histórico de OS por vehicle, importer Firebird popula tabela em massa pra cliente legacy migrado.

## Goals — Features (faz)

- `<PageHeader>` "Veículos" + botão Criar + ação "Importar do Firebird" (artisan trigger US-OFICINA-002)
- Listagem com filtros: vehicle_type (automovel/caminhao/cacamba/equipamento), plate (busca parcial), contact (dono)
- Coluna placa com **componente MercosulPlate** (visual fiel padrão BR)
- Coluna OS abertas (count) — clique vai pra Service Orders filtrado por vehicle_id
- Coluna `legacy_id` (visível apenas pra superadmin) — rastreabilidade pós-importer Firebird
- Soft delete (deleted_at) — preserva histórico OS mesmo após "remover" vehicle
- Multi-tenant Tier 0 — dados scopados business_id
- Inertia::defer em count de OS por vehicle (aggregated query)

## Non-Goals — Features (NÃO faz)

- Editar contact (dono) inline — vai pra Show/Edit
- Excluir hard delete (canon = soft delete pra preservar OS)
- Importer Firebird inline (canon = artisan `officeimpresso:import-vehicles`)
- Gestão de manutenção preventiva agendada (futuro V1)

## UX Targets

- p95 first-paint < 700ms (50 veículos paginados)
- Placa Mercosul render fiel mesmo em listagem densa
- Filtro placa responsivo < 200ms (debounce 250ms)

## UX Anti-patterns

- Placa em texto puro sem componente Mercosul (canon = `<MercosulPlate>` shared)
- Excluir sem confirm dialog
- Mostrar `legacy_id` pra usuário comum (apenas superadmin debug)
- Eager count(orders) em todas linhas sem defer (N+1)

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php)
- [Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php)

## Refs

- [SPEC.md US-OFICINA-001 + US-OFICINA-002 importer](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [ADR 0137 §"Escopo arquitetural V0"](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0093 multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
