<!-- schema-allowlist: reconciliação read-only ds:critic — mapeamento de conceitos S1-S5+CX sobre o sistema existente; nenhuma task MCP criada, nenhum código tocado, nenhuma ADR escrita (instrução do solicitante: "só persistir este relatório"). -->
---
date: 2026-06-13
hour: "14:00 BRT"
duration: "1h"
topic: "RECONCILIAR — ds:critic / adversário do sistema: mapear S1-S5+CX sobre scorer/report/ledger/guards/ratchets/dual-brain já existentes"
authors: [W, C]
outcomes:
  - "ModuleGradeServiceV4 (Waves 19-28) MERGEOU & LIVE (ADR 0163, ~92pp, 4/4 buckets) — mas é grade de módulo BACKEND, ortogonal ao ds:critic de tela"
  - "ds:critic de tela JÁ tem: scorer agregado (design-identity-grade SOFT), scorer por-tela (score-mechanized), consolidador, report (cartão de evidência), ledger (9% adoção), guards, ratchets, judge dual-brain (pr-ui-judge manual)"
  - "S1 PARCIAL ~80% · S2 FALTA ~35% · S3 PARCIAL ~70% · S4 FALTA ~25% · S5 PARCIAL ~55% · CX PARCIAL ~30%"
  - "META-ACHADO: EVAL_PROTOCOL.md (2026-06-09) já desenha S3/S4/S5/CX mas está PRESO em proposta esperando [W] congelar GOLDEN_SET. O gap é design-não-construído, não design-faltando. Criar ADR pra S3/S4/CX duplicaria o EVAL_PROTOCOL."
  - "Só G2 (severidade 0-4 + veto-P0) justifica ADR nova (muda contrato de merge-block). G1/G3/G5 são PRs pequenos; G4/G6 são entrega da EVAL-002/003 já aprovada-em-espírito."
prs: []
us: []
related_adrs:
  - 0163-governance-v4-metas-alcancadas-ondas-19-28
  - 0254-design-identity-grade-deterministico
  - 0240-task-ledger-git-native-cowork-code
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0236-governanca-evolucao-doc-design
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
audit: true
---

# RECONCILIAR · ds:critic — estado real vs S1–S5 + CX

> Papel: [CL] adversário do sistema. Mapeado contra o código real do `@main`
> (worktree `frosty-greider`, branch `feat/governance-ds-rollout-ledger`).
> **Read-only:** nenhuma task criada, nenhum código tocado, nenhuma ADR escrita.
> Decisão do solicitante: persistir o relatório e parar.

## 1 · ESTADO — em que Wave estamos

Há **dois** sistemas de "Waves" que o prompt funde. Separá-los é o 1º ato adversário:

| Sistema | Gradua | Estado | Evidência |
|---|---|---|---|
| **module-grade-v4** ("ModuleGradeServiceV4", **Waves 19-28**) | módulos **backend** (34, 4 buckets) | ✅ **MERGEOU & LIVE** — ~92pp, 4/4 buckets acima da meta | ADR 0163 · `Modules/Governance/Console/Commands/ModuleGradeV4Command.php` |
| **DS-identity / telas** (scorer/report/ledger/guards) | identidade visual das **telas** | 🟡 vivo, gate **SOFT** | tabela abaixo |
| **EVAL** (EVAL-001/002/003) | **comportamento dos agentes** | 🔴 EVAL-001 **proposta** (não congelada), 002/003 não iniciadas | `prototipo-ui/evals/EVAL_PROTOCOL.md` |

**Resposta direta:** ModuleGradeServiceV4 (Wave 21) **sim, mergeou** — Waves 19-28 fechadas
(ADR 0163). Mas é o grade de **módulo backend**, ortogonal ao `ds:critic`. O `ds:critic`
(identidade de tela) vive nas peças abaixo; a camada de **comportamento** (onde S2-S5/CX
moram) está numa proposta de 2026-06-09 esperando o [W] congelar.

### O que o `ds:critic` JÁ cobre (inventário verificado)

| Peça | Arquivo | O que faz | CI |
|---|---|---|---|
| scorer agregado | `scripts/design-identity-grade.mjs` (ADR 0254) | 1 nota /100 do `resources/js` inteiro, 8 dims, σ=0, ratchet `--check` | `design-identity-gate.yml` **SOFT** |
| scorer por-tela | `prototipo-ui/audit/score-mechanized.mjs` | **1 `design-report.json` por tela**, R1-R10 com peso, dedução por severidade, top_gaps | ❌ não-CI |
| consolidador | `prototipo-ui/audit/consolidate.mjs` | placar pior→melhor (`CONSOLIDADO.md/json`) | ❌ não-CI |
| report | `scripts/ds-report.mjs` (ADR 0209/0239/0240) | ds/* por regra×módulo, **cartão de evidência** `--module X --json` (pass + `measured_against_sha`) | parte do ds-gate |
| ledger | `scripts/ds-ledger.mjs` | **censo por tela** (tokens/primitivos/probe/dark/[W]) → `governance/ds-ledger.json` = **9%** | `DsRolloutController` |
| guards | `ds-guard.mjs`, `conformance-gate`, `components-tree-guard`, `layout-primitives-guard`, `pageheader-guard`, `reuse-index`, `a11y-ratchet`, eslint `ds/*` | bloqueiam por delta>0 / paleta inventada / árvore | múltiplos `.yml` |
| ratchets | família `*-baseline.mjs --check` | catraca delta>0 (ADR 0209/0254) | sim |
| dual-brain (judge) | `pr-ui-judge` (Brain B Sonnet) | review semântico 9 dims, sev `critical/warning/info` | **manual-only** (workflow deletado, ADR 0271) |
| trava única | `governance-gate-umbrella.yml` (ADR 0256/0258) | 1 check always-run agrega gov + roda meta-teste dos scripts | advisory→enforce |
| meta-gate (padrão) | `casos-meta-gate.yml`, `dominio-meta-gate.yml`, `mutation-gate.yml` | controle-negativo do próprio guard | sim |
| drift (artefato) | `governance-drift.yml` (ADR 0216) | DriftCheckers SCOPE/FS/secrets, cron diário | sim |

## 2 · MAPEAR S1–S5 + CX

| Conceito | Veredito | Por quê |
|---|---|---|
| **S1** orquestrador `ds:critic` (1 score/tela) | 🟡 **PARCIAL ~80%** | `score-mechanized.mjs` **já** emite 1 nota/tela (R1-R10 peso) + `consolidate.mjs` placar. **Falta:** wrapper único `ds:critic` que rode scorer+ledger+guards numa passada → 1 verdict canônico; e a metade julgada (R5/R8/R10 = `n/a`) que deixa a nota como "teto provisório". Hoje 3 superfícies por-tela (report, ledger, judge) **não compõem**. |
| **S2** severidade 0–4 + veto-P0 | 🔴 **FALTA ~35%** | Vocabulário **fragmentado**: pesos R1=3…R6=1 (score-mechanized), `critical/warning/info` (judge), P1-P3 (audits), janela-veto 12h + catraca-reversa (AUTONOMY_LADDER). **Falta:** escala **0–4 canônica única** + **veto-P0 automático** (hoje gate é delta>0 binário, identity é soft, umbrella advisory, judge manual — nada faz "1 P0 ⇒ PR travado independente da nota"). |
| **S3** loop escape→gate (LIÇÕES→check) | 🟡 **PARCIAL ~70%** | `REPLAY_CASES.md` formaliza "toda L-NN → RC-NN no mesmo PR"; `ds-guard.mjs` mecaniza L-02/21/23; ratchet converte escapes em delta-gate. **Falta:** (a) meta-check que **FALHA se L-NN nova landa sem RC-NN** (hoje é palavra, não gate); (b) RC-03/04/05 "FAIL por construção"; (c) `prototipo-ui/evals/results/` **VAZIO** — replay não roda em CI. |
| **S4** drift-watch (score vs sinal real) + holdout | 🔴 **FALTA ~25%** | `governance-drift.yml` mede drift de **artefato** (outra coisa). EVAL-002 **especifica** delta judge-vs-[W] **mas é proposta** (sem `RUBRICA_W.md`, sem `outcome-metrics.js`). Auditoria sênior de hoje É drift manual (humano achou 423 hits que o score **allowlista**). **Holdout: não existe** (GOLDEN_SET é replay de comportamento, não conjunto retido anti-Goodhart). |
| **S5** meta-eval (`--meta`) + trava única CI | 🟡 **PARCIAL ~55%** | Padrão meta-gate **existe e é maduro** (`casos-meta-gate`, `dominio-meta-gate`, ADR 0258); trava única **existe** (`governance-gate-umbrella`, ADR 0256). **Falta:** plugar o `ds:critic` — não há meta-teste do **scorer de identidade**, e os scorers DS (identity soft, score-mechanized/ledger não-CI) **fora da umbrella**. |
| **CX** adversário permanente (red-team) | 🔴 **PARCIAL/FALTA ~30%** | EVAL-003 **especifica** red-team mensal → `evals/results/redteam-AAAA-MM.md`; praticado **ad-hoc** hoje (2 auditorias adversárias 2026-06-13). **Falta:** cadência — EVAL-002 (pré-req) é proposta, `evals/results/` vazio, nenhum cron rodando. Papel vive na cabeça de quem audita. |

### Meta-achado (o ato adversário central)

**O maior gap não é conceitual.** `EVAL_PROTOCOL.md` (2026-06-09) já desenha S3 (REPLAY),
S4 (delta judge-vs-[W]), S5 (calibração) e CX (red-team mensal). Está **preso em PROPOSTA**
esperando o [W] congelar o GOLDEN_SET (ato de soberania, AUTONOMY_LADDER A0/A1). **Criar ADR
novo pra S3/S4/CX duplicaria o EVAL_PROTOCOL** — viola "não reescreva o que as Waves já
entregaram" + append-only. O destravamento vale mais que qualquer ADR nova.

## 3 · SÓ os "falta" — proposta (NÃO executada)

| # | Gap | Entrega proposta | ADR nova? | Esf. |
|---|---|---|---|---|
| **G1 · S1** | wrapper único `ds:critic` | PR: `scripts/ds-critic.mjs` (compõe score-mechanized + ds-ledger + guards → 1 verdict/tela) + npm `ds:critic` + fecha metade julgada R5/R8/R10 | ❌ não (composição canon) | S |
| **G2 · S2** | escala 0–4 + **veto-P0** | **ADR nova** (muda contrato de merge-block = Tier-0-adjacente) + PR mapeando R1-R10/ds-* → sev 0-4 e veto-P0 no `ds:critic --check` | ✅ **sim** (única) | M |
| **G3 · S3** | meta-check "lição sem RC = fail" | PR: `scripts/licao-rc-guard.mjs` + workflow path-scoped. Emenda ao EVAL_PROTOCOL | ❌ não (emenda) | S |
| **G4 · S4** | drift-watch do score + **holdout** | Destravar EVAL-001 ([W] congela GOLDEN_SET) → construir EVAL-002 (`outcome-metrics.js` + `RUBRICA_W.md` + delta judge-vs-[W]) + holdout set no GOLDEN_SET | ❌ não (entrega EVAL-002) | M-L |
| **G5 · S5** | ds:critic na trava única + meta-gate do scorer | PR: `design-identity-meta-gate.yml` (padrão `casos-meta`) + `ds:critic --check` na `governance-gate-umbrella` | ❌ não (segue ADR 0256/0258) | S |
| **G6 · CX** | red-team operacional | Construir EVAL-003 — cron mensal + 1º `evals/results/redteam-2026-06.md` | ❌ não (entrega EVAL-003) | M |

**Veredito do adversário:** só **G2** justifica ADR nova (muda o contrato de bloqueio).
G1/G3/G5 são PRs pequenos compondo peças que já existem. **G4 e G6 não precisam de nada
novo — precisam que o [W] congele a EVAL-001 e que EVAL-002/003 sejam construídas.** Caminho
de maior ROI: **(a) destravar EVAL-001 → (b) G1 wrapper → (c) G2 ADR severidade/veto.**

---

## Atualização — 2026-06-13 (destravar e vai)

Wagner decidiu **destravar a EVAL-001** (revertendo a escolha inicial de "só
persistir"). Ato de congelamento [W] executado neste mesmo commit:

- `EVAL_PROTOCOL.md` · `GOLDEN_SET.md` → status flip **PROPOSTA → CANON/CONGELADO**
  por [W] 2026-06-13. **Casos GS-01..12 e RC-01..06 congelados AS-IS** — nenhum
  caso editado (regra de não-contaminação do avaliador respeitada).
- EVAL-001 fica completa (golden set congelado + RC-01..06 + AUTONOMY_LADDER
  referenciada) → **destrava EVAL-002** (drift-watch delta judge-vs-[W] + holdout,
  fecha S4) e **EVAL-003** (red-team mensal, fecha CX).
- Próximos passos de maior ROI (não executados ainda): **G1** wrapper `ds:critic`,
  **G2** ADR severidade 0-4 + veto-P0, depois EVAL-002/003.

**Arquivo:** `memory/sessions/2026-06-13-reconciliar-ds-critic-s1-s5-cx.md`
**Status:** reconciliação audit-only + ato de congelamento EVAL-001 ([W]-autorizado).
**Refs:** EVAL_PROTOCOL.md · AUTONOMY_LADDER.md · REPLAY_CASES.md · ADR 0163/0254/0256/0258/0216/0271 · auditoria sênior 2026-06-13 (61/100) · audit adversário Cliente 2026-06-13 (58/100).
