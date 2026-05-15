---
page: /products/create
component: resources/js/Pages/Products/Create.tsx
owner: wagner
status: live
last_validated: 2026-05-14
parent_module: Products
related_adrs: [0110, 0107, 0104, 0093, 0141]
tier: A
charter_version: 1
---

# Page Charter — /products/create

> **Status:** live (US-PROD-002 — migração MWART 2026-05-14). Form pattern Cockpit V2 com sticky header + sticky footer + 3 sections collapsible. Persona canary: **Lara Caçambas** (filha do Martinho — responsável estoque).

---

## Mission

Cadastrar produto novo numa tela única curta com sections collapsible — substitui `product.create.blade.php` legacy (~4 telas scroll com 23 custom_fields visíveis). Foco velocidade: **6 campos obrigatórios visíveis** (Nome, SKU, Unidade, Categoria, Preço venda, Preço compra) + resto colapsável. Pre-fill query params pra futura integração com `/compras` autocomplete.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb
- Header h1 "Novo produto" + subtitle + ação sticky topo (Cancelar / Salvar)
- 3 sections:
  - **Identificação** (sempre aberta): Nome · SKU (auto-generate se vazio) · Unidade · Tipo (single/variable/combo) · Categoria · Marca
  - **Preço e estoque** (collapsible — default aberto): Preço venda · Preço compra · % lucro derivado · Habilitar estoque · Alerta qty
  - **Avançado** (collapsible — default fechado): NCM · CST/CSOSN · CFOP interno · CFOP externo · % ICMS / PIS / COFINS / IPI · Descrição · Garantia · Locações
- type='single' padrão (Martinho caçambas/peças simples — sem variations complex MVP)
- Validação inline com `<FieldError>` por campo (role="alert")
- Atalho Cmd/Ctrl+S salva · Esc cancela
- Auto-open `<details>` Avançado quando erro está em campo colapsado
- Salvar = redireciona pra `/products/{id}` (Show)
- Pre-fill via query params (`?name=...&sku=...`) — preparação futura compras

---

## Non-Goals — Features (NÃO faz)

- ❌ Variations matrix (type='variable') no MVP — Blade legacy preservado pra cliente que precisar
- ❌ Combo composition (type='combo') no MVP — Blade legacy preservado
- ❌ Quick add modal (`/products/quick_add` permanece como Blade pro fluxo POS — pop-up DataTables)
- ❌ Upload media múltiplo (brochures) — Blade legacy preservado
- ❌ Opening stock inline — fluxo separado pra `/opening-stock/add?product_id={id}`
- ❌ Selling price groups inline — fluxo separado em `/products/add-selling-prices/{id}` Blade
- ❌ Rack positions UI — Blade legacy preservado (raramente usado)

---

## UX Targets

- p95 first-paint < 1200ms
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (Lara)
- Save click → response < 800ms
- Footer sticky permanece visível durante scroll
- Tipografia canon: h1 24px, label 13px, input height 36px

---

## UX Anti-patterns

- ❌ Tabs `border-b-2` em vez de sections collapsible
- ❌ Botões Cancelar/Salvar duplicados (canon = 1x sticky)
- ❌ Cor crua `bg-(gray|red|...)-N`
- ❌ `font-bold` em h1
- ❌ `sessionStorage` em vez de localStorage prefixed `oimpresso.`
- ❌ Renderizar 23 custom_fields todos visíveis (só campos relevantes pra biz/vertical)

---

## Tests anti-regressão

- [tests/Feature/Products/ProductsInertiaTest.php](../../../../tests/Feature/Products/ProductsInertiaTest.php) — atalhos + 3 sections

---

## Refs

- [Design.md §16 Cockpit V2](../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [RUNBOOK Products](../../../../memory/requisitos/Products/RUNBOOK-products.md)
- Pattern referência: [Crm/Contacts/Create.tsx](../Crm/Contacts/Create.tsx)
