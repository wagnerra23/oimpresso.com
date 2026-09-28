---
slug: 0417-aposenta-tela-piloto-0253
number: 417
title: "Critério da tela-piloto da ADR 0253 aposentado junto com a Prova Viva"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-28"
module: _DesignSystem
tags: [design-system, layout-primitives, criterio-de-pronto, financeiro]
supersedes: []
supersedes_partially: [0253-primitivos-layout]
superseded_by: []
related:
  - 0253-primitivos-layout
pii: false
---

# ADR 0417 — O critério da tela-piloto da 0253 se aposenta com a Prova Viva

> O merge do [W] é o ato (R10).

## Decisão expressa

Em 2026-09-28, [W] determinou apagar a tela `/financeiro/prova-viva` (*"ela não faz mais parte do
projeto, ficou defasada"*), removida no #8037. Em seguida: **"pode escrever a ADR aposentando o
critério da 0253"**.

A [ADR 0253](0253-primitivos-layout.md) exigia, na entrega e no item 2 do "Critério de pronto",
**"≥1 tela piloto composta 100% por primitivos"**. Esse item é **aposentado**, não transferido:

- **Ele foi cumprido.** A Prova Viva entrou em 2026-06-07 (#2372) e provou o critério enquanto existiu.
- **Não se escolhe outra tela-piloto.** A função da piloto era provar que os primitivos bastam para
  compor uma tela inteira antes de exigir isso por máquina. Essa prova foi feita. Eleger uma
  substituta só para manter o item vivo seria atribuir a uma tela real um papel que ninguém pediu.

## O que continua valendo da 0253

Tudo o mais: a camada `resources/js/Components/layout/` (hoje `box`, `stack`, `inline`, `grid`,
`container` e `text`), a regra de usar só tokens existentes, e os itens 1, 3 e 4 do critério de
pronto. O enforcement que a 0253 previa como passo seguinte segue ativo por conta própria no
`layout-primitives-guard` (`scripts/layout-primitives-guard.mjs`), que não depende de tela-piloto.

## Consequências

- Nenhum código muda com esta ADR. As referências vivas à piloto já foram retiradas no #8037
  (comentário do guard, `echo` do workflow, RUNBOOK de paridade, SDD do Financeiro).
- Menções à Prova Viva em handoffs, sessões e changelogs são fato datado e ficam como estão.
- Os protótipos do Cowork que citam o "gabarito Prova Viva 9.75" (`financeiro-page.jsx` e
  `financeiro.css`, nos espelhos de Wagner e Felipe, e o `documentacao-page.jsx`) são do Cowork e se
  corrigem lá.
