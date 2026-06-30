# Handoff 2026-06-30 21:45 — Poda F1 (DS gate) pronto, swap+merge pendente Wagner

> Bloco **F1** do [ADR 0314](../decisions/proposals/0314-poda-gates-onda-2-lei-fusoes.md) executado. PR pronto e provado; falta **só** o swap atômico de branch protection + merge (R10 — Wagner). Sessão log: [2026-06-30-poda-gates-f1-ds-gate-fusao.md](../sessions/2026-06-30-poda-gates-f1-ds-gate-fusao.md).

## Estado: PR [#3456](https://github.com/wagnerra23/oimpresso.com/pull/3456) — `mergeState: BLOCKED` (esperado)

Funde 7 workflows DS/cor → 1 `ds-gate.yml`. Required vira 1 contexto `DS gate` (agrega cor-crua + ui-lint via `needs`+exit-1; 5 advisory no mesmo workflow não bloqueiam).

**Provado por CI:**
- F1 limpo: 8/8 sub-jobs SUCCESS (incl. ui-lint + design-index PHP/Pest reais) → `DS gate` ✅.
- Counterfactual (#3457, fechado): arquivo viola ui-lint → job `UI Lint (LEI)` failure → `DS gate` **failure**. O required morde. Os 5 advisory ficaram verdes (não false-fail).
- Local: 7/7 Node sub-checks PASS · `memory-health` 0🔴 · `baseline-tamper-guard` ✓ (BASELINE-ABSORB).

**`BLOCKED` é o deadlock documentado (ADR 0261), não falha:** branch protection ainda exige `Conformance · cor-crua ratchet vs baseline` + `UI Lint · ratchet vs baseline`, que não rodam mais (workflows deletados) → "Expected — waiting". Resolve no swap. Zero falha real (advisory `dup-detector` ack'd no corpo).

## AÇÃO PENDENTE WAGNER — swap atômico + merge (ordem importa)

⚠️ **NÃO swapar antes do merge** estando #3458/#3459 abertos: eles não têm `ds-gate.yml` → ficariam presos em `DS gate` "Expected". Sequência:

1. (opcional) mergear #3458 e #3459 antes, ou aceitar que rebasam depois.
2. Rodar o PATCH (admin) — remove os 2 LEI, adiciona `DS gate`, mantém `strict:false` + `enforce_admins:true`:
   ```
   gh api -X PATCH repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks --input <29-contexts>
   ```
   Os 29 contextos-alvo = `governance/required-checks-baseline.json` (`classic_protection.contexts`) DO PR #3456 (já reflete pós-swap). Comando completo no corpo do PR / pedir ao [CC].
3. **Merge #3456 IMEDIATO** após o PATCH (fecha a janela onde main-vivo ≠ baseline-em-main → `protection-drift` vermelho transitório).
4. PRs restantes (F2 #3459, #3458) rebase onto main.

## Coordenação registry (mesmos arquivos, chaves diferentes — 3-way mergeável)

- **D-3 (#3455)** já mergeou no meio da sessão; F1 foi rebasado em cima (registry 89→83, checkM 82→76, removals D-3 preservados).
- **F2 (#3459)** edita `gates-registry.json` + `.memory-health-baseline.json` checkM em paralelo → quem mergear depois de F1 rebase trivial.

## Próximos blocos da poda (ADR 0314, não nesta sessão)

F2 (#3459, em voo) · F5 trio-tela · D-1 LEI (demoções de required) — F5/D-1 tocam branch protection: **não rodar em paralelo com swap de F1**.
