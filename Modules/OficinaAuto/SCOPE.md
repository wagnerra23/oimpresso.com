---
module: OficinaAuto
purpose: "Vertical oficinas automotivas BR (mecânica geral, recapagem, locação caçamba). Schema próprio veículo+placa (≠ Repair que usa serial_no+device neutro)."
contains:
  - "DataController"
  - "InstallController"
  - "ProducaoOficinaController"
  - "Public/AprovacaoOsController — endpoint público SEM auth pra cliente aprovar/rejeitar OS via link WhatsApp + PIN (US-OFICINA-006). Token HMAC carrega business_id assinado. Throttle:30,1 + lockout 5 tentativas PIN."
  - "ServiceOrderController"
  - "VehicleController"
  - "ServiceOrderFsmActionController (app/Http/Controllers — shared FSM canon, espelha SaleFsmActionController)"
not_contains:
  - "Kanban shared infra → Modules/Repair (consumido opcionalmente)"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Núcleo transactions/contacts → UltimatePOS core"
trust_required: L2
owner: wagner
permission_prefix: oficina_auto.*
charter_adr: 0137
related_adrs:
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0137-modules-oficinaauto-qualificada
  - 0093-multi-tenant-isolation-tier-0
  - 0129-state-machine-canonica-fsm-rbac
url_prefixes:
  - /oficina-auto/*
drift_alerts: []
---

# Modules/OficinaAuto — vertical oficinas automotivas BR

> ADR mãe: [0137](../../memory/decisions/0137-modules-oficinaauto-qualificada.md) (amends [0121](../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7)
> SPEC: [memory/requisitos/OficinaAuto/SPEC.md](../../memory/requisitos/OficinaAuto/SPEC.md)
> Charter: [memory/requisitos/OficinaAuto/OficinaAuto.charter.md](../../memory/requisitos/OficinaAuto/OficinaAuto.charter.md)
> **Status: 🟡 em construção (V0)** — sinal qualificado por Vargas + Martinho (ADR 0137)
> CNAEs: 4520-0/01 (mecânica geral) · 2212-9/00 (recapagem) · 4581-4/00 (locação caçamba)
> Concorrentes: Ultracar, Oficina Integrada, Onmotor, Manager Full

## Estado V0 (Sprint 1 — US-OFICINA-001)

Scaffold completo nWidart com:

- **8 peças canônicas** (RUNBOOK-criar-modulo): module.json, composer.json, Config, ServiceProvider + RouteServiceProvider, DataController, InstallController, Routes/web.php
- **2 migrations:** `vehicles` (multi-placa nullable — Vargas case) + `service_orders` (FK vehicle + transaction nullable)
- **2 Eloquent Models** com global scope multi-tenant Tier 0 (ADR 0093)
- **2 Controllers CRUD** (Inertia render — VehicleController + ServiceOrderController)
- **8 Pages Inertia** (Index/Create/Show/Edit × Vehicles + ServiceOrders) com AppShellV2
- **3 Pest tests** (Vehicle CRUD + multi-tenant isolation + ServiceOrder CRUD)
- **9 permissões Spatie** (access + 4 vehicle.* + 4 service_order.*)
- **Sidebar entry** "Oficina Auto" dropdown (Veículos + Ordens de Serviço)

## Sprint 2+ — backlog ativo (em SPEC.md)

| US | Descrição | Estado |
|---|---|---|
| **US-OFICINA-001** | Scaffold V0 (este PR) | em curso |
| **US-OFICINA-002** | Importer Firebird `EQUIPAMENTO_VEICULO` → `vehicles` (Martinho 91 vehs) | backlog |
| **US-OFICINA-003** | FSM canônica OS (3 estados Simples + 5 estados Complexa) — ADR 0129 | backlog |
| **US-OFICINA-004** | UI Kanban OS Vargas — multi-item + multi-mecânico | backlog V1 |

## Não-goals (V0)

- ❌ NÃO implementar FSM antes de US-OFICINA-003 (status string livre na V0)
- ❌ NÃO implementar importer Firebird antes de US-OFICINA-002
- ❌ NÃO implementar Kanban antes de US-OFICINA-004 (V1)
- ❌ NÃO substituir núcleo UltimatePOS (transactions/contacts continuam canônicos)
- ❌ NÃO duplicar Modules/Repair shared infra — OficinaAuto usa vocabulário/schema próprio (placa+veículo) onde Repair usa schema neutro (serial_no+device)
