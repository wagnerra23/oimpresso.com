---
slug: 0445-comparacao-de-pixel-sai-do-projeto
number: 445
title: "A comparação de pixel (snapshots do VRT) sai do projeto; os testes de comportamento no browser ficam"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-04"
module: governance
quarter: 2026-Q4
tags: [governanca, visual, vrt, snapshot, baseline, ci]
related_adrs: [0108-regressao-visual-pest-browser-tier-2, 0409-zero-baseline-de-tolerancia-conformidade-absoluta, 0411-snapshot-de-pixel-fora-do-passo-3-da-0409]
supersedes: [0411-snapshot-de-pixel-fora-do-passo-3-da-0409]
supersedes_partially: [0108-regressao-visual-pest-browser-tier-2]
pii: false
review_triggers:
  - "o [W] pedir de volta uma referência de pixel para alguma tela"
  - "uma regressão visual chegar a produção sem que nenhum teste de comportamento a pegue"
---

# ADR 0445 · A comparação de pixel sai do projeto

## Contexto

O workflow `.github/workflows/visual-regression.yml` fazia duas coisas no mesmo job:

1. **Comparação de pixel.** Tirava foto de telas, estados isolados e fluxos, e comparava com
   128 arquivos `.snap` versionados (22 MB) em `tests/.pest/snapshots/`. Tinha modo update para
   regravar as fotos, canário que contava telas comparadas e comentário de falha no PR.
2. **Testes de comportamento no browser.** Render de telas do Patrimônio, Financeiro, Home,
   Manufacturing e Repair, etiquetas do Purchase, contrato do sidebar (auto-rail), axe de
   acessibilidade, smoke em Firefox e WebKit, sondas de dimensão, perf budget do Lighthouse e o
   job `tema-escuro`.

O job saiu da lista de required em 2026-08-26 e a zona cinza deixou de bloquear em 2026-09-04.
Mesmo assim a parte de pixel continuava rodando e deixando PRs vermelhos por dívida herdada. Em
2026-11-04, a tela `Fiscal/Nfse` dava exatamente 2,0508% no PR #10292 e no PR #10152, de 29/10,
os dois sem relação com ela.

A [ADR 0411](0411-snapshot-de-pixel-fora-do-passo-3-da-0409.md) tinha decidido em 2026-09-23 que
os snapshots continuavam como referência do `visual-regression`.

## Decisão

[W], 2026-11-04, textual: *"visual regresao ja foi desativado, removaporfavor do projeto"*. Escopo
escolhido pelo [W] na mesma conversa: **só a parte de pixel**.

1. Saem a comparação de pixel e tudo que só ela usava: os testes `PixelBaselineTest`,
   `IsolatedStatesBaselineTest`, `FinanceiroFlowBaselineTest`, `ComprasFlowBaselineTest` e
   `SellsCreateFlowBaselineTest`; as fotos em `tests/.pest/snapshots/`; o modo update que
   regravava baseline e abria PR; o canário de telas comparadas; os uploads de foto e de
   diff-view; os lints dos manifestos de fluxo e estado; o `snap-diff`; o retry de flake das
   suítes de pixel; os atalhos `visreg:*` do `package.json`.
2. Ficam os testes de comportamento no browser, no mesmo arquivo, que passa a se chamar
   "Browser E2E (Pest 4 Browser)". O nome do arquivo e o id do job `visual-regression` ficam por
   ora, porque dezenas de scripts e documentos citam esse nome.
3. Esta ADR substitui a 0411 e substitui em parte a [ADR 0108](0108-regressao-visual-pest-browser-tier-2.md),
   na parte em que ela criou o snapshot de pixel como gate.

## Consequências

- Mudança visual não intencional deixa de ser pega por comparação de imagem. O que sobra são os
  testes de comportamento e a medição advisory protótipo × aplicação da ADR 0408.
- O classificador `scripts/governance/ui-impact.mjs` deixa de exigir foto para cada tela do
  manifesto e deixa de reprovar tela do raio sem entrada no manifesto.
- Ficam para um PR seguinte, sem efeito de pixel: a rota `/_visreg-state` e o manifesto
  `tests/Browser/visreg-states.json`, os seeders `Visreg*Flow*`, as funções de zona cinza do
  `tests/Browser/Support/VisregThreshold.php` e a renomeação do arquivo e do job.
