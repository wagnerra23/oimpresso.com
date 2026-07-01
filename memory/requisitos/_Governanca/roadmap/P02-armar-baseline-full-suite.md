---
roadmap_item: P02
slug: P02-armar-baseline-full-suite
onda: 0
status: executed
executed_at: "2026-07-01"
depende_de: [P01]
destrava: [P13]
related_adrs: [275, 279]
esforco_estimado: "0.3d codável + 2-3 nightlies de relógio real (≈3d calendário, gargalo = janela CT100)"
---

# P02 · Armar o baseline da full-suite (valid 0→3, armed false→true)

> **✅ EXECUTADO 2026-07-01** ([PR #3500](https://github.com/wagnerra23/oimpresso.com/pull/3500), merge `8469ebce68`) — `full_suite_pass_rate` no baseline armado: `not_yet_measured/armed:false/valid:0` → `measured/value:298/armed:true/valid:3/direction:down`. Valor capturado da fonte real (branch órfã `nightly-floor@554047dd06`, `intersection_of:3`, runs 20260628/20260630/20260701). Counterfactual provado (floor 298 armado → exit 0; floor 320 armado → 🔴 exit 1; mesmo 320 pré-arme → impune exit 0; `sdd-floor-read.test.mjs` 8/8). Gate CI `SDD scorecard ratchet (GT-G3)` verde. A catraca da métrica-mãe agora MORDE — regressão do floor >298 = exit 1.

## Problema (o que está quebrado, em 2-3 frases)
A métrica-mãe do programa SDD (`full_suite_pass_rate`, o "floor" do nightly CT100) já é
**medida** pelo scorecard, mas a catraca GT-G3 **não morde** ela: no baseline commitado ela
está `armed: false` / `valid_measurements: 0`, então uma regressão do floor passa como `warn`
(exit 0), nunca como `red` (exit 1). Hoje (2026-06-21) o floor real **subiu 274 → 295**
(regressão de +21 arquivos-que-falham) e ninguém foi punido — exatamente o buraco que P02 fecha.

## Causa-raiz (evidência VERIFICADA — file:line reais que confirmei)
- **Baseline desarmado.** `governance/sdd-scorecard-baseline.json:25-32` — `full_suite_pass_rate`
  está `"status": "not_yet_measured"`, `"value": null`, `"armed": false`, `"valid_measurements": 0`.
- **A catraca só pune métrica armada.** `scripts/governance/sdd-scorecard.mjs:303` —
  `if (b.armed === true || ARMED) red.push(msg); else warn.push(msg);`. Com `armed:false` no
  baseline e sem `SDD_RATCHET_ARM=1`, a regressão cai em `warn` → `ratchet()` retorna `0`
  (`sdd-scorecard.mjs:308`), exit 0. Logo: **não morde.**
- **`valid_measurements` é campo MANUAL, não há contador automático.** `grep -rn valid_measurements scripts/ governance/`
  só acha ocorrências no `governance/sdd-scorecard-baseline.json` (linhas 21, 30, 38, 46, 55, 65, 74, 82, 90, 99).
  Nenhum `.mjs` lê, incrementa ou escreve esse campo. A regra "3 medições válidas consecutivas"
  (ADR 0275 §3, `0275-...md:62`) é **julgamento humano gravado no baseline via PR**, não automação.
- **ADR 0279 prevê P02 explicitamente.** `memory/decisions/0279-sdd-medir-governar-floor-nightly.md:84`
  — `**PR-3:** após 3 medições válidas, **armar** o ratchet do floor.` P02 = esse PR-3.
- **Critério de promoção a required (downstream, não é P02).** ADR 0275 §5 linha R1
  (`0275-...md:86`): 7 nightlies verdes + p95 ≤25min + strict/merge-queue. Isso é P13, não P02.

## Estado atual no repo (o que achei ao verificar agora)
**Divergência relevante vs a evidência do item — reporto honestamente:**

1. **A evidência diz "full_suite ... apesar de ~15 runs reais" e cita o baseline como
   `valid_measurements:0`.** Confirmado no **baseline** (`sdd-scorecard-baseline.json:30`). MAS o
   **scorecard vivo** `governance/sdd-scorecard.json:45-81` já tem `full_suite_pass_rate`
   **`"status": "measured", "value": 274, "intersection_of": 3`**. Ou seja: o **read-side já enxerga
   o floor** — P01 (read-side) **já aterrissou** (PR #2958 `feat(sdd): read-side do floor MEDIR→GOVERNAR`,
   confirmado em `git log -- governance/sdd-scorecard.json`). A premissa "só conta depois que P01
   fizer o read-side enxergar o floor" **já está satisfeita** no momento de planejar.

2. **O "floor por interseção = 295" do contexto global está CORRETO e é de HOJE.** O arquivo
   `governance/nightly-floor.json` é **gitignored** (`.gitignore:97`) e **NÃO existe no HEAD do main**
   (`git cat-file -e HEAD:governance/nightly-floor.json` → "NOT in HEAD"). Ele vive só na branch órfã
   `origin/governance/nightly-floor` (1 arquivo) e é **materializado em runtime** pelo workflow
   (`.github/workflows/sdd-scorecard.yml:65-72`, passo "Materializa o floor da branch governance/nightly-floor").
   - Branch órfã HOJE (`git show origin/governance/nightly-floor:governance/nightly-floor.json`):
     `floor_count: 295, intersection_of: 3, computed_at: 20260621-020001`.
   - Working-tree / `sdd-scorecard.json` commitado: `274` (run de ontem, 20260620). O floor
     **subiu 274 → 295** entre ontem e hoje = regressão real de +21, hoje impune.
   - Write-side ESTÁ rodando: último commit da órfã = `chore(sdd): nightly floor 20260621-020001 [skip ci]`.
     PR #3083 (`docs(sdd): floor JÁ funciona`) corrobora. Logo o "risco-mãe" global
     (write publica na órfã, read lê do main onde nunca aterrissa) **já foi mitigado** pelo passo de
     materialização — o elo MEDIR→GOVERNAR está fechado no job `sdd-scorecard.yml`. O que falta é só **ARMAR**.

3. **Roadmap dir não existia.** `memory/requisitos/_Governanca/roadmap/` foi criado agora para
   abrigar este plano (antes só havia `BLUEPRINT-SDD-ONDA1.md` em `_Governanca/`).

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
PR único editando **só** `governance/sdd-scorecard-baseline.json` que:
1. `full_suite_pass_rate.status` = `"measured"`, `value` = floor real capturado da fonte
   (não do plano — regra anti-stale ADR 0275 §3; capturar do `sdd-scorecard.mjs --json` rodado
   contra o floor materializado, ex. 295), `armed: true`, `valid_measurements: 3`.
2. `nota_armamento` citando ADR 0279 PR-3 + ADR 0275 §3 + a proveniência (sha de origin/main +
   data + as 3 medições válidas consecutivas que justificam o flip).
3. **Counterfactual passa** (ver seção Gate): com `armed:true`, um floor pior que o baseline →
   `node scripts/governance/sdd-scorecard.mjs --ratchet` **sai 1**; floor igual/melhor → sai 0.
4. `node scripts/governance/sdd-floor-read.test.mjs` continua verde (não regredir read-side).
5. PR ≤ ~30 linhas de diff (1 arquivo, 1 intent — commit-discipline).

## Passos (ordenados, concretos)
1. **Confirmar 3 medições válidas consecutivas da fonte real.** Verificar que o passo "Medir" do
   `sdd-scorecard.yml` produziu `full_suite_pass_rate.status: measured` (value não-nulo, não-mock)
   em ≥3 runs consecutivas do cron `50 9 * * *` (ou validar via histórico dos commits da órfã +
   3 runs do scorecard que leram floor materializado). Documentar os 3 sha/datas.
2. **Capturar o `value` da fonte real**, nunca do plano: rodar localmente, com o floor da órfã
   materializado, `node scripts/governance/sdd-scorecard.mjs --json` e ler
   `.metrics.full_suite_pass_rate.value` (anti-stale ADR 0275 §3). Decidir se o baseline congela
   274 (run de ontem) ou 295 (hoje) — **recomendo congelar o MAIS RECENTE estável** para não nascer
   já regredido; registrar a escolha na `nota_armamento`.
3. **Editar `governance/sdd-scorecard-baseline.json`** no bloco `full_suite_pass_rate` (linhas 25-32):
   status→measured, value→<capturado>, armed→true, valid_measurements→3, adicionar `unit` +
   `nota_armamento`.
4. **Provar o gate ANTES de commitar** (counterfactual local — ver seção Gate).
5. **Abrir PR** citando ADR 0279 PR-3 + ADR 0275 §3, com o diff visível do baseline e a evidência
   das 3 medições no corpo do PR. NÃO tocar `.mjs` nem o workflow (P02 é só dado/baseline).
6. (fora de P02, registra como follow-up P13) promoção a required = ADR 0275 §5 linha R1.

## Arquivos a tocar (lista real)
- `governance/sdd-scorecard-baseline.json` — **único arquivo editado** (bloco `full_suite_pass_rate`, ~linhas 25-32).
- (consulta, NÃO editar) `scripts/governance/sdd-scorecard.mjs:293-311` (ratchet), `:248`+`:117-139`
  (read-side do floor), `governance/nightly-floor.json` (materializado/gitignored), `.github/workflows/sdd-scorecard.yml:100-108`.

## Gate / counterfactual (COMO provo que o gate MORDE — qual diff dá exit 1)
O gate é o passo `Ratchet` do `sdd-scorecard.yml:100-108` chamando `sdd-scorecard.mjs --ratchet`.
Counterfactual reproduzível **localmente, sem CI**:

1. Com o baseline JÁ armado (`full_suite_pass_rate.armed:true`, value=295), materializar um floor
   PIOR (ex.: editar a cópia working-tree de `governance/nightly-floor.json` para `floor_count: 320`)
   e rodar `node scripts/governance/sdd-scorecard.mjs --ratchet` → **deve imprimir
   `🔴 RATCHET (ARMADA): full_suite_pass_rate: baseline 295 → 320 (só pode DESCER)` e sair `1`**
   (`sdd-scorecard.mjs:307-310`).
2. Floor IGUAL ou MELHOR (`floor_count: 295` ou `270`) → `--ratchet` sai `0` (sem regressão).
3. **Prova de que era impune ANTES (estado atual):** com o baseline como está hoje
   (`armed:false`), o mesmo floor 320 cai em `⚠️ RATCHET (desarmada — reporta, não pune)`
   (`sdd-scorecard.mjs:306`) e sai `0`. Esse contraste (mesmo diff: exit 0 desarmado → exit 1 armado)
   É a prova de que P02 muda o comportamento de "não morde" para "morde".
4. Sanidade: `SDD_RATCHET_ARM=1 node scripts/governance/sdd-scorecard.mjs --ratchet` (simulação que
   trata tudo como armado, `sdd-scorecard.mjs:37`) deve coincidir com o resultado do passo 1.

## Dependências (e por que)
- **P01 (read-side do floor)** — DoD do P02 (armar) só faz sentido se o scorecard JÁ lê o floor.
  **Verificado: P01 já aterrissou** (PR #2958; `sdd-scorecard.json` mostra full_suite `measured`).
  Portanto a dependência está **satisfeita** — P02 está desbloqueado para começar.
- **Destrava P13** (promoção da full-suite a required, ADR 0275 §5 R1): só faz sentido promover a
  required uma métrica que já está armada (catraca mordendo) e estável por 7 nightlies. P02 é o
  pré-requisito honesto de P13.

## Esforço (recalibrado ADR 0106)
- **Codável (IA-pair):** editar 1 bloco de JSON + escrever `nota_armamento` + rodar o counterfactual
  local = trivial. ~30min de mão; com margem 2x ≈ **0.3d codável**.
- **Humano-limitado / relógio do mundo real (GARGALO):** a regra exige **3 medições válidas
  CONSECUTIVAS da fonte real**. A fonte é o nightly CT100 + o cron `50 9 * * *` do
  `sdd-scorecard.yml`. Hoje o `sdd-scorecard.json` mostra 1 snapshot (274). Confirmar 3 consecutivas
  válidas exige **2-3 ciclos de nightly** (1/dia) = **≈2-3 dias de relógio real**, não comprimível
  por IA. Se já houver 3 runs válidas no histórico (verificar passo 1), o relógio cai para ~0 e o
  esforço vira só os 0.3d codáveis.
- **Total realista:** 0.3d de trabalho + 0-3d de janela de nightly = **≈3d calendário** no pior caso.

## Kill-criteria / risco (quando parar ou reabrir)
- **Floor instável (oscila muito entre runs):** se as 3 medições "consecutivas" divergirem
  fortemente (ex. 274 → 295 → 250), a fonte não está estável o bastante para armar — **PARAR**,
  não armar, e abrir investigação da variância da nightly (flakiness) antes de P02. Armar uma fonte
  instável reproduz o erro do visual-regression (ADR 0275 §Justificativa: "armar antes da hora
  queima confiança").
- **Escolher congelar 274 (run velha) com floor real já em 295:** nasceria já regredido (295>274) e
  o primeiro `--ratchet` armado daria exit 1 sozinho, bloqueando PRs alheios injustamente.
  **Mitigação:** congelar o valor mais recente estável (295) ou o pior dos 3 últimos, e registrar a
  decisão na `nota_armamento`.
- **Branch órfã parar de publicar >48h:** o watchdog (ADR 0275 métrica 9) desarma a métrica. Se isso
  ocorrer durante a janela de P02, **reabrir** e re-confirmar a sequência de 3 antes de armar.
- **Reabrir se** alguém editar `sdd-scorecard.mjs` mudando a semântica de `full_suite_pass_rate`
  (ex. voltar a medir pass-rate em vez de floor) — o baseline armado precisaria ser re-capturado.
