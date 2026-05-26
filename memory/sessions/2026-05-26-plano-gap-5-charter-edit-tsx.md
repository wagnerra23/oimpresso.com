---
title: "Plano Gap 5 — Charter Edit.tsx (MWART Gate soft warn → verde)"
date: "2026-05-26"
type: gap-plan
status: draft
gap_id: 5
modulo: OficinaAuto
us_relacionada: governance MWART
cliente: time interno
esforco_estimado: "1-2h IA-pair (fator 10x ADR 0106)"
roi: alto-governance-barato
bloqueia_demo: nao (soft warn aceita hoje)
---

# Plano Gap 5 — Charter Edit.tsx

## Contexto

MWART Process ([ADR 0104](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) eh o unico caminho de migracao Blade→Inertia. Gate verde exige `.charter.md` ao lado do `.tsx` (skill `charter-write` Tier B). Hoje as duas telas Edit do OficinaAuto rodam em **soft warn** — aceita-se sem charter mas alerta no PR.

## Identificacao QUAL Edit.tsx

Glob revelou **2 candidatos** em OficinaAuto:

1. `resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx` — editar OS (mais critico, Wave 5 PR #1631 adicionou section inline items embedded, modo FOCO sem SubNav)
2. `resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx` — editar veiculo (mais simples, scaffold V0 US-OFICINA-001)

**Outros Edit.tsx que NAO sao alvo deste gap:** ServiceOrders/Create.tsx, Vehicles/Create.tsx, Vehicles/Show.tsx, ProducaoOficina/Index.tsx — alguns ja tem charter (e.g. AprovacaoPublica.charter.md mergeada hoje PR #1627), outros nao precisam.

**Recomendacao Wagner aprova:** **AMBOS** sao alvo do Gap 5 (mas em 2 charters separados). ServiceOrders Edit primeiro (mais movimentado nas waves recentes), Vehicles Edit segundo.

## Research estado-da-arte (charter writing 2026)

Charter format canonico oimpresso ja maduro — exemplos high-tier:
- `resources/js/Pages/Cliente/Index.charter.md` (v7, status: live, parent_module: Cliente / Crm) — 170 linhas, append-only com Onda 1/2/3 PTDP
- `resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md` (Wave 4 PR #1627) — promovido draft→live no PR

Frontmatter required (schema enforced em `charter.schema.json`):
- `page:` rota
- `component:` path .tsx
- `owner:` wagner
- `status:` draft | live | superseded
- `last_validated:` YYYY-MM-DD
- `charter_version:` int
- `parent_module:` PascalCase
- `related_adrs:` lista slugs strings (NUNCA integers — licao 3.2 sessao 2026-05-26 4PRs)
- `tier:` A | B | C
- `page_id:` slug sem `/` (licao 3.5 sessao 2026-05-26 4PRs)

Secoes minimas:
- Mission (1 paragrafo)
- Goals (bullets)
- Non-Goals (bullets)
- UX Targets (p95 metrics)
- Automation Anti-hooks (bullets)
- Sub-components (lista files)
- Refs

## Arquivos a tocar

| Arquivo | Operacao | Notas |
|---|---|---|
| `resources/js/Pages/OficinaAuto/ServiceOrders/Edit.charter.md` | NOVO — charter Tier A page edit OS | Charter completo com section inline items embedded mode FOCO |
| `resources/js/Pages/OficinaAuto/Vehicles/Edit.charter.md` | NOVO — charter Tier B page edit veiculo (simpler) | Charter scaffold US-OFICINA-001 |
| `resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx` | NO EDIT — apenas leitura | Confirma charter bate com codigo |
| `resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx` | NO EDIT — apenas leitura | Confirma charter bate com codigo |

## Mini draft ServiceOrders/Edit.charter.md (esqueleto)

```yaml
---
page: /oficina-auto/ordens-servico/{id}/edit
page_id: oficina-auto-ordens-servico-edit
component: resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx
owner: wagner
status: live
last_validated: 2026-05-26
charter_version: 1
parent_module: OficinaAuto
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0137-modules-oficinaauto-qualificada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0182-pageheadertabs-canon-pattern-telas
  - 0190-primary-button-roxo-universal-295
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
---

# Page Charter — /oficina-auto/ordens-servico/{id}/edit

## Mission

Editar OS existente em **modo FOCO** (sem SubNav cluttering) — usuario foca em
campos basicos (veiculo, status, mileage, datas, notes) + section inline
"Itens da OS" embutida (Wave 5 US-OFICINA-005-bis PR #1631) com row + sheet
form 480px. Save via useForm Inertia put. Status segue FSM canonico ADR 0143.

## Goals

- PageHeader sem tabs ("Editar OS #{id}")
- Form 7 campos: vehicle_id select + transaction_id readonly + mileage_at_service input + status select (statuses prop) + 4 datetimes
- Notes textarea full-width
- Section inline "Itens da OS" (modo FOCO embedded — NAO modal):
  - ServiceOrderItemRow per item (icone tipo + qtd + valor unit + total + actions hover)
  - PageHeaderPrimary "Adicionar item" (roxo 295 ADR 0190)
  - ServiceOrderItemFormSheet shadcn Sheet 480px lateral
- Validation errors inline per field
- Botao "Salvar" primary + "Cancelar" (volta Show)

## Non-Goals

- ❌ Editar items batch
- ❌ Mudar veiculo apos started_at (FSM lock)
- ❌ Editar FSM status fora do PipelineStage (este Edit eh status livre legacy V0)
- ❌ Editar transaction_id (read-only, auto-faturado pelo Observer ADR 0192)
- ❌ Adicionar DVI inline (DVI vive no drawer ProducaoOficina, nao no Edit fullpage)

## UX Targets

- p95 first-paint < 600ms (form simples, sem Inertia::defer)
- p95 save round-trip < 400ms
- p95 abrir Sheet items < 200ms
- Mobile 360px: form usavel (1 col stack)

## Automation Anti-hooks

- ❌ NAO dispara EnviarLinkAprovacaoWhatsappJob no Edit (so quando Observer detecta status='orcamento' via Save)
- ❌ NAO auto-recalcula final_total no client — Observer faz server-side
- ❌ NAO acessa OS de outro business_id (ADR 0093 Tier 0)
- ❌ NAO permite delete item batch

## Sub-components

- `_components/ServiceOrderItemRow.tsx` (Wave 5)
- `_components/ServiceOrderItemFormSheet.tsx` (Wave 5)

## Refs

- ADR 0093 Multi-tenant Tier 0
- ADR 0143 FSM canon
- ADR 0192 Auto-faturar OS→Venda Observer
- ADR 0194 Dominio Martinho mecanica pesada
- PR #1631 Wave 5 US-OFICINA-005-bis (section inline items)
- RUNBOOK-edit.md
```

## Mini draft Vehicles/Edit.charter.md (esqueleto mais curto)

```yaml
---
page: /oficina-auto/veiculos/{id}/edit
page_id: oficina-auto-veiculos-edit
component: resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx
owner: wagner
status: live
last_validated: 2026-05-26
charter_version: 1
parent_module: OficinaAuto
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0137-modules-oficinaauto-qualificada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: B
---

# Page Charter — /oficina-auto/veiculos/{id}/edit

## Mission

Editar veiculo cadastrado (scaffold V0 US-OFICINA-001) — campos basicos placa
Mercosul + tipo + ano + chassi + renavam + motor + km + combustivel + cor + notes.

## Goals

- PageHeader sem tabs ("Editar veiculo {plate}")
- Form 13 campos
- Save via useForm Inertia put
- Volta para Show ou Index

## Non-Goals

- ❌ Mudar placa apos veiculo ter OS (lock — ADR FSM tracks por vehicle_id)
- ❌ Editar batch
- ❌ Upload foto veiculo (Gap 1 futuro via HasArquivos)

## UX Targets

- p95 < 600ms
- Mobile 360px usavel

## Automation Anti-hooks

- ❌ NAO acessa veiculo de outro business_id (ADR 0093)
- ❌ NAO consulta APIs externas (DENATRAN/Senatran)

## Refs

- ADR 0093
- ADR 0137
- RUNBOOK-edit.md (compartilhado com ServiceOrders edit — pode dividir futuro)
```

## Restricoes Tier 0 deste gap

1. **Sem code change `.tsx`** — APENAS criar charters. Skill `charter-write` puro-doc.
2. **Schema canon enforced** — frontmatter aspas em datas, slugs strings em related_adrs, page_id sem `/` (licoes 3.2 + 3.5 + 3.6 + 3.7 sessao 2026-05-26 4PRs).
3. **Append-only** — depois de mergear, mudancas viram `v2` append, NUNCA edit-in-place (regra MWART).
4. **Wagner aprova** — Tier A charter (ServiceOrders/Edit) precisa de aprovacao explicita Wagner. Tier B (Vehicles/Edit) PR padrao.
5. **NAO inventar features** — charter espelha codigo EXISTENTE de Edit.tsx, nao adiciona requisitos novos (eh governance retroativa).

## Mini-comparativo atual → target

| Aspecto | Hoje (soft warn) | Target Gap 5 (verde) |
|---|---|---|
| MWART Gate ServiceOrders/Edit | warn yellow "missing charter" | verde |
| MWART Gate Vehicles/Edit | warn yellow | verde |
| Skill `charter-fetch` (S4+) | retorna 404 | retorna spec rica |
| Reviewer onboarding | precisa ler 200 linhas de Edit.tsx | le 60-line charter |
| Append-only governance | N/A | v1 estabelece baseline |

## Esforco estimado

- Audit codigo ServiceOrders/Edit.tsx pra extrair Mission/Goals/Non-Goals: 30min
- Escrever ServiceOrders/Edit.charter.md (Tier A): 45min
- Audit codigo Vehicles/Edit.tsx: 15min
- Escrever Vehicles/Edit.charter.md (Tier B): 30min
- Run `charter-validate` skill local: 15min
- **Total: 2h IA-pair** (fator 10x ADR 0106) — gap mais barato dos 6

## Smoke criteria

- [ ] `mcp__oimpresso__charter-fetch` `page_id: oficina-auto-ordens-servico-edit` retorna charter completo
- [ ] CI gate MWART verde nos 2 PRs (sem soft warn yellow)
- [ ] AJV strict schema valido (datas com aspas, slugs strings)
- [ ] Append-only confirmed — proximo PR que mudar `Edit.tsx` adiciona v2 ao charter (smoke futuro)

## Dependencias

- **Conflito potencial Gap 1 (upload foto)** — Gap 1 toca ServiceOrders/Edit.tsx adicionando section uploads? **Resposta: NAO** — Gap 1 toca `ServiceOrderRichSheet.tsx` (drawer ProducaoOficina), nao Edit.tsx fullpage. Sem conflito.
- **Conflito potencial Wave 5 PR #1631** — ja mergeado, charter retroativo eh OK.
- **PR independente** dos outros 5 gaps.

## DRAFT task pra Wagner copy-paste

```yaml
title: "Gap 5 — Charter Edit.tsx (governance MWART verde)"
module: OficinaAuto
us: governance-mwart
priority: low-mas-barato
estimated_hours: 2
owner_proposal: claude-paralelo
description: |
  Subir MWART Gate de soft warn pra verde escrevendo .charter.md ao lado de:
  - ServiceOrders/Edit.tsx (Tier A — Wave 5 mudou recente)
  - Vehicles/Edit.tsx (Tier B — scaffold V0)

  Charter retroativo espelha codigo EXISTENTE, sem requisitos novos.
  Schema canon: aspas em datas, slugs strings related_adrs, page_id sem '/'.

  Append-only: v1 baseline, mudancas futuras viram v2.

  Pre-flight: Ler ambos Edit.tsx + charter.schema.json + AprovacaoPublica.charter.md
  como referencia.

  Refs: ADR 0104 MWART, ADR 0094 §Charter > Spec
acceptance_criteria:
  - "charter-fetch retorna spec rica nos 2 page_ids"
  - "CI MWART Gate verde nos 2 PRs"
  - "AJV strict schema valido"
  - "Wagner aprova ServiceOrders/Edit Tier A explicitamente"
```

## Refs

- [ADR 0104 MWART Process canon](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- ADR 0094 Constituicao v2 §Charter > Spec
- ADR 0093 Multi-tenant
- `resources/js/Pages/Cliente/Index.charter.md` (exemplo Tier A live v7)
- `resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md` (exemplo OficinaAuto Wave 4)
- skill `charter-write` Tier B
- skill `charter-fetch` (S4+ dormente, sera ativada)
- Sessao 2026-05-26 4 PRs §3 (licoes schema canon)
