---
slug: 0380-errata-0292-distiller-freshness-health-check-advisory
number: 380
title: "Errata 0292/0291 D-D — `distiller_freshness` no health-check vira ADVISORY (o alarme DURO cobra um remédio que prod não pode executar)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-08-26"
module: jana
tags: [memoria, distiller, freshness, health-check, alarme, advisory, errata, keystone]
supersedes: []
superseded_by: []
related:
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0292-errata-0291-distiller-freshness-scorecard-deterministico
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
pii: false
---

> **Errata de [ADR 0292] / [ADR 0291] D-D**, medida por [C] e **autorizada por [W]** no chat 2026-08-26
> (aprovação da recomendação "(b) + (e)" após a medição registrada abaixo). O corpo das duas é append-only
> (Constituição Art. 3); por isso a correção vem em ADR nova `kind: errata`. **Ratificação = merge [W]** —
> mesmo mecanismo da 0292. Se [W] não mergear, o check segue DURO e nada muda.

# ADR 0380 — `distiller_freshness` do health-check é ADVISORY

## O que as ADRs anteriores decidiram

A [ADR 0291] D-D contratou o `checkDistillerFreshness()` do `jana:health-check` como **gêmeo DURO**
(*"destilação que parou derruba o exit/cron (D-3)"*). A [ADR 0292], ao separar o critério do scorecard
(determinístico, vs data-git) do critério do runtime (vs "hoje"), **reafirmou** o health-check como duro:
*">0 → derruba exit code + ALERT de cron"*.

## O que a medição de 2026-08-26 mostrou

O check está vermelho **de hora em hora** (`{"ok":false,"value":13,"threshold":0}`), e a mensagem nomeia o
remédio `jana:distill-module-truth`. Medindo os dois lados:

| O que | Medido |
|---|---|
| Portas com `distilled_at` | **14** (`stamped=14, stale=13` — bate com o `value` do check) |
| Quem escreveu esses carimbos | **13 de 15 por HUMANO**; só 2 (`PaymentGateway`, `Whatsapp`) trazem o literal `distilled_by: jana:distill-module-truth` |
| Portas que uma rodada `--all` reescreveria | **58 de 80** (não 13) — 44 delas **nunca destiladas** |
| Custo dessa rodada | ~324 KB de doc canônico; **293 seções** fora das 4 canônicas apagadas; **57** portas perdem o campo `id:` (consumido por `doc-id-index.mjs` no CI) |
| Efeito sobre o próprio alarme | `stamped` 14 → 58; sem cron, em 8 dias são **58 stale** — 4,5× o vermelho de hoje |

E o remédio **não é executável em produção por construção**: o cron existiu, rodou diário de 22/jun a
01/jul, e foi re-comentado em [#3545](https://github.com/wagnerra23/oimpresso.com/pull/3545) porque o write é
`file_put_contents` na árvore deployada, **sem git** — o deploy seguinte reseta. ~500 reescritas, 100%
perdidas. As pré-condições de religação estão nomeadas no próprio [Kernel](../../app/Console/Kernel.php)
(venue git-backed + fluxo de skim + `--module=X`, **nunca** `--all`) e **nenhuma foi construída**.

## A decisão

`checkDistillerFreshness()` passa a emitir `'advisory' => true`. Consequência mecânica (o comando já
respeita o campo em `allChecksOk()` e no filtro do ALERT): **continua reportando na tabela e no `--json`,
deixa de derrubar o exit code e de disparar o ALERT de cron.**

### Por que advisory e não "consertar o número"

Três razões independentes, cada uma suficiente:

1. **O alarme cobra ação que prod não pode executar.** Alarme cujo remédio é estruturalmente indisponível
   não é alarme — é ruído com carimbo de gate. É a mesma família do gate-de-teatro que a
   [ADR 0271] deletou, pelo lado oposto: em vez de verde-que-não-fica-vermelho, vermelho-que-não-fica-verde.
2. **O ruído está afogando uma sentinela viva.** O check derruba o exit do `jana:health-check` **horário**,
   cujo `onFailure` loga `Schedule jana:health-check (horário · sentinela inbound) FALHOU`. Essa cadência
   existe por causa do [#2726](https://github.com/wagnerra23/oimpresso.com/pull/2726) — recebimento WhatsApp
   morto 3 dias sem ninguém ver. Um alarme real de inbound sai **hoje no mesmo formato** do ruído horário.
3. **O check mede outra coisa do que o nome diz.** 13 dos 15 carimbos são humanos; o mais fresco
   (`Ponto`, 2026-08-21) foi bumpado num PR de RUNBOOK cujo `distilled_by` declara
   *"manual [C] — redestilação PARCIAL"*. `distiller_freshness` mede, na prática, **trabalho humano em
   BRIEFING** — não a execução do distiller.

### O que esta errata NÃO muda

- O critério **determinístico do scorecard** (0292) permanece intacto — só o runtime muda.
- O contrato do distiller (0291 D-A/D-B/D-C/D-E) permanece: output mutável, `distilled_at` + proveniência,
  guardas Tier 0 (PII, CT100-gated), cron desligado.
- O `--all` permanece **proibido** como 1ª destilação (Kernel + medição desta ADR, por dois caminhos
  independentes).
- Advisory **não** é "desligado": o número segue visível na tabela, no `--json` e no `HealthSnapshotService`.

## Decisão em aberto (não decidida aqui)

A recomendação aprovada por [W] foi "(b) + (e)". Esta ADR entrega **só o (b)**. O **(e)** — legitimar a
**redestilação humana parcial por PR**, que já é a prática viva em 13 de 15 portas, com o `distilled_by`
declarando o que foi re-medido e o que não foi — exige reconciliar a linha
*"NUNCA forjar o carimbo à mão"* de [`doc-id-stamp.mjs`](../../scripts/governance/doc-id-stamp.mjs) com o
que o corpus faz. Isso é ADR própria, com o [W] decidindo o texto da regra — não entra por PR de código.

Enquanto (e) não é decidido, o campo `distilled_by` continua sendo o registro honesto de quem carimbou.

## Consequências

- **Ganho:** a sentinela horária de inbound volta a ser legível; o alarme para de cobrar o impossível.
- **Risco aceito:** destilação parada deixa de paginar. Mitigado porque a métrica **determinística** do
  scorecard (0292) segue medindo o mesmo `distilled_at` contra a data-git, e essa é a régua que o stream
  MEM já governa.
- **Reabertura:** promover de volta a DURO exige que as pré-condições do #3545 existam (venue git-backed +
  skim) — aí o remédio passa a ser executável e o alarme volta a fazer sentido.

## Implementado em

`Modules/Jana/Console/Commands/HealthCheckCommand.php` (`checkDistillerFreshness()` — `advisory => true`)
+ Pest `Modules/Jana/Tests/Feature/Smoke/DistillerFreshnessAdvisoryTest.php` (bite-test: prova que o check
não derruba o gate).
