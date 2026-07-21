---
module: Brief
purpose: "Daily Brief (camada L7 / contexto) — gera estado consolidado ≤3.5k tokens 6x/dia + tool MCP brief-fetch + endpoint HTTP. Reduz onboarding de sessão Claude de ~30k para ~3k tokens. Sprint 1 da Constituição V2 (ADR 0091)."
contains:
  - "BriefFetchController — endpoint POST /api/mcp/tools/brief-fetch (cache 5min + audit log + telemetria)"
  - "BriefFetchTool — tool MCP equivalente (mcp.oimpresso.com), schema JSON, force_refresh restrito a Wagner"
  - "GenerateBriefCommand — artisan brief:generate, roda via cron 0 7,11,14,17,20,23 * * * (6x/dia America/Sao_Paulo)"
  - "BriefGeneratorService — pipeline CALL refresh_brief_inputs_cache → Brain B (sonnet-4-6) → grava mcp_briefs"
  - "BriefValidator + ValidationResult — valida output (7 headers obrigatórios, ≤3500 tokens, sem PII)"
  - "BriefServiceProvider — registra command, tool MCP e routes"
not_contains:
  - "Geração de chat/conversa em tempo real → Modules/Jana"
  - "Tools MCP genéricas (memoria-search, decisions-fetch, etc.) → Modules/Jana ou Modules/TeamMcp"
  - "Tokens MCP CRUD → Modules/TeamMcp"
  - "Audit dashboard UI → Modules/Governance"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
trust_required: L1
owner: wagner
permission_prefix: n/a
charter_adr: 0091
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0091-daily-brief
url_prefixes:
  - /api/mcp/tools/brief-fetch
db_tables_owned:
  - mcp_briefs (snapshot do brief gerado — 1 linha por execução, valid 0|1)
  - mcp_skill_telemetry (criada pelo schema do Daily Brief; escrita por BriefFetchTool/Controller)
db_tables_consumed:
  - mcp_audit_log (cada fetch grava entry)
  - mcp_cycles, mcp_tasks, mcp_decisions (lidos via procedure refresh_brief_inputs_cache)
drift_alerts: []
---

# Modules/Brief — Daily Brief gerador + tool MCP

## Missão

Toda sessão Claude Code começa com a tool `brief-fetch` carregando ~3k tokens de estado vivo (cycle ativo, HITL pending, decisões 24h, skills uso 7d). Substitui 5-8 chamadas exploratórias do passado (`cycles-active` + `sessions-recent` + `tasks-active` + `decisions-search`) que consumiam ~30k tokens de contexto exploratório.

Skill `brief-first` (Tier A always-on) força chamada como **primeira tool** em qualquer sessão.

## Trust level

**L1** — infra de contexto pra IA. Mesma faixa que TeamMcp/Governance/SRS. Ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Pipeline da geração

```
cron 0 7,11,14,17,20,23 * * *
        ↓
GenerateBriefCommand
        ↓
1. CALL refresh_brief_inputs_cache()  ← procedure MySQL agrega cycle/tasks/decisions
2. BriefGeneratorService::run()
   - lê cache de inputs
   - chama Brain B (gpt-4o-mini, ADR 0091 Sprint 1 patch)
   - retorna markdown ≤3500 tokens
3. BriefValidator::validate()
   - 7 headers obrigatórios
   - sem PII
   - tamanho < cap
4. INSERT em mcp_briefs (valid=1 / valid=0+erro)
5. Cache::forget('brief.current')
```

## Pipeline da leitura

```
Claude/agent → POST /api/mcp/tools/brief-fetch
                       ↓
BriefFetchController::__invoke
        ↓
1. mcp.auth middleware valida token
2. throttle:60,1 protege contra loop
3. Cache::remember('brief.current', 5min, fn () =>
     SELECT * FROM mcp_briefs WHERE valid=1 ORDER BY created_at DESC LIMIT 1)
4. Audit log + telemetria
5. Resposta JSON { content, generated_at, tokens, ... }
```

`force_refresh=true` chama `BriefGeneratorService::run()` na hora — restrito a Wagner, cap 8/dia (defesa contra loop de IA).

## Quando NÃO é tocado

- ❌ Conhecimento canônico (ADRs, sessions, requisitos) → Modules/KB
- ❌ Tools MCP de tasks (`my-work`, `tasks-list`, etc.) → Modules/Jana ou Modules/TeamMcp
- ❌ Audit / governance UI → Modules/Governance
- ❌ Constitution doc edit → `memory/governance/CONSTITUTION.md`

## Estado de implementação

| Item | Status |
|---|---|
| Migration `mcp_briefs` (Sprint 1 PR #104) | ✅ feito |
| Procedure `refresh_brief_inputs_cache` | ✅ feito (PR #107 reescrita com schema real) |
| GenerateBriefCommand + cron 6x/dia | ✅ feito |
| BriefGeneratorService (Brain B = gpt-4o-mini) | ✅ feito (PR #106 trocou de claude-sonnet-4-6) |
| BriefFetchController HTTP | ✅ feito |
| BriefFetchTool MCP | ✅ feito (PR #109) |
| Skill `brief-first` Tier A always-on | ✅ feito |
| `force_refresh` cap 8/dia Wagner | ✅ feito |

---

- **v1.0.0** (2026-05-06) — SCOPE.md inicial. Módulo entregue na Sprint 1 da Constituição V2 (ADR 0091).
