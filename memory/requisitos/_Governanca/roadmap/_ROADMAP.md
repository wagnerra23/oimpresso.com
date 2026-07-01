# Roadmap SDD — Enforcement (de 60/100 à 1ª decisão em L3)

> Origem: avaliação adversarial 2026-06-21 (composto **60/100**) — [session log](../../../sessions/2026-06-21-sdd-avaliacao-adversarial.md).
> Régua: o **teste contrafactual** (se um funcionário tentar quebrar uma decisão já tomada, o processo barra sozinho?). Hoje a detecção está em **L2 (medido, advisory)**; **L3 (required + counterfactual) tem 0 gates SDD**.
> 13 projetos detalhados, cada um verificado no repo real. **Status: EM EXECUÇÃO — vários itens já landaram (ver reconciliação abaixo).**
> Última atualização: 2026-07-01 — P09 executado + reconciliação de bookkeeping (P01/P03/P05/P08/P13). P08 landou 2026-06-21 (#3140) mas ficou fora do 1º passe de reconciliação — corrigido.

## ✅ Reconciliação de estado (2026-07-01 — verificado no repo/branch-protection real)

Os docs nasceram `proposed` mas o trabalho landou sem atualizar o bookkeeping. Estado REAL (cada um verificado, não presumido):

| Item | Estado | Prova |
|---|---|---|
| **P01** | ✅ executado | floor commit-back ativo (auto-PR); `full_suite=298 measured` no main |
| **P03** | ✅ executado | `sqlite-test-corruptors --strict` exit 0 (corruptores REAIS=0) |
| **P05** | ✅ executado | `anchor entry/covers gate` na lista `required` (branch protection) |
| **P06** | 🟡 parcial | migration `mcp_sdd_scorecard_history` **aplicada em prod** + linha SDD **aparece no brief** (composta 50, k=2). Snapshot refrescado à mão 2026-07-01. **Falta:** o cron diário — `schedule:run` NÃO está no Hostinger; **decisão Wagner 2026-07-01: agendar no CT100, não Hostinger** (ADR 0062 — IA/governança ≠ shared hosting). |
| **P07** | 🟡 código-completo | 6 peças no main (`coverage-compute.mjs`, `measureCoverage()`, hardcode substituído, `ct100-fullsuite.sh` publica clover→`nightly-coverage.json`, `sdd-scorecard.yml` materializa, meta-teste). **pcov verificado no container CT100.** Falta só a 1ª nightly rodar (relógio: 3 noites p/ armar). |
| **P08** | ✅ executado | `drift_alarms`+`backfill_error_rate` = `measured` no scorecard + 6ª catraca `anchor-lint` morde no gate-selftest (#3140); needed 0 secret/prod |
| **P09** | ✅ executado | `anchored_dead=0` E `placeholder=0` no main (#3473+#3475) |
| **P12-C** | ✅ executado | recall-eval mock no CI (`jana-recall-eval.yml`) + schedule real no `Kernel.php:438` (dom 06:30 BRT) |
| **P12-D** | 🔄 em curso | `OPENAI_API_KEY` É secret do repo → dispatch `jana-ragas-canary update_baseline=true mode=real` disparado 2026-07-01; bot popula baseline real (>0), mata a tautologia |
| **P13** | ✅ executado | `SDD scorecard ratchet (GT-G3)` na lista `required` — **2º dente em L3** |
| **Pfr** | ⏳ NÃO feito | `foundation-ratchet` **não** está na lista `required` (o "1º dente" do plano não flipou; o GT-G3/P13 virou o dente que landou) |

**Divergência do plano (honesta):** a DECISÃO de 2026-06-21 elegeu `foundation-ratchet` como 1º dente e GT-G3 como 2º. Na prática **o GT-G3 (P13) landou required e o foundation-ratchet não** — a ordem inverteu. Pfr segue pendente.

**Ainda falta (pós-verificação 2026-07-01):** **P10** (batches IA — campanha multi-dia + fila A6) · **Trilho B** (`P02`→`P04`, burn-down full-suite required, semanas CT100) · **peso_real flag** (P12-5, Tier 0 — exige smoke CT100, nunca cego) · **Pfr** foundation-ratchet · relógio (nightlies P07, RAGAS terminando) · follow-ups (`req_sem_lane` reconhecer CT100-nightly, **cron SDD-snapshot agendar no CT100**). As métricas `not_yet_measured` estão **desbloqueadas** (secret + pcov existem) — resta o relógio, não a mão.

> **`dead_tests` pré-P10 — ✅ fechado (2026-07-01):** os 49 `**Testado em:**` mortos (Accounting/Crm/Essentials/LaravelAI/Manufacturing/RecurringBilling/Repair/_DesignSystem — todos testes planejados nunca escritos) reconciliados pra gramática `_lacuna_` (convenção Financeiro/NfeBrasil 2026-06-23). `anchor-lint --check` saiu de exit 1 → **exit 0** (dead_tests 49→0; anchor_coverage 12.6% inalterado). F2 total destravado pra P10.

## Achado-chave (refinou o diagnóstico)

A avaliação adversarial disse que o **risco-mãe** era o read-side do floor cego. Os agentes de planejamento, re-verificando, acharam que **isso já está parcialmente resolvido** (PR #2958: read-side lê o arquivo, CI materializa via `git fetch`, `scorecard.json` em main mostra `full_suite measured=274`). O gap real é **menor e mais barato** do que parecia: falta (a) **commit-back** do floor pra main (hoje stale 1 noite: 274 vs branch 295) e (b) **armar o baseline** (`armed:false`/`valid_measurements:0`).

**Consequência:** existem **dois caminhos pra L3**, e o mais rápido **não depende da suíte ficar verde**.

## DECISÃO Wagner (2026-06-21)

**O 1º gate a virar `required` é o `foundation-ratchet`** (não o GT-G3). Alinhado ao BLUEPRINT-SDD-ONDA1 Gap 3. Motivo: já tem selftest 13/13, baseline armado com medição real, catracas só-descem (menor risco de falso-positivo na janela de 14d) e **não depende de P05/P08** — é o dente mais estável pra abrir precedente. **P13 (GT-G3) vira o 2º dente.** Próximo passo aprovado: commitar o roadmap + começar o Trilho A (P06 quick win + P05/P08 + preparar a promoção do foundation-ratchet).

## Dois trilhos pra L3

### 🦷 Trilho A — caminho rápido à 1ª decisão em L3 (não precisa de suíte verde)
**1º dente = promover `foundation-ratchet` a `required`** (remover `continue-on-error` + entrar no `required-checks-baseline` + 7 verdes/14d). Já está armado e advisory — basta iniciar a janela e preparar o flip. **0 relógio humano fora os 14d.**
Em paralelo, `P05` + `P08` → **`P13`** (GT-G3, o **2º dente**) + janela advisory 14d. GT-G3 guarda o scorecard/ghost-baseline, que também já está armado (`ghost_count` + `front_door_coverage`).

### 🧱 Trilho B — full-suite `required` (R1) — o burn-down pesado
`P01` (commit-back) → `P02` (armar baseline) · `P03` (18 corruptores) → `P04` (7 noites verdes) → **R1** (promoção futura).
Esforço dominado por **7+ noites de relógio real** (CT100). Semanas, não dias.

### ⚡ Quick wins (avulsos, visíveis)
- **`P06`** (~1h, zero código novo): migrar a tabela em prod → **a linha SDD aparece no brief** + card do dashboard sai do empty-state. *Soft-dep de P01 — pode soltar já* (composta v1 = média das armadas; já há 2 armadas → sai não-nula sem P01).
- **`P07`**: `pcov` no CI → destrava `coverage_pct` (catraca C2).

### 📚 Conteúdo (paralelo, travado no volume)
`P09` ✅ **executado 2026-07-01** (dead=0 + placeholder=0 no main; #3473+#3475) → `P10` (batches IA + fila Wagner) · `P11` (renames + distiller) · `P12` (decay real).

## Os 13 projetos

| # | Projeto | Onda | Depende | Destrava | Esforço (código / relógio) | DoD (counterfactual) |
|---|---|:---:|---|---|---|---|
| [P01](P01-reconectar-read-side-floor.md) ✅ | Commit-back do floor pra main | 0 | — | P02,P06,P07,P13 | **executado** | ✅ floor auto-PR ativo; full_suite=298 measured |
| [P02](P02-armar-baseline-full-suite.md) | Armar baseline full-suite | 0 | P01 | P13 | 0.3d / +3 noites | `armed:true`,`valid:3`; regressão → exit 1 |
| [P03](P03-us-gov-021-isolamento-era-sqlite.md) ✅ | US-GOV-021: isolar 18 corruptores | 2 | — | P04 | **executado** | ✅ `sqlite-test-corruptors --strict` exit 0 (REAIS=0) |
| [P04](P04-burn-down-ate-nightly-verde.md) | Burn-down até nightly verde | 2 | P03,P01,P02 | (R1) | 3-4d / **2-3 sem** | 7 noites floor=0, skipped não infla |
| [P05](P05-fechar-grandfather-baseline-tamper-guard.md) ✅ | Fechar grandfather (vetor #2848) | 1 | — | P11,P13 | **executado** | ✅ entry/covers armado a required |
| [P06](P06-materializar-g7-g8-historia-brief.md) | Migrar prod → linha SDD no brief | 3 | (P01 soft) | — | **~1h** / 1-2d cron | `snapshot` FAILURE→exit 0 + 1 row |
| [P07](P07-instrumentar-pcov-ci-coverage.md) | `pcov` no CI (coverage_pct) | 3 | — | P13 | 0.8d / 3+14d | `coverage_pct` vira `measured` |
| [P08](P08-conectar-metricas-gt-e-fixture-anchor.md) ✅ | Conectar 2 métricas GT + fixture anchor | 1 | — | P13 | **executado** (#3140) | ✅ `drift_alarms`+`backfill_error_rate` `measured`; 6ª catraca `anchor-lint` morde |
| [P09](P09-sa-a4-sanear-placeholders-anchored-dead.md) ✅ | SA-A4: sanear placeholders + dead | 4 | — | P10 | **executado 2026-07-01** (#3473+#3475) | ✅ `anchor-lint` dead=0, placeholder=0 |
| [P10](P10-sa-a5-a6-batches-ia-fila-wagner.md) | SA-A5/A6: batches IA + fila + enforce | 4 | P09 | P13 | 3-4d / 2-3 sem | PR sem ledger → umbrella vermelho |
| [P11](P11-kl-e2-renames-reseed-distiller.md) | KL E2: renames + re-seed + distiller | 4 | P05 | — | 1d / dias | `ghost_count` ratchet morde; freshness `measured` |
| [P12](P12-decay-real-ragas-recall.md) | Decay real: RAGAS + recall-eval | 5 | — | — | 1d / **secret Wagner** | RAGAS baseline>0 (sai da tautologia) |
| [P13](P13-promover-gt-g3-required.md) ✅ | **Promover GT-G3 a `required`** | 6 | P05,P08 | — | **executado** | ✅ `SDD scorecard ratchet (GT-G3)` na lista required |

## Divergências que os agentes acharam (criticar aqui)

1. **Risco-mãe menor que o laudo.** Read-side já conectado (#2958); falta só commit-back + armar. P01 caiu de "refazer o elo" pra "1 job CI + 1 edit". *(P01, P02)*
2. **P06 é quick-win de ~1h** — composta já sai não-nula (2 métricas armadas), não precisa de P01. A linha SDD no brief pode acender essa semana. *(P06)*
3. **O lever do floor são 18 corruptores, não 14/27 quarentenados** — os quarentenados são conjunto DISJUNTO dos que dropam tabela CORE. Já existe auditor `sqlite-test-corruptors.mjs` medindo os 18 certos. *(P03)*
4. **P11 DoD `ghost_count→0` é estruturalmente impossível só com codemod** — o detector conta ghosts em `adr/` mas o corretor PULA `adr/` por design (#2729 Tier 0). PontoWr2/Copiloto/DocVault citam 100% dentro de `adr/`. Exige reconciliar detector×corretor antes de prometer →0. *(P11)*
5. **P12 pior que a evidência** — `JanaServiceProvider:79` não é cron, é registro; `jana:recall-eval` está ZERO agendado (sem CI, sem Kernel). *(P12)*
6. **Conflito de ordem do 1º required — RESOLVIDO 2026-06-21:** Wagner escolheu **foundation-ratchet** (BLUEPRINT Gap 3) como 1º dente; GT-G3 (P13) é o 2º. *(P13)*

## Caminho crítico recomendado

**1º dente:** promover `foundation-ratchet` (já armado/advisory → iniciar janela 14d + flip) — **sem depender da suíte verde**.
Em paralelo: `P06` (quick win visível, ~1h) · `P05` + `P08` (~2-3d) → **`P13`** GT-G3 (2º dente).
Trilho B começa em paralelo (`P01`→`P02`, `P03`→`P04`) rumo a R1.
**Não promover nada antes do baseline armado e do grandfather fechado (P05)** — senão vira `main` required-vermelho.
