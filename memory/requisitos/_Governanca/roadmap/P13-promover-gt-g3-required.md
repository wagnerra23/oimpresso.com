---
roadmap_item: P13
slug: promover-gt-g3-required
onda: 6
status: executed
executed_at: "2026-07-01"
depende_de: [P05, P08]
destrava: []
related_adrs: [0275, 0271, 0261, 0279, 0282]
esforco_estimado: "0.7d codavel + 14d relogio (janela advisory) + ~5min Wagner-flip"
---

# P13 · Promover GT-G3 a required (a 1ª decisão SDD em L3)

> **✅ EXECUTADO 2026-07-01** (reconciliação de bookkeeping) — gate `SDD scorecard ratchet (GT-G3)` na lista required (branch protection).

## Problema (o que está quebrado, em 2-3 frases)

A infra de medição do SDD é honesta e morde em fixtures (gate-selftest 5 catracas × 2 = LIVE comprovado), mas **0 dos ~17-18 required checks de `main` são gates SDD** — o sistema imunológico está em "modo observação". GT-G3 (a meta-catraca do scorecard, workflow `sdd-scorecard.yml`) é o único gate SDD com infra pronta para virar o **1º required SDD**, mas hoje roda com `continue-on-error: true` nos 3 sinais (sempre verde; vermelho só como annotation) e não está no `governance/required-checks-baseline.json`. Promovê-lo é o marco que tira o programa de **L2 (medido, advisory)** para **L3 (gate required + counterfactual)**.

## Causa-raiz (evidência VERIFICADA — file:line reais)

1. **GT-G3 roda advisory por design.** `.github/workflows/sdd-scorecard.yml:80,91,101` — os 3 steps de sinal (`Determinismo`, `Staleness`, `Ratchet`) carregam `continue-on-error: true`. O job-context resultante é `SDD scorecard meta-catraca (advisory · determinismo + staleness + ratchet)` (`sdd-scorecard.yml:51`), que **contém a palavra "advisory"** — logo o flip exige renomear o context (ADR 0275 §5 ponto 3 + 4). Os 2 primeiros steps (floor read/write meta-test, linhas 59-63) são **hard** (sem `continue-on-error`) — isso já é uma trava real do workflow.
2. **GT-G3 não está nos required.** `governance/required-checks-baseline.json` lista 18 contexts congelados (17 `classic_protection.contexts` linhas 18-35 + 1 `rulesets.contexts` "Governance Gate" linha 40); **nenhum é SDD**. `capturado_em: 2026-06-20`, `enforcement_level: everyone` (linha 16) = `enforce_admins: true`. A única menção a SDD é `_meta.adr` (linha 4, é só ponteiro de doc).
3. **A catraca MORDE de fato.** `scripts/governance/sdd-scorecard.mjs:293-311` (`ratchet()`): usa `existsSync(BASELINE)`, compara métrica `measured` vs `governance/sdd-scorecard-baseline.json`, e retorna **exit 1** quando uma métrica **armada** regride (`if (b.armed === true || ARMED) red.push(...)` linha 303; `if (!red.length) return 0` linha 308). gate-selftest cobre isso: `gate-selftest.mjs:106-109` espera `bad → /RATCHET \(ARMADA\): ghost_count/` com `SDD_RATCHET_ARM=1` (linha 63).
4. **Existem exatamente 2 métricas armadas hoje** (essenciais ao counterfactual): `ghost_count` (value 14, direction `down`, `armed:true`, `valid_measurements:3` — `sdd-scorecard-baseline.json:49-58`) e `front_door_coverage` (value 100, direction `up`, `armed:true` — linhas 59-68). As outras 8 estão `armed:false`. **Só uma regressão num desses dois dá exit 1** — o counterfactual TEM que tocar um deles.
5. **Precedente de risco confirmado no ADR.** ADR 0275 §1 (Contexto, `0275...md:26`) cita `visual-regression` promovido e mergeando vermelho 2× em 24h (PRs #2544/#2548). É a razão dura de não flipar sobre baseline não-armado.
6. **PR #3117 é doc-proposta, não o flip.** Commit `3d856269e7 docs(handoff): ... promoção de gates a required (proposta + Wave-0/Wave-1)` — proposta, não a remoção do `continue-on-error` + edição do baseline.

## Estado atual no repo (o que achei ao verificar agora)

- Workflow `sdd-scorecard.yml` existe e está no padrão advisory descrito (cabeçalho linhas 13-18 documenta a regra de promoção batendo com ADR 0275 §5 linha G3: "7 execuções diárias verdes + 0 falso-positivo em 14d").
- gate-selftest.mjs cobre **5 catracas** (knowledge-drift, foundation-ratchet, ledger-check, sdd-scorecard, memory-health) — não "10/10" literal; a evidência do task ("LIVE=10/10") refere-se a **5 catracas × 2 fixtures = 10 casos**. Confirmado verde de design (`gate-selftest.yml` sem `continue-on-error`, linha-count 0).
- **DIVERGÊNCIA 1 (resolução parcial do risco-mãe):** o `governance/sdd-scorecard.json` commitado em `main` JÁ mostra `full_suite_pass_rate: status: measured, value: 274` (`sdd-scorecard.json:45-48`) — o read-side do floor JÁ aterrissa no JSON canônico. O alerta do contexto global ("read-side lê de main onde nunca aterrissa") está **parcialmente desatualizado**. Mas o **baseline** (`sdd-scorecard-baseline.json:25-32`) ainda diz `full_suite_pass_rate: not_yet_measured, armed:false, valid_measurements:0` → a métrica-mãe **não está armada** (P05 resolve isso).
- **DIVERGÊNCIA 2 (qual gate é o 1º required — conflito interno do repo):** a `session 2026-06-21-sdd-avaliacao-adversarial.md:38` recomenda **GT-G3** como "único com infra pronta". Mas o `BLUEPRINT-SDD-ONDA1.md` Gap 3 (linhas 144-199) recomenda **`foundation-ratchet` PRIMEIRO** e classifica `sdd-scorecard (G3)` como "depois (critério G3 do ADR 0275)" na tabela linha 162. **São recomendações conflitantes no próprio repo.** Este plano honra o item solicitado (P13 = GT-G3) mas registra: se P13 for executado antes de `foundation-ratchet`, é um caminho mais arriscado, porque GT-G3 tem 3 sinais (determinismo, staleness, ratchet) vs 1 do foundation-ratchet, e staleness/determinismo podem dar falso-positivo de manutenção. **Kill-criteria abaixo cobre isso.**
- `roadmap/` existe mas P05/P08 ainda não escritos (sessões paralelas).

## Objetivo / DoD (critério de pronto OBJETIVO e checável)

GT-G3 está **required** em `main` E o counterfactual prova que MORDE:

1. `gh api repos/wagnerra23/oimpresso.com/branches/main/protection` lista o context do job sdd-scorecard nos `required_status_checks.contexts`.
2. `governance/required-checks-baseline.json` foi atualizado **no mesmo PR do flip** incluindo esse context (senão `protection-drift.mjs` acusa 🟡 drift — ADR 0275 §5).
3. Os 3 `continue-on-error: true` foram removidos de `sdd-scorecard.yml` (steps 80/91/101) **no mesmo PR** + o context foi renomeado tirando "advisory".
4. **Counterfactual provado:** um PR sintético que afrouxa um baseline armado (ex.: `ghost_count.value: 14 → 16` em `sdd-scorecard-baseline.json`) faz o check **falhar (exit 1) e BLOQUEAR o merge** — não só annotation. Run linkada no PR de promoção (ADR 0275 §5 ponto 4: evidência = runs, não narração).
5. Evidência de 7 execuções diárias verdes consecutivas + 0 falso-positivo em janela de 14 dias (ADR 0275 §5 linha G3).

## Passos (ordenados, concretos)

1. **Pré-flight (bloqueante):** confirmar P05 (floor armado / baseline com ≥3 medições válidas) e P08 fecharam — ver Dependências. Se GT-G3 depende de medir `full_suite`/floor sem ruído, a janela de 14d só vale após P05/P08.
2. **Armar a janela advisory (relógio real, 14d):** com o workflow já advisory, coletar 14 dias de runs diárias (cron `50 9 * * *`, `sdd-scorecard.yml:39`) + os runs de PR. Registrar verde/vermelho por dia. Critério ADR 0275 §5 G3: 7 execuções diárias verdes + 0 FP em 14d. **NÃO flipar antes** (anti-precedente visual-regression).
3. **Construir o counterfactual reproduzível (codável):** criar um job/step de prova OU um script `scripts/governance/counterfactual-sdd-scorecard.sh` que: (a) copia `sdd-scorecard-baseline.json` num sandbox, (b) afrouxa `ghost_count` (14→16) OU `front_door_coverage` (100→90) — ambos armados, (c) roda `node scripts/governance/sdd-scorecard.mjs --ratchet` contra o baseline afrouxado com a medição real, (d) asseite `exit 1` + saída casando `/🔴 RATCHET \(ARMADA\)/`. Reaproveitar a fixture `tests/governance-fixtures/sdd-scorecard/bad/` que o selftest já usa (`gate-selftest.mjs:108`).
4. **Validar o flip em modo-sombra (opcional, mais forte):** rodar o counterfactual num PR-draft real e confirmar que, com o `continue-on-error` removido, o job-context fica vermelho e bloqueia.
5. **Pacote de promoção (PR único — codável, sem flip):** num só PR: (a) remover os 3 `continue-on-error: true` (`sdd-scorecard.yml:80,91,101`); (b) renomear o job `name:` (linha 51) e o workflow `name:` (linha 1) tirando "advisory"; (c) adicionar o context novo a `governance/required-checks-baseline.json` `classic_protection.contexts`; (d) citar ADR 0275 no corpo + linkar a run do counterfactual (exit 1) + a evidência de 7/14d.
6. **Wagner-flip (humano, ~5min, NÃO codável):** Wagner roda `gh api repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks/contexts -X POST -f 'contexts[]=<context EXATO renomeado>'`. ADR 0275 §5 ponto 3: só Wagner toca branch protection. Confirmar consumo da vaga semanal (máx 1 promoção/semana civil, ADR 0275 §5 ponto 2).
7. **Verificação pós-flip (codável):** rodar o counterfactual contra `main` protegido e confirmar que o merge é BLOQUEADO (não só annotation). Confirmar `protection-drift.mjs` verde (baseline e protection batem).

## Arquivos a tocar (lista real)

- `.github/workflows/sdd-scorecard.yml` — remover 3× `continue-on-error` (linhas 80/91/101); renomear `name:` (linhas 1 e 51) tirando "advisory".
- `governance/required-checks-baseline.json` — +1 context em `classic_protection.contexts` (hoje 18 total; vira 19), no MESMO PR do flip.
- `scripts/governance/counterfactual-sdd-scorecard.sh` — **novo** (node/bash puro; idioma clone do counterfactual do BLUEPRINT Gap 3 receita) OU step embutido no próprio workflow.
- `tests/governance-fixtures/sdd-scorecard/bad/` — reusado (já existe; não criar).
- **Wagner-only (não-arquivo):** o clique `gh api` de branch protection.
- Possível: RUNBOOK leve "como promover um gate a required" (counterfactual → evidência → PR baseline → Wagner-flip → watchdog) — o BLUEPRINT Gap 3 linha 192 sugere que vire template canônico pros próximos flips.

## Gate / counterfactual (COMO eu provo que o gate MORDE)

**O diff que DEVE dar exit 1 e bloquear o merge:** um PR que edite `governance/sdd-scorecard-baseline.json` afrouxando uma métrica **armada**:

```
-      "value": 14,          # ghost_count (armed:true, direction down)
+      "value": 16,
```

Com GT-G3 required (sem `continue-on-error`), `sdd-scorecard.mjs --ratchet` mede o `ghost_count` real (14) contra o baseline afrouxado (16). Como `direction: down` e `16 > 14`... atenção à mecânica exata: o ratchet compara **medição atual vs baseline**. O counterfactual honesto é o **inverso** — afrouxar o baseline e **piorar a medição**, OU manter a medição e provar que uma regressão real (ex.: introduzir um ghost novo que sobe `ghost_count` para 15) dá `m.value(15) > b.value(14)` com `direction down` → `worse=true` → `armed:true` → `red.push` → **exit 1** (`sdd-scorecard.mjs:300-310`). A saída casa `/🔴 RATCHET \(ARMADA\): ghost_count/` (mesma regex do `gate-selftest.mjs:108`).

**Prova de que MORDE (não só annotation):** após o flip, o job-context aparece em `required_status_checks.contexts` → GitHub bloqueia o botão merge enquanto vermelho. **Prova de que NÃO morde hoje:** o mesmo diff num PR atual fica verde (3 sinais com `continue-on-error`) — vermelho só vira annotation no step. A transição advisory→required É a diferença observável.

**Counterfactual negativo (anti-falso-flip):** se o counterfactual NÃO reproduzir exit 1 (ex.: a métrica que toquei está `armed:false`), o flip NÃO acontece — exatamente o pecado do visual-regression que o ADR 0275 §5 evita.

## Dependências (e por quê)

- **P05** — armar a métrica-mãe (floor / `full_suite_pass_rate` de `valid_measurements:0` → ≥3, `armed:true`). Hoje GT-G3 morde via `ghost_count`/`front_door_coverage` (já armados), então tecnicamente o counterfactual funciona SEM P05. Mas o **valor** de promover GT-G3 é governar a métrica-mãe; promover sobre um scorecard cuja métrica central ainda é `not_yet_measured` no baseline é meio-marco. P05 fecha o "armar o floor" do BLUEPRINT Gap 1a.
- **P08** — (inferido: burn-down de quarentena/erros até a nightly ficar VERDE, conforme a sequência da `session 2026-06-21:38`). Sem nightly estável, a janela de 14 dias acumula ruído e falso-positivos de staleness/determinismo → não fecha o critério "0 FP em 14d". P08 estabiliza o sinal que GT-G3 mede.
- **Anti-grandfather (BLUEPRINT Gap 2, fora de P13):** se um dos baselines armados puder ser afrouxado no mesmo PR do código sem trava, o counterfactual de P13 é honesto mas o gate fica vulnerável ao mesmo buraco do #2848. Vale registrar como risco, não como dependência dura (GT-G3 não é o knowledge-ghost-gate).

## Esforço (recalibrado ADR 0106)

- **Codável (10x IA-pair + margem 2x):** counterfactual reproduzível (0.3d) + pacote de PR (remover continue-on-error, renomear, editar baseline, RUNBOOK leve) (0.3d) + verificação pós-flip (0.1d) = **~0.7 dev-day**.
- **Relógio do mundo real (humano-limitado, NÃO comprime):**
  - **14 dias** de janela advisory (7 execuções diárias verdes + 0 FP) — ADR 0275 §5 G3. Não acelera: depende de runs diárias reais. **Só começa a contar depois de P05/P08** (senão acumula FP).
  - **~5 min Wagner-flip** — o clique `gh api` de branch protection (ADR 0275 §5 ponto 3, único que Wagner faz).
  - **1 vaga/semana civil** — se outra promoção (ex.: foundation-ratchet) consumir a vaga, P13 espera a próxima semana.
- **Total honesto:** ~0.7d de código que pode estar pronto numa tarde, mas o marco só fecha após **14 dias de relógio** + o flip humano.

## Kill-criteria / risco (quando parar ou reabrir)

- **PARAR o flip se** a janela de 14d acumular ≥1 falso-positivo (run vermelha por manutenção/flakiness de determinismo ou staleness, não por regressão real) → reabrir a janela do zero. ADR 0275 §5 G3 exige 0 FP.
- **NÃO flipar se** P05/P08 não fecharam: flipar sobre nightly instável ou baseline não-armado transforma o melhor ativo (gate que morde) em `main` required-vermelho — o erro literal do visual-regression (ADR 0275 §1, PRs #2544/#2548).
- **REAVALIAR a ordem:** o `BLUEPRINT-SDD-ONDA1.md` Gap 3 argumenta que **`foundation-ratchet` deve ser o 1º required SDD**, não GT-G3 (foundation-ratchet é 1 sinal determinístico vs 3 de GT-G3; menor superfície de FP). Se o time decidir seguir o BLUEPRINT, P13 vira o **2º** flip (após foundation-ratchet) e a vaga semanal vai para `foundation-ratchet` primeiro. **Recomendo registrar essa decisão (GT-G3 vs foundation-ratchet como 1º) antes de gastar a janela de 14d** — é uma escolha de identidade do marco "1ª decisão SDD em L3".
- **DEMOÇÃO** (se promovido e gerar atrito real): exige PR + ADR (ADR 0275 §5 ponto 5) — demoção invisível de 1 clique está barrada. Reabrir o ADR se a regra de 1 promoção/semana virar gargalo comprovado.
