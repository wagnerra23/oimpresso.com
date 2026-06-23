---
page: /products/{id}
component: resources/js/Pages/Produto/Show.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Show]
  divergence_from_blueprint: "Page full (não drawer 320px lateral) — mesma família AppShellV2 + tokens + Hero KPIs + tabs do blueprint Cockpit, mas renderizado como Page completa em vez de drawer overlay. ADR 0149 admite (mesma entidade, mesma família visual, mesmos tokens)"
---

# Page Charter — /products/{id} (DRAFT)

## Mission

Mostrar detalhe completo do produto com Hero KPIs + tabs (Resumo · Composição · Variações · Preços · Movimento · Fiscal). Reusa pattern visual do drawer Cowork blueprint como Page full.

## Goals

- AppShellV2 + PageHeader (h1 nome + SKU mono + categoria small)
- Hero KPIs strip 4 cards: Estoque · Custo · Preço varejo · Vendas no mês
- Tabs: Resumo (default) · Composição (se BOM) · Variações (se variable) · Preços (se price_groups) · Movimento (deferred) · Fiscal
- `<Deferred>` em rackDetails e variations
- Ações: "Editar" primary + "Histórico estoque" outline
- Faixa de reposição visual (mín/máx) — pattern Cowork blueprint
- Multi-tenant scopado business_id

## Non-Goals

- ❌ Editar inline (apenas view; edit em rota separada)
- ❌ Deletar inline
- ❌ Mudar SKU/type
- ❌ Drawer overlay (é Page full)

## UX Targets

- p95 < 800ms
- 1280px sem scroll horizontal
- Cabe drawer ou Page (responsivo)

## Anti-patterns

- ❌ Mutação em GET
- ❌ Cor crua

## Automation Hooks

- GET `/products/{id}`
- `business_id` global scope

## Anti-hooks

- ❌ Não dispara jobs/emails/Brain B
- ❌ Não escreve no banco em GET

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/Show.tsx')
it('Page importa AppShellV2')
it('Page declara interface ProdutoShowPageProps')
it('Controller cross-tenant retorna 404')
it('Page tem Hero KPIs (Estoque/Custo/Preço/Vendas)')
```

## Refs

- Blueprint drawer: `produto-cockpit/produto-cockpit-page.jsx::DrawerView`
- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-show.md`
- Visual comparison: `memory/requisitos/Inventory/produto-show-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
