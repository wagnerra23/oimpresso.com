---
slug: 0133-system-health-audit-canonico
number: 133
title: "System health audit canônico — 5 dimensões automáticas (Tool MCP + cron daily)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-10
module: Governance
tags: [governanca, observabilidade, auditoria, mcp, automacao, tiered-cost, constituicao-v2]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0091, 0094, 0119, 0130, 0131, 0132]
pii: false
review_triggers:
  - "Wagner pedir audit reavaliação 2x sem mudança no output → expandir checks (signal weak)"
  - "≥3 checks ficarem ❌ por >30 dias → criar US específica por dimensão pra escalar pra Wagner"
  - "≥1 check virar flaky (oscila OK/FAIL sem mudança real no sistema) → reescrever threshold com tolerância"
  - "Tool MCP `system-health-audit` passar a custar >R$ [redacted Tier 0]/mês de DB queries → otimizar via cache materialized view"
  - "Dimensão nova surgir (ex: PII leak em logs, dependency security audit) → adicionar como check 6+ (não inflar ADR; criar 0NNN nova)"
---

# ADR 0133 — System health audit canônico

## Contexto

Sessão 2026-05-10 (audit paralelo Wagner pediu "avalie meu sistema, compare com os melhores") rodou 5 sub-agents em paralelo cobrindo dimensões críticas:

1. **Observability AI** (Langfuse + OTLP exporter)
2. **Evals automáticos** (RAGAS gate CI pra Jana memory)
3. **ADR garbage collection** (lifecycle update de 132+ ADRs)
4. **Cost dashboard team-level** (`mcp_usage_diaria` agregação)
5. **Test coverage gate** (pcov + workflow CI)

Output dos 5 sub-agents virou:
- 3 tasks MCP (US-INFRA-016, US-COPI-105, US-COPI-106) com escopo refinado
- US-INFRA-016 já implementada nesta sessão (Langfuse stack rodando + PR-1 exporter live + smoke validado)

Wagner então pediu (2026-05-10 22h):
> "avalie meu sistema novamente. automatize as análises"

Audit manual via 5 sub-agents Opus paralelos custa ~1 ciclo de tokens significativo + Wagner precisa pedir explicitamente. Pra os 5 gaps ficarem **monitorados continuamente** sem custo recorrente, decidiu-se automação SQL+FS-only (princípio 2 Constituição v2 — tiered cost: zero LLM call).

## Decisão

### 1. Command artisan `jana:system-audit`

`Modules/Jana/Console/Commands/SystemAuditCommand.php` — mesmo pattern de `jana:health-check` ([ADR existente](../../README.md)). 5 checks rodam sequencialmente:

| # | Check | Sinal OK | Threshold |
|---|---|---|---|
| 1 | `observability_pipeline` | `LANGFUSE_HOST` env + HTTP 200 em `/api/public/health` | timeout 5s |
| 2 | `eval_ci_gate` | `.github/workflows/eval-recall-gate.yml` exists | filesystem |
| 3 | `adr_stale_count` | ≤ 5 ADRs canon com refs a Vizra/CURRENT.md/TASKS.md (não-deprecated) | threshold 5 |
| 4 | `cost_dashboard_aggregation` | ≥ 1 row em `mcp_usage_diaria` últimas 24h | SQL count |
| 5 | `test_coverage_gate` | `pcov` OR `coverage-clover` em algum workflow `.github/workflows/*.yml` | grep |

**Output**: tabela stdout (humano) OU JSON (`--json` machine-readable consumido pela tool MCP).

**Exit code**: 0 se tudo OK, 1 se qualquer check falhou (cron pode escalar via `--notify` que loga ALERT no channel `single`).

### 2. Tool MCP `system-health-audit`

`Modules/Jana/Mcp/Tools/SystemHealthAuditTool.php` — wrapper sobre `jana:system-audit --json`. Schema:

```php
'format' => 'markdown' | 'json'  // default markdown
```

Markdown output: tabela 5-row + lista remediations por check falhado. Cada remediation cita US-XXX-NNN concreta quando aplicável.

Registrada em `Modules/Jana/Mcp/OimpressoMcpServer.php` ao lado das outras 30+ tools. Acessível via `mcp call system-health-audit` no Claude Code.

### 3. Schedule cron daily 06:15 BRT

`app/Console/Kernel.php`:
- `06:00` BRT: `jana:health-check --notify` (existente, 6 checks Jana)
- `06:15` BRT: `jana:system-audit --notify` (novo, 5 checks Constituição v2)

15min de defasagem evita disputa de DB. `withoutOverlapping()` + `environments(['live'])` mantém pattern padrão.

### 4. Princípio 2 Constituição v2 — tiered cost

**ZERO LLM call** no cron (signal-only). Análise profunda fica disponível via skill `/system-audit` (futura, opção C híbrida) que dispara sub-agents — mas só Wagner-on-demand.

Custo estimado do cron daily:
- 1 HTTP request pra Langfuse health
- 1 file_exists check
- 1 glob + regex em ~140 ADRs
- 1 SQL count em `mcp_usage_diaria`
- 1 glob + grep em ~10 workflows YAML

Tempo: <2s. Custo $: zero (sem LLM, sem APIs paid). Custo DB: desprezível (single COUNT query).

### 5. Integração com brief diário

Tool MCP `brief-fetch` já agrega outputs de outras dimensões. Em PR follow-up (não neste), adicionar seção `## System Audit` no brief que consulta a tabela onde `jana:system-audit` posta resultado (TODO: criar tabela `jana_system_audit_runs` quando volume justificar — por ora basta channel `single` log).

### 6. Reativo + proativo

- **Reativo** (atual): cron diário grava log; sub-agents Wagner-on-demand fazem reasoning profundo
- **Proativo** (futuro, review_trigger 1): se ≥3 checks falharem por >30d, criar US específica auto-escalando pra Wagner

## Não-decidido (fora de escopo)

- **Skill `/system-audit` deep (opção C híbrida)** — disparada por Wagner, roda 5 sub-agents paralelos (replica padrão desta sessão), salva em `memory/audits/YYYY-MM-DD-HHMM-deep.md` append-only conforme ADR 0130. Fica P2 follow-up — cron daily já fecha 80% do valor.
- **Slack alert** quando check falha 2 dias seguidos — overkill enquanto time é 5 pessoas; Wagner vê via brief diário.
- **Dashboard UI `/copiloto/admin/system-audit`** — 1 página com 5 sparklines + histórico 30d. Vale quando tabela `jana_system_audit_runs` existir.
- **Audit governance "rigor" auto-calculado** (top X% global vs benchmark) — depende de dados externos; fica como prompt deep do skill `/system-audit`.

## Consequências

### Positivas

- **Loop fechado por métrica** (princípio 4) — 5 dimensões críticas monitoradas continuamente sem custo recorrente
- **Self-validating system** — Wagner vê regressão (Langfuse caiu, eval gate quebrou) no brief diário sem precisar pedir audit
- **Tiered cost honored** (princípio 2) — SQL+FS only no cron; LLM reasoning fica explícito (skill on-demand)
- **Discoverability** — tool MCP `system-health-audit` no Claude Code = Felipe/Maiara podem consultar estado sem permission especial
- **Remediations auto-citadas** — cada check falhado já aponta US-XXX-NNN ou comando concreto pra resolver

### Negativas

- **Checks signal-only não capturam nuance** — "observability healthy" ≠ "métricas chegam sem perda". Capture vs lag. Mitigado por skill deep on-demand.
- **Threshold 5 em `adr_stale_count` é arbitrário** — sai numa rule fixed; review_trigger 1 ajusta se virar barulho.
- **Cron daily acrescenta 1 schedule a mais** — Hostinger já tem 6+ schedules; trivial. Risco: disputa de DB com `jana:health-check` 06:00 mitigado por 15min defasagem.
- **Tool MCP roda Artisan call** — overhead boot Laravel ~200ms. Aceitável pra tool consultiva.

### Neutras

- **Convergência com pattern existente** (`jana:health-check`) — manutenção homogênea, dev novo entende fácil

## Plano de implementação (1 PR — neste worktree)

1. ADR 0133 (este arquivo) — registro da decisão
2. `Modules/Jana/Console/Commands/SystemAuditCommand.php` — 5 checks + signature + table render
3. `Modules/Jana/Mcp/Tools/SystemHealthAuditTool.php` — wrapper MCP + render Markdown
4. `Modules/Jana/Mcp/OimpressoMcpServer.php` — registrar tool nova
5. `app/Console/Kernel.php` — schedule daily 06:15 BRT
6. Pest tests em `tests/Feature/Jana/SystemAuditCommandTest.php` cobrindo 5 paths (cada check OK + 1 caso FAIL)

Estimate: ~600 linhas adicionadas + ~10 linhas modificadas no Kernel.

## Referências

- [ADR 0091](0091-daily-brief.md) — Daily Brief (canal natural pra brief integrar audit)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio 2 tiered cost + princípio 4 loop fechado)
- [ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md) — `whats-active` tool MCP (mesmo pattern de wrapper sobre SQL)
- [ADR 0130](0130-handoff-append-only-mcp-first.md) — Handoff append-only (skill `/system-audit` futuro vai gravar em `memory/audits/` conforme padrão)
- [ADR 0131](0131-tiering-memoria-canonico-local-segredo.md) — Tiering memória (segredos do Langfuse acessados read-only no check 1)
- [ADR 0132](0132-langfuse-self-host-ct100.md) — Langfuse self-host (check 1 valida health desta stack)
- US-INFRA-016 — Langfuse exporter (resolvido nesta sessão — check 1 deve passar)
- US-COPI-105 — Eval CI gate (TODO — check 2 vai passar quando done)
- US-COPI-106 — ADR GC ritual (TODO — check 3 ajuda priorizar)
