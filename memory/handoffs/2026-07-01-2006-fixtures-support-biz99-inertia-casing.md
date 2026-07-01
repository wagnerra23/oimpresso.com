---
date: "2026-07-01"
time: "20:06 BRT"
slug: fixtures-support-biz99-inertia-casing
tldr: "Suite tests/Feature/Support (Modo Suporte) era cobertura FALSA (32/40 falhando por fixture). Helper seededSupportClientTenant (biz=99 válido) + fix casing js/pages→js/Pages no config Inertia. Suite 40/40 no CT100. PRs #3562 + #3563 mergeados."
prs: [3562, 3563]
decided_by: [W]
related_adrs: [0305-modo-suporte-cross-tenant-exceto-operador, 0101-tests-business-id-1-nunca-cliente, 0062-separacao-runtime-hostinger-ct100]
next_steps: ["nada pendente — main verde 40/40; observar CI verde já confirmado nos 2 PRs"]
---

# Fixtures Support biz=99 + casing Inertia — suite 40/40

## Estado MCP no momento do fechamento
- **Cycle:** nenhum ATIVO em COPI.
- **my-work (@wagner):** 30 tasks (8 REVIEW, 8 BLOCKED, 14 TODO) — nenhuma tocada nesta sessão (trabalho foi test-infra, sem US associada).
- **Handoffs irmãos hoje:** 2110 (P10 wave1), 1814 (máquina revisão), 1800 (P11 e2b), 1645 (SDD trilho B). Este é paralelo a eles (test-infra, escopo isolado).

## O que aconteceu
`tests/Feature/Support/` (ADR 0305/0308/0309) era **cobertura FALSA**: loader morria no double-`uses` (corrigido #3554, já mergeado) e, rodando no CT100/MySQL, falhava 32/40 por **fixture**, não por bug de produto. Dois bugs distintos → dois PRs (1 intent cada), ambos mergeados por Wagner nesta sessão:
- **#3563 `test(support)`** — fixture biz=99 (alvo da tarefa).
- **#3562 `fix(inertia)`** — casing `js/pages`→`js/Pages` (descoberto ao rodar; afeta Inertia component-existence na suite inteira em Linux).

## Artefatos gerados
- `tests/Support/WithSeededTenant.php` — helper `seededSupportClientTenant()` (~50 linhas; espelha `FullSuiteMinimalTenantSeeder`).
- 7 arquivos `tests/Feature/Support/*` — substituídos `Business::firstOrCreate(['id'=>99],...)` quebrados + seed antes de cada user biz=99 + fix não-agente-em-biz=1 no `SupportClientViewServiceTest` + imports órfãos removidos.
- `config/inertia.php` — 1 linha (casing).
- Session log: `memory/sessions/2026-07-01-fixtures-support-biz99-inertia-casing.md`.

## Persistência
- **git:** #3562 + #3563 mergeados em `main` (branches auto-deletadas). Este handoff/session log via commit próprio.
- **MCP:** webhook GitHub→MCP propaga docs em ~2min após push.
- **BRIEFING:** N/A (test-infra, sem módulo de produto).

## Próximos passos pra retomar
Nada pendente. Smoke final no `main` mergeado (CT100/MySQL): **40 passed / 0 failed (121 assertions)**. Container restaurado pra `origin/main`.

## Lições catalogadas
1. Cobertura FALSA aqui tinha 2 vetores: loader morto **e** skip no CI (SQLite via `xxxSchemaReady()`) — só CT100/MySQL prova (ADR 0062).
2. Casing de `config('...paths')` (`js/pages` vs `js/Pages`) é invisível no Windows, fatal no Linux — quebra Inertia component-existence na suite toda.
3. DB persistente do staging envenena `firstOrCreate` (casa por username, não corrige business_id) — deletei linha órfã `view_nao_agente` pra revalidar; CI (migrate:fresh) imune.

## Pointers detalhados
- Session log (narrativa completa + tabela de validação): ver acima.
- PRs #3562 / #3563 (bodies com causa-raiz + validação).
