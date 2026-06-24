---
title: "Anchor gates ARMING — baseline grandfather + diff-aware + runbook de promoção (operacionaliza SA-A2-ter)"
status: proposed
date: "2026-06-23"
decisores: [Wagner (aprova + flipa branch protection), Claude Code (autor)]
related_adrs:
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0298-teto-de-governanca-anti-proliferacao-gates
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
origem: "Wagner: 'o código não pode ser feito e refeito por cada pessoa — regra nova sem aceite/teste não pode entrar'. Operacionaliza o gate de entrada (#3315) + covers-check (#3310) pra MORDEREM de verdade sem avermelhar o legado: baseline grandfather (ratchet só-desce) + fiação diff-aware advisory + runbook de promoção (clique do Wagner)."
prs: [3310, 3315, 3312]
---

# Anchor gates ARMING: do advisory que não morde pro gate que morde só mentira nova

> **Operacionaliza** a [proposta SA-A2-ter](2026-06-23-anchor-covers-check-sa-a2-ter.md) (covers-check)
> e o gate de entrada G1b (#3315). NÃO supersede nada — é a camada de **arming** (advisory→required
> por calendário · ADR 0275) com o **baseline grandfather** que faltava pra não avermelhar o legado.

## Contexto — o gate existe mas não morde

`scripts/governance/anchor-lint.mjs` já tem, em `origin/main`, dois gates ADVISORY:

- **covers-check** (`--check-covers`): `**Testado em:**` que cita teste existente sem `// @covers-us <US-ID>` (#3310).
- **gate de entrada** (`--check-entry`): US que se diz IMPLEMENTADA sem DoD/aceite (`req_sem_aceite`) OU sem teste que a cobre (`req_sem_covering_test`) (#3315).

Mas o `.github/workflows/anchor-drift.yml` rodava só `--check` normal (dead/zombie) — os dois nunca eram invocados em CI. **Débito medido em `origin/main` (full-tree):** 43 US sem aceite · 59 sem teste-que-cobre · 0 covers · e nenhum teste usava `@covers-us`. Armar `--check-entry` cru avermelharia as 43/59 legadas de uma vez — inaceitável.

## Decisão — 4 fases (respeitando ADR 0275: gate sobe 1/semana, baseline grandfather, flip do Wagner)

### G1-adoção — `@covers-us` nos testes NfeBrasil (commit `5c1e065`)

Marcador `// @covers-us US-NFE-XXX` nos 6 testes reconciliados (#3312) + `**Testado em:**` nos 3 blocos US do SPEC, mapeados ao teste que PROVA cada US (não genérico):

| US | testes que cobrem |
|---|---|
| US-NFE-001 (cert A1) | `CertificadoServiceTest` + `CertificadoControllerTest` |
| US-NFE-008 (manifestação) | `ManifestacaoServiceTest` + `ManifestacaoControllerTest` |
| US-NFE-010 (motor NCM) | `MotorTributarioServiceTest` + `TributacaoControllerTest` |

Resultado (fs-puro, sem rodar Pest): NfeBrasil `req_sem_covering_test` 3→0, `testado_sem_covers` 0. O covers/entry-gate virou REAL pra essas US.

### G1-arming — baseline grandfather + `--baseline` (commit `7f555ea`)

- `anchor-lint.mjs` ([scripts/governance/anchor-lint.mjs](../../../scripts/governance/anchor-lint.mjs)):
  - `--baseline <path>` grandfathera o débito legado; entry/covers grandfatherados NÃO contam no veredito de saída (o report mantém visibilidade total — transparência). **Sem `--baseline` = comportamento IDÊNTICO ao anterior** (zero-regressão advisory).
  - `--emit-baseline` imprime o baseline da dívida ATUAL (determinístico, sorted+unique). **A mesma engine CHECA e EMITE** → as chaves (`entry-aceite:`/`entry-teste:`/`covers:`) nunca derivam.
- `governance/anchor-entry-baseline.json` — **99 chaves** (43 aceite + 56 teste · covers=0). NfeBrasil NÃO entra (US-001/008/010 já corrigidas no G1 — dívida PAGA, não grandfatherada).
- **Ratchet só-desce** (`scripts/governance/baseline-tamper-guard.mjs`): registrado em `GUARDED` + `GROW_TRAILER_REQUIRED`. CRESCER a lista (grandfatherar mentira nova) exige trailer `BASELINE-GROW`, isolado ou pareado. DIMINUIR (dívida paga) é livre. Path adicionado ao trigger do `baseline-tamper-guard.yml` (teto ADR 0298 — estende, não cria).

### G1-fiação — job `anchor-entry-covers` diff-aware (commit `d139992`)

`anchor-drift.yml` ganha um **job próprio** `anchor-entry-covers` (contexto separado, pra promoção independente · ADR 0275 §5) que roda nos SPECs TOCADOS:

```
node anchor-lint.mjs --check-entry --check-covers \
  --baseline governance/anchor-entry-baseline.json $CHANGED
```

Diff-aware + baseline → morde só mentira **NOVA/tocada** (no-new-lie), igual o `anchored_dead` já faz. Cron/dispatch = report full-tree com baseline (exit 0, visibilidade da dívida a pagar).

### Fase 4 — promoção a required (clique do Wagner · ESTE documento)

Ver **Runbook de promoção** abaixo. Só o Wagner flipa a branch protection (ADR 0275 §5).

## Prova de que ARMA (doutrina ADR 0303 — cada brick prova ANTES)

- **gate-selftest 28/28** ([scripts/governance/gate-selftest.mjs](../../../scripts/governance/gate-selftest.mjs)) — nova catraca `anchor-lint-entry-baseline` (good/bad): MESMA US violadora, só o baseline muda → good grandfathera (exit 0), bad só decoy (exit 1). Prova que o grandfather é **per-US (no-new-lie)**, não um "desligar tudo".
- **sandbox tamper-guard**: crescer `anchor-entry-baseline.json` sem trailer → exit 1; com `BASELINE-GROW` → exit 0 (ratchet só-desce mecânico).
- **injeção end-to-end**: SPEC com US NOVA sem aceite/teste (não-baselined) rodada pela linha do job → exit 1 (morde), legado segue isento.
- **fixture** `tests/governance-fixtures/anchor-lint-entry-baseline/` (good/bad + README).

## Runbook de promoção a required (para o Wagner)

> **Pré-condição (ADR 0275 §5):** N≥3 medições/runs VERDES do job `anchor entry/covers gate (advisory)` em PRs reais que tocaram SPEC, sem falso-positivo do legado, ≥1/semana de cadência. Como é advisory + diff-aware + grandfatherado, o esperado é verde em PR normal e vermelho só quando alguém mete US nova sem aceite/teste.

1. Confirme N runs verdes (Actions → "Anchor & Doneness Drift" → job `anchor entry/covers gate (advisory)`).
2. Abra UM PR que, no MESMO commit (regra do `required-checks-baseline.json`):
   - adicione o contexto a `governance/required-checks-baseline.json` → `classic_protection.contexts` (ordem alfabética):
     ```diff
        "A11y axe · runtime nos componentes canon",
     +  "anchor entry/covers gate (advisory)",
        "Append-only canon (ADRs, handoffs, Constituição)",
     ```
   - (commit trailer `BASELINE-ABSORB` NÃO é preciso: ADICIONAR contexto required é APERTAR, não afrouxar — `detectRequiredChecks` só morde REMOÇÃO).
3. **Wagner** marca `anchor entry/covers gate (advisory)` como required na branch protection do `main` (Settings → Branches, ou o ruleset) — **só o Wagner faz isso** (ADR 0275 §5).
4. Merge. A partir daí, US nova sem aceite/teste-que-cobre num SPEC tocado BLOQUEIA o merge.

> **Opcional no mesmo PR:** renomear o job pra dropar o sufixo `(advisory)` (atualizando o contexto nos 2 lugares juntos) — os jobs irmãos `anchor-lint`/`doneness-lint` seguem a mesma convenção "(advisory) até promover".

## Integração com a Phase B verde ([#3318](https://github.com/wagnerra23/oimpresso.com/pull/3318))

Em paralelo, a [#3318](https://github.com/wagnerra23/oimpresso.com/pull/3318) (G1b-verde) adicionou `anchor-lint --junit --check-verde` (prova de comportamento VERDE-por-arquivo via JUnit). É uma **dimensão ORTOGONAL** à do arming: `--check-entry` ganhou a 3ª exigência (`req_teste_vermelho`). Reconciliado por merge (commit de merge na branch):

- O `--baseline` grandfathera **entry-aceite/entry-teste/covers**; `req_teste_vermelho` (verde) **NÃO** entra no baseline — é gate à parte, **dormente sem `--junit`** (o job `anchor-entry-covers` não passa `--junit`, então a dimensão verde não morde por ele). Grandfather de verde é trabalho futuro acoplado ao lane JUnit (MySQL fiscal).
- `gate-selftest` ficou **30/30** (13 base + `anchor-lint-verde` da #3318 + `anchor-lint-entry-baseline` deste PR) — ambas as catracas provam que mordem.

## Consequências

- ✅ Fecha o pedido do Wagner: regra nova (US implementada) sem aceite + teste-que-cobre não entra — sem refazer o que já foi provado.
- ✅ Legado (43+56) não avermelha: grandfather per-US, ratchet só-desce, paga-se a dívida encolhendo o baseline (livre).
- ✅ Tudo fs-puro (sem PHP/DB/git no lint · restrição mantida), estende workflows/scripts existentes (teto ADR 0298 — zero workflow novo).
- ⚠️ **Caveat pré-existente:** `doneness-lint` (job irmão · ADR 0302) fica vermelho em NfeBrasil por 6 conflitos `status:done`×sem-âncora legados (US-049/050/051/052/060/061) — em `origin/main` ANTES deste PR. Advisory, fora do escopo deste arming; reconciliar é trabalho separado (adicionar `**Implementado em:**` verificado a essas US done).

## Como pagar a dívida (encolher o baseline)

Pra cada US legada: adicionar DoD + `**Testado em:**` com teste que declare `@covers-us`, depois regenerar `node anchor-lint.mjs --emit-baseline > governance/anchor-entry-baseline.json` (encolhe — livre, sem trailer). 1 PR/módulo, como o Financeiro/NfeBrasil já fizeram pros dead_tests (ADR 0303).

## Referências

- Commits: `5c1e065` (G1-adoção) · `7f555ea` (G1-arming) · `d139992` (G1-fiação)
- [#3310](https://github.com/wagnerra23/oimpresso.com/pull/3310) covers-check · [#3315](https://github.com/wagnerra23/oimpresso.com/pull/3315) entry-gate · [#3312](https://github.com/wagnerra23/oimpresso.com/pull/3312) reconciliação NfeBrasil
- ADR 0303 (testado/wired) · 0273 (gramática) · 0302 (doneness) · 0275 (calendário advisory→required) · 0298 (teto) · 0271 (subtração segura) · 0256 (gate-selftest GT-G6)
