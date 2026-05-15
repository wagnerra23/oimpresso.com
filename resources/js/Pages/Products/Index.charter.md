---
page: /products
component: resources/js/Pages/Products/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-14
parent_module: Products
related_adrs: [0110, 0107, 0104, 0093, 0141]
tier: A
charter_version: 1
---

# Page Charter — /products

> **Status:** live (US-PROD-001 — migração MWART 2026-05-14). Aplicando **Cockpit Pattern V2** ADR 0110 (mesma estética de `/sells`, `/contacts`, `/financeiro/boletos`).

---

## Mission

Listar produtos com busca instant + filtros por categoria/marca/tipo/estoque, abrir detalhe ou edição em poucos cliques — substitui Blade legacy AdminLTE roxo (`product.index.blade.php` + DataTables + jQuery + Select2) preservando endpoints AJAX legacy como fallback condicional. Persona alvo canary: **Lara (filha do Martinho Caçambas)** — responsável estoque biz=164 prod, monitor 1280px, ~1.838 products.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb (Cockpit canon)
- Header h1 "Produtos" + subtitle + botão único "Novo produto"
- 4 KPIs cards: Total · Com estoque · Em alerta (qty<alert_quantity) · Inativos
- Filter pills `rounded-full + counter`: Todos / Com estoque / Em alerta / Inativos
- Busca livre debounce 300ms — nome + SKU + officeimpresso_codigo + sub_sku
- Filtros dropdown: categoria · marca · tipo (single/variable/combo)
- Tabela limpa 7 colunas: imagem · nome (+SKU em linha 2) · categoria/marca · unidade · preço venda · estoque atual · ações
- Click linha → `/products/{id}` (Show)
- DropdownMenu de ações por linha: Ver · Editar · Histórico estoque · Duplicar · Desativar/Ativar · Excluir
- Paginação numérica + per_page selector [10, 25, 50, 100]
- Endpoint REST canon: `GET /products/list-json` paginado
- Multi-tenant Tier 0: `business_id` scope obrigatório

---

## Non-Goals — Features (NÃO faz)

- ❌ Bulk edit (Blade legacy `/products/bulk-edit` preservado pra superuser)
- ❌ Mass deactivate/delete (Blade legacy preservado pra admin Wagner)
- ❌ Selling price groups inline (`/products/add-selling-prices/{id}` Blade preservado)
- ❌ Print labels (rota Blade preservada — botão visível em ações)
- ❌ Real-time updates (WebSocket) — não no MVP
- ❌ Inline edit (preço, qty na própria tabela) — não no MVP
- ❌ Drag-drop reorder — não no MVP
- ❌ Combinador `type=combo` ainda usa Blade pesado pro Create (Martinho só faz `single`)

---

## UX Targets

- p95 first-paint < 1500ms (KPIs + 25 linhas)
- p95 fetch list-json < 500ms
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC)
- Cabe em monitor 1280px sem scroll horizontal (Lara)
- Drawer/edit abre em < 1000ms após click
- Tipografia canon ADR 0110: h1 22-24px, pill 12px, badge 11px
- Cores semânticas Cockpit V2: rose (alerta estoque) / emerald (ok) / amber (em alerta) / blue (info ativo)

---

## UX Anti-patterns

- ❌ Tabs `border-b-2 border-primary` em filter (canon = pills `rounded-full`)
- ❌ Modal/Dialog pra detalhe de linha (canon = Sheet lateral OU navegação)
- ❌ Cor crua `bg-(gray|red|green)-N` (canon = rose/emerald/amber/blue semântico)
- ❌ `font-bold` em h1 (canon = `font-semibold`)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.`)
- ❌ DataTables jQuery (canon = fetch + React state)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/products/list-json?q=&type=&category_id=&brand_id=&status=&enable_stock=&page=&per_page=&sort=&dir=` | `{ data: ProductRow[], meta: {...} }` |
| GET | `/products` (X-Inertia) | Inertia render Products/Index |
| GET | `/products` (X-Requested-With ajax) | DataTables legacy (preservado) |

---

## Tests anti-regressão

- [tests/Feature/Products/ProductsInertiaTest.php](../../../../tests/Feature/Products/ProductsInertiaTest.php) — estrutural + cross-tenant + branch Inertia + listJson

---

## Refs

- [Design.md §16 Cockpit V2](../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 Processo MWART canônico](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [RUNBOOK Products](../../../../memory/requisitos/Products/RUNBOOK-products.md)
- Pattern referência: [Crm/Contacts/Index.tsx](../Crm/Contacts/Index.tsx), [Sells/Index.tsx](../Sells/Index.tsx)
