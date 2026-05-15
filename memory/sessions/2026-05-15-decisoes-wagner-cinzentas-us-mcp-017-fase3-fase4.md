# Decisões Wagner — Cinzentas US-MCP-017 + escopo Fase 3+4 (2026-05-15 18:50)

> **Tipo:** session log — registro de decisões cinzentas resolvidas.
> **Origem:** handoff 2026-05-15 18:30 pendia 5 cinzentas + escolha escopo Fase 3 vs 4 vs ambas.
> **Aprovação Wagner:** 2026-05-15 18:45 via AskUserQuestion no chat.

## Escopo aprovado

**AMBAS Fase 3 + Fase 4** em paralelo (~17h IA-pair total).

## Cinzentas resolvidas

### C1+C2 — Multi-tenant edge + Discovery módulos

**Decisão:** "Incluir todos + Glob"

- Módulos cross-tenant (Jana/Infra/Mcp) **ENTRAM no scan** com flag `crossTenant: true` no response shape.
- Discovery via `Glob memory/requisitos/*/SPEC.md` (auto-detect filesystem). Zero manutenção quando módulo novo nasce.
- Filtro opcional por `config/modules_statuses.json` se quiser excluir Disabled.

**Implicação pra implementação:**
- Schema response ganha campo `crossTenant: boolean` (default false).
- Módulos `Jana`, `Infra`, `Mcp`, `Brief` → `crossTenant: true` → tasks/handoffs retornados SEM filtro `business_id`.
- Módulos com scope (`Whatsapp`, `Sells`, `Crm`, `Financeiro`, ...) → herda `business_id` do `Request::user()`.
- Discovery: `glob(base_path('memory/requisitos/*/SPEC.md'))` → array de nomes módulo.

### C3+C4+C5 — Cache + Thresholds + Granularidade

**Decisão:** "TTL 5min + Config + Top-level (Recommended)"

- Cache: `Cache::remember('module-state:<x>:biz=<n>', 300, ...)` — paridade `brief-fetch` ADR 0091. Simples + barato.
- Drift thresholds em `config/mcp.php`:
  ```php
  'module_state' => [
      'cache_ttl_seconds' => 300,
      'drift' => [
          'stale_doing_days' => 7,
          'stale_blocked_days' => 30,
          'charter_morto_days' => 60,
          'runbook_morto_days' => 60,
          'pr_count_alert' => 10,
      ],
  ],
  ```
- Granularidade: **top-level only**. `Modules/Sells` é uma unit. `Modules/Sells/Compras/` NÃO ganha entry separado a menos que `memory/requisitos/Sells/Compras/SPEC.md` exista (raro).

## Implicação Fase 4 RUNBOOK

O [RUNBOOK existente](../requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md) já assume essas escolhas (são as "Recommended" do §10 do SPEC). Implementação segue RUNBOOK fielmente, apenas adicionando:

- Campo `crossTenant: boolean` no response shape (§5 schema).
- Lista hardcoded módulos cross-tenant: `['Jana', 'Infra', 'Mcp', 'Brief']` (ou config).
- Config `config/mcp.php` com section `module_state`.

## Pendências pós-implementação

- Wagner valida smoke biz=1 (Fase 6 gate humano) — Whatsapp + Sells + Jana + Crm + módulo inexistente.
- Se output >800 tokens ou ruidoso, voltar Fase 2 ajustar coletores.

## Refs

- [SPEC US-MCP-017](../requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md) §10 áreas cinzentas
- [RUNBOOK module-state-tool](../requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md) 7 fases
- [Handoff 2026-05-15 18:30](../handoffs/2026-05-15-1830-audit-memory-claude-code-evolution-fase1-2-hooks-regression-fix.md) (origem pendências)
- [ADR 0091](../decisions/0091-daily-brief.md) — pattern cache 5min
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 isolation
