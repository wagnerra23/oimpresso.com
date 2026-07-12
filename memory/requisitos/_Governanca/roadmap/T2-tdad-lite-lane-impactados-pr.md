# T2 · TDAD-lite — lane de impactados-no-PR (modo sombra)

> ⛔ **CORTADO — 2026-07-12** (decisão Wagner, delegada a Claude "decida todas"). A avaliação adversarial do SDD pontuou T2 em **8/100**: depende de T1 (também CORTADO, 0/7 artefatos), e está fora do escopo consciente até R1+C2 fecharem. **Não executar.** Reabrir só com T1 revivido + sinal qualificado + reabertura da ADR 0314. Ver `memory/sessions/2026-07-12-sdd-avaliacao-adversarial-processo.md`.

---
roadmap_item: T2
slug: tdad-lite-lane-impactados-pr
onda: 7
status: cortado
depende_de: [T1]
destrava: []
related_adrs: [0275, 0314, 0062, 0101]
esforco_estimado: "1.5-2d codável (IA-pair) + 14d modo sombra (relógio) — flip a required SÓ via reabertura da ADR 0314"
---

> **Contexto da decisão pendente:** mesma nota do T1 — a avaliação 2026-07-01 propôs CORTE
> ([_ROADMAP.md §Decisões pendentes #2](_ROADMAP.md)); este escopo existe pra Wagner decidir
> com números. **T2 só começa depois de T1 publicar 7 artefatos válidos.**

## Problema (2-3 frases)
O feedback de teste por PR hoje é o lane sqlite curado (subset FIXO — não sabe o que o PR
tocou) + a nightly CT100 (D+1, depois do merge). Regressão introduzida num PR só aparece no
floor da madrugada seguinte — caro de bissectar e já em main. Um lane que roda SÓ os testes
que exercitam os arquivos do diff daria sinal em minutos, no PR, sem pagar a suíte inteira.

## Fonte canônica (ADR 0275 §5 linha 89)
`T2 | TDAD-lite (lane impactados-no-PR) | modo sombra 14 dias + falso-negativo <1%
(regressão que o lane deixou passar e a full pegou). Auto-demoção pré-autorizada:
falso-negativo >1% em janela de 7 dias pós-flip → volta a advisory imediatamente, sem ADR`.

## Como (desenho técnico honesto)
1. **Workflow novo `tdad-lite.yml`** (advisory de nascença, `continue-on-error` NUNCA —
   advisory = fora da lista required, mas o job em si falha vermelho de verdade; lição
   mwart-gate/ADR 0271: gate que não pode falhar é teatro):
   a. materializa `governance/test-map.json` da órfã (mesmo trilho floor/coverage);
   b. `git diff --name-only origin/main...HEAD` → arquivos PHP tocados;
   c. resolve impactados no mapa (+ o próprio arquivo de teste se o diff tocar testes);
   d. roda o subconjunto no lane sqlite existente (`--filter` por lista) — MySQL real só
      no CT100 (ADR 0101/0062), então o lane herda as MESMAS limitações do sqlite curado:
      testes mysql-only ficam fora (registrados como `unmapped_skipped` no summary, nunca
      silenciosamente).
2. **Fallback fail-open declarado**: mapa ausente/stale >7d OU diff toca arquivo sem entrada
   no mapa → lane roda vazio com aviso `::warning::` explícito + summary marca
   `verdict: no_signal` (nunca verde-mentiroso — verde só quando rodou ≥1 teste impactado).
3. **Métrica de sombra**: cada run grava `{pr, sha, tests_run, verdict}` em artifact;
   script `tdad-shadow-report.mjs` cruza com o floor da nightly: regressão que entrou em
   main E o lane tinha dado verde = **falso-negativo** (o número que decide a promoção).
4. **Read-side scorecard**: NENHUM em v1 (evita métrica de forma). O relatório de sombra é
   o artefato de decisão.

## DoD (objetivo, checável)
1. Lane roda em todo PR que toca `**/*.php` e publica summary com `tests_run` +
   `unmapped_skipped` + `verdict` — 0 falha de infra em 14 dias corridos (modo sombra).
2. `tdad-shadow-report.mjs` produz FN medido sobre a janela: **FN <1%** é o critério da
   ADR 0275 §5 pra sequer PROPOR flip.
3. Latência do lane p95 ≤10min (senão não compete com o sqlite curado e vira ruído).

## Promoção — trava explícita (ATENÇÃO)
Mesmo com FN<1% + 14d sombra, **flip a required exige reabrir a ADR 0314** (política vigente:
required = só Tier-0 dinheiro/PII/multi-tenant/fiscal; TDAD-lite é quality). Caminho: nova
ADR/emenda append-only + janela + flip Wagner — NUNCA merge no calado (precedente proibido:
[proibições §ideias descartadas, entrada 2026-07-01](../../../proibicoes.md)). A auto-demoção
pré-autorizada da 0275 §5 continua valendo APÓS um eventual flip.

## Riscos / kill-criteria
- **FN estrutural**: mapa é do sqlite? NÃO — o mapa T1 vem da nightly MySQL (suíte inteira),
  mas o lane T2 EXECUTA em sqlite. Teste impactado que só passa/falha em MySQL = FN inevitável.
  Se o FN medido vier majoritariamente dessa classe → T2 degrada pra "lane informativo"
  (comenta no PR quais testes rodariam) ou MORRE. Medir antes de julgar.
- **Staleness do mapa**: renamed/deleted tests entre o mapa (D-1..D-7) e o PR → resolver
  com `existsSync` no resolvedor (teste sumido = drop com log, nunca crash).
- **Custo CI**: lane extra em todo PR. Se p95 >10min ou fila de runners congestionar,
  restringir trigger a paths de maior risco (`app/**`, `Modules/**/Services/**`).
- **Zero gate novo required**: ADR 0314 D-1/lei de fusões — se um dia promover, avaliar
  FUSÃO num required existente antes de gate novo.

## Dependências
- `depende_de: [T1]` — duro: sem mapa com 7 publicações válidas, o lane não tem insumo.

## Esforço (recalibrado ADR 0106)
- **Codável**: ~1.5-2d IA-pair (workflow + resolvedor + shadow-report + meta-teste do
  resolvedor com fixtures de mapa).
- **Relógio**: 14d modo sombra (ADR 0275 §5) + decisão Wagner. FN<1% não acelera com IA.
