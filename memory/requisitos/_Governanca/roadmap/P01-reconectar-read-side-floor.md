---
roadmap_item: P01
slug: reconectar-read-side-floor
onda: 0
status: executed
executed_at: "2026-07-01"
depende_de: []
destrava: [P02, P06, P07, P13]
related_adrs: [279, 275]
esforco_estimado: "0.5d codavel + 1 nightly (relogio real, 24h) pra prova end-to-end"
---
# P01 · Reconectar o read-side do floor (no-raiz)

> **✅ EXECUTADO 2026-07-01** (reconciliação de bookkeeping) — commit-back do floor ativo (auto-PR); full_suite floor=298 measured no main.

## Problema (o que esta quebrado, em 2-3 frases)
O elo MEDIR→GOVERNAR do floor da nightly CT100 esta **parcialmente** reconectado: o numero
chega ate o scorecard, mas (a) o `governance/sdd-scorecard.json` **commitado no main esta
defasado** (floor 274, hash `977c2647`) versus o floor REAL publicado na branch orfa
(floor 295, hash `4a5470fe`, `computed_at 20260621`), porque **nenhum job aterrissa o valor
medido de volta no main** — o CI so mede pro job-summary; e (b) o baseline ainda traz
`full_suite_pass_rate` como `not_yet_measured / armed:false / valid_measurements:0`, entao
mesmo medido, **o floor nao GOVERNA** (a catraca nao morde nele). O risco-mae continua:
a metrica-mae do programa esta "measured mas nao governed", e o numero que o time enxerga
no main mente por defasagem.

## Causa-raiz (evidencia VERIFICADA — file:line reais que voce confirmou)
**DIVERGENCIA RELEVANTE DA EVIDENCIA DO BRIEFING** — reporto abaixo o estado REAL:

1. **Read-side JA le o arquivo (a evidencia esta desatualizada).** A evidencia afirma que
   `measureFullSuiteFloor` "retorna not_yet_measured perene". **Falso no main atual.**
   - `scripts/governance/sdd-scorecard.mjs:117-139` — `measureFullSuiteFloor(floorPath = join(ROOT, 'governance', 'nightly-floor.json'))` faz `existsSync` e, se presente e valido, retorna `status:'measured', value:f.floor_count`. So cai em `notYet` se o arquivo ausente/invalido. Nao ha hardcode.
   - O `sdd-scorecard.json` **commitado** (`governance/sdd-scorecard.json`, metrica `full_suite_pass_rate`) ja esta `status:"measured", value:274, source:"...ADR 0279 Opcao A..."`. Confirmado via `node -e` lendo o JSON.

2. **Write-side funciona e publica na branch orfa.** `scripts/tests/ct100-fullsuite.sh:269-295`
   computa o floor (`floor-compute.mjs --window 3`) e da `git push -f ... HEAD:refs/heads/governance/nightly-floor` com `[skip ci]` via deploy key `/root/.ssh/oimpresso_floor_deploy`.
   `git show origin/governance/nightly-floor:governance/nightly-floor.json` retorna **floor_count 295, intersection_of 3, computed_at 20260621-020001** — 3 runs reais (19/20/21-jun). O transporte CT100→branch esta vivo.

3. **A materializacao no CI JA EXISTE (Opcao A implementada).** `.github/workflows/sdd-scorecard.yml:65-72`
   tem o step "Materializa o floor da branch governance/nightly-floor": `git fetch origin governance/nightly-floor --depth 1 && git show FETCH_HEAD:governance/nightly-floor.json > governance/nightly-floor.json`. Logo, durante o run do workflow, o scorecard **enxerga 295**.

4. **O verdadeiro no-raiz: o valor medido nunca aterrissa no main.** O workflow `sdd-scorecard.yml`
   tem `permissions: contents: read` (linha 46-47) e **nao tem nenhum step de commit/push**
   (confirmado: zero ocorrencia de `git push`/`git commit`/`peter-evans`/`contents: write`).
   O step "Staleness" (`sdd-scorecard.yml:90-98`) so EMITE `::warning::` com `continue-on-error: true`
   — nao falha o build e nao corrige. Entao o `sdd-scorecard.json` no main so muda quando um
   **humano** roda `node scripts/governance/sdd-scorecard.mjs` e commita (ultima vez: PR #2958,
   `git log` de `governance/sdd-scorecard.json`). Resultado: main mostra 274, realidade e 295.

5. **Floor medido mas DESARMADO.** `governance/sdd-scorecard-baseline.json` → `full_suite_pass_rate`
   = `{status:"not_yet_measured", value:null, armed:false, valid_measurements:0}`. Pelo
   `ratchet()` (`sdd-scorecard.mjs:293-311`), so metrica com `b.value` numerico e `m.status==='measured'`
   entra na comparacao; e so vira `red` (exit 1) se `armed:true`. Com o baseline ainda
   `not_yet_measured`, a regressao do floor (ex 274→295, que e PIORA porque `direction:down`)
   **passa silenciosa**. ADR 0275 §3: arma so apos 3 medicoes validas consecutivas da fonte real.

## Estado atual no repo (o que voce achou ao verificar agora)
- Read-side: **implementado e correto** (`sdd-scorecard.mjs:117-139`), com meta-teste 4-lados
  passando (`scripts/governance/sdd-floor-read.test.mjs`, rodado hard no CI em `sdd-scorecard.yml:59-60`).
- Write-side: **vivo no CT100** (3 runs reais na branch orfa, floor 295).
- Materializacao CI: **ja faz `git fetch origin governance/nightly-floor`** (`sdd-scorecard.yml:65-72`).
- **Gap real 1 (aterrissagem):** nenhum job commita o `sdd-scorecard.json` atualizado no main
  → arquivo defasado (274 vs 295). `gate-selftest.yml` cita "sdd-scorecard --ratchet ARMADO"
  num comentario (linha 9) mas **nao roda o ratchet contra o floor real** — so `gate-selftest.mjs`
  com fixtures e `SDD_RATCHET_ARM=1`.
- **Gap real 2 (governar):** baseline tem o floor `armed:false / valid_measurements:0`; a catraca
  nao morde nele.
- Diretorio `memory/requisitos/_Governanca/roadmap/` **nao existia** — criado por este plano.
  (Existe um `memory/requisitos/Governance/SPEC.md` em ingles que cita US-GOV-023/US-GOV-018.)

## Objetivo / DoD (criterio de pronto OBJETIVO e checavel)
O floor medido pela nightly **aterrissa no main automaticamente** e **governa**:
1. Existe um job CI (no `sdd-scorecard.yml` ou novo) que, no schedule diario, materializa o
   floor da branch orfa, roda o agregador e **abre PR (ou commita)** atualizando
   `governance/sdd-scorecard.json` quando o valor mudou. **DoD checavel:** apos a proxima
   nightly, `git show main:governance/sdd-scorecard.json | jq .metrics.full_suite_pass_rate.value`
   == `git show origin/governance/nightly-floor:governance/nightly-floor.json | jq .floor_count`
   (hoje: 274 != 295; depois: iguais).
2. O step "Staleness" (`sdd-scorecard.yml:90-98`) — apos a aterrissagem virar automatica —
   **nao reclama mais por defasagem do floor** (o git diff fica limpo).
3. Decisao de armamento registrada: com `valid_measurements>=3` (ja temos 3 runs: 19/20/21),
   o baseline do floor pode subir pra `armed:true` (isso e o destrava de **P02**, mas P01
   deixa o `valid_measurements` correto e documenta a contagem). **DoD:** baseline reflete
   `valid_measurements>=3` pro `full_suite_pass_rate`.

## Passos (ordenados, concretos)
1. **Confirmar a contagem de medicoes validas.** Ler os 3 runs da branch orfa
   (`floor-compute.mjs --window 3`) e checar a regra de "medicao valida" (ADR 0275 §3 +
   def US-GOV-018: interseccao >=2 runs). Documentar quantas validas existem hoje.
2. **Decidir o mecanismo de aterrissagem (a decisao deste plano):**
   - **2a (auto-commit via job):** adicionar job ao `sdd-scorecard.yml` com `permissions: contents: write`,
     so no `schedule`/`workflow_dispatch` (NUNCA em `pull_request`, pra nao recursar), que roda
     o agregador e da `git commit`+`git push` do `sdd-scorecard.json` se mudou. `[skip ci]` na
     mensagem pra nao disparar a suite. Risco: commit-bot direto no main (precisa `contents: write`
     + analisar se branch protection do main bloqueia push direto — provavel que sim).
   - **2b (auto-PR via peter-evans/create-pull-request):** o job abre PR com o JSON atualizado;
     Wagner aprova. Mais alinhado com a disciplina "1 PR = 1 intent" e com branch protection.
     Custo: 1 PR/dia de ruido (mitigavel: so abre PR quando o valor MUDA).
   - **Recomendacao:** 2b (auto-PR) — repo=SSOT mantido, branch protection respeitada, diff
     visivel, e o numero so muda quando ha floor novo. Evita dar `contents: write` no main.
3. **Implementar o job escolhido** em `.github/workflows/sdd-scorecard.yml` (ou novo
   `sdd-scorecard-publish.yml`), reusando o step de materializacao ja existente (linhas 65-72).
4. **Atualizar o baseline `valid_measurements`** do `full_suite_pass_rate` pra refletir as
   medicoes reais (deixa P02 so precisar virar `armed:true`).
5. **Rodar `node scripts/governance/sdd-scorecard.mjs` local com o floor 295 materializado**
   e commitar o `sdd-scorecard.json` resultante (sincroniza o main AGORA, sem esperar nightly).
6. **Validar end-to-end** esperando a proxima nightly (relogio real) OU forcando via
   `workflow_dispatch` apos um run manual do `ct100-fullsuite.sh`.

## Arquivos a tocar (lista real)
- `.github/workflows/sdd-scorecard.yml` (adicionar job de publish/auto-PR; reusar step 65-72).
  Alternativa: novo `.github/workflows/sdd-scorecard-publish.yml`.
- `governance/sdd-scorecard.json` (sincronizar 274→295 no commit do passo 5).
- `governance/sdd-scorecard-baseline.json` (corrigir `valid_measurements` do `full_suite_pass_rate`).
- `memory/requisitos/Governance/SPEC.md` (atualizar US-GOV-023 com o estado "aterrissagem automatica").
- (NAO tocar) `scripts/governance/sdd-scorecard.mjs` — read-side ja correto.
- (NAO tocar) `scripts/tests/ct100-fullsuite.sh` — write-side ja correto.

## Gate / counterfactual (COMO eu provo que o gate MORDE — qual diff deve dar exit 1)
Como este plano e sobre o ELO (aterrissagem), a prova objetiva e dupla:

**Prova de aterrissagem (a metrica chega):** apos rodar o pipeline, o comando abaixo passa
de "diferentes" pra "iguais":
```
A=$(git show main:governance/sdd-scorecard.json | jq -r '.metrics.full_suite_pass_rate.value')
B=$(git show origin/governance/nightly-floor:governance/nightly-floor.json | jq -r '.floor_count')
[ "$A" = "$B" ]   # hoje: 274 vs 295 (FALHA); DoD: iguais (PASSA)
```

**Prova de que o gate morde (a metrica governa)** — este e o counterfactual real, mas ele
**pertence a P02** (armar o ratchet do floor). Depois de armar (`armed:true` no baseline):
um PR que editasse `governance/sdd-scorecard.json` piorando o floor (ex `value: 295` →
`value: 350`, ou seja, mais arquivos-que-falham, `direction:down`) deve fazer
`node scripts/governance/sdd-scorecard.mjs --ratchet` retornar **exit 1**
(`sdd-scorecard.mjs:300-308`, `red.push` quando `armed===true`). Hoje, com `armed:false`,
esse mesmo diff retorna exit 0 (warn). **P01 garante que o numero chega correto; P02 faz morder.**

## Dependencias (e por que)
- `depende_de: []` — este e o no-raiz; nao espera ninguem. O read-side e o write-side ja
  existem; P01 so fecha a aterrissagem + corrige a contagem.
- `destrava: [P02, P06, P07, P13]` — porque:
  - **P02** (armar o ratchet do floor): so faz sentido com o numero correto aterrissando.
  - **P06/P07/P13** (promocoes/governanca que dependem do floor governando): herdam o elo.
  - Sem P01, qualquer promocao da Semana 4-6 (ADR 0279 "Nao-objetivos") nao tem dado honesto.

## Esforco (recalibrado ADR 0106)
- **Codavel com IA-pair (10x + margem 2x):** ~0.5 dia. E so 1 job CI (auto-PR ou auto-commit)
  + 1 commit sincronizando o JSON + 1 edit no baseline. O grosso (read-side, write-side,
  materializacao) JA ESTA FEITO — por isso o esforco e baixo.
- **Humano-limitado / relogio real:** ~1 nightly (24h) pra a prova end-to-end automatica
  fechar com dado novo gerado pelo cron CT100. O commit manual do passo 5 sincroniza o main
  IMEDIATAMENTE (sem esperar), mas a prova de que o job AUTOMATICO funciona exige 1 ciclo de
  cron OU 1 `workflow_dispatch` apos run manual do `ct100-fullsuite.sh`. Tambem ha 1 ponto de
  decisao humana do Wagner (2a auto-commit vs 2b auto-PR) — minutos, nao dias.

## Kill-criteria / risco (quando parar ou reabrir)
- **Reabrir** se a branch orfa `governance/nightly-floor` parar de receber pushes (deploy key
  revogada, cron CT100 caido) — ai o gap volta a ser o transporte, nao a aterrissagem. Checar
  `git log -1 origin/governance/nightly-floor` periodicamente (P13 pode ser um sentinela disso).
- **Parar (2b)** se o auto-PR gerar ruido inaceitavel — fallback pra 2a (auto-commit `[skip ci]`)
  ou pra atualizacao manual quinzenal documentada.
- **Risco de recursao:** se 2a (auto-commit) disparar o proprio `sdd-scorecard.yml` por
  alterar `governance/sdd-scorecard.json` (esta nos `paths:` do trigger, linha 31). Mitigar com
  `[skip ci]` na mensagem E rodar o job de publish so em `schedule`/`workflow_dispatch`.
- **Risco de mentira invertida:** se aterrissarmos o numero MAS o baseline seguir `armed:false`
  (P02 nao fechar), o time pode achar que "governa" quando so "mede". Por isso P01 documenta
  explicitamente que governar = P02; P01 sozinho NAO declara o elo fechado.
