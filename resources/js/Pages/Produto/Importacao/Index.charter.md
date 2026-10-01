---
id: resources-js-pages-produto-importacao-index-charter
page: /import-products · /import-opening-stock
component: resources/js/Pages/Produto/Importacao/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/produto-acoes.jsx
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-importacao.md
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Produto
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — Importação de produtos e de estoque inicial (DRAFT · `/import-products` · `/import-opening-stock`)

> **Status:** draft. Recortado do trio proposto `cowork-inbox/produto-telas-novas/Importacao.charter.md`
> (F1 [CC] 2026-08-21) ao que a thread 05 entrega (modo produtos e, no PR-b, estoque inicial). Casos: [`Index.casos.md`](Index.casos.md)
> · contrato: `governance/design/contracts/produto-importacao.contract.json` (derivado no `_saida-07`).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Importação é a operação mais destrutiva do catálogo: uma coluna fora de ordem cria centenas de
produtos errados. A tela tem uma obrigação antes de qualquer estética — **dizer o que vai
acontecer antes de acontecer**.

## Goals — Features (faz, nesta fase)

- **R1** Ordem das colunas é contrato: 37 colunas, a primeira linha é cabeçalho e é ignorada. As
  instruções numeram as 37 com obrigatório/opcional e os valores aceitos.
- **R3** Não existe importação parcial — a tela diz isso antes. **Enviar planilha** só libera depois
  de uma conferência sem erro do mesmo arquivo.
- **R6** A conferência é do servidor: roda a mesma validação do `store()` (unidade, imposto, local,
  SKU repetido) e desfaz tudo — nada é gravado até **Enviar planilha**. Vale pra .csv, .xls e .xlsx.
- Conferência sem erro lista uma linha por produto que seria criado (linha, nome, SKU, tipo,
  estoque inicial, custo e preço com imposto, como o servidor calculou). Com erro, mostra a mensagem
  do servidor, com a linha.
- **Baixar modelo** baixa `files/import_products_csv_template.xls`.
- **Modo estoque inicial** (`/import-opening-stock`, `product.opening_stock`): mesma tela, 6 colunas.
  A conferência roda o próprio `store()` — que valida e grava linha a linha no mesmo laço — e desfaz;
  lista por linha SKU, produto, local, quantidade, custo, saldo do local e total do lançamento como
  ficaram gravados antes do rollback. `?classico=1` mantém a Blade.

## Non-Goals (nesta fase)

- ❌ Leitura do .csv no navegador (R2/R4/R5 do trio proposto): a conferência do servidor substitui.
- ❌ Relatar **todos** os erros de uma vez: o `store()` para no primeiro, e mudar isso é mexer no
  algoritmo de importação (regra mestre VALOR/ESTOQUE).
- ❌ Converter arquivo, corrigir planilha, de-para de coluna, importação agendada.

## Anti-hooks

- ❌ Mudar cálculo de custo/preço/quantidade ou a forma de gravar estoque. A tela só troca a
  apresentação e acrescenta o dry-run. Guarda: UC-PIMP-04 e UC-PIMP-09.
- ❌ Conferência que deixa rastro (produto, marca, categoria, estoque ou imagem baixada). Guarda: UC-PIMP-03 e UC-PIMP-08.
- ❌ Ler ou gravar fora do `business_id` da sessão (Tier 0). Guarda: UC-PIMP-05 e UC-PIMP-10.
