---
name: Freshness Pipeline (Memory health observability)
gap: D7 #2
auditoria: AUDITORIA-MEMORIA-2026-05-15.md
status: aceito
authority: canonical
lifecycle: ativo
decided_at: 2026-05-15
related: [0053, 0055, 0067]
---

# Freshness Pipeline — Memory Health Observability

> **GAP D7 #2** (auditoria memoria-senior 2026-05-15) — complementa sync git→DB ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) + time-decay query-time (Onda 5 — `MeilisearchDriver::applyTimeDecay`) com **pipeline ativo de frescura** sobre `mcp_memory_documents`.

## Por que existe

Sync git→DB já replica conteúdo via webhook GitHub + cron 5min, mas até 2026-05-15 **não havia observabilidade do índice**:

- Doc de 2024 pesava igual a doc 2026 no recall — mitigado parcialmente por `time_decay` (query-time scoring) e seu boost por status, mas sem **alerta** quando o índice mesmo silencia.
- Não havia jeito de saber se sync falhou silenciosamente (webhook perdido + cron em erro): index vira "biblioteca infinita" sem priorização temporal.
- Letta 2026 e Mem0 explicitamente listam staleness como **open problem** ([State of AI Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)). Não há silver bullet: a melhor prática real é **observability + alerts + targeted reindex** (thresholds explícitos).

## 4 níveis de staleness

| Nível | Threshold default | Significado |
|---|---|---|
| **FRESH** | `indexed_at >= NOW - 1d` | Recém-indexado; pode aparecer com peso normal no retrieval |
| **WARM** | `1d < age < 7d` | OK — sync semanal cobre |
| **STALE** | `7d <= age < 30d` | Warning — investigar quando ratio > 20% |
| **CRITICAL** | `age >= 30d` ou `indexed_at IS NULL` | Alerta automático em `mcp_alertas_eventos` |

Ajustáveis via env:

```
JANA_FRESHNESS_PIPELINE=true|false
JANA_FRESHNESS_THRESHOLD_FRESH=1
JANA_FRESHNESS_THRESHOLD_WARM=7
JANA_FRESHNESS_THRESHOLD_STALE=30
JANA_FRESHNESS_AUTO_REINDEX=false   # off por default; cron usa --reindex explicit
```

## Drift detection (DB ↔ git)

Dois tipos:

1. **Drift tipo A — DB sabe que mudou**
   `updated_at > indexed_at` (Eloquent registrou update mas Scout não re-embedou).

2. **Drift tipo B — git diverge**
   `git_sha` do DB ≠ HEAD git pro arquivo. Best-effort: requer `shell_exec` habilitado (CT 100 sim, Hostinger não — degrada gracioso retornando `null`).

## Schedule canônico

```php
// app/Console/Kernel.php
$schedule->command('jana:freshness-check --alert --reindex --limit=50')
    ->dailyAt('04:30')
    ->timezone('America/Sao_Paulo')
    ->onOneServer()
    ->withoutOverlapping(20)
    ->environments(['live'])
    ->appendOutputTo(storage_path('logs/jana-freshness.log'));
```

**Por que 04:30?** Janela quieta — depois do `backup:run` (01:30) e `weeklyOn cleanup` (03:00 domingo), antes do `jana:health-check` (06:00). Não compete com `mcp:sync-memory` (every5min) — pelo contrário, é a rede de detecção quando ele silenciou.

## Comando

```bash
# Relatório (sem mexer)
php artisan jana:freshness-check

# JSON pra monitoring
php artisan jana:freshness-check --json

# Persistir alertas CRITICAL (idempotente por dia)
php artisan jana:freshness-check --alert

# Cria alertas + enfileira reindex pros stale/drift
php artisan jana:freshness-check --alert --reindex --limit=50

# Dry-run audita sem mexer em nada
php artisan jana:freshness-check --dry-run

# Detalhe por doc (lista CRITICAL + DRIFT)
php artisan jana:freshness-check --detail
```

> ⚠️ Symfony reserva `--verbose` ([rule .claude/rules/commands.md](../../.claude/rules/commands.md)) — usar `--detail` em vez disso.

### Exit codes

- `0` — saúde OK (0 critical, 0 drift)
- `1` — há CRITICAL ou drift detectado (cron alerta operacional via `appendOutputTo`)

## Métricas alvo

| Métrica | Target |
|---|---|
| (FRESH + WARM) / total | ≥ 80% |
| CRITICAL / total | ≤ 5% |
| Drift detectado / total | ≤ 1% |

Dashboard futuro (não escopo desta PR): expor em `/copiloto/admin/memoria` ([Modules/Jana/Resources/views](../../../Modules/Jana)) — só mostra contagem por nível + lista CRITICAL.

## Alertas mcp_alertas_eventos

Schema canônico já existente ([migration `2026_04_29_600001_create_mcp_alertas_eventos_table`](../../../Modules/Jana/Database/Migrations/2026_04_29_600001_create_mcp_alertas_eventos_table.php)). Esta feature **reusa** com:

- `tipo` = `memory_staleness`
- `severidade` = `high`
- `chave_idempotencia` = `memory_staleness:{slug}:{YYYY-MM-DD}` — não duplica no mesmo dia
- `business_id` = `null` (repo-wide, cross-tenant — `mcp_memory_documents` é compartilhada)
- `metadata` = `{doc_id, slug, git_path, indexed_at, idade_dias, tipo_alert: staleness_critical}`

## Troubleshooting

### "Mas o time-decay já não cuida disso?"

Não. Time-decay (`MeilisearchDriver::applyTimeDecay`) é **query-time**: pondera score do que já está no índice. Se sync git→DB falhou e o doc nunca foi reindexado, **o time-decay nem chega ali** — o doc nunca volta na busca. Freshness pipeline é **index-time observability**: detecta o silêncio do sync.

### "Por que `auto_reindex` é off por default?"

Pra evitar runaway em cenário de bug (todos os 350+ docs flagados como CRITICAL por causa de mudança no parser de frontmatter). O cron passa `--reindex --limit=50` explícito, dá pra Wagner desabilitar via env sem mexer no Kernel.

### "Detector de drift git não funciona no Hostinger"

Esperado. Hostinger desabilita `shell_exec` no shared hosting (mesmo motivo do `lerGitSha` em `IndexarMemoryGitParaDb` degradar gracioso). Em CT 100/local funciona normal. Drift tipo A (`updated_at > indexed_at`) funciona em qualquer ambiente.

### "Como aumentar/diminuir thresholds?"

Via env (sem mexer em código):

```bash
# Stale começa em 14d em vez de 7d
JANA_FRESHNESS_THRESHOLD_WARM=14
JANA_FRESHNESS_THRESHOLD_STALE=45
```

Não criar novas categorias sem ADR canon — 4 níveis foram calibrados contra Letta/Mem0/LangChain 2026.

## Custo / latência

- **Custo IA**: ZERO. Pipeline é SQL + filesystem only (ADR 0094 §2 tiered cost).
- **Latência cron**: ~2-5s pra 350 docs (1 query + iteração in-memory).
- **Queue jana-index**: max 50 jobs/execução × ~100ms cada = ~5s de queue.

## Relacionado

- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server canon + sync git→DB
- [ADR 0055](../../decisions/0055-multi-tenant-isolation-tier-0.md) — `mcp_alertas_eventos` schema canônico
- [ADR 0067](../../decisions/0067-mcp-tools-retrieval-sprint-8.md) — Retrieval Sprint 8 + time-decay (query-time)
- [AUDITORIA-MEMORIA-2026-05-15](../../audits/AUDITORIA-MEMORIA-2026-05-15.md) — origem deste gap D7 #2
