---
page: /sells
component: resources/js/Pages/Sells/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-08"
parent_module: Sells
related_adrs: ["0110-cockpit-pattern-v2-canon-list-detail", "0107-emendation-0104-visual-comparison-gate-f3", "0109-claude-design-plugin-integrado-processo-mwart", "0104-processo-mwart-canonico-unico-caminho", "0093-multi-tenant-isolation-tier-0"]
tier: A
charter_version: 1
---

# Page Charter — /sells

> **Status:** live (US-SELL-008 mergeado 2026-05-08, PR #261). Página de referência viva do **Cockpit Pattern V2** ADR 0110.

---

## Mission

Listar vendas com filtros por status de pagamento e abrir detalhes em drawer lateral — substitui Blade legacy AdminLTE roxo (`sell.index.blade.php`) preservando DataTables AJAX como fallback condicional.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb (Cockpit canon)
- Header `<PageHeader>` shared: h1 "Vendas" + subtitle + botão "Nova venda" único
- 3 KPIs cards: Abertas (due+partial), Atrasadas (rose destaque), Total
- 5 filter pills `rounded-full + counter`: Todas / Pago / A receber / Parcial / Atrasadas
- Tabela limpa 6 colunas: data + nº fatura (com red dot bullet se overdue) + cliente 2 linhas + total + pago + status badge
- Click linha abre drawer `SaleSheet` lateral direito (`<Sheet>` shadcn)
- Linha selecionada `bg-blue-50/60` (info active)
- Endpoint REST canon: `GET /sells-list-json` (limit 50, filtra payment_status)
- Multi-tenant Tier 0: `business_id` global scope + permission gate (direct_sell.view + variants)

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição inline (vai pra `/sells/{id}/edit` Blade legacy)
- ❌ Print direto (rota Blade `/sells/{id}/print`)
- ❌ Filtros avançados (date range, location, customer, source) — backlog US-SELL-009
- ❌ Paginação (limita 50 últimas; banner "Filtros adicionais em US-SELL-009")
- ❌ Export PDF/Excel (DataTables legacy ainda tem botão próprio quando `?ajax=1`)
- ❌ Bulk actions (checkbox seleção múltipla) — não no MVP
- ❌ Real-time updates (WebSocket/Centrifugo) — não no MVP
- ❌ Migrar `index()` Blade view por completo — fallback `request()->ajax()` mantido

---

## UX Targets

- p95 first-paint < 1500ms (KPIs + 50 linhas)
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC)
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE)
- Drawer abre em < 300ms após click (fetch JSON + render)
- Tipografia canon ADR 0110: h1 22-24px, pill 12px, badge 11px
- Cores semânticas Cockpit V2: rose/emerald/amber/blue (NÃO cor crua)

---

## UX Anti-patterns

- ❌ Tabs `border-b-2 border-primary` em filter (canon = pills `rounded-full`)
- ❌ Modal/Dialog pra detalhe de linha (canon = Sheet lateral)
- ❌ KpiCard custom inline (canon = `@/Components/shared/KpiCard`)
- ❌ Cor crua `bg-(gray|red|green)-N` (canon = rose/emerald/amber/blue semântico)
- ❌ `font-bold` em h1 (canon = `font-semibold`)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.`)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells-list-json?payment_status=&limit=50` | 8 fields/linha + is_overdue derivado |
| GET | `/sells/{id}/sheet-data` | Drawer detail JSON (lines + payments + customer + urls) |
| GET | `/sells` (X-Inertia) | Inertia render Sells/Index |
| GET | `/sells` (X-Requested-With ajax) | DataTables legacy (preservado) |

---

## Tests anti-regressão

- [tests/Feature/Sells/SellsIndexPageTest.php](../../tests/Feature/Sells/SellsIndexPageTest.php) — 24 testes estruturais
- [tests/Feature/Sells/SaleSheetComponentTest.php](../../tests/Feature/Sells/SaleSheetComponentTest.php) — 22 testes drawer
- [tests/Feature/Sells/SellControllerEndpointsTest.php](../../tests/Feature/Sells/SellControllerEndpointsTest.php) — 21 testes backend
- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico (esta Page no canon target)

---

## Refs

- [Design.md §16 Cockpit V2](../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- PR #261 (commit `cfa7930a` + hotfix `0b5a09d5`) — implementação inicial
