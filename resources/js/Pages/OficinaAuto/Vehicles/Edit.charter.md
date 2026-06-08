---
page: /oficina-auto/veiculos/{id}/edit
component: resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx
page_id: oficina-auto-veiculos-edit
owner: wagner
status: live
last_validated: "2026-05-26"
parent_module: OficinaAuto
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0137-modules-oficinaauto-qualificada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: B
charter_version: 1
---

# Page Charter — /oficina-auto/veiculos/{id}/edit

> **Charter retroativo Gap 5 (2026-05-26):** v1 baseline scaffold V0 US-OFICINA-001. Espelha codigo EXISTENTE de `Edit.tsx` apos `@memcofre` header comment. Append-only — futuras mudancas viram v2.

## Mission

Editar dados de veiculo cadastrado (scaffold V0 US-OFICINA-001) em form simples modo FOCO (sem SubNav) — usuario atualiza identificacao (placa Mercosul principal + secundaria, chassi principal + secundario, tipo, ano fabricacao/modelo, RENAVAM, motor, KM entrada, combustivel, cor) + notes. Save unico via `useForm.put()` Inertia retornando pro Show. Suporta sub-vertical 4 caminhao basculante ([ADR 0194](../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)) com campos `secondary_plate` + `secondary_chassis` (caminhao + carreta).

## Goals

- **G1.** PageHeader sem tabs ("Editar {plate}") em modo FOCO + acao `Voltar` ghost.
- **G2.** Form com 13 campos pre-preenchidos via `useForm`:
  - `plate` input (placa principal, uppercase auto, maxLength 10, required)
  - `secondary_plate` input (placa secundaria caminhao + carreta — sub-vertical 4 ADR 0194)
  - `chassis` + `secondary_chassis` inputs
  - `vehicle_type` select (opcoes vindas de `vehicleTypes` prop)
  - `manufacture_year` + `model_year` inputs
  - `renavam` input
  - `engine` input
  - `mileage_at_entry` input number
  - `fuel_type` input
  - `color` input
  - `notes` textarea
- **G3.** Validation errors inline per field (`errors.plate`).
- **G4.** Footer com botoes "Cancelar" outline (volta pro Show) + "Salvar alteracoes" primary (disabled durante `processing`).

## Non-Goals

- NAO mudar `plate` apos veiculo ter OS aberta (FSM tracks por `vehicle_id` — server-side enforce, Edit nao impede mas backend rejeita se houver OS ativa).
- NAO editar veiculos em batch — uma operacao por vez.
- NAO consultar APIs externas (DENATRAN/Senatran/Sintegra) pra preenchimento automatico — feature pos-V0.
- NAO permitir upload de foto do veiculo no Edit V0 — Gap 1 futuro via `HasArquivos` trait.
- NAO incluir historico de OS do veiculo inline — historico vive no Show via tab/section.
- NAO permitir mudanca de `business_id` em hipotese alguma (Tier 0 [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

## UX Targets

- **p95 first-paint:** < 600ms (form simples sem `Inertia::defer`, payload ja contem dados eager).
- **p95 save round-trip:** < 400ms (single PUT).
- **Mobile 360px:** form usavel em 1 col stack (max-w-3xl mx-auto + grid-cols-2 colapsa via Tailwind responsive).
- **Placa auto-uppercase:** transformacao `onChange` previne typos low-end Android Martinho clientes.

## Automation Anti-hooks

- NAO acessa veiculo de outro `business_id` (multi-tenant Tier 0 [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — global scope `Vehicle` model enforce).
- NAO consulta APIs externas (DENATRAN/Senatran) — sem custo externo, sem LGPD-extra-scope.
- NAO dispara recalculo de OS abertas em background ao mudar `mileage_at_entry`.
- NAO loggar payload `notes` em telemetria (texto livre, pode conter info sensivel cliente).
- NAO redireciona pra rota fora `/oficina-auto/veiculos/{id}` apos Save (Inertia `put` retorna pro Show via controller).

## Sub-components

- `@/Components/shared/PageHeader` (header modo FOCO sem tabs)
- `@/Components/ui/{Button,Input,Label}` (shadcn primitives)
- `@/Layouts/AppShellV2` (shell canonico)

## Refs

- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 MWART canonico unico caminho](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0137 Modules OficinaAuto qualificada](../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0194 Dominio Martinho mecanica pesada](../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- RUNBOOK: `memory/requisitos/OficinaAuto/RUNBOOK-edit.md` (compartilhado com ServiceOrders edit — pode separar futuro)
- Charter sibling: `resources/js/Pages/OficinaAuto/ServiceOrders/Edit.charter.md` (Tier A live)

@see Modules/OficinaAuto/Http/Controllers/VehicleController.php (action `edit` + `update`)
@see Modules/OficinaAuto/Models/Vehicle.php (model com business_id scope)
