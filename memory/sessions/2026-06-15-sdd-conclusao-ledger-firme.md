---
date: "2026-06-15"
topic: "Ledger firme de conclusão SDD (2 frentes adversariais): 7 já-feitos verificados, 6 PR-READY com spec exata, 3 decisões Wagner, 0 meio-feito — registro anti-vazamento"
authors: [W, C]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0276-par-adversarial-refutador"]
prs: []
---

# Ledger firme de conclusão SDD — registro anti-vazamento (2026-06-15)

> **Pedido [W]:** "a lista está sempre furando… conclusão firme, considere as threads, adversário automatizando em sequência." Workflow `sdd-conclusao-2-frentes` (29 agents, ~2.1M tokens): 2 frentes em paralelo (Medir/Governar ∥ Bugs/Drift), **refutador adversarial por item**. Cada item cai em **exatamente um balde** — este doc é o registro durável pra NINGUÉM reprocessar a lista do zero.
> **Achado-chave:** 7 dos itens "a fazer" estavam **JÁ FEITOS** (verificados em `main`, não no plano) — sem o adversário, teríamos re-trabalhado item pronto. É a prova de que o vazamento é real e o adversário o estanca.

## Tabela mestra (status FINAL pós-refuta)
| ID | Frente | Item | Status | Onde |
|---|---|---|---|---|
| F2-1 | Bugs | WithoutGlobalScopes Tier-0 `// SUPERADMIN:` | ✅ JÁ FEITO | PR #2679 (0 viol/2779 arq) |
| F2-2 | Bugs | ChannelUserAccess 1-grant-ativo | ✅ JÁ FEITO | PR #2648 (generated col `revoked_marker`) |
| F2-3 | Bugs | CSAT open→resolved dispara job | ✅ JÁ FEITO | PR #2672 |
| F2-4 | Bugs | Vestuario DataController | ✅ JÁ FEITO | PR #2673 (`9e1f9d715`) |
| F2-5 | Bugs | NFSe cancelar() spanBiz | ✅ JÁ FEITO | `ce75b74b6f` (NFSe, não NfeBrasil) |
| F2-6 | Bugs | `@test`→`#[Test]` | ✅ JÁ FEITO | 0 viol/1229 arq (gap Ponto = task à parte) |
| F2-7 | Bugs | DESIGN.md link quebrado | ✅ JÁ FEITO | PR #2677 (0 links quebrados) |
| F1-2 | Medir | Unificar `anchor_coverage` (3 valores→1) | 🟢 PR-READY | spec §A |
| F1-3 | Medir | Re-armar ratchets live (ghost 27→14, door 63.9→100) | 🟢 PR-READY | spec §B |
| F1-4 | Medir | Regenerar `sdd-scorecard.json` stale | 🟢 PR-READY | spec §C |
| F1-5 | Governar | Plugar `ledger-check` advisory no umbrella | 🟢 PR-READY | spec §D |
| F2-8 | Drift | Drift doc↔código US-GOV-018 | 🟢 PR-READY | spec §E |
| F2-9#11 | Bugs | Restaurar fixtures moeda NumUf | 🟢 PR-READY (valor a confirmar) | spec §F |
| F1-1 | Medir | Read-side `full_suite` (matar hardcode falso) | 🟡 PARKED-WAGNER | decisão §1 |
| F2-9#1 | Bugs | FeedbackRelevance score-floor | 🟡 PARKED-WAGNER | decisão §2 |
| F2-9#5 | Bugs | ContactObserver CACHE-01 | 🟡 PARKED-WAGNER | decisão §3 |
| F2-9#3/4/7/8 | Bugs | 4 unclear não-verificáveis sem Pest | ⏸ PARKED-NIGHTLY | re-triar no run limpo |

**PARKED-NIGHTLY:** nenhum *deliverable codável* espera a nightly. O gargalo do "verde do floor" é o **harness quebrado** (imagem CT100 sem binário `mysql`, floor não-determinístico 1514–2197), não tarefa parada.

## Specs exatas dos PR-READY (executáveis cold — sem Pest, verificáveis por node/git/php -l)

**§A · F1-2 — unificar `anchor_coverage`** (1 arq: `scripts/governance/sdd-scorecard.mjs`): deletar `measureAnchors()` strict-grep (consts `PLACEHOLDER_RE`/`ANCHOR_PATH_RE`/`US_HEADING_RE`/`FIELD_RE`) + nova `measureAnchors()` delegando a `execSync(node scripts/governance/anchor-lint.mjs --json)`; `metrics.anchor_coverage.value=an.coverage_pct` (fonte única, ADR 0273 §2). No mesmo PR: regenerar `sdd-scorecard.json` + editar baseline `anchor value=2.8 armed=false`. Aceitação: `sdd-scorecard.mjs --json .anchor_coverage.value == anchor-lint --json .coverage_pct` (ambos 7.9). ⚠️ MUDA a definição do número — pede aval [W].

**§B · F1-3 — re-armar ratchets** (1 arq data-only: `governance/sdd-scorecard-baseline.json`, medido em checkout LIMPO de origin/main): `ghost_count.value 27→14`, `front_door_coverage.value 63.9→100`; manter `armed:true valid_measurements:3`; nota_armamento citando "re-armado live @ <SHA do PR>; stale absorvido (ADR 0275 §3)". Verif: `--ratchet` exit 0.

**§C · F1-4 — regenerar json stale** (1 arq: `governance/sdd-scorecard.json`): `git worktree add --detach /tmp/x origin/main && node scripts/governance/sdd-scorecard.mjs` → commit diff ~8 linhas (front_door 63.9→100, ghost 15→14, anchor 3.5→3.6). NÃO hand-edit. (Pode ir junto de §A/§B no mesmo PR "scorecard honesto".)

**§D · F1-5 — ledger-check advisory** (1 arq: `.github/workflows/governance-gate-umbrella.yml`): após o step `test:memory-health`, inserir step `ledger-check — refutação de lote IA (advisory · GT-G5)` rodando `node scripts/governance/ledger-check.mjs --pr ${{github.event.pull_request.number}} --base origin/${{github.base_ref}} --head HEAD` com `continue-on-error: true` (sem `--enforce`). `fetch-depth:0` já existe.

**§E · F2-8 — drift doc↔código US-GOV-018** (2 arq): `memory/requisitos/Governance/SPEC.md` (status `todo→review` + strike A.2 FK-off revertida); `tests/TestCase.php` (remover bloco dead-code `FULLSUITE_FK_OFF` no setUp + import órfão `use Illuminate\Support\Facades\DB;`). php -l inalterado.

**§F · F2-9 #11 — fixtures moeda NumUf** (1 arq: `tests/Unit/Utils/NumUfHeuristicPtBRTest.php`): restaurar os 3 data-rows + 1 assertion corrompidos pelo redactor (`'R$ [redacted Tier 0]'` → valores reais `R$ 2.500,80`=2500.80 etc). ⚠️ **valor exato a confirmar** do commit known-good (não estava limpo em `23142eed7`); restore seguro AGORA (redactor só age em CPF/CNPJ no commit). Corrige 3 chaves duplicadas impossíveis no data-provider.

## Decisões PARKED-WAGNER (1 pergunta objetiva cada)
1. **F1-1 write-side do floor:** quem grava `governance/nightly-floor.json` (cron CT100 abrindo PR / step manual RUNBOOK / secret SSH), qual o shape, e qual run conta — dado que os 3 runs atuais são do harness QUEBRADO (banda 1514–2197)? *Atalho:* landar read-side já com fallback notYet honesto + matar o comentário falso, ramo `measured` ativa quando o write-side publicar. (Casado com proposal #2765.)
2. **F1-1#1 FeedbackRelevance:** a fórmula tem floor (~35 mín) que torna COLD (<30) inalcançável — corrigir a LÓGICA (ADR 0195) ou relaxar a EXPECTATIVA do teste?
3. **F2-9#5 ContactObserver:** `cacheKey` não casa DDI (`'554899872822'` vs `'48999872822'`+DDI) — typo de fixture (corrigir) ou gap real de normalização DDI no Observer?

## Veredito (do sintetizador, verificado)
1. **Conclusão-firme: SIM** — 14 itens em baldes disjuntos, 0 meio-feito. Os 7 já-feitos provam que o programa caça "a suite mente" com sucesso (gates verdes reais).
2. **Do codável-agora, 6/6 viraram PR-READY** — todos mecânicos, ≤2 arquivos, diff exato, aceitação por `node`/`git diff`/`php -l`.
3. **O único bloqueio do "verde do floor": o harness da nightly (FV-F3) está quebrado** — até consertar + re-rodar limpo (interseção ≥2), `full_suite_pass_rate` fica honestamente `notYet`. Nenhum deliverable codável espera por isso.

> **Regra anti-vazamento:** este ledger é o estado canônico desta lista. Qualquer sessão/IA aplica os PR-READY pela spec §A-§F sem re-triar; PARKED só sai do balde quando a decisão/condição registrada for satisfeita. Pareado com a arquitetura durável proposta em `memory/decisions/proposals/arquitetura-rede-ia-duravel-anti-vazamento.md` (o mecanismo que torna isto automático).
