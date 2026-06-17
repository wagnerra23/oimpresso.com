---
page: /home
component: resources/js/Pages/Home/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-22"
parent_module: Dashboard
parent_spec: memory/requisitos/Dashboard/SPEC.md
related_adrs: [93, 94, 101, 104]
related_us: [US-DASH-001, US-DASH-006]
related_prototype: n/a (F6 Soft wrapper — sem protótipo Cowork; reusa session+queries core UltimatePOS)
tier: A
charter_version: 2
---

# Page Charter — /home

> **Status v2:** Fase 6 Soft expandida 2026-05-22 (Wagner aprovou caminho B). 8 KPIs em 2 grupos (Vendas / Compras & Custos), PageHeader canon ADR 0180 substituindo gradient hardcoded (fix contraste WCAG AA). Charter v1 entregue 2026-05-21.
> Persona: **Larissa [L]** (dona PME vestuário ROTA LIVRE biz=4) + qualquer user logado UPOS. Desktop ≥1024px.
>
> **Read-only:** todo dado vem de queries existentes do core UltimatePOS (`TransactionUtil::getSellTotals` + `getPurchaseTotals` + `getTransactionTotals`, `BusinessLocation::forDropdown`). Mutations (registrar venda etc.) continuam nas telas dedicadas (`/sells/pos`, `/expense`, etc.).
>
> **Fallback Blade preservado:** `?legacy=1` força `view('home.index')` original com charts ECharts + widgets pluggable de outros módulos.

---

## Mission (1 frase)

Servir como **landing pós-login** do oimpresso entregando 8 KPIs de operação em 2 grupos (Vendas / Compras & Custos) em ≤800ms numa shell Inertia React, com fallback discreto pra Blade legacy quando o usuário precisa de gráficos ou widgets pluggable de outros módulos.

---

## Goals — Features (faz)

- AppShellV2 layout com breadcrumb único `Início`
- Welcome banner ("Bem-vindo, {primeiro_nome}") em superfície clara (PageHeader canon ADR 0180) com ícone `layout-dashboard`
- **8 KPI cards** em 2 grupos:
  - **Vendas:** Total Vendas (sky) · Líquido (emerald) · A Receber (amber) · Devoluções Venda (rose)
  - **Compras & Custos:** Total Compras (violet) · A Pagar (orange) · Reembolso Compra (teal) · Despesas (stone)
- Cada card tem ícone Lucide semântico + tooltip explicando o cálculo
- Filtro loja dropdown quando `all_locations.length > 1 && is_admin`
- Banner discreto no rodapé: "Ver versão completa com gráficos e widgets" → `/home?legacy=1`
- Permission gate `dashboard.data` — sem permission, KPI cards somem (shell minimal)
- Customer redirect preservado (`user_type=user_customer` → `Modules/Crm/Http/Controllers/DashboardController`)
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL — `session('user.business_id')` em todas queries
- `?legacy=1` força Blade original (canário + acesso aos widgets pluggable)
- Range default = mês corrente (do início do FY ao end calculado por `BusinessUtil::getCurrentFinancialYear`)
- Totais derivados todos do mesmo `getTransactionTotals` + `getSellTotals` + `getPurchaseTotals` — sem AJAX extra

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ **NÃO renderiza charts ECharts** — preservados em `?legacy=1`. Backlog Rewrite Cockpit V2 (US-DASH-002)
- ❌ **NÃO renderiza widgets pluggable** (`moduleUtil->getModuleData('dashboard_widget')`) — mecanismo Blade-only, preservado em `?legacy=1`. Backlog ADR widget registry React (US-DASH-003)
- ❌ **NÃO toca endpoints AJAX** (`/home/get-totals`, `/home/product-stock-alert`, `/home/purchase-payment-dues`, `/home/sales-payment-dues`) — preservados intactos pro Blade legacy continuar funcionando
- ❌ **NÃO toca `/calendar`** (`getCalendar` continua Blade)
- ❌ **NÃO toca customer dashboard** (`Modules/Crm/Http/Controllers/DashboardController`)
- ❌ **NÃO substitui filtros de data** do Blade legacy — Soft mostra range default fixo (FY corrente). Backlog US-DASH-004 (filtro datas persistido em Inertia)
- ❌ **NÃO permite mutação** — sem botões "criar venda" inline. Atalhos viram menu / navegação separada

---

## UX targets (mensuráveis)

- **First-paint ≤ 800ms** com 4 KPI cards (queries `TransactionUtil::getSellTotals` indexadas por `business_id`)
- **0 erros JS console** (Pest GUARD valida)
- **Larissa entende KPIs em ≤ 5s** — cards com label PT-BR + tom semântico
- **Acesso ao legacy em ≤ 1 clique** — link discreto no rodapé com text claro

---

## Anti-hooks (sinais de drift)

> Quando esta tela "ganhar" funcionalidade, suspeite — fica fácil escorregar pra F6 Hard sem ADR.

- ⚠️ Aparecer **chart inline** sem ADR US-DASH-002 — vira Rewrite Cockpit V2 (não Soft)
- ⚠️ Aparecer **widget de outro módulo** sem registry — drift pra Blade-only break
- ⚠️ Aparecer **botão "criar venda" inline** — drift, KPI screen vira shortcuts
- ⚠️ Quebrar contrato "fallback `?legacy=1` continua funcionando" — qualquer mudança que quebre o Blade legacy é red flag (todo cliente ainda depende)
- ⚠️ Aparecer **session storage** para filtros — preferir query string (`?location_id=`)

---

## Test plan (Pest GUARD)

Cobertos em `tests/Feature/Home/HomeIndexInertiaTest.php`:

1. ✅ `renderiza Inertia component Home/Index com shape esperado` (user_name, is_admin, can_dashboard_data, totals, legacy_url, endpoints)
2. ✅ `customer redirect preservado` (`user_type=user_customer` → 302)
3. ✅ `sem permission dashboard.data → totals é null` (shell minimal)
4. ✅ `?legacy=1 retorna Blade (não Inertia)`
5. ✅ `Tier 0 multi-tenant — não vaza locations de outro business` — invariante ADR 0093
6. ✅ `totals expõe 8 campos canônicos` — guard charter v2 (total_sell, net, invoice_due, total_expense, total_purchase, purchase_due, total_sell_return, total_purchase_return)

---

## Backlog (não no escopo F6 Soft)

- **US-DASH-002 — Charts ECharts em Inertia** — Rewrite Cockpit V2 wave (F1→F4 com protótipo Cowork)
- **US-DASH-003 — Widget registry pluggable React** — ADR nova obrigatória
- **US-DASH-004 — KPI defer com filtro datas + loja persistido** — hoje range default fixo (FY corrente)
- **US-DASH-005 — Stock alert + dues tabelas DataTables migradas** — hoje continuam Blade AJAX

---

## Refs

- [memory/requisitos/Dashboard/SPEC.md](../../../memory/requisitos/Dashboard/SPEC.md) — US-DASH-001
- [memory/requisitos/Dashboard/RUNBOOK-home-index.md](../../../memory/requisitos/Dashboard/RUNBOOK-home-index.md)
- [memory/requisitos/Dashboard/BRIEFING.md](../../../memory/requisitos/Dashboard/BRIEFING.md)
- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- Pattern Soft wrapper precedente: PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288)
- `app/Http/Controllers/HomeController.php` — Controller adaptado
- `resources/views/home/index.blade.php` — Blade legacy preservado (fallback `?legacy=1`)
