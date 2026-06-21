---
slug: 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
number: 275
title: "Scorecard SDD canônico — 10 métricas com catraca, composta v1/v2 (regimes não comparáveis), regra de armamento (3 medições) e calendário único de promoções a required"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-12"
module: governance
quarter: 2026-Q2
tags: [governance, sdd, scorecard, metricas, catraca, required, promocoes, anti-stale, verificacao]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0261-enforcement-faseado-gates-ci"]
pii: false
---

# ADR 0275 — Scorecard SDD canônico: 10 métricas, composta v1/v2, armamento e calendário único de promoções

## Contexto

A auditoria de 2026-06-12 ([session log](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md)) deu nota composta **59/100** ao sistema SDD: a spec mente (campo `Implementado em` majoritariamente placeholder), a suite mente (full-suite nunca rodou verde em DB real; canário RAGAS compara mock com mock) e o conhecimento drifta (módulos citando `Modules/X` inexistente). O plano de reestruturação ([plano-mãe](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md), Semana 0, frente **GT-G1**) exige um scorecard único que torne a reestruturação **medível, testável e garantida** — e um calendário de promoções a required que evite o precedente do `visual-regression` (promovido e mergeando vermelho 2× em 24h, PRs #2544/#2548) e a promotion-fatigue do decisor único.

**Números re-derivados do repo real em 2026-06-12 @ `afecf98f6`** (regra anti-stale — nunca confiar em número de plano):

- **anchor_coverage**: 57 SPECs em `memory/requisitos/*/SPEC.md`; **15 com campo** `Implementado em` / 42 sem; **84 campos** no total = **48 placeholder + 36 preenchidos**; dos 36 preenchidos, só **18 são estritos** (todos os paths citados existem no disco; 7 citam ≥1 path inexistente; 11 sem path repo-relativo parseável). Denominador: **769 headings de US** (903 US-ids distintos citados).
- **ghost_count**: `node scripts/governance/knowledge-drift.mjs` → **27 nomes ghost distintos**, citados por **39 módulos**.
- **front_door_coverage**: mesmo script → **64% (39/61)** — ADR 0270 citava 62% (36/58); número moveu, confirmando a regra anti-stale.
- **full_suite_pass_rate**: inexistente — `ci.yml` roda subset hardcoded (linha 95) com `--no-coverage`; `coverage: none` (linha 29). Proxy do débito: **1.422** `markTestSkipped` e **84 arquivos** com `Business::first()` cru.
- **ragas real**: canário atual é mock com scores fixos 0.85/0.78 (`jana-ragas-gate.yml` linha 162; `scripts/jana-ragas-runner.py` linha 26).
- **Branch protection real** (gh api 2026-06-12): **16 required contexts**, `enforce_admins: true`.

## Decisão

### 1. Scorecard único

`governance/sdd-scorecard.json` é a **fonte única** do estado SDD, gerado por agregador node sem deps (frente GT-G2). Histórico em `mcp_sdd_scorecard_history` (1 row/dia, frente GT-G7). Cada métrica carrega: `value`, `baseline`, `target`, `direction`, `armed`, `valid_measurements`, `source`, `measured_at`.

### 2. As 10 métricas (fonte · baseline · alvo · cadência · catraca)

| # | Métrica | Fonte (medidor) | Baseline (reconhecimento 2026-06-12) | Alvo | Cadência | Catraca |
|---|---|---|---|---|---|---|
| 1 | `anchor_coverage` | `anchor-lint.mjs` (SA) sobre SPECs | estrito: 18 anchors válidos / 769 US (~2,3%); 48/84 campos placeholder | 100% (com `_pendente_` como estado válido de 1ª classe) | por PR + nightly | só sobe |
| 2 | `full_suite_pass_rate` | nightly MySQL diagnóstica (FV-F3, artefato JUnit) | **nunca medido** — 1ª nightly real define | 100% do não-quarentenado | nightly | só sobe |
| 3 | `n_quarantine` | contagem `@group legacy-quarantine` | 0 (grupo ainda não existe; pico esperado na quarentena em massa Q3) | 0 pós burn-down | por PR | só diminui (após pico Q3) |
| 4 | `coverage_pct` | `pest --coverage` (pcov) na nightly | 0 — sem instrumentação (`coverage: none`) | só sobe; sem alvo numérico em v1 | nightly | só sobe |
| 5 | `ghost_count` | `knowledge-drift.mjs` (identity-drift) | **27** nomes ghost / 39 módulos citantes | 0 | por PR (catraca por módulo) + nightly | só diminui |
| 6 | `front_door_coverage` | `knowledge-drift.mjs` (cobertura de porta) | **64%** (39/61) | 100% | nightly / health-check | só sobe |
| 7 | `recall_eval_violations` | golden set recall (KL-C2; depende do alias map) | inexistente — golden set não criado | 0 | diário pós-C2 | só diminui |
| 8 | `ragas_real_uptime` | `jana-ragas-canary` modo REAL (KL-D1..D4) | 0% real (mock-com-mock hoje) | ≥95% em janela 30d (janela conta SÓ após regime congelar em D4) | diário | só sobe |
| 9 | `drift_alarms` | protection-drift + watchdog de staleness (GT-G4) | inexistente — lista real congelada: 16 required contexts | 0 alarmes abertos | diário | n/a — alarme advisory **perene**, nunca required |
| 10 | `backfill_error_rate` | ledger do refutador (GT-G5, `governance/sdd-verification-ledger.json`) | sem lotes — ledger nasce vazio | <2% | por lote IA | só diminui |

**Regra de baseline anti-stale:** o baseline OFICIAL de cada métrica é capturado na **1ª medição real da fonte** (script/workflow rodando), nunca copiado deste ADR ou de plano. Os números acima são reconhecimento de campo (método documentado no Contexto) e servem só pra dimensionar o trabalho.

### 3. Regra de armamento

Métrica só **ARMA** (pode reprovar PR / disparar alerta / entrar na composta) após **3 medições válidas consecutivas** da fonte real — válida = fonte executou sem erro, em modo não-mock, com valor não-nulo. Antes disso fica `armed: false` no JSON: é reportada, mas não pune. Fonte que para de rodar >48h dispara o watchdog (métrica 9) e a métrica **desarma** até nova sequência de 3.

### 4. Composta v1/v2 — regimes NÃO comparáveis

- Normalização por métrica: `score_i = clamp((value - baseline) / (target - baseline), 0, 1) × 100` para métricas "só sobe"; invertida para "só diminui"; binária para alarmes (100 se 0 abertos, senão 0) e threshold (métrica 10: 100 se <2%, senão 0).
- **v1** = média simples das métricas **armadas** (sem pesos — peso é opinião, média simples é auditável), rotulada `sdd_score_v1 (k/10)` com k = nº de armadas.
- **v2** = mesma fórmula quando **10/10 armadas**. Rótulo `sdd_score_v2`.
- **Proibido** comparar v1 com v2 ou plotar trend-line cruzando a fronteira: o denominador muda conforme métricas armam, então v1 de hoje não é comparável nem com v1 de semana passada se k mudou. Todo relatório imprime o k junto do score.
- A nota 59/100 da auditoria e a 90/100 de 2026-06-05 são de regimes anteriores distintos — não entram em nenhuma série.

### 5. Calendário único de promoções a required

Regras duras (valem pra TODO gate do repo, não só SDD):

1. **Gate novo nasce ADVISORY.** Sem exceção (reafirma ADR 0261/0271).
2. **Máx 1 promoção a required por semana civil.** Vaga não usada NÃO acumula.
3. **Wagner é o único que flipa branch protection.** Agente propõe com evidência; humano executa o clique.
4. Toda promoção exige: critérios objetivos abaixo **atingidos com evidência linkada** (runs, não narração) + PR atualizando `required-checks-baseline.json` (GT-G4) citando este ADR.
5. **Demoção exige PR + ADR** (fecha o buraco da demoção invisível de 1 clique) — única exceção: auto-demoção pré-autorizada do T2 (abaixo).

Critérios objetivos pré-escritos por gate (ordem proposta; gate que não atingiu critério não consome a vaga da semana):

| Ordem | Gate | Critério objetivo pré-escrito pro flip |
|---|---|---|
| R1 | full-suite MySQL não-quarentenado | 7 nightlies verdes consecutivas + p95 duração ≤25min + `strict: true` ou merge queue ativo no momento do flip (anti-race de 2 PRs verdes isolados) |
| C2 | catraca coverage | 14 dias advisory + taxa de falso-positivo <5% (alarmes incorretos / total de alarmes na janela) |
| T1 | mapa teste↔arquivo (`--coverage-php` per-test) | artefato gerado em 7 nightlies consecutivas sem erro; é insumo do T2, não vira required sozinho |
| T2 | TDAD-lite (lane impactados-no-PR) | modo sombra 14 dias + falso-negativo <1% (regressão que o lane deixou passar e a full pegou). **Auto-demoção pré-autorizada:** falso-negativo >1% em janela de 7 dias pós-flip → volta a advisory imediatamente, sem ADR |
| A10 | anchor gate (SA) | `anchor_coverage` = 100% estrito ANTES do flip + 14 dias advisory com FP <5% |
| G3 | meta-catraca de baselines | 7 execuções diárias verdes + 0 falso-positivo em 14 dias |

## Justificativa

Sem scorecard único, cada onda mede a si mesma e a soma não é auditável. Sem regra de armamento, métrica recém-nascida (1 medição, fonte instável) reprova PR alheio e queima confiança no sistema — o precedente visual-regression mostrou o custo de armar antes da hora. Sem calendário com critério pré-escrito, cada promoção vira negociação ad-hoc com decisor único fatigado. Composta com regimes separados evita o erro já cometido (comparar 90 estrutural com 59 funcional como se fosse queda).

Reabrir quando: 10/10 armadas e estáveis por um quarter (avaliar pesos na composta v3), ou se a regra de 1 promoção/semana virar gargalo comprovado com fila de gates prontos.

## Consequências

**Positivas:** reestruturação SDD vira número diário auditável; promoções deixam de ser juízo de momento; métricas não punem antes de provar estabilidade; demoção invisível fica barrada.

**Negativas / Trade-offs:** média simples ignora que anchor_coverage importa mais que coverage_pct (aceito em v1 pela auditabilidade); 1 promoção/semana atrasa gates prontos em fila; manter 10 fontes vivas tem custo de manutenção (mitigado pelo watchdog).

**Riscos mitigados:** régua stale (baseline só na 1ª medição real); gate required nascido vermelho (armamento + critérios pré-escritos); comparação de regimes (v1/v2 rotulados, k explícito).

## Referências

- [Plano-mãe da reestruturação SDD](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) — §2 (scorecard), §4 Semana 0 frente GT e Semanas 4-6 (promoções)
- [Auditoria SDD 2026-06-12](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md) — nota 59/100
- ADR 0270 — ciclo de vida da informação (define `front_door_coverage`)
- ADR 0271 — estado real dos required (fonte do "16 contexts" e do precedente de drift)
- ADR 0261 — enforcement faseado (gates nascem advisory)
- PR #2588 (GT-G5) — protocolo refutador + ledger, fonte da métrica 10
