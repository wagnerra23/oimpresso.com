---
title: "RUNBOOK — route-hits: sinal contínuo de execução real (servido)"
module: Infra
owner: W
status: ativo
last_validated: "2026-07-09"
preconditions:
  - "PR de coleta mergeado + deploy Hostinger feito"
  - "Wagner aprovou canary (ROUTE_HITS_ENABLED é default OFF)"
steps:
  - "Canary: ligar ROUTE_HITS_ENABLED=true no .env de prod + observar 7d"
  - "Flush roda sozinho (schedule daily 00:15 BRT); conferir tabela route_hits"
  - "Export: php artisan route-hits:export --write + commit do JSON"
---

# RUNBOOK — route-hits (sinal "servido")

## O que é

Eixo **runtime** da governança spec↔código (grade v3, fraqueza "verificação
runtime" 5/10, régua Coverband/Wallarm). O estático já existia (anchor-lint:
dead/zombie/wired; charter-live-signal: prod-flags/smoke). Isto adiciona a
prova **dinâmica**: a rota foi de fato **servida** nos últimos N dias.

Pipeline (4 peças, todas neste repo):

1. **Middleware [`ContadorHitsRota`](../../../app/Http/Middleware/ContadorHitsRota.php)** — fim do grupo `web`
   (chokepoint real: core + módulos nWidart). Conta em `terminate()`
   (pós-response) via `Cache::increment`. **Default OFF** (`ROUTE_HITS_ENABLED`).
   Chave: `route_hits:{Y-m-d}:{identidade}` — identidade = nome da rota OU
   URI-pattern (`repair/{id}/edit`). **ZERO PII, ZERO tenant, ZERO DB síncrono.**
2. **`route-hits:flush`** (schedule daily 00:15 BRT) — cache → tabela agregada
   `route_hits` (batch; único caminho de escrita). `--prune` apaga além de
   `ROUTE_HITS_RETENCAO_DIAS` (default 90).
3. **`route-hits:export [--dias=30] --write`** — tabela → ledger versionável
   `governance/route-hits.json` (`rotas` + `pages` via Reflection do método de
   action → `Inertia::render` literal). Mesmo protocolo do
   `governance:prod-flags`: roda no host de PROD, commit manual, **nunca editar
   o JSON à mão**.
4. **Consumidores** (PR separado): `anchor-lint.mjs` (advisory
   `servido`/`nao_servido` — "existe+roteado mas 0 hits em Nd") e
   `charter-live-signal.mjs` (3ª fonte de sinal pra `status: live`).

## Rollout (gate Wagner)

| Fase | Ação | Critério de avanço |
|---|---|---|
| 0 | Merge + deploy com flag OFF | zero mudança de comportamento (middleware early-return) |
| 1 | Canary: `ROUTE_HITS_ENABLED=true` no .env prod | 7d sem regressão de latência/erro (laravel.log + smoke R1) |
| 2 | Primeiro export: `php artisan route-hits:export --write` via SSH + commit | JSON revisado (zero PII — só nomes de rota/pattern) |
| 3 | Lints passam a consumir o ledger (PR B) | advisory only — nunca avermelha gate |

Se pesar no shared hosting: `ROUTE_HITS_SAMPLE_RATE=0.25` (amostragem — o
export registra o rate vigente) ou desligar a flag (kill-switch instantâneo).

## O que NÃO é

- **Não é prova de correção** — hit ≠ funciona (isso é smoke R1/Pest).
- **Não é analytics de usuário/tenant** — deliberadamente sem `business_id`
  (o ledger é público no git; ver comment da migration).
- **Não substitui** anchor-lint/charter-live-signal — enriquece com o eixo uso.

## Troubleshooting

- Tabela vazia com flag ON → conferir schedule `route-hits-flush` rodou
  (`storage/logs/laravel.log`, entry de falha nomeada) e driver de cache
  (`CACHE_DRIVER=file` no Hostinger — increment funciona; TTL 48h).
- Rota esperada ausente do export → rota tem nome? Senão entra pelo
  URI-pattern. Página ausente de `pages` → render por variável/closure não é
  atribuído (conservador por design).
