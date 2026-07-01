---
roadmap_item: P12
slug: decay-real-ragas-recall
onda: 5
status: executed
executed_at: "2026-07-01"
depende_de: []
destrava: []
related_adrs: [0270, 0274, 0275, 0232]
esforco_estimado: "1.0d codavel + IA-pair (margem 2x) + relogio real bloqueado por Wagner (secret) + 1 nightly CT100 pra 1a medicao real"
---
# P12 · Decay real: RAGAS (secret) + recall-eval (schedule)

> **🟡 EM CURSO 2026-07-01** — **Trilha C FEITA:** recall-eval mock no CI (`jana-recall-eval.yml`) + schedule real no `Kernel.php:438` (dom 06:30 BRT). **Trilha D EM CURSO:** `OPENAI_API_KEY` confirmado como secret do repo → dispatch `jana-ragas-canary update_baseline=true mode=real` disparado; o bot popula o baseline real (>0) e mata a tautologia. **peso_real flag (passo 5): SEGURA** — Tier 0 (toca recall do chat), exige smoke CT100, nunca cego.

## Problema (o que esta quebrado, em 2-3 frases)
O decaimento por lifecycle (ADR 0270 D-4) e a deteccao de regressao de qualidade (RAGAS) existem em codigo mas NAO MEDEM NADA REAL. O gate RAGAS canary compara contra um baseline zerado (`value: 0.0`), o que torna a deteccao de regressao uma tautologia (baseline 0 → nunca alerta). O eval de recall (`jana:recall-eval`) existe, tem golden set e testes, mas nao roda em LUGAR NENHUM — nem CI, nem schedule. E o time-decay no recall (`peso_real.retrieval_enabled`) esta com flag OFF, efeito zero em prod. Resultado: tres mecanismos de "esquecer e medir o que esquece" que parecem implementados mas estao todos dormentes ou estruturalmente incapazes de falhar.

## Causa-raiz (evidencia VERIFICADA — file:line reais que voce confirmou)

**Trilha D — RAGAS baseline zerado (tautologia):**
- `governance/jana-ragas-baseline.json` — confirmado: `metrics.faithfulness.value = 0.0`, `metrics.answer_relevancy.value = 0.0`, ambos `mode: "placeholder"`, `evaluated_questions: 0`, `last_updated: null`. O `_meta.description` admite: "Enquanto values=0, runner trata como N/A (baseline desconhecido → nunca alerta regressao)".
- `scripts/jana-ragas-runner.py:108` — `if baseline <= 0: return 0.0` (delta sempre 0 quando baseline zerado).
- `scripts/jana-ragas-runner.py:124` — `is_regression = baseline_val > 0 and delta < -threshold_pct`. Com `baseline_val = 0`, a expressao e estruturalmente sempre `False`. **O gate e incapaz de falhar por construcao.**
- `.github/workflows/jana-ragas-canary.yml:89-98` — modo efetivo: cron (`schedule`) solicita `real`, mas `if effective == real && HAS_OPENAI_KEY != true → effective=mock` + warning. Sem o secret, todo run diario cai em mock silencioso. Linhas 23-25 do header confirmam: "Sem secret OPENAI_API_KEY no repo: TODO run cai em mock = $0".
- Secret `OPENAI_API_KEY` confirmado ausente: o scorecard descreve `ragas_real_uptime` como "hoje compara mock com mock" (`governance/sdd-scorecard.json:142`).

**Trilha C — recall-eval nunca roda (ghost command):**
- `Modules/Jana/Console/Commands/JanaRecallEvalCommand.php` — comando existe e e robusto (mock valida estrutura local; real consulta Meilisearch).
  - signature linha 39-42: `--mode=mock` default | `--mode=real`.
  - linha 23/40/272: modo real e rotulado "CT 100, fase 2" e a mensagem de erro de Meilisearch inacessivel diz "modo real roda no CT 100 (fase 2)" — nunca rodou em real.
- `Modules/Jana/Providers/JanaServiceProvider.php:79` — comando REGISTRADO (`JanaRecallEvalCommand::class`). **CORRECAO da evidencia:** a evidencia diz "C5 cron registrado em JanaServiceProvider.php:79". Isso NAO e um cron — e o registro do COMANDO artisan. Nao ha cron algum nessa linha.
- **DIVERGENCIA / achado pior que a evidencia:** `jana:recall-eval` NAO esta agendado no `app/Console/Kernel.php` (grep por `recall` no Kernel retorna vazio) E NAO e invocado em NENHUM workflow `.github/**` nem em `scripts/**` (greps confirmados vazios). E um comando 100% orfao de pipeline: existe + golden set (`tests/eval/recall-golden.yaml`, 188 linhas, confirmado) + testes + alias map (`governance/adr-alias-map.json`, confirmado), mas nunca executa em CI nem em schedule. Para comparacao, o irmao `jana:drift-sentinel` SIM tem schedule (`app/Console/Kernel.php:398`, `weeklyOn(0, '06:00')`).
- `peso_real.retrieval_enabled` flag OFF: `Modules/Jana/Config/config.php:737` — `'retrieval_enabled' => false`. Os multiplicadores de lifecycle existem (`lifecycle_mult`, linhas 754+: historical=0.5, superseded=0.3) mas o guard do driver so aplica se a flag estiver ON. Efeito em prod hoje: zero.

**Scorecard (read-side, confirma que nada e medido):**
- `governance/sdd-scorecard.json:128-135` — `recall_eval_violations`: `status: not_yet_measured`, `value: null`, source "golden set recall (KL-C2)".
- `governance/sdd-scorecard.json:137-144` — `ragas_real_uptime`: `status: not_yet_measured`, `value: null`, target `≥95%`, source "RAGAS canario modo REAL diario (KL-D1/D4) — hoje compara mock com mock".

## Estado atual no repo (o que voce achou ao verificar agora)
- RAGAS: infra completa (3 workflows: canary diario, gate PR/weekly, MVP), runner anti-teatro (resolve modo efetivo, mostra mock vs real no summary), `update_baseline` via workflow_dispatch funcional. So falta o secret + 1 run real pra popular baseline.
- recall-eval: comando + golden set + alias map + testes (`RecallDegradationTest.php`) prontos. Mock e auto-suficiente (so disco + YAML, sem Meilisearch) → poderia rodar em CI HOJE sem o CT 100. Falta apenas: (a) um step de CI que rode `--mode=mock`, (b) schedule/CT100 pro `--mode=real`.
- peso_real: service + config + testes prontos; flag `retrieval_enabled` OFF por design (kill-switch funcional, verificado por tinker 2026-06-12 segundo comentario config.php:731-737).
- Tudo no scorecard como `not_yet_measured` (honesto — nao mente sobre medir).

## Objetivo / DoD (criterio de pronto OBJETIVO e checavel)
Sai da tautologia e do ghost-command. Pronto quando TODAS verdadeiras:
1. **Secret** `OPENAI_API_KEY` adicionado ao repo (Settings → Secrets) — **so Wagner faz** (Vaultwarden → GitHub secret).
2. **Baseline RAGAS real populado**: `governance/jana-ragas-baseline.json` com `faithfulness.value > 0`, `answer_relevancy.value > 0`, `mode != "placeholder"`, `last_updated != null` — via `workflow_dispatch update_baseline=true mode=real` (commit do github-actions[bot]).
3. **recall-eval mock em CI**: existe um step de pipeline que executa `php artisan jana:recall-eval --mode=mock` e cujo exit 1 reprova (gate ou job advisory que aparece no PR). Hoje nao existe.
4. **recall-eval real agendado**: entry no `app/Console/Kernel.php` chamando `jana:recall-eval --mode=real` (environments `['live']`, CT100) OU workflow nightly CT100 — com 1a execucao real registrada (`recall_eval_violations` sai de `null` no scorecard).
5. **peso_real ON**: `copiloto.peso_real.retrieval_enabled = true` em runtime de prod (apos validacao homolog/CT100) — `read_path` aplica time-decay; ADR `historical` nao aparece no top-3 de query sobre tema vigente (DoD F4 do ADR 0270:157).
6. Scorecard: `recall_eval_violations` e `ragas_real_uptime` saem de `not_yet_measured` pra `measured` com `value` real (1a medicao da FONTE, nunca do plano — `baseline_rule`).

## Passos (ordenados, concretos)
1. **[Wagner, relogio real]** Adicionar `OPENAI_API_KEY` ao Vaultwarden (se ainda nao) → GitHub repo secret. Bloqueia tudo de RAGAS-real. Custo estimado ~$1.22/mes (canary diario, header do workflow:25).
2. **[codavel]** Adicionar gate/job de `recall-eval --mode=mock` na CI. Opcao minima: anexar step ao `governance-gate.yml` ou criar `jana-recall-eval.yml` (espelhar estrutura do `jana-ragas-canary.yml`). Roda `php artisan jana:recall-eval --mode=mock --json`, exit 1 reprova. Como o mock so toca disco/YAML, nao precisa Meilisearch — roda em ubuntu-latest. Comecar ADVISORY (`continue-on-error: true`) ate estabilizar, depois promover (calendario ADR 0275).
3. **[Wagner via dispatch]** Apos secret: `gh workflow run jana-ragas-canary.yml -f update_baseline=true -f mode=real`. Verifica commit do bot atualizando `governance/jana-ragas-baseline.json` com values > 0. A partir daqui o canary diario detecta regressao relativa de verdade.
4. **[codavel]** Adicionar schedule no `app/Console/Kernel.php` pra `jana:recall-eval --mode=real`, espelhando o bloco `jana:drift-sentinel` (linhas 387-408): `->weeklyOn(...)` ou diario, `->environments(['live'])`, `->timezone('America/Sao_Paulo')`, `->withoutOverlapping()`, com `onFailure` logando. ALTERNATIVA (preferida se Meilisearch read-only nao estiver acessivel do cron prod): workflow nightly no runner CT100.
5. **[Wagner + CT100, relogio real]** Validar `peso_real.retrieval_enabled = true` em homolog/CT100 (toca o coracao do chat — Tier 0, teste nunca cego). Ligar em prod via config runtime. Confirmar DoD F4 (ADR 0270:157) com 1 query de teste.
6. **[codavel]** Atualizar `governance/sdd-scorecard.json` para `measured` nas 3 metricas quando a FONTE produzir o 1o valor real (deixar o proprio pipeline carimbar, nao editar a mao — `baseline_rule` anti-stale).

## Arquivos a tocar (lista real)
- `governance/jana-ragas-baseline.json` — populado por bot via dispatch (passo 3), NAO editar a mao (`_meta.como_atualizar`).
- `.github/workflows/jana-recall-eval.yml` — **criar** (nao existe; espelhar `jana-ragas-canary.yml`). OU adicionar step em `.github/workflows/governance-gate.yml`.
- `app/Console/Kernel.php` — adicionar schedule de `jana:recall-eval --mode=real` (espelhar bloco linhas 387-408).
- `Modules/Jana/Config/config.php:737` — `retrieval_enabled => true` (apos validacao CT100; ou flip via config runtime de prod sem editar arquivo).
- `governance/sdd-scorecard.json:128-144` — `not_yet_measured` → `measured` (carimbado pelo pipeline, passo 6).
- (read-only, referencia) `scripts/jana-ragas-runner.py`, `Modules/Jana/Console/Commands/JanaRecallEvalCommand.php`, `tests/eval/recall-golden.yaml`, `governance/adr-alias-map.json` — nao mudar; ja prontos.

## Gate / counterfactual (COMO eu provo que o gate MORDE)
- **RAGAS (apos baseline real):** counterfactual = injetar uma regressao sintetica. Com baseline `faithfulness=0.84` populado, um run real (ou mock forcando current `0.70`) deve produzir `delta_pct = -16.7%` > threshold 5% → `is_regression = true` (`runner.py:124` agora com `baseline_val > 0`) → exit 1 → issue auto-aberta (`jana-ragas-canary.yml:272`). **Prova de que mordeu:** o mesmo diff com baseline=0 NAO falha (tautologia atual); com baseline>0, falha. A transicao "passa→falha quando baseline sai de 0" E a mordida.
- **recall-eval mock:** counterfactual = adicionar ao golden set uma `query` cujo `expected` aponta um slug que nao existe no disco, OU um `violation` que nao e superseded. `JanaRecallEvalCommand` linha 177 (`slug nao existe no disco`) ou 189 (`violation sem status superseded`) empurra pra `$this->errors`, `gate_status='fail'`, retorna `self::FAILURE` (exit 1). Hoje esse exit 1 nao reprova nada porque o comando nao roda em pipeline — apos o passo 2, reprova o PR/job. **Prova:** PR com golden set quebrado fica vermelho.
- **recall-eval real:** counterfactual = com `peso_real` OFF, um ADR `historical` aparece no top-3 e `recall_eval_violations > 0` (`runReal`, linha 287). Com `peso_real` ON (time-decay), o mesmo ADR cai do top-N → `recall_eval_violations = 0`. **Prova:** o valor da metrica muda quando a flag liga = decay esta de fato mordendo o caminho de leitura.

## Dependencias (e por que)
- Nenhuma dependencia de outro item de roadmap (`depende_de: []`). Auto-contido.
- **Dependencia humana dura:** passos 1, 3, 5 dependem do Wagner (secret no Vaultwarden/GitHub; aprovar run de baseline; validar+ligar flag Tier 0 no CT100). Sem o secret, RAGAS-real fica eternamente em mock (estado atual). Sem validacao CT100, peso_real nao deve ligar em prod (toca chat = Tier 0).
- **Dependencia de janela:** 1 nightly CT100 (ou 1 dispatch manual) pra a 1a medicao real aterrissar no scorecard.

## Esforco (recalibrado ADR 0106)
- **Codavel (IA-pair, 10x + margem 2x):**
  - Passo 2 (gate/job recall-eval mock): ~0.3d. Espelhar workflow existente, comando ja pronto.
  - Passo 4 (schedule Kernel real): ~0.2d. Espelhar bloco drift-sentinel.
  - Passo 6 (carimbar scorecard): ~0.2d, ou automatico se o pipeline ja escreve.
  - Subtotal codavel: **~0.7-1.0d**.
- **Humano-limitado (relogio do mundo real — NAO comprime com IA):**
  - Passo 1 (secret): minutos de trabalho do Wagner, mas e BLOQUEADOR e historicamente nunca foi feito ("Wagner NUNCA adicionou" — confirmado pelo estado mock-mock). Risco de ficar parado indefinidamente aqui.
  - Passo 3 (run baseline real): 1 execucao (~15min workflow) + aprovacao Wagner.
  - Passo 5 (validar+ligar peso_real Tier 0 no CT100): exige janela de homolog + smoke real, NAO local/cego (ADR 0270:139). Relogio real.
  - 1 nightly CT100 pra recall-eval real: 1 ciclo (ate 24h) pra 1a medicao.

## Kill-criteria / risco (quando parar ou reabrir)
- **Kill (parar):** se Wagner decidir nao adicionar `OPENAI_API_KEY` (custo ou postura), a Trilha D (RAGAS-real) PARA — mas a Trilha C (recall-eval mock em CI) e INDEPENDENTE do secret e deve ser entregue mesmo assim (e o ganho mais barato: tira um ghost-command do limbo, zero custo de LLM, zero CT100). Nao deixar o bloqueio humano do RAGAS travar o gate mock de recall.
- **Risco (vigiar):** ligar `peso_real.retrieval_enabled` em prod sem validacao CT100 e regressao Tier 0 (toca recall do chat → alucinacao sobre dado velho, classe L-OP-002). NUNCA ligar local/cego.
- **Risco (anti-teatro):** ao popular o baseline RAGAS, garantir que foi via `mode=real` com secret presente — se rodar `update_baseline` em mock, grava scores fixos do mock e a tautologia vira "baseline de mentira > 0" (pior: parece medir, mede teatro). O step `update_baseline` (workflow:248-270) ja injeta `OPENAI_API_KEY`; conferir que o run efetivo foi `real` no summary antes de aceitar o commit do bot.
- **Reabrir:** se `ragas_real_uptime` cair abaixo de 95% (secret expira/quota estoura → volta a mock silencioso) ou se `recall_eval_violations` voltar a `null` no scorecard (schedule quebrou) — reabrir, e o sintoma do elo MEDIR→GOVERNAR rompendo de novo.
