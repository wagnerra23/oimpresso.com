# 2026-06-30 — Poda de gates onda 2, bloco F1 (fusão DS/cor 7→1)

> Executa o bloco **F1** do [ADR 0314](../decisions/proposals/0314-poda-gates-onda-2-lei-fusoes.md) — o maior/mais sensível (mexe em required). Wagner pré-aprovou executar em sessão nova.

## O que foi feito

Fundiu **7 workflows DS/cor → 1 `ds-gate.yml`** com 1 job por sub-check (mesmos scripts, mesmos baselines, mesmo exit):

| Original (deletado) | Job no `ds-gate.yml` | Papel |
|---|---|---|
| `conformance-gate.yml` | `conformance` | **LEI** (cor-crua ratchet — ADR 0209/0235/0190) |
| `ui-lint.yml` | `ui-lint` | **LEI** (Constituição UI v2 — ADR UI-0013) |
| `css-size-gate.yml` | `css-size` | advisory |
| `ds-canon-color-guard.yml` | `ds-canon-color` | advisory |
| `design-index-gate.yml` | `design-index` | advisory (short-circuit interno por git diff) |
| `bundle-lint.yml` | `bundle-lint-selftest` + `bundle-lint` | advisory |
| `scorer-sync-gate.yml` | `scorer-sync` | advisory |

**Required = 1 contexto novo `DS gate`** (job agregador, `needs: [conformance, ui-lint]`, `if: always()`, exit 1 real). Bloqueia só nos 2 sub-checks LEI. Os 5 advisory rodam no mesmo workflow mas **não entram no `needs`** → vermelho deles não bloqueia (propriedade garantida por construção do GitHub Actions, não por convenção).

Roda em **todo PR sem path filter** (required-readiness — ADR 0263/0261/0271): cada sub-check é ratchet/scan do tree atual → passa trivial quando nada do domínio mudou.

## Sincronia de registro (3 registries no MESMO PR)

A poda é cirurgia de registro, não só "deletar arquivo" (ADR 0314 §Contexto):

1. `scripts/governance/gates-registry.json` — −7 chaves, +`ds-gate.yml` (Check G do memory-health falha se workflow ∉ registry).
2. `scripts/governance/.memory-health-baseline.json` `checkM` — −7, +`ds-gate.yml` (grandfather; Check M pula). `detectMemoryHealth` do tamper-guard **não** inspeciona checkM, então a edição é invisível pra ele.
3. `governance/required-checks-baseline.json` — −`Conformance · cor-crua ratchet vs baseline`, −`UI Lint · ratchet vs baseline`, +`DS gate`. Vigiado por `protection-drift.mjs`; `detectRequiredChecks` do tamper-guard vê as 2 remoções como **demoção** → exigiu trailer `BASELINE-ABSORB:` no commit.

## Provas (smoke local + counterfactual CI)

- **Node sub-checks (tree limpo):** 7/7 PASS (`conformance-gate --all`, `foundation-guard`, `css-size-baseline`, `ds-canon-color-guard`, `bundle-lint`, `bundle-lint.test`, `scorer-sync-check`). design-index = PHP/Pest (não roda local — Tier 0).
- **`memory-health.mjs`** → 0 🔴. **`baseline-tamper-guard.mjs`** → ✓ via `BASELINE-ABSORB`.
- **Counterfactual CI** (PR scratch #3457): arquivo novo viola ui-lint (hex+emoji) → job `ui-lint` vermelho → agregador `DS gate` exit 1. Prova que o required morde. (Resultado registrado no handoff.)
- **Clean PR CI** (#3456): todos os 8 jobs executam (incl ui-lint + design-index PHP) + `DS gate` verde = happy path + wiring.

## Coordenação / pegadinhas

- **D-3 (#3455) mergeou no meio da sessão** editando os mesmos `gates-registry.json` + `checkM` (chaves diferentes — create-test-business + run-financeiro-resync). Rebase de F1 sobre o novo main → conflito nos 2 JSON resolvido regenerando de `origin/main` + transform F1 (registry 89→83, checkM 82→76, removals D-3 preservados).
- **Swap do branch protection NÃO foi feito** (R10) — é ato de admin no merge. `required-checks-baseline.json` já reflete o estado pós-swap. Sequência atômica documentada no handoff.

## PRs

- [#3456](https://github.com/wagnerra23/oimpresso.com/pull/3456) — F1 (não mergeado).
- [#3457](https://github.com/wagnerra23/oimpresso.com/pull/3457) — counterfactual scratch (fechar após observar).

Refs: ADR 0314 F1.
