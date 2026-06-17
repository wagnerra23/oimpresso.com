---
page: /orcamento
component: resources/js/Pages/Orcamento/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-09"
parent_module: Orcamento
related_adrs: [110, 107, 93]
tier: A
charter_version: 1
---

# Page Charter — /orcamento (DRAFT)

> **Status:** draft criado em batch 2026-05-09 a partir de [`orc-page.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/orc-page.jsx) (6.3 KB) + [`data-orc-prod.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/data-orc-prod.jsx). Wagner aprova **Non-Goals + Automation Anti-hooks** ANTES de virar `status: live`.
>
> ⚠️ **Backend canon UPOS:** orçamento provável = `App\Transaction` com `type: quotation` ou `type: sell` + `status: draft`. Confirmar com Wagner ou abrir ADR `arq/NNNN-orcamento-vs-venda.md` antes de F3. **NÃO inventar** `App\Quotation` se não existir.

---

## Mission

Listar orçamentos com KPIs de pipeline (em aberto/aprovados/perdidos + valor + conversão %) — substitui `/sells/quotations` Blade legacy preservando Cockpit V2 visual.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb
- `<PageHeader>` shared: h1 "Orçamentos" + subtitle + botões "Filtros" + "Novo orçamento" (rota Blade legacy `/sells/create?type=quotation`)
- 5 KPI cards: Em aberto / Valor em aberto / Aprovados / Valor aprovado / Conversão %
- 4 tabs (filter pills `rounded-full + counter`):
  - **Ativos** (default) — `status IN (rascunho, enviado, negociacao)`
  - **Aprovados** — `status = aprovado`
  - **Perdidos** — `status = perdido`
  - **Todos** — sem filtro
- Search bar (busca em cliente + número + itens)
- Tabela 8 colunas: Número / Cliente / Itens / Valor / Validade / Responsável / Status / Probabilidade
- Status semântico (badge `rounded-full`):
  - `rascunho` — stone (cinza)
  - `enviado` — sky (azul)
  - `negociacao` — amber (amarelo)
  - `aprovado` — emerald (verde)
  - `perdido` — rose (vermelho)
- Probabilidade % por orçamento (input manual ou cálculo histórico)
- Click row abre drawer (consistente com SaleSheet pattern)
- URL sync filtros (tab + busca persistem em querystring)
- Multi-tenant: `App\Transaction` filtrado por `business_id` + `type='quotation'`
- Permission gate: `quotation.view`, `quotation.create`, `quotation.update` (confirmar nomes)

---

## Non-Goals — Features (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova.

- ❌ CRUD inline (criar/editar via rotas Blade `/sells/create?type=quotation`, `/sells/{id}/edit`)
- ❌ Converter orçamento em venda inline (vai pra rota dedicada `/orcamento/{id}/convert`)
- ❌ Enviar PDF por email do drawer (vai pra rota Blade legacy)
- ❌ Cálculo automático de probabilidade via histórico (manual)
- ❌ Bulk actions
- ❌ Auto-expirar orçamento ao passar validade (cron separado)
- ❌ Print direto (rota Blade `/orcamento/{id}/print`)
- ❌ Comparativo período-anterior (backlog)
- ❌ Forecast de receita baseado em probabilidade (backlog reports)
- ❌ Notificação push ao cliente quando orçamento é aprovado (backlog Modules/Whatsapp)
- ❌ Edição inline de validade no drawer (read-only — edição via rota Blade)

---

## UX Targets

- p95 first-paint < 1200ms (50 orçamentos)
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal
- Drawer abre < 300ms
- Tab switching < 200ms (Inertia partial reload)
- Tipografia canon: h1 22-24px, KPI value 28px, badge 11px
- Cores semânticas semantic (rose/amber/emerald/sky/stone)
- Probabilidade `tabular-nums` (alinha colunas)
- Validade vencida: badge rose "Vencida há Nd"

---

## UX Anti-patterns

- ❌ Tabs `border-b-2` (canon = pills `rounded-full`)
- ❌ Modal pra detalhe (canon = Sheet)
- ❌ Cor crua `bg-(red|green|orange)-N`
- ❌ KPI custom inline (canon = `@/Components/shared/KpiCard`)
- ❌ Probabilidade sem `tabular-nums` (desalinha)
- ❌ `font-bold` em h1
- ❌ `sessionStorage`

---

## Automation Hooks

- Endpoint `GET /orcamento` — controller (a confirmar nome) retorna lista paginada filtrada por `type='quotation'` ou tabela própria
- Endpoint `GET /orcamento/{id}/sheet-data` — drawer detail
- Multi-tenant: `App\Transaction` (ou `App\Quotation` se existir) com `business_id` global scope
- Permission middleware: `can:quotation.view` no `__construct`

---

## Automation Anti-hooks

> ⚠️ Wagner aprova.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara SMS/WhatsApp
- ❌ Não escreve no banco (read-only puro)
- ❌ Não roda job de expiração ao abrir (cron separado)
- ❌ Não muda status de orçamento no render
- ❌ Não chama Brain B
- ❌ Não acessa orçamento de outro `business_id`
- ❌ Não regenera PDF ao abrir drawer
- ❌ Não converte orçamento em venda automaticamente

---

## Métricas vivas (Pest GUARD)

```php
// tests/Feature/Orcamento/IndexCharterTest.php

it('renders under 1200ms p95 with 50 quotations')
it('does not emit emails on render')
it('does not dispatch jobs on render')
it('does not mutate state on GET')
it('does not change quotation status on render')
it('isolates quotations by business_id')
it('returns 404 for cross-tenant quotation access')
it('renders at 1280px without horizontal scroll')
it('classifies status correctly across 5 enum values')
it('shows badge "Vencida" for past-validity quotations')
it('formats probability with tabular-nums')
it('uses localStorage prefix oimpresso.orcamento.*')
```

---

## Comparáveis canônicos (`mwart-comparative` V4)

- **Pipedrive deals pipeline** (status semantic + probability)
- **Stripe invoices** (lista densa filter pills)
- **HubSpot deals** (apenas para padrão drawer)
- **Excluir:** Salesforce (enterprise overhead), Pipedrive Kanban (não é board nesta Page)

---

## Refs

- Material visual: [`ui_kits/cowork-2026-05-09/orc-page.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/orc-page.jsx) (6.3 KB) + [`data-orc-prod.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/data-orc-prod.jsx)
- Canon visual: [ADR ui/0012](../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)
- [ADR 0110 — Cockpit Pattern V2](../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight obrigatório (NÃO inventar `App\Quotation`)
- Backend: a confirmar entre `App\Transaction` (`type: quotation`) ou model dedicado

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [CL] | Charter draft criado em batch. Path `Pages/Orcamento/Index.tsx` (PT-BR). **Decisão pendente Wagner:** (1) backend Model — `App\Transaction` UPOS canon com `type='quotation'` (provável) OU model dedicado; (2) probabilidade % é manual ou calculada (histórico aprovação por cliente); (3) integração com Sells/Create (botão "converter em venda"). **Aprovação pendente** em Non-Goals + Anti-hooks pra `status: live`. |
