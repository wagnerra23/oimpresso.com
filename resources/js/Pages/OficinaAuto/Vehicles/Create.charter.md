---
page: /oficina-auto/veiculos/create
component: resources/js/Pages/OficinaAuto/Vehicles/Create.tsx
related_us: [US-OFICINA-001]
page_id: oficina-auto-veiculos-create
owner: wagner
status: live
last_validated: "2026-06-09"
parent_module: OficinaAuto
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0137-modules-oficinaauto-qualificada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: B
charter_version: 2
---

# Page Charter — /oficina-auto/veiculos/create

> **Charter retroativo (2026-05-31):** v1 baseline scaffold V0 US-OFICINA-001. Espelha codigo EXISTENTE de `Create.tsx` apos `@memcofre` header comment. Criado junto da restauracao de paridade Edit<->Create (mesmos 13 campos, DS Select/Textarea, erros em todos os campos). Append-only — futuras mudancas viram v2. Gemeo de `Edit.charter.md` (mesmo conjunto de campos, diferenca = POST vs PUT e defaults vazios).
>
> **v2 (2026-06-09 · decisao Wagner):** adiciona **consulta de placa** (digita placa → busca dados tecnicos do veiculo e auto-preenche o form). O Non-Goal v1 "NAO consultar APIs externas" era explicitamente "feature pos-V0" — agora promovido. Escopo travado em **SO DADOS TECNICOS** (marca/modelo/ano/cor/combustivel/chassi/renavam): o **proprietario (PII de terceiro) NAO e consultado nem armazenado** (decisao Wagner 2026-06-09 — remover PII do proprietario). Adapter agnostico (driver `stub` padrao, `http` pluga fornecedor real via .env — key no Vaultwarden). NAO so paridade com Edit.tsx neste ponto (Edit nao tem o botao Buscar).

## Mission

Cadastrar novo veiculo (scaffold V0 US-OFICINA-001) em form simples modo FOCO (sem SubNav) — atendente registra identificacao (placa Mercosul principal + secundaria, chassi principal + secundario, tipo, ano fabricacao/modelo, RENAVAM, motor, KM entrada, combustivel, cor) + notes antes de abrir OS. Save unico via `useForm.post()` Inertia retornando pro Show do veiculo criado. Suporta sub-vertical 4 caminhao basculante ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)) com campos `secondary_plate` + `secondary_chassis` (caminhao + carreta).

## Goals

- **G1.** PageHeader sem tabs ("Novo veiculo") em modo FOCO + acao `Voltar` ghost.
- **G2.** Form com 13 campos (vazios, `vehicle_type` default `automovel`) via `useForm` — PARIDADE EXATA com Edit.tsx:
  - `plate` input (placa principal, uppercase auto, maxLength 10, required)
  - `secondary_plate` input (placa secundaria caminhao + carreta — sub-vertical 4 ADR 0194)
  - `chassis` + `secondary_chassis` inputs (maxLength 30)
  - `vehicle_type` Select DS compound (opcoes vindas de `vehicleTypes` prop)
  - `manufacture_year` + `model_year` inputs number (min 1900 / max 2100)
  - `renavam` input (maxLength 11)
  - `engine` input (maxLength 50)
  - `mileage_at_entry` input number (min 0)
  - `fuel_type` input (maxLength 30)
  - `color` input (maxLength 30)
  - `notes` Textarea DS
- **G3.** Validation errors inline em TODOS os campos (`errors.<campo>`) + `aria-invalid` + foco/scroll automatico no 1o campo invalido apos resposta do servidor (`FIELD_ORDER`).
- **G4.** Footer com botoes "Cancelar" outline (volta pra Index) + "Salvar veiculo" primary (disabled durante `processing`).
- **G5. (v2)** Botao "Buscar" ao lado da `plate` (+ Enter no campo) chama `POST /oficina-auto/veiculos/consulta-placa` (AJAX, throttle 20/min, permission `oficinaauto.vehicle.create`) e auto-preenche os campos tecnicos retornados (`manufacture_year`/`model_year`/`color`/`fuel_type`/`chassis`/`engine`/`renavam`). Marca/modelo (sem coluna V0) vao pra `notes` so se vazio. Feedback inline `role=status` (sucesso emerald / nao-encontrado muted / erro destructive). Loading via `Loader2` spinner. Driver agnostico (`stub` default).

## Non-Goals

- NAO criar veiculos em batch — uma operacao por vez (importer em massa = artisan `officeimpresso:import-vehicles`, US-OFICINA-002).
- NAO consultar nem armazenar dados do PROPRIETARIO (nome/CPF) na consulta de placa — escopo v2 e SO dados tecnicos do veiculo (decisao Wagner 2026-06-09 — sem PII de terceiro). Owner permanece vinculado manualmente a um Contact em fluxo proprio.
- NAO persistir resultado da consulta automaticamente — auto-preenche o form e o operador confere/salva (Save unico continua sendo a fonte da verdade).
- NAO permitir upload de foto do veiculo no Create V0 — Gap futuro via `HasArquivos` trait.
- NAO vincular contact (dono) inline no Create V0 — relacao tratada em fluxo proprio.
- NAO setar `business_id` no frontend — Model::creating hook seta automaticamente (Tier 0 [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

## UX Targets

- **p95 first-paint:** < 600ms (form simples sem `Inertia::defer`, so prop `vehicleTypes` leve).
- **p95 save round-trip:** < 400ms (single POST).
- **Mobile 360px:** form usavel em 1 col stack (max-w-3xl mx-auto + grid colapsa via Tailwind responsive).
- **Placa auto-uppercase:** transformacao `onChange` previne typos low-end Android Martinho clientes.
- **Erro guiado:** foco + scroll-into-view no 1o campo invalido evita usuario "perder" o erro em form longo.

## Automation Anti-hooks

- NAO cria veiculo em outro `business_id` (multi-tenant Tier 0 [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Model::creating hook + global scope enforce). Cache da consulta de placa e namespeada por `business_id` (um tenant nunca ve resultado cacheado de outro).
- A consulta de placa (v2) NAO traz proprietario (PII de terceiro) — so dados tecnicos (decisao Wagner 2026-06-09). Custo externo e opt-in (driver `stub` default sem rede; `http` so quando fornecedor plugado via .env, key no Vaultwarden).
- NAO loggar a placa em claro nem payload `notes`/`chassis`/`renavam` em telemetria (PII — span Otel so com `plate_prefix` 3 chars; excecoes redacionadas via PiiRedactor).
- NAO redireciona pra rota fora `/oficina-auto/veiculos/{id}` apos Save (Inertia `post` retorna pro Show via controller `store`).

## Sub-components

- `@/Components/shared/PageHeader` (header modo FOCO sem tabs — props `title`/`description`/`icon`/`action`)
- `@/Components/ui/{Button,Input,Label}` (shadcn primitives)
- `@/Components/ui/select` (Select compound DS — Trigger/Value/Content/Item)
- `@/Components/ui/textarea` (Textarea DS)
- `@/Layouts/AppShellV2` (shell canonico)

## Refs

- [ADR 0093 Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 MWART canonico unico caminho](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0137 Modules OficinaAuto qualificada](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0194 Dominio Martinho mecanica pesada](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- RUNBOOK: `memory/requisitos/OficinaAuto/RUNBOOK-create.md` (compartilhado com ServiceOrders create — pode separar futuro)
- Charter gemeo: `resources/js/Pages/OficinaAuto/Vehicles/Edit.charter.md` (mesmo conjunto de 13 campos)

@see Modules/OficinaAuto/Http/Controllers/VehicleController.php (action `create` + `store` + `consultaPlaca` v2)
@see Modules/OficinaAuto/Entities/Vehicle.php (model com business_id scope)
@see Modules/OficinaAuto/Services/VehicleLookupService.php (orquestra consulta de placa — adapter agnostico, cache por business_id, span Otel PII-safe)
@see Modules/OficinaAuto/Services/PlacaLookup/ (PlacaProvider interface + StubPlacaProvider + HttpPlacaProvider + PlacaLookupResult DTO)
