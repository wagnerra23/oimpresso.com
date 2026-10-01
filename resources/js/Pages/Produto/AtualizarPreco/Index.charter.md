---
id: resources-js-pages-produto-atualizarpreco-index-charter
page: /update-product-price
component: resources/js/Pages/Produto/AtualizarPreco/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/produto-acoes.jsx
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-atualizar-preco.md
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Produto
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — Atualizar preço por planilha (DRAFT · `/update-product-price`)

> **Status:** draft. Recortado da ficha `08-atualizar-preco.md` do playbook Produto e dos casos
> propostos `cowork-inbox/produto-telas-novas/Importacao.casos.md` (UC-PRC-01..04). Casos:
> [`Index.casos.md`](Index.casos.md) · contrato: `governance/design/contracts/produto-atualizar-preco.contract.json`
> (derivado no `_saida-07`). Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mudar o preço de muitos produtos de uma vez sem digitar um por um: exporta a planilha, edita, devolve.
Antes de gravar, a tela mostra o que vai mudar. Preço errado em lote vira venda errada em todo o balcão.

## Goals — Features (faz, nesta fase)

- **R1** Passo 1 exporta a planilha atual (`/export-product-price`, sem mudança): uma coluna por grupo **ativo**.
- **R2** Passo 2 sobe a planilha e mostra a **conferência**: antes → depois por SKU e preço, sem gravar.
- **R3** "Aplicar preços" só habilita com a conferência aceita e sem alerta; aplica o MESMO arquivo na
  rota de sempre (`/import-product-price`).
- **R4** Chips dos grupos ativos; atalhos "Gerenciar grupos" e "Editar preço na tela".
- Gate `product.update` (o mesmo da Blade).

## Non-Goals (nesta fase)

- ❌ Mudar parse, arredondamento, separador decimal ou a forma de gravar preço (regra mestre de valor).
- ❌ Ler ou recalcular preço no navegador — a planilha sobe como arquivo.
- ❌ Recusar linha com nome/variação alterados (a ficha pede; o `import()` atual não faz e mudá-lo é decisão [W]).
- ❌ Importar estoque inicial ou produtos (thread 05).

## Anti-hooks

- ❌ Conferência que reimplementa o cálculo: ela executa o `import()` e desfaz. Guarda: UC-PATPRC-03.
- ❌ Mostrar preço ou grupo de outro negócio (Tier 0). Guarda: UC-PATPRC-01 e UC-PATPRC-04.
- ❌ Gravar preço em variação de outro negócio quando o SKU coincide (Tier 0). Guarda: UC-PATPRC-06.
- ❌ Exportar ou importar sem `product.update`. Guarda: UC-PATPRC-08.
- ❌ Caminho novo gravando diferente do antigo. Guarda: UC-PATPRC-02.
