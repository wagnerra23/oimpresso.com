---
slug: 0408-medicao-de-paridade-agendada-advisory-emenda-0290
number: 408
title: "Medição de paridade protótipo×prod pode ser AGENDADA e advisory — o que a 0290 recusa é o GATE"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-21"
module: governance
tags: [design-system, paridade, fidelidade-visual, cadencia, advisory, emenda]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0290-fidelity-lock-v0-recusado
  - 0390-emenda-0384-smoke-em-ambiente-controlado
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
pii: false
---

# ADR 0408 — Medição de paridade agendada é permitida; o gate continua recusado

> **Emenda à [ADR 0290](0290-fidelity-lock-v0-recusado.md), que permanece `recusado` e NÃO é
> superseded.** O que esta ADR separa é uma distinção que a 0290 não precisou fazer em
> 2026-06-18, porque na época só existia a proposta de gate: **medir e registrar** ≠ **bloquear
> merge**. A recusa do gate fica inteira. A medição agendada passa a ser permitida, advisory.

## Contexto

O `scripts/design/design-diff-lote.mjs` nasceu em 2026-09-06 (item 4 autorizado por [W]) para
fazer, em lote, o mesmo gesto que vinha sendo feito uma tela por vez à mão: renderiza o
protótipo do espelho e o vivo, injeta a **mesma sonda** (`design-diff.mjs --probe`) nos dois, e
grava o veredito por dimensão como dado.

Em 2026-09-18 a auditoria do loop de aprendizado mediu o estado dele, com `rg --hidden` no repo
inteiro:

- **nenhum** workflow o invoca;
- **nenhum** cron (conferido em `memory/governance/AUTOMATIONS.md`);
- **nenhum** consumidor do resultado fora do próprio script;
- o `--selftest` dele nunca rodou em CI — a única ocorrência do nome num workflow era uma
  linha de **comentário** ([#7551](https://github.com/wagnerra23/oimpresso.com/pull/7551) ligou
  o selftest; hit em comentário não é invocador, §5 2026-07-28).

Ou seja: a máquina existia, media certo, e o resultado morria no disco. É o caso que a regra
"LIGUE A MÁQUINA" (item 2, `memory/proibicoes.md`) chama de bug, não de neutralidade.

O bloqueio para ligá-la era a **0290** — reforçada pela lápide §5 2026-07-09, que estende a
recusa a *"render pareado não-hermético, **CI ou agendado**, sob qualquer nome"*. E o lote é
não-hermético por construção: exige auth (`/_visreg-login`) e CDN (o shell do Cowork carrega
React/Babel/Tailwind).

O bloqueio foi apresentado a [W] em 2026-09-18, com a medição acima. A decisão veio em
**2026-09-21**, de uma palavra: **"ligue"**. As medições deste documento são de 18/09 e estão
datadas como tais; o que é de 21/09 é a decisão.

## Decisão

**Medição de paridade protótipo×prod pode rodar agendada, desde que seja ADVISORY.** O gate
continua recusado, com o critério de reabertura da 0290 intacto.

| | permitido | proibido |
|---|---|---|
| **medir e registrar** (advisory, não bloqueia merge) | ✅ esta ADR | — |
| **bloquear merge / required** por fidelidade de render pareado | — | ❌ 0290, critério de reabertura = check **hermético** (render-free, sem auth/CDN, sem mapa de cor à mão) |

**Os 3 motivos da 0290 continuam válidos — e é por endereçá-los que a medição passa:**

1. *"Verde quando os dois lados renderizam erro"* — o lote **não** dá veredito nesse caso: o
   **D0** exige que a identidade da view seja provada por copy do contrato, e sem isso grava
   `⛔ NÃO MEDI — identidade da view não provada` (11 das 134 linhas do corpus de 2026-09-18).
   Somado a isso, o step de CI **falha visivelmente** se o app não subir, em vez de medir a
   ausência do app contra o protótipo.
2. *"Match OKLCH↔Tailwind é tautológico (mapa à mão)"* — não existe mapa. O `design-diff`
   compara **lightness** e **hue** direto do valor computado; não há whitelist cor↔classe.
3. *"'Falha na 1ª divergência injustificada' é backdoor de prosa"* — não há campo de
   justificativa, e nada falha: o lote grava dado.

**O que a 0290 exigia e NÃO foi cumprido** — e por isso o gate segue proibido: o critério de
reabertura dela é *hermético* (render-free, sem auth/CDN). O lote endereça os motivos por
**outro caminho** (D0 + fail-visível), não por hermetismo. A distinção importa: motivo
endereçado libera **medir**; critério cumprido liberaria **bloquear**. Só o primeiro aconteceu.

## Como foi ligado

Estendendo o dono, nunca abrindo paralelo ([LC-19](../LICOES_CODE.md)): o
**`design-smoke-ci.yml`** ([ADR 0390](0390-emenda-0384-smoke-em-ambiente-controlado.md)) já sobe o app efêmero
com migrate + seeds Visreg + `build:inertia` + Playwright + auth-bridge — exatamente o aparato
que o lote precisa — e já é **advisory por desenho** (`push: main` + `workflow_dispatch`, nunca
`pull_request`).

O `visual-regression.yml` foi descartado como hospedeiro **por medição, não por gosto**: 42
condicionais de `github.event_name` em 56 steps, todas escritas para `pull_request`/
`workflow_dispatch` — acrescentar `schedule` exigiria revisar 42 ramos do gate visual do
projeto. No `design-smoke-ci` são zero.

Cadência: **semanal** (segunda 04:40 UTC), não nightly. O universo do lote são as telas
`anchored` do `application-report.json`, e elas mudam por **trabalho humano**, não por relógio.

## Consequências

- ✅ O resultado passa a ter **consumidor**: `scripts/design/lote-resumo-ci.mjs` publica no step
  summary o **denominador primeiro**, os vereditos com soma conferida, as telas medidas contra
  mais de uma fonte (nomeando as **contraditórias**) e o **corte por campo**.
- ✅ Três regras ficam mecânicas em vez de lembradas:
  - **denominador junto do número** — o universo muda várias vezes ao dia (medido em
    2026-09-18: 5 mudanças, e o [#7518](https://github.com/wagnerra23/oimpresso.com/pull/7518)
    moveu 59 `anchored`→`compared` de uma vez). Tela que **sai** do denominador leria como
    "resolvida" (§5 2026-07-27);
  - **chave (tela, fonte)** — 7 telas do corpus aparecem 2× com fontes distintas e **2 com
    vereditos opostos** (`Fiscal/Cockpit`: "sem bug" por uma âncora, "4 bugs" por outra);
  - **corte por CAUSA, nunca "N telas divergentes"** — 276 dos 885 achados (31%) são o mesmo
    quarteto `shell.*.presenca` repetido em 69 telas: uma causa sistemática, não 69 trabalhos.
- ✅ **Sem nota de fidelidade**, por construção — §5 2026-07-17 proíbe agregar esses vereditos
  num número único, porque não são comensuráveis.
- ✅ **Sem baseline commitada** — dado bruto vai para artifact. Baseline congelada está vetada
  por [W] e o §5 tem 3 lápides de rebake que não fecha (2026-08-24 · 2026-08-26 · 2026-09-02).
  "Antes" honesto = o **mesmo comando** em dois SHAs.
- ⚠️ **Não cria task.** Quem decide o que vira trabalho é humano — o padrão do `mv-metabolismo`
  (o cron propõe, o merge de [W] aprova, a sessão pós-merge cria). Isto aqui só publica.

## Residual honesto

- **Acoplamento declarado:** se `select.count` for 0, o boot do job é pulado e o lote não roda.
  Medido em 2026-09-18: `--select` devolve 4. Se um dia der 0, o silêncio é visível (a seção do
  resumo não é emitida), mas ninguém alarma.
- **Cobertura real é menor que o universo:** das 68 `anchored`, apenas **23 são executáveis**
  hoje — 39 sem rota derivável e 11 com rota parametrizada. E **58 das 68 não têm contrato D0**,
  então a maioria do que é medido não tem identidade de view provada. O lote reporta isso; não
  esconde.
- **O CDN continua sendo o ponto frágil** que a 0290 nomeou. Uma indisponibilidade dele degrada
  o lado design — e é o D0 que impede isso de virar veredito falso, não o hermetismo.

## Anchor

**Implementado em:** `.github/workflows/design-smoke-ci.yml` (trigger `schedule` + 3 steps) ·
`scripts/design/lote-resumo-ci.mjs` (consumidor, `--selftest` com 14 casos) ·
`scripts/governance/gates-registry.json` (entrada `design-smoke-ci.yml` emendada).
