# RUNBOOK — Sentinela de transporte CT100→main (Onda 1)

> Fecha o gap de freshness/drift entre o que está em **GitHub main** e o que cada
> **env deployado** (MCP server / app) realmente serve. Incidente que motivou: o
> container MCP ficou **~19 dias stale vs main** e ninguém viu (tools
> handoff-pending/-ack inalcançáveis o tempo todo, silenciosamente).
>
> Estende o `DeployDriftChecker` (ADR 0216), que só cobria o ambiente ONDE o audit
> roda (CT 100). Agora cobrimos envs REMOTOS + frescor do índice + escalonamento.

## O que cada peça pega

| Checker | name | Pega | Severity | Fonte |
|---|---|---|---|---|
| `DeployDriftChecker` (já existia) | `deploy_drift` | Código LOCAL (`.git/HEAD` do CT 100) != main | high | arquivo webhook + origin/main ref |
| **`McpServedDriftChecker`** (novo) | `mcp_served_drift` | Commit **servido** por cada env remoto (`GET /api/mcp/version`) != main | high | `/api/mcp/version` (ADR 0256) × main |
| **`McpIndexFreshnessChecker`** (novo) | `mcp_index_freshness` | Índice `mcp_memory_documents` defasado vs último commit em git `memory/` | high | `max(updated_at)` × `git log -1 -- memory/` |

Os 3 rodam dentro do `governance:audit --all --notify` **já agendado** (Kernel daily 06:15 BRT).
Não há workflow novo — nada a registrar em `scripts/governance/gates-registry.json`.

## Como rodar manualmente

```bash
# Tudo (igual ao cron):
php artisan governance:audit --all --notify

# Só os checkers da sentinela de transporte:
php artisan governance:audit --check=mcp_served_drift --json
php artisan governance:audit --check=mcp_index_freshness --json

# Pela tag (pega os 3 + futuros de transporte):
php artisan governance:audit --tag=transporte --json
```

## Config (todas overridable por .env)

| Chave (`config/governance.php`) | Env | Default | O quê |
|---|---|---|---|
| `deploy_drift_envs` | `GOVERNANCE_DEPLOY_DRIFT_ENVS` (JSON) | `[{nome:mcp, url:https://mcp.oimpresso.com}]` | Envs que o `mcp_served_drift` consulta |
| `mcp_index_freshness_max_lag_hours` | `GOVERNANCE_MCP_INDEX_FRESHNESS_MAX_LAG_HOURS` | `6` | Lag tolerado (h) entre commit memory/ e índice |
| `drift_escalation_days` | `GOVERNANCE_DRIFT_ESCALATION_DAYS` | `3` | Dias abertos antes de escalar a severidade |
| `copiloto.mcp.drift_token` | `MCP_DRIFT_TOKEN` | — | Bearer do `/api/mcp/version` (mesmo da sentinela) |

Adicionar o app **Hostinger** ao `mcp_served_drift` só quando ele expuser `/api/mcp/version`
próprio — **Hostinger ≠ CT 100** (ADR 0062). Exemplo de override:

```
GOVERNANCE_DEPLOY_DRIFT_ENVS=[{"nome":"mcp","url":"https://mcp.oimpresso.com"},{"nome":"app","url":"https://oimpresso.com"}]
```

## Escalonamento por persistência (parte B)

O trait `PersistsDriftAlert` grava cada finding em `mcp_alertas_eventos` com idempotência
**diária**. Se o MESMO drift segue **aberto** há mais de `drift_escalation_days` dias, o
novo registro é gravado com **severidade elevada** (`warn→high`, `high→critical`) e
`metadata.escalated=true` + `first_seen_at` + `dias_aberto`, e o título ganha `[ESCALADO]`.
Sem coluna/migration nova — reusa `created_at` da primeira ocorrência aberta.

Efeito prático: o `--notify` (Centrifugo `governance:drift`) passa a tratar como **alerta
ativo** (severidade alta/crítica) em vez de mais um log diário ignorável. É o que teria
quebrado o silêncio de 19 dias.

## Como reagir ao alerta

1. **`mcp_served_drift` high** → um env serve commit != main.
   - Ler o `evidence`: `env`, `served`, `main`, `deployed_at`, `idade`.
   - É o env do CT 100 (MCP)? `git reset --hard origin/main` + `composer dump-autoload`
     + reload octane (ver `INFRA-ACESSO-CANON`). Atenção ao incidente classmap stale
     (deploy interrompido sem `composer dump-autoload` = 500 em toda rota).
2. **`mcp_index_freshness` high** → índice da memória parou de atualizar.
   - O job `IndexarMemoryGitParaDb` (webhook GitHub→MCP **ou** cron 5min) pode ter
     falhado calado. Checar `storage/logs/laravel.log`, o webhook e o cron.
   - Rodar o reindex manual do job e confirmar que `max(updated_at)` avança.
3. **Findings `low`/`info`** (HTTP 500/401, timeout, git/DB indisponível) → **não** são
   drift confirmado; são "não verificável agora". Não derrubam o audit. Se persistirem
   dias seguidos, o escalonamento eleva e aí investigar (token errado? env morto?).

## Notas de design / limites

- Falha de rede/HTTP **nunca** lança exception que quebra o audit — vira finding low/info.
- Os 3 checkers são **system-level** (sem `business_id`): `mcp_memory_documents` é
  repo-wide (ADR 0053) e os envs/commits são da plataforma. ADR 0093 §Exceção repo-wide.
- O `mcp_served_drift` reusa a MESMA fonte de "main" do `DeployDriftChecker`
  (`DeployDriftChecker::latestMainSha`) — single source of truth, sem duplicação.
- Token `MCP_DRIFT_TOKEN`: se vazar, revela só o SHA servido (sem user/RBAC) — ADR 0256.
