---
id: resources-js-pages-produto-cadastros-index-charter
page: /units
component: resources/js/Pages/Produto/Cadastros/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/produto-cadastros.jsx
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-cadastros.md
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Produto
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — Cadastros de apoio (DRAFT · `/units`, abas)

> **Status:** draft. Copiado do trio proposto `cowork-inbox/produto-telas-novas/Cadastros.charter.md`
> (F1 [CC] 2026-08-21) e recortado ao que a thread 02 entrega (Unidades e Marcas no #8371, Categorias na continuação). Casos: [`Index.casos.md`](Index.casos.md)
> · contrato: `governance/design/contracts/produto-cadastros.contract.json` (derivado no `_saida-07`).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

São tabelas pequenas que ninguém abre por prazer: você vem aqui porque o cadastro de produto pediu
uma unidade que não existe. A tela é **um lugar** (não 6 itens de menu), resolve em segundos e nunca
deixa quebrar o catálogo por engano. D3 [W] 2026-10-01: Page parametrizada, uma tela com abas.

## Goals — Features (faz, nesta fase)

- **R1** Uma tela, 6 abas (Variações · Grupos de preço · Unidades · Categorias · Marcas · Garantias),
  contador das abas vivas vindo do estado do servidor.
- **R3** Coluna "Produtos" clicável: abre o índice de produtos filtrado por aquele valor. Zero não é clicável.
- **R4** Registro em uso não é excluído: a confirmação diz quantos produtos usam e não oferece o botão
  destrutivo. A recusa é do servidor (`UnitController@destroy` já recusava; `BrandController@destroy` passa a recusar).
- **R6** Unidade múltipla de base mostra `1 cx = 1000 Un` na linha.
- **R7** Categoria mostra a hierarquia na própria linha (`↳ Lonas · em Comunicação visual`). Só entra
  categoria de produto (`category_type = product`). **Excluir categoria com subcategoria é recusado**
  pelo servidor (`TaxonomyController@destroy`), igual à categoria em uso — o protótipo dizia que as
  filhas iam junto; a troca está registrada no `_saida-02`.
- **R8** Primeira vez explica pra que serve o cadastro; busca sem resultado oferece limpar.
- Cada aba pela sua permissão (`unit.*`, `category.*`, `brand.*`). Sem `view` a aba mostra o motivo; sem `create` o
  Novo fica desabilitado com o motivo; sem `delete` o Excluir não existe.

## Non-Goals (nesta fase)

- ❌ Modal de criar/editar na tela nova (R2). Segue nos modais da Blade (`?classico=1`) — pendente no `_saida-02`.
- ❌ Abas Variações, Grupos de preço e Garantias (thread 03): abrem a tela atual de cada uma.
- ❌ Criar/editar categoria na tela nova: segue no modal de `/taxonomies?type=product`.
- ❌ Cadastro de imposto, de local, taxonomia de despesa ou de Oficina; merge de duplicata.

## Anti-hooks

- ❌ Decidir Inertia × DataTables só por `request()->ajax()`: o Inertia manda `X-Requested-With` junto
  do `X-Inertia` (§5 2026-09-08). Guarda: UC-PCADAP-02.
- ❌ Lista ou contagem sem `business_id` (Tier 0). Guarda: UC-PCADAP-04.
