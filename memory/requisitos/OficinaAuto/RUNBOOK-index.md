# RUNBOOK — Pages OficinaAuto Index (V0 scaffold)

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN) — V0 placeholder
> **Status:** scaffold — UX final entra Sprint 2+ (US-OFICINA-002 importer)
> **Refs:** ADR 0137 §"Escopo arquitetural V0", SPEC.md US-OFICINA-001

## Telas cobertas por este RUNBOOK

Hook `block-mwart-violation.ps1` matcha pelo nome do arquivo. Dois Index.tsx no módulo:

1. `resources/js/Pages/OficinaAuto/Vehicles/Index.tsx` — lista de veículos
2. `resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx` — lista de OS

Ambos usam pattern AppShellV2 + tabela simples shadcn + filtros básicos.

## Contrato (V0 scaffold)

### Vehicles/Index.tsx
- **Props:** `vehicles: Vehicle[]` (limit 100), `filters: { q: string }`
- **Componentes:** `AppShellV2` + `Card` + tabela manual (placeholder — DataTables/TanStack chega Sprint 3+)
- **Ações:** botão "+ Novo veículo" → `/oficina-auto/veiculos/create`
- **Filtro:** input `q` → busca placa/secondary_plate/chassis (LIKE)
- **Multi-tenant:** Global scope no Model filtra automaticamente (ADR 0093)

### ServiceOrders/Index.tsx
- **Props:** `orders: ServiceOrder[]` com `vehicle` eager loaded, `filters: { status: string }`
- **Componentes:** AppShellV2 + tabela placeholder
- **Filtro:** dropdown status (8 opções V0; FSM canônica entra US-OFICINA-003 ADR 0129)
- **Multi-tenant:** Global scope automático

## Não-goals V0

- ❌ NÃO implementar Kanban drag-drop (Sprint 4 — US-OFICINA-004 Vargas)
- ❌ NÃO implementar busca avançada / FIPE / CRLV (Sprint 5+)
- ❌ NÃO implementar export PDF "passaporte do veículo"
- ❌ NÃO implementar bulk actions

Foco V0: **arquitetura correta + multi-tenant Tier 0 enforce + Pest verde**. UX final espera primeiro cliente piloto reportar (ADR 0105 + ADR 0106).

## Pegadinhas

- Ziggy NÃO disponível — use template literal `` `/oficina-auto/veiculos/${id}` `` (skill criar-modulo §Pegadinhas)
- AppShellV2 lê shell.menu via Inertia shared props — não passar layout manual
- Status como string (não enum) — V0 deixa livre, validação no Controller via `in:` regra
