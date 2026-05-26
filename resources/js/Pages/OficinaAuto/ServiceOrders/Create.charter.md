---
page: /oficina-auto/service-orders/create
component: resources/js/Pages/OficinaAuto/ServiceOrders/Create.tsx
owner: wagner
status: live
last_validated: 2026-05-26
parent_module: OficinaAuto
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0093-multi-tenant-isolation-tier-0
  - 0110-tipografia-canon-h1-subtitle
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
charter_version: 2
---

# Page Charter — /oficina-auto/service-orders/create

> **Status:** live (V0). Formulário de abertura de OS — `order_type` configurável.
>
> **Sub-vertical 4 ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** Martinho biz=164 LIVE prod usa principalmente `order_type='manutencao'` (sub-vertical 4 mecânica pesada caminhão basculante). Campos condicionais de locação (`daily_rate`/`expected_return_date`/`delivery_address`) preservados nullable como schema sub-vertical 3 hipotético sem cliente real ancorado. Toggle UI mantido por compat — review_trigger M6+ caso cliente real de locação container surgir.

## Mission

Permitir abertura rápida de OS pelo atendente em ≤ 30s — escolher vehicle (existente ou criar inline), order_type, dados mínimos do trabalho, e abrir em status `aberta` ou já avançar pra `orcamento` se manutenção complexa.

## Goals — Features (faz)

- `<PageHeader>` h1 "Nova Ordem de Serviço" + back link
- Toggle `order_type`: manutenção (default) vs locação (caçamba/equipamento)
- Autocomplete vehicle por placa (Mercosul + legacy) — se não existe, botão "Criar veículo novo" abre drawer
- Autocomplete contact (cliente) — required (Martinho atende caminhões basculantes de transportadoras/construtoras de terceiros — sub-vertical 4 mecânica pesada ADR 0194)
- Campos condicionais por order_type:
  - **Manutenção:** mileage_at_service, notes, expected_completion
  - **Locação:** delivery_address, daily_rate, expected_return_date
- Validação client-side: campos required por order_type
- Submit → POST /oficina-auto/service-orders → redirect Show
- Multi-tenant Tier 0 — vehicle_id deve pertencer ao business atual (server-side double-check)

## Non-Goals — Features (NÃO faz)

- Criar items (peças/serviços) — pós-criação na Edit page ou drawer
- Atribuir mecânico — fica na transição FSM `aberta → em_servico`
- Gerar NFe automática — fica em status `entregue`/`concluida` via Modules/NfeBrasil action

## UX Targets

- p95 submit response < 500ms
- Autocomplete vehicle responde < 150ms (debounce 250ms)
- Validação inline antes de submit (sem round-trip)
- Mobile-friendly (atendente usa tablet 1024px Vargas / mobile 360px Martinho)

## UX Anti-patterns

- Submit sem CSRF (canon = Inertia useForm)
- Campos sempre visíveis ignorando order_type (clutter)
- Autocomplete sem debounce (flood backend)
- Toast genérico "Erro" sem detalhe acionável

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php) (cria OS Simples + Complexa)
- [Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php) (vehicle autocomplete depende)

## Refs

- [SPEC.md US-OFICINA-001](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [RUNBOOK-create.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-create.md)
- [ADR 0137 §"Escopo arquitetural V0"](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
