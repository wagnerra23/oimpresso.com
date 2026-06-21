---
date: "2026-06-16"
time: "08:31"
slug: foundation-ratchet-raiz-conta-uso-real
tldr: "Sessão 'continue' que acordou na branch STALE frosty-greider (232 atrás do main). Orientação confirmou: lanes KL/Leva do SDD 100% mergeadas + F5 diferido de propósito (ADR 0105, gatilho ~30/jun). O único item canônico pendente era o drift do foundation-ratchet — e a RAIZ era o detector contar a palavra 'RefreshDatabase' em comentário. Conserto mergeado (#2810): detector conta uso real do trait, baseline honesto 71→15, travado por fixture+selftest 13/13. Depois, teste do round-trip de persistência MCP (tasks-create só persiste após commit+push→webhook→DB). Off-cycle CYCLE-08."
cycle: CYCLE-08
prs: [2810]
related_adrs:
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
---

# Handoff — foundation-ratchet conta USO real de RefreshDatabase (não menção) · #2810

## Estado MCP no momento

- **Cycle:** CYCLE-08 (Receita — Onda A) · 12 dias restantes (→ 2026-06-28). Esta sessão foi **off-cycle** (governança/SDD).
- **my-work @wagner:** 30 tasks — REVIEW(4): FIN-4, US-TR-305/306/307 · BLOCKED(6): US-NFE-043…048 (dormentes) · TODO(20) começa com US-SELL-036, US-OFICINA-026, US-FISCAL-018, US-SELL-009 (todos p0).
- **SDD:** nada de código pendente que não seja signal-gated (ver handoff #2806). F5 (hard-gates A6/A7) **diferido de propósito**.

## O que aconteceu

1. **"continue" acordou na branch STALE `feat/governance-ds-rollout-ledger`** (worktree `frosty-greider-83ab2f`), 232 commits atrás do main, com 1016 arquivos não-commitados de 3 frentes desconexas. Decisão [W]: abandonar e disparar uma lane SDD canônica.
2. **Recon revelou que NÃO há lane SDD pendente:** frente KL inteira mergeada + refutada (#2743 E1, #2750 fusões, #2761 Estoque, #2754 BRIEFINGs) · Leva 1+2 (#2794-2805) · teto honesto ~86-88 · F5 signal-gated (gatilho ~30/jun via `mcp_task_events` claim-less).
3. **Único item concreto pendente = drift do `foundation-ratchet`** (`n_refresh_database` pintando vermelho em toda PR). [W] escolheu **conserto de raiz**.
4. **A raiz não era "teste pesado":** o detector casava `\bRefreshDatabase\b` **cru** — contava a palavra em comentário/docstring/string de `skip()`. Medição no main: 81 contados, **só 15 usam o trait, 56 falsos positivos** (testes era-sqlite que mencionam o trait só pra explicar que o EVITAM).
5. **Conserto + merge (#2810):** detector passa a contar uso real (`uses(...RefreshDatabase::class)` / `use …RefreshDatabase;`, comentários removidos antes); baseline re-armado **71→15**; travado por fixture `comment-vs-uso` + 2 asserts no selftest → **13/13**; CI 100% verde; squash-merge; worktree+branch limpos.
6. **Teste do round-trip de persistência MCP (a pedido [W]):** `tasks-create US-GOV-026` **não persiste** sem commit — só vira durável após `SPEC.md → git push → webhook → DB`. Confirmado que o `my-work`/`tasks-detail` de uma conversa nova só enxerga o que está no DB (git-backed). Nada foi gravado (US-GOV-026 ficou só texto).

## Artefatos gerados

- **[PR #2810](https://github.com/wagnerra23/oimpresso.com/pull/2810)** (mergeado · squash `b81769d34`) — `scripts/tests/foundation-ratchet.mjs` (helper `refreshDatabaseTraitUsed`) + baseline 71→15 + `foundation-ratchet.test.mjs` (+2 asserts) + fixture `comment-vs-uso/` (3 arquivos). +74/−5.
- Este handoff.

## Persistência

- **git (main):** #2810 mergeado — conserto vivo, `origin/main:foundation-ratchet-baseline.json` = `n_refresh_database: 15`.
- **MCP:** nada (sessão off-cycle; nenhuma task tocada; US-GOV-026 não-persistida de propósito).
- **BRIEFING:** sem mudança (não tocou módulo de produto).

## Próximos passos pra retomar

> **"continue" numa conversa nova → orientar via `brief-fetch` + `my-work`.** Não há lane SDD pendente (F5 diferido). A métrica-mãe é **CYCLE-08 receita** — escolher item p0 do my-work: `US-OFICINA-026` (Martinho Caçambas), `US-FISCAL-018` (cockpit Larissa biz=4), `US-SELL-009` (cutover ROTA LIVRE) ou `FIN-4` (cobrança ROTA LIVRE, em review).

- **Housekeeping:** worktree/branch STALE `frosty-greider-83ab2f` (`feat/governance-ds-rollout-ledger`) segue abandonado — limpar com `git worktree remove` quando nenhuma sessão tiver cwd lá.
- **SDD F5:** reavaliar ~30/jun lendo `mcp_task_events` por frequência de mutação claim-less (handoff #2806).

## Lições catalogadas

- **Catraca que faz `grep` de keyword crua conta MENÇÃO como USO.** Métrica de governança tem que medir uso real (trait aplicado), não a palavra em comentário — senão mede forma, não correção (ADR 0275). Conserto: remover comentários antes + casar só `uses(...)`/`use …;`.
- **`tasks-create` (MCP) NÃO é durável até `commit + push → webhook → DB`.** O SPEC.md em git é a fonte da verdade; o DB que `brief-fetch`/`my-work` leem é cache sincronizado pelo push. "Lista que sobrevive pra conversa nova" = git-backed.

## Pointers detalhados

- Handoff antecessor (estado SDD): [2026-06-15 23:36 — SDD Leva 2 COMPLETA](2026-06-15-2336-sdd-leva2-fechamento.md).
- Detector + baseline: `scripts/tests/foundation-ratchet.mjs`, `scripts/tests/baselines/foundation-ratchet-baseline.json`.
- Selftest + fixtures: `scripts/tests/foundation-ratchet.test.mjs`, `scripts/tests/fixtures/foundation-ratchet/comment-vs-uso/`.
