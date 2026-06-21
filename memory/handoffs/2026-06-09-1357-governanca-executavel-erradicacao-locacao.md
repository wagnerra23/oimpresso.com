---
date: "2026-06-09"
hour: "13:57 BRT"
slug: governanca-executavel-erradicacao-locacao
topic: "Governança executável (casos/dominio/E2E viram gate required) + erradicação de order_type=locacao na Oficina + research estado-da-arte + freshness G-6"
tldr: "Loop zero-toque [W]. 10 PRs mergeados (#2462-2469). ADR 0264 (governança executável: 4 gates trio/traceability/E2E/domínio) + ADR 0265 (Oficina=reparo, erradica locacao). F1 guards casos:check+dominio:check (ratchet, baseline real 426 dívida) → F2 flip pra REQUIRED via gh api → erradicação backend locacao (enum {manutencao,mecanica}, importer, KPI, validação) provada por dominio:check 1→0 + Pest verde. G-5 metadata viva (owner/last_run/Status) + G-6 frescor via git (pegou Oficina casos stale). F1b Playwright harness não-required. Research 7 estratégias com nota. PAUSA opção B: saltos #2 (Status derivado do verde) e #3 (domínio além de enum) = passe dedicado."
duration: "~6h"
authors: [CL]
session: frosty-greider-83ab2f
---

# Governança executável + erradicação de locação → main (10 PRs)

> Origem: handoff-mãe Cowork `PROMPT_PARA_CODE_GOVERNANCA-EXECUTAVEL.md` + 3 docs (ADRs + handoff erradicação). [W] zero-toque: *"se não tiver testes vai desandar… sem uma regra obrigando fazer vai morrer no tempo"* + *"Pode apagar aluguel de caçamba… eu não uso é alucinação, quero reparo"*. Depois: *"pesquise os melhores e pontue cada estratégia"* → *"sim eu quero faça"* (3 saltos) → **(B) paro aqui, atacamos #2/#3 num passe dedicado**.

## Estado MCP no momento

- **Cycle CYCLE-08 Receita Onda A** (32% decorrido, 19d). Estes 10 PRs são **governança/infra = OFF-cycle** (drift conhecido; o ciclo é monetizar carteira legacy). Justificativa: pedido direto [W] + fecha gap "specs morrem no tempo" + mata alucinação que atravessava todos os gates.
- `my-work`: 30 tasks. Relevante tocada: **US-OFICINA-026** (outreach Martinho) — a erradicação limpa o domínio da Oficina pré-contrato pioneer.

## O que aconteceu (narrativa)

Decisão-mãe: as 4 camadas que seguram drift (charter/casos/teste/proibições) viram **máquina, não disciplina**. Fasamento espelha ADR 0261 (baseline → required → ratchet).

1. **ADRs (#2462):** numerei/ratifiquei **0264** (governança executável, 4 gates) + **0265** (Oficina=reparo, erradica locacao). RECONCILIAÇÃO: o handoff citava "ADR 0244 estratégia de teste" — **não existe no @main** (lá 0244=ds-v5-canon); por ADR 0238 numerei 0264/0265 e absorvi a estratégia de teste no 0264 (fundamentada em ADRs reais 0108/0128/0255). Sinalizado a [W].
2. **F1 guards (#2463):** `casos:check` (G-1 trio + G-2 traceability UC↔teste) + `dominio:check` (G-4 enum⇔dicionário `memory/dominio/oficina-auto.md`). node:fs puro, ratchet gêmeo de no-mock/pageheader. **Relatório de dívida real fotografado:** 277 páginas · 144 sem charter · 276 sem casos.md · 6 UC órfão · order_type=locacao (1 divergência domínio). Meta-testes físicos (caixa-preta) provam os gates.
3. **anti-retorno (#2464):** linha em `proibicoes.md` + CHANGELOG + `RUNBOOK-erradicacao-locacao.md`. A alucinação já falha mecanicamente (dominio:check) mesmo antes do enum.
4. **F2 flip (#2465):** removi `paths:` (always-run) + **`gh api` add required_status_checks** os 2 ratchets → gates BLOQUEANTES no main (8 required). Meta-testes movidos pra workflow path-scoped próprio.
5. **F1b Playwright (#2466):** harness + spec Oficina UC-06 (locators resilientes, zero edição na tela viva) + `e2e-gate.yml` **workflow_dispatch não-required** (1º run verde pendente do stack; ADR 0261 anti-flaky).
6. **G-5 metadata (#2467):** trava owner+last_run+Status por UC. Honesto: presença, não frescor.
7. **erradicação backend (#2468):** enum `{locacao,manutencao,mecanica}`→`{manutencao,mecanica}` (migration idempotente MySQL-guard data-fix-first), importer `normalizeOrderType`, KPI `locacao_ativa`, **validação `StoreServiceOrderRequest`**, menu Caçambas→Veículos, testes Wave/W28. **Prova: dominio:check 1→0 + Pest verde.** Achei MAIS acoplamento que o RUNBOOK (ServiceOrderController/AprovacaoOsController dead-code + fixtures FSM) = follow-up documentado.
8. **G-6 frescor (#2469):** staleness via git (commit do .tsx > last_run → stale). **Pegou Oficina casos stale REAL** (tela 08/jun > casos 02/jun). Resolve "last_run mente".

**Research:** 7 estratégias pontuadas vs mundo (fitness functions, living docs/SbE, testing trophy, ratchet/betterer, DDD ubiquitous language, Backstage catalog, RTM, policy-as-code). Mais fortes: ratchet+gates (8–8.5). Mais atrás: spec executável + freshness.

## Artefatos gerados

- ADRs: `memory/decisions/0264-*.md` + `0265-*.md` (+ índice regen).
- Guards: `scripts/casos-coverage-guard.mjs` (G-1/2/5/6) + `scripts/domain-dict-guard.mjs` (G-4) + baselines.
- Dicionário: `memory/dominio/oficina-auto.md` (semente).
- CI: `casos-gate.yml` (fetch-depth:0), `dominio-gate.yml`, `casos-meta-gate.yml`, `dominio-meta-gate.yml`, `e2e-gate.yml`.
- Meta-testes: `tests/casosGuard.spec.ts` + `tests/dominioGuard.spec.ts`.
- Playwright: `playwright.config.ts` + `e2e/`.
- Oficina: migration `2026_06_09_000001_erradica_locacao` + 7 arquivos prod/teste.
- Docs: `proibicoes.md`, `OficinaAuto/CHANGELOG.md`, `RUNBOOK-erradicacao-locacao.md`.

## Persistência

- **git:** 10 PRs squash-merged --admin no main (#2462→#2469). HEAD `ca01b7cca`.
- **MCP:** webhook propaga em ~2min. ADRs 0264/0265 visíveis via `decisions-search`.
- **BRIEFING:** OficinaAuto não atualizado (skip — domínio mudou mas capacidade não; opcional).

## Próximos passos pra retomar

```
# Passe dedicado dos saltos #2 e #3 (recomendação aceita opção B):
#2 (Status derivado do verde): infra test-results — runner carimba status OU CI armazena resultados. NÃO é regex.
#3 (domínio além de enum): estender dominio:check pra constantes de código / where('status','X'). Escopo cuidadoso (só módulos com dict).
# Follow-up erradicação: limpar dead-code ServiceOrderController/AprovacaoOsController + fixtures FSM (Pest local).
# Decisão pendente [W]: enforce_admins gates casos/dominio? (ADR 0261 faseado, já required mas admin fura)
```

## Lições catalogadas

- **Baseline de gate git-dependente tem que nascer com histórico CHEIO** (#2469): worktree shallow → G-6 pulou local → baseline sem stale → CI fetch-depth:0 → stale virou violação nova → gate vermelho. Fix: `git fetch --unshallow` + regravar. Travado no commit.
- **Reconciliação de numeração ADR** (ADR 0238): handoff Cowork numera em esquema paralelo; [CL] confirma nº livre no @main e absorve refs fantasma em ADRs reais. NÃO fabricar ADR não-recebida.
- **Erradicação Tier-0 live-prod sem Pest local:** CI Pest É o loop de verificação (~2min); migration idempotente+MySQL-guard = no-op em SQLite (seguro nos testes). Achado: blast-radius maior que o handoff (controller dead-code + fixtures FSM-roteadas).
- **`pull_request` usa o workflow file do PR** (não do base) → mudança de gate testável no próprio PR.
- **strict branch protection** força update-branch repetido quando PRs irmãos mergeiam (dança chata mas correta).

## Pointers detalhados (on-demand)

- ADR 0264/0265 (decisões + reconciliação numeração).
- `RUNBOOK-erradicacao-locacao.md` (P1→P5 + limite Tier 0 FSM keys disponivel/locada = dívida F3 charter v4).
- Scorecard das 7 estratégias (na conversa desta sessão).
