---
slug: 0292-errata-0291-distiller-freshness-scorecard-deterministico
number: 292
title: "Errata 0291 D-D — distiller_freshness no scorecard mede staleness vs doc mais novo do módulo (determinístico, não vs 'hoje')"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-06-19"
module: jana
tags: [memoria, distiller, freshness, scorecard, determinismo, sdd, errata, keystone]
supersedes: []
superseded_by: []
related:
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
pii: false
---

> **Errata de [ADR 0291] D-D**, proposta por [CL] em 2026-06-19, **autorizada por [W]** no chat 2026-06-19
> ("Sim — stale vs doc mais novo do módulo"). O corpo do 0291 é append-only (Constituição Art. 3); por isso
> esta correção vem em ADR nova `kind: errata`, **não** em edição inline (gate `Append-only canon`). Ratificação = merge.

# ADR 0292 — Errata 0291 D-D: `distiller_freshness` no scorecard é determinístico

## O que o 0291 D-D dizia (e por que precisa de errata)

O [ADR 0291] D-D, ao contratar a métrica `distiller_freshness`, descreveu o valor medido como
*"nº de portas com `distilled_at` > 7d (stale)"* — um critério **relativo a "hoje"**. Ao implementar (PR
do PR-D), isso colidiu com uma invariante dura do scorecard:

- `governance/sdd-scorecard.json` é **determinístico por contrato** (`_meta.determinismo`: *"sem timestamps
  no corpo — re-run sem mudança no repo = diff vazio"*; o `sdd-scorecard.yml` avisa quando o JSON commitado
  "defasa"). Um valor relativo a "hoje" **mudaria todo dia** mesmo sem mudança no repo → quebraria a
  invariante e faria o aviso de defasagem disparar perpetuamente.

## Correção (o critério, por runtime)

`distiller_freshness` continua lendo o `distilled_at:` das portas e continua **anti-stale** (`not_yet_measured`
até o 1º carimbo, como o floor do [ADR 0279]). Muda só **como** a staleness é definida, e isso difere por runtime
**de propósito**:

- **Scorecard** (commitado, determinístico): uma porta é *stale* quando seu `distilled_at` ficou **>7d atrás
  da data-git do doc mais novo do módulo** — um **fato imutável da história**, não o relógio. Mede *"o distiller
  ficou pra trás dos eventos?"*, que é exatamente o D-3 do [ADR 0270] (*"destilação que para = porta envelhece"*).
  Portas **sem** `distilled_at` entram só no `detail` (cobertura pendente), **não** contam como stale (rollout
  não-punitivo, espelha `front_door` 62%→100%).
- **Health-check** (`jana:health-check`, runtime, **não-commitado**): o alarme **DURO** pode usar **"hoje"** —
  conta portas com `distilled_at` **>7d vs agora** (espelha `checkProfileDrift`, que compara `gerado_em` vs
  `now()->subDays(7)`). >0 → derruba exit code + ALERT de cron.

Ambos leem o **mesmo** `distilled_at` e disparam a **mesma** ação (rodar `jana:distill-module-truth` na porta
atrasada). A diferença de âncora (história do git × relógio) é o que reconcilia *determinismo no placar* com
*alarme vivo na operação*.

## O que NÃO muda do 0291

Tudo o mais do D-D e do contrato (D-A input, D-B output mutável + `distilled_at` + proveniência, D-C comando +
cron, D-E guardas Tier 0 G5/PII/CT100) permanece como em [ADR 0291]. Esta errata é cirúrgica: só o critério de
staleness do **scorecard**.

## Implementado em

PR-D do keystone — `scripts/governance/sdd-scorecard.mjs` (`measureDistillerFreshness()` + node test
`sdd-distiller-freshness.test.mjs`) e `Modules/Jana/Console/Commands/HealthCheckCommand.php`
(`checkDistillerFreshness()` + helper puro `distillerFreshnessStats()` + Pest).

## Referências
- [ADR 0291] contrato do distiller-módulo-verdade (alvo desta errata)
- [ADR 0270] D-3/D-5 (ciclo de vida; "destilação que para = porta envelhece")
- [ADR 0279] padrão anti-stale `notYet`→`measured` + determinismo do scorecard
- [ADR 0275] scorecard SDD canônico (onde vive a métrica, stream MEM)
