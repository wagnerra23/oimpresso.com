---
date: "2026-06-21"
time: "12:58 BRT"
slug: 2026-06-21-1258-onda1-gaps-cruzados
tldr: "Onda 1 dos gaps cruzados das 3 auditorias. Achado dominante: em 5 frentes o PRIMITIVO já existia (audit superestimou os gaps). Entregues #3148 (gitleaks history-scan + .gitleaks.toml) e #3151 (baseline-tamper-guard 1/6→6/6), ambos MERGED. Transporte CT100→main (A+B+C) delegado a audit-implement-expert async (branch feat/onda1-transporte-sentinel) — IN-FLIGHT; próxima sessão revisa o diff + abre PR."
decided_by: [W]
cycle: CYCLE-08
prs: [3148, 3151]
related_adrs:
  - 0215-secrets-governance-5-camadas-automaticas
  - 0216-governance-drift-framework-driftchecker-plugavel
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
next_steps:
  - "Quando o agente aec9e6650923ed541 pushar feat/onda1-transporte-sentinel: revisar o diff (2 DriftCheckers novos + escalonamento + Pest) e abrir o PR."
  - "Pendência do Wagner (não-codável): rotacionar os segredos vivos (MinIO key, token Hostinger EXPIRED, 3 'falta Vault') — o gitleaks-history (#3148) dá a lista."
  - "Opcional: promover secret-scan + os checkers de transporte de warn→required (calendário ADR 0271)."
---

## Estado MCP no momento

- **Cycle:** CYCLE-08 "Receita — Onda A" (7d restantes). Trabalho desta sessão = **off-cycle** (governança/processo).
- **origin/main:** `409c782a0a` (já com #3148, #3151 e o doc de sessão #3156).

## O que aconteceu

Continuação direta do handoff [12:14](2026-06-21-1214-auditoria-maturidade-checkl-adr0297.md). [W] disse "pode fazer onda 1" → ataquei as 3 frentes dos gaps cruzados das auditorias.

**Achado dominante — o oposto do que o audit dizia.** Em **5 das peças**, o primitivo JÁ ESTAVA construído. "Validar antes de agir" pagou: não dupliquei nada, só entreguei o tecido conectivo faltante.

| Frente | O audit dizia | Realidade | Entrega |
|---|---|---|---|
| Segredos | "sem gitleaks/history/pre-commit" | gitleaks de PR-diff **e** pre-commit JÁ existem | **#3148** — o 3º portão faltante: **history-scan** (`gitleaks-history.yml` advisory) + `.gitleaks.toml`. MERGED. |
| Refutador-baseline | "exigir refutador no baseline" | `baseline-tamper-guard` (#3128) JÁ existe, mas cobria **1/6** baselines | **#3151** — detector genérico `detectCountRatchet` → cobre **6/6** (conformance/fontramp/foundation-guard/dsih/scheme). e2e testado. MERGED. |
| Transporte CT100→main | "sem sentinela" | `DeployDriftChecker` (ADR 0216) JÁ detecta SHA-drift; **`/api/mcp/version`** JÁ existe (endpoint dedicado a sentinela externa, ADR 0256); persistência de alerta JÁ existe | **delegado async** — falta só o CONSUMIDOR + escalonamento + freshness do índice. |

## Transporte sentinel — IN-FLIGHT (não fechado)

`audit-implement-expert` (agente `aec9e6650923ed541`, worktree isolado, background) está implementando A+B+C:
- **A** `McpServedDriftChecker` — consome `/api/mcp/version` de cada env, compara com `latestMainSha`.
- **B** escalonamento em `PersistsDriftAlert` — drift que persiste > N dias sobe severity + alerta ativo.
- **C** `McpIndexFreshnessChecker` — `mcp_memory_documents.max(updated_at)` vs último commit em `memory/`.
- + Pest tests + RUNBOOK. Branch `feat/onda1-transporte-sentinel`, **sem PR** (eu reviso e abro).

**Retomar:** quando o agente terminar (notificação automática), `git diff origin/main..feat/onda1-transporte-sentinel`, revisar (Tier-0: confere que só consome o endpoint, não inventa MCP internals), `gh pr create`.

## Artefatos / Persistência

- #3148 + #3151 já em `main`. Este handoff + índice neste PR.
- Branch do transporte pendente de push pelo agente.
- Webhook GitHub→MCP propaga ~2min após merge.

## Lições catalogadas

- **Auditoria multi-agente fresh tende a superestimar gaps** quando não cruza com o que já foi construído. 5/5 frentes tinham primitivo. Regra: antes de "adicionar X", grep o repo por X — quase sempre já existe um esqueleto.
- **Não posso rodar PHP/Pest local (CT 100 only)** → build PHP grande (transporte) foi delegado a subagente com spec densa + worktree isolado; CI Pest valida.
- `DeployDriftChecker` só cobre o env onde roda (follow-up multi-env explícito no próprio ADR 0216) — foi o que deixou o stale de 19d passar.

## Pointers detalhados

- Detalhe das 3 auditorias: `memory/sessions/2026-06-21-arte-*.md`.
- Padrão DriftChecker: `Modules/Governance/Contracts/DriftChecker.php` + `DeployDriftChecker.php` (referência).
- Endpoint sentinela: `Modules/TeamMcp/Http/Controllers/Mcp/HealthController.php::versao` (`/api/mcp/version`).
