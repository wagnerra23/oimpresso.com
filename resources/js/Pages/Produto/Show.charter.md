---
id: resources-js-pages-produto-show-charter
page: /products/{id}
component: resources/js/Pages/Produto/Show.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-show.md
related_visual_comparison: memory/requisitos/Produto/_telas/produto-show-visual-comparison.md
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
- Isolamento por `business_id` via `where` **explícito** no controller (`->where('business_id', …)->findOrFail`)
<!-- Reconciliação factual 2026-07-26 (Fase 2.6): esta linha dizia "`business_id` global scope".
     Medido: `grep -c addGlobalScope app/Product.php` = 0 — NÃO há global scope em `App\Product`.
     A contenção é manual e repetida query a query (mesma correção já aplicada no Index.charter).
     Consequência registrada em `Show.casos.md` UC-PSHOW-02 e no §6.1 CU-PROD-10 do SDD.
     Só FATO corrigido; nenhuma intenção (Goals/Non-Goals/Anti-hooks) tocada. -->


## Anti-hooks

- ❌ Não dispara jobs/emails/Brain B
- ❌ Não escreve no banco em GET

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/Show.tsx')              // ✅ Wave2ShowInertiaTest (string-match)
it('Page importa AppShellV2')                                     // ✅ Wave2ShowInertiaTest (string-match)
it('Page declara interface ProdutoShowPageProps')                 // ✅ Wave2ShowInertiaTest (string-match)
it('Controller cross-tenant retorna 404')                         // ✅ defesa REAL: ProdutoShowContratoTest (UC-PSHOW-02)
it('Page tem Hero KPIs (Estoque/Custo/Preço/Vendas)')             // ⬜ SEM LASTRO — a tela tem 0 KPIs
```

<!-- Reconciliação factual 2026-07-26 (Fase 2.6): o bloco prometia 5 testes sem dizer quais
     existem. Medido (`ls tests/Feature/Produto/` + grep dos nomes): 3 existem como string-match
     no fonte (passariam com o isolamento quebrado e o preço vazando), 1 ganhou defesa real de
     comportamento em `ProdutoShowContratoTest`, e o último não tem lastro — o charter promete
     4 Hero KPIs e a tela renderiza 0 (divergência aberta, ver Show.casos.md §Divergências).
     A lista de INTENÇÃO fica preservada (é do [W]) — só foi anotado o que tem defesa real. -->


## Refs

- Blueprint drawer: `produto-cockpit/produto-cockpit-page.jsx::DrawerView`
- RUNBOOK: `memory/requisitos/Produto/_telas/RUNBOOK-produto-show.md`
- Visual comparison: `memory/requisitos/Produto/_telas/produto-show-visual-comparison.md`
<!-- Reconciliação factual 2026-07-26 (Fase 2.6 do sdd-from-source): os dois paths apontavam para
     `memory/requisitos/Inventory/`, que não contém nenhum dos dois arquivos
     (`ls memory/requisitos/Inventory/` = BRIEFING.md, SPEC.md). Os arquivos reais estão em
     `Produto/_telas/` — e o `related_runbook:` do frontmatter (L11) já apontava o certo, ou seja,
     o charter se contradizia. Só FATO corrigido; nenhuma intenção (Goals/Non-Goals) tocada. -->

- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
