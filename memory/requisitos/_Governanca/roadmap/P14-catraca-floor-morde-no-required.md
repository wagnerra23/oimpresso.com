---
roadmap_item: P14
slug: catraca-floor-morde-no-required
onda: 0
status: proposed
depende_de: []
destrava: [P04, P13-R1, C2]
related_adrs: [0275, 0279, 0303, 0314]
esforco_estimado: "0.5-1d codavel + 2 flips Wagner (rename); zero relogio de janela"
---

# P14 · Fazer a catraca do floor MORDER no check required (fecha o defeito nº 1 da avaliação 67/100)

> Origem: avaliação adversarial 2026-07-01 ([session log](../../../sessions/2026-07-01-sdd-avaliacao-adversarial.md), composto 67/100) — defeito reproduzido por 2 skeptics independentes + re-verificado com file:line por agent dedicado (workflow `wf_26bdd155`, snapshot `dd3ed7c311`).

## Problema

O check **required** `SDD scorecard ratchet (GT-G3)` roda `node scripts/governance/sdd-scorecard.mjs --ratchet` sem materializar `governance/nightly-floor.json` (gitignored em `.gitignore:97`, ausente no checkout de PR) → `measureFullSuiteFloor()` (`sdd-scorecard.mjs:117-139`) devolve `not_yet_measured` → `ratchet()` **pula a métrica em silêncio** no `continue` de `sdd-scorecard.mjs:391`. Resultado: floor=298 `armed:true` (baseline linhas 25-34) é lei só no papel — **nenhum PR que regrida a suite é bloqueado hoje**. Teatro de gate no número mais caro do programa.

## Causa-raiz (verificada, file:line)

1. `.github/workflows/sdd-scorecard-ratchet.yml` (44 linhas) — **nenhum step de materialização**; roda o `--ratchet` direto (linha 44).
2. O step que materializa a órfã existe SÓ em workflows não-required: `sdd-scorecard.yml:78-93` (advisory) e `sdd-scorecard-publish.yml:72-79` (cron/push-main, nunca `pull_request`).
3. `sdd-scorecard.mjs:391` — `if (!b || typeof b.value !== 'number' || m.status !== 'measured') continue;` → métrica armada + fonte ausente = skip silencioso.
4. `gate-selftest.mjs:281-284` — zero counterfactual de `full_suite`; a auto-prova (40/40) nunca exercita a única métrica cara.
5. Única métrica vulnerável hoje: `full_suite_pass_rate` (fonte externa). `coverage_pct` herdará o MESMO buraco quando armar (C2) — o fix protege as duas.

## Fix (defesa em 2 camadas)

**(a) Materializar a órfã no workflow required** — step novo no `sdd-scorecard-ratchet.yml` (entre setup-node e o Ratchet), adaptado do publish, com ramo do floor **HARD** (full_suite está armada; fetch falho = step vermelho) e coverage soft enquanto `armed:false`:

```yaml
- name: Materializa floor + coverage da órfã governance/nightly-floor (P14 · ADR 0279/0275)
  run: |
    git fetch origin governance/nightly-floor --depth 1
    git show FETCH_HEAD:governance/nightly-floor.json > governance/nightly-floor.json
    if git show FETCH_HEAD:governance/nightly-coverage.json > governance/nightly-coverage.json 2>/dev/null; then
      echo "coverage materializado"
    else
      echo "coverage ausente — coverage_pct segue not_yet_measured (endurecer no PR que armar C2)"
    fi
```

**(b) Fail-red no script** — `armed:true ∧ ¬measured ⇒ exit 1` (fecha órfã deletada/JSON corrompido/step removido por regressão futura). Trocar `sdd-scorecard.mjs:391`:

```diff
-    if (!b || typeof b.value !== 'number' || m.status !== 'measured') continue;
+    if (!b || typeof b.value !== 'number') continue;
+    if (m.status !== 'measured') {
+      if (b.armed === true || ARMED) red.push(`${name}: ARMADA no baseline (value ${b.value}) mas medição = ${m.status} — fonte ausente/ilegível no checkout. Materialize a órfã ou desarme via PR no baseline (ADR 0275 §3).`);
+      continue;
+    }
```

Guarda de não-regressão: só dispara com valor numérico + `armed:true` — desarmadas e `value:null` intocadas; ghost_count/front_door medem do repo e nunca caem aqui.

**(c) Counterfactual no gate-selftest** — 2 catracas novas (40→42): `sdd-scorecard-floor` (bad: `floor_count:299` > baseline 298 ⇒ `/RATCHET \(ARMADA\): full_suite_pass_rate/`) e `sdd-scorecard-floor-ausente` (bad: fonte ausente com armed ⇒ `/ARMADA no baseline.*not_yet_measured/`). Runner novo SEM `SDD_RATCHET_ARM` (prova o caminho `b.armed===true` real). Fixtures sob `tests/governance-fixtures/` são versionáveis (`git check-ignore` confirma: pattern `.gitignore:97` é ancorado à raiz).

**(d) Rename dos required com "(advisory)" no nome — são 6, não 2** (gh api live): 4 em `anchor-drift.yml:51/80/111/139` + `tier0-guards-advisory.yml:36` + `nfebrasil-pest.yml:34`. Rename ingênuo **deadlocka** (required match é por nome de job). Ordem zero-window: PR-1 adiciona job duplicado com nome novo → Wagner adiciona os 6 contexts novos via gh api → PR-2 remove jobs velhos + troca no `required-checks-baseline.json` → Wagner remove contexts velhos → `protection-drift.mjs` verde. Obrigatória pros 2 Tier-0-grade (Tier-0 guards, NfeBrasil). Nomes de ARQUIVO não mudam, só o `name:` do job.

**(e) Carona:** adicionar `governance/nightly-coverage.json` ao `.gitignore` (inconsistência: floor é ignorado, coverage não).

## Trade-off analisado (política — apresentado ao Wagner ANTES do flip, R10)

- **Nightly CT100 parada >48h NÃO bloqueia PRs**: a órfã retém o último floor publicado; fetch segue ok, status segue `measured` (stale mas comparável). Fail-red só dispara em fonte ausente/corrompida — fail-closed correto.
- **Staleness fica ADVISORY** (P14b follow-up, ~15 LOC): estender `protection-drift.mjs:112-128` (`WATCHDOG_SOURCES` linhas 47-51 não mapeia full_suite) pra ler `computed_at` da órfã e avermelhar 🔴 advisory se >48h. Required dependente de wall-clock = gate-que-grita-lobo — deliberadamente fora.
- **Risco red-until-fixed COLETIVO**: com o fix, floor noturno >298 avermelha TODOS os PRs até descer ou subir baseline via PR visível (tamper-guard permite afrouxamento isolado). Trajetória 274→295→298 torna provável — é a catraca funcionando, mas é freeze de merges do repo: **Wagner decide ciente**.
- **Desarme continua humano** (PR no baseline, auditado) — nunca desarme automático dentro do required (recriaria o skip silencioso com outra roupa).

## DoD (counterfactual)

1. PR de teste com floor regredido na fixture → `SDD scorecard ratchet` **exit 1** citando `full_suite_pass_rate`.
2. Checkout sem a órfã materializada → **exit 1** (não skip).
3. gate-selftest 42/42 verde em CI.
4. Zero required com "(advisory)" no nome; `protection-drift` verde com baseline atualizado.

## Riscos residuais (honestos)

Fetch transiente = vermelho falso (re-run resolve); runs locais de `--ratchet` exigem materialização manual (mensagem traz o one-liner); a confiança termina na órfã — o write-side do CT100 (deploy key) não é verificado pelo required (residual perene do transporte ADR 0279, fora de escopo).
