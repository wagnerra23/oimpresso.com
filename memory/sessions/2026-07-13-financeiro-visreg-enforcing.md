---
date: "2026-07-13"
hour: "17:19 BRT"
topic: "Encerramento da ativação enforcing de regressão visual do Financeiro"
authors: ["C", "W"]
prs: [4236, 4237, 4238, 4239]
outcomes:
  - "Baselines de estados e fluxos Financeiro armadas."
  - "Gate Financeiro promovido a enforcing."
---

## TL;DR

PRs #4236–#4239 foram mergeadas. Estados isolados e fluxos Financeiro agora possuem baseline e falham o CI quando a diferença excede os thresholds. Estados globais continuam advisory porque Oficina dark ainda apresenta drift real.

## Evidência

- `origin/main` em `3f07362fbe` contém a promoção enforcing da PR #4239.
- GitHub confirmou as quatro PRs como mergeadas; nenhuma branch de ativação tem PR aberta.

## Retomada

Investigar os cinco snapshots que mudaram no segundo update e estabilizar Oficina dark antes de ampliar a promoção global.
