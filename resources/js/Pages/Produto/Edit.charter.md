---
id: resources-js-pages-produto-edit-charter
page: /products/{id}/edit
component: resources/js/Pages/Produto/Edit.tsx
related_prototype: prototipo-ui/cowork/Wagner/produto-blade-forms.jsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
related_us: [US-PROD-020, US-PROD-023]
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-edit.md
related_visual_comparison: memory/requisitos/Produto/_telas/produto-edit-visual-comparison.md
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/produto-blade-forms.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Edit]
  divergence_from_blueprint: "none — o bloco FormProduto do blueprint é a tradução do PRÓPRIO edit.blade.php desta tela (cabeçalho do .jsx: 'create.blade.php / edit.blade.php + partials/single|variable|combo_product_form_part -> FormProduto'), não um pattern emprestado de outra tela"
---

# Page Charter — /products/{id}/edit (DRAFT)

## Mission

Editar produto existente reusando estrutura Create.tsx 100% — diferença é `useForm` inicializado com `product` props recebidos do controller. Type select disabled (não muda após criar).

## Goals

- Mesma estrutura Create.tsx
- Header "Editar produto · {nome} · SKU mono small"
- Type select disabled
- Defaults preenchidos com produto atual
- Rodapé de ações: Cancelar · "Salvar e adicionar preços por grupo" (com grupo de preço) · "Salvar e adicionar estoque inicial" (com `product.opening_stock`; desligado sem controle de estoque) · "Salvar e adicionar outro" · "Salvar alterações"; "Voltar à lista" no cabeçalho (desde 2026-11-04, forma do protótipo; os dois do meio desde 2026-11-25, UC-PEDIT-35)
- Multi-tenant: produto cross-tenant retorna 404

## Non-Goals

- ❌ Mudar `type` (Single/Variable/Combo) após criar
- ❌ Acrescentar, tirar ou renomear valor de variação no Editar (Wave 3)
  - _Até 2026-11-24 esta linha era "Editar variations dinamicamente". Em 2026-11-25 (UC-PEDIT-32, pedido do [W] pelo item 3 das pendências de 24/11) o Editar passou a editar o sub-SKU e os cinco preços das variações já gravadas; o que segue fora é mudar o conjunto de valores._
- ❌ Deletar produto inline

## UX Targets

- p95 < 800ms
- 1280px sem scroll horizontal
- Form preenchido em <300ms (já vem do controller)

## Anti-patterns

- ❌ NÃO mexer no método `update()` PHP (out of scope)
  - _Exceção datada (2026-10-15, UC-PEDIT-09): o `update()` passou a aceitar `origem_mercadoria` (0–8), gravado só quando o campo vem — o Blade não manda e não zera. Nada mais do método mudou._
  - _Exceção datada (2026-11-25, UC-PEDIT-32): no ramo do produto variável o `update()` passou a ler `variacoes_editadas` (só as linhas mexidas, presas ao produto). O `product_variation_edit` do Blade não mudou._
  - _Exceção datada (2026-11-25, UC-PEDIT-35): com `submit_type=update_n_edit_opening_stock` vindo da tela React (cabeçalho `X-Inertia`), o `update()` devolve `Inertia::location` para o estoque inicial em vez do 302, como o `store()` já faz no Novo (UC-PCAD-21). Pelo Blade, igual._
- ❌ NÃO recriar SKU server-side no client

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/Edit.tsx')
it('Page importa AppShellV2')
it('Page declara interface ProdutoEditPageProps com product')
it('Page tem type select disabled')
it('Controller cross-tenant retorna 404')
```

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-edit.md`
- Visual comparison: `memory/requisitos/Inventory/produto-edit-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
| 2026-11-25 | [W+C] | Non-Goal de variações estreitado: sub-SKU e preço das variações gravadas entram no Editar (UC-PEDIT-32); acrescentar, tirar e renomear valor seguem fora. |
