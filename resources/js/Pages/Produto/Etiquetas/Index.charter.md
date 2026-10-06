---
id: resources-js-pages-produto-etiquetas-index-charter
page: /labels/show
component: resources/js/Pages/Produto/Etiquetas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/produto-acoes.jsx
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-etiquetas.md
owner: wagner
status: draft
last_validated: "2026-10-06"
parent_module: Produto
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — Imprimir etiquetas (DRAFT · `/labels/show`)

> **Status:** draft. Recortado do charter F1 `cowork-inbox/produto-telas-novas/Etiquetas.charter.md`
> (R1–R8) e da ficha `04-etiquetas.md` do playbook Produto. Casos: [`Index.casos.md`](Index.casos.md) ·
> contrato: `governance/design/contracts/produto-etiquetas.contract.json` (derivado no `_saida-07`).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Montar uma folha de etiquetas certa de primeira. Quem etiqueta o estoque não volta para conferir na
tela, volta para reimprimir, e reimpressão é papel perdido.

## Goals — Features (faz, nesta fase)

- **R1** Uma linha por produto, com nº de etiquetas próprio. O produto entra por busca (nome ou SKU)
  ou vem da compra/produto pela URL (`purchase_id`, `product_id`).
- **R2** O preço na prévia é o do grupo de preço da linha, o mesmo que a impressão sairia.
- **R3** "Com imposto" / "Sem imposto" vale para a folha inteira e aparece sempre.
- **R4** Cada informação da etiqueta tem liga/desliga e corpo em pt; desligada, o corpo fica desabilitado.
- **R5** O modelo vem dos modelos de etiqueta do negócio (e do sistema); a prévia pagina por folha real
  e o botão diz quantas folhas vão sair.
- **R6** Lote, validade e data de embalagem só aparecem na etiqueta se preenchidos.
- **R8** Sem produto, a tela explica o caminho e "Imprimir" fica desabilitado.
- Gate `print_labels.access`.
- Atrás da flag MWART por negócio `mwart.produto_etiquetas` (`MWART_PRODUTO_ETIQUETAS` + `_BIZ`, lista só na env).
  Desligada ou negócio fora da lista: a Blade de sempre. Decisão [W] 2026-10-06: biz=1 primeiro.

## Non-Goals (nesta fase)

- ❌ Mudar o que sai impresso: a folha continua em `/labels/preview`, mesmo código (regra mestre de valor).
- ❌ Calcular preço no navegador: a linha chega com o preço pronto do servidor.
- ❌ Editor de layout de etiqueta ou cadastro de modelo (é `/barcodes`).
- ❌ Código de barras real na prévia: o traço é representação.
- ❌ Campos personalizados do produto: `preview_2.blade.php` não os imprime.
- ❌ R7 do F1 (prova de impressão com marcas de corte e tira CMYK): o DS do app não tem esses componentes.

## Anti-hooks

- ❌ Prévia com preço diferente do impresso. Guarda: UC-PETQ-02 e UC-PETQ-03.
- ❌ Produto, grupo ou modelo de etiqueta de outro negócio. Guarda: UC-PETQ-01 e UC-PETQ-04.
- ❌ Abrir sem `print_labels.access`. Guarda: UC-PETQ-01.
- ❌ Virar para todos os negócios no deploy, ou escrever business_id no código. Guarda: UC-PETQ-01.
