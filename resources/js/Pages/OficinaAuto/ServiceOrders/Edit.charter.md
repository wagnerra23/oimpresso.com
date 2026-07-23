---
page: /oficina-auto/ordens-servico/{id}/edit
component: resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx
page_id: oficina-auto-ordens-servico-edit
owner: wagner
status: live
last_validated: "2026-05-26"
parent_module: OficinaAuto
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0137-modules-oficinaauto-qualificada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0182-pageheadertabs-canon-pattern-telas
  - 0190-primary-button-roxo-universal-295
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
charter_version: 1
---

# Page Charter — /oficina-auto/ordens-servico/{id}/edit

> **Contexto sub-vertical 4 ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** edicao de OS de **mecanica pesada caminhao basculante** (sub-vertical 4 Martinho LIVE prod biz=164). Schema OS generico (manutencao OR locacao preservado nullable).
>
> **Charter retroativo Gap 5 (2026-05-26):** v1 baseline espelha codigo EXISTENTE de `Edit.tsx` apos Wave 5 PR #1631 introduzir section inline "Itens da OS" embedded modo FOCO. Append-only — mudancas futuras viram v2.

## Mission

Editar Ordem de Servico existente em **modo FOCO** (sem SubNav, Wave 5 PR #1631 skill `pageheader-canon` Fase 4-bis) — usuario foca em 9 campos basicos do form (veiculo, status, mileage, 4 datetimes, notes) **+** section inline "Itens da OS" embutida (NAO modal) com row clicavel + Sheet shadcn 480px lateral pra editar/criar item. Save unico via `useForm.put()` Inertia retornando pro Show. Status segue FSM canonico ([ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).

## Goals

- **G1.** PageHeader sem tabs ("Editar OS #{id}") em modo FOCO — `subtitle="Atualizar status, datas, observacoes"` + acao `Voltar` ghost.
- **G2.** Form com 9 campos pre-preenchidos via `useForm`:
  - `vehicle_id` select (opcoes vindas de `vehicles` prop com plate + secondary_plate + vehicle_type)
  - `status` select (opcoes vindas de `statuses` prop FSM canon)
  - `mileage_at_service` input number (KM na entrada)
  - 4 datetime-local: `entered_at`, `expected_completion`, `completed_at`, `delivered_at`
  - `notes` textarea full-width
- **G3.** Section inline "Itens da OS" embedded (Wave 5 US-OFICINA-005-bis):
  - Header com icon Package + titulo + contador `(N)` + botao `PageHeaderPrimary` "Adicionar item" (roxo 295 ADR 0190)
  - `<ul>` de `ServiceOrderItemRow` divide-y (icone tipo + descricao + qtd + valor unit + total + actions hover edit/delete)
  - Footer com Total OS formatado BRL emerald-700
  - Empty state com Package icon + CTA "Adicionar item"
- **G4.** `ServiceOrderItemFormSheet` shadcn Sheet 480px lateral pra criar/editar item (controlled via `sheetOpen` + `editingItem`).
- **G5.** Delete item com `window.confirm()` + optimistic UI rollback em caso de erro HTTP.
- **G6.** Validation errors inline per field (`errors.vehicle_id`).
- **G7.** Footer com botoes "Cancelar" outline (volta pro Show) + "Salvar alteracoes" primary (disabled durante `processing`).

## Non-Goals

- NAO editar items em batch — uma operacao por vez via Sheet.
- NAO mudar `vehicle_id` apos OS ter `started_at` registrado (FSM lock — validado server-side, Edit nao impede mas backend rejeita).
- NAO editar FSM `status` fora do `PipelineStage` (este Edit eh status livre legacy V0 — opera com `statuses` prop generica, nao com FSM transitions oficiais ADR 0143).
- NAO editar `transaction_id` — read-only, auto-faturado pelo `ServiceOrderObserver` ([ADR 0192](../../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)).
- NAO adicionar DVI (Digital Vehicle Inspection) inline — DVI vive no drawer `ProducaoOficina`, nao no Edit fullpage.
- NAO permitir upload de fotos no Edit fullpage — Gap 1 futuro via `HasArquivos` trait dentro do drawer.
- NAO recalcular `final_total` no client — `Observer` faz server-side ao detectar mudanca de `status`.

## UX Targets

- **p95 first-paint:** < 600ms (form simples, payload ja contem `order.items` eager-loaded — sem `Inertia::defer`).
- **p95 save round-trip:** < 400ms (single PUT, sem batch items).
- **p95 abrir `ServiceOrderItemFormSheet`:** < 200ms (shadcn Sheet lazy mount).
- **p95 delete item optimistic:** < 250ms (UI responsivo, rollback se HTTP falhar).
- **Mobile 360px:** form usavel em 1 col stack (max-w-3xl mx-auto + grid-cols-2 colapsa via Tailwind responsive).
- **Empty state items:** mensagem clara "Nenhum item lancado ainda" + microcopy CTA.

## Automation Anti-hooks

- NAO dispara `EnviarLinkAprovacaoWhatsappJob` direto do Edit — Job dispatcha quando `ServiceOrderObserver::updated()` detecta `status` mudou pra `orcamento` ([ADR 0192](../../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) + charter `AprovacaoPublica`).
- NAO auto-recalcula `final_total` no client (`useMemo totalOs` eh display-only — Observer recalcula server-side ao Save).
- NAO acessa OS de outro `business_id` (multi-tenant Tier 0 [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — global scope `ServiceOrder` model enforce).
- NAO permite delete batch items via Edit — operacao 1-by-1 com `confirm()`.
- NAO redireciona pra rota fora `/oficina-auto/ordens-servico/{id}` apos Save (Inertia `put` retorna pro Show via controller `redirect()`).
- NAO loggar payload `notes` em telemetria (pode conter PII do cliente final — texto livre).

## Sub-components

- `_components/ServiceOrderItemRow.tsx` (Wave 5 PR #1631 — row item com icon tipo, qtd, valor unit, total BRL, actions edit/delete hover)
- `_components/ServiceOrderItemFormSheet.tsx` (Wave 5 PR #1631 — shadcn Sheet lateral 480px com form item)
- `@/Components/shared/PageHeader` (header modo FOCO sem tabs)
- `@/Components/PageHeader/PageHeaderPrimary` (botao roxo 295 universal ADR 0190)
- `@/Layouts/AppShellV2` (shell canonico)

## Refs

- [ADR 0093 Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituicao v2 7 camadas](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0104 MWART canonico unico caminho](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0137 Modules OficinaAuto qualificada](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0143 FSM pipeline canon](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0182 PageHeaderTabs canon pattern](../../../../../memory/decisions/0182-pageheadertabs-canon-pattern-telas.md)
- [ADR 0190 Primary button roxo universal 295](../../../../../memory/decisions/0190-primary-button-roxo-universal-295.md)
- [ADR 0192 Auto-faturar OS-Venda Observer](../../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- [ADR 0194 Dominio Martinho mecanica pesada](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- PR #1631 Wave 5 US-OFICINA-005-bis (section inline items embedded)
- RUNBOOK: `memory/requisitos/OficinaAuto/RUNBOOK-edit.md`
- Charter sibling: `resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md` (Tier A live)

@see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (action `edit` + `update`)
@see Modules/OficinaAuto/Http/Controllers/ServiceOrderItemController.php (Wave 5 backend items)
@see Modules/OficinaAuto/Observers/ServiceOrderObserver.php (hook status -> orcamento, auto-faturar)
@see resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderItemRow.tsx
@see resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderItemFormSheet.tsx
