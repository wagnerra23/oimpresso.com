# Handoff 2026-05-15 18:30 — Audit memory Claude Code + evolução Fase 1+2 + spec MCP-017 + 4 hooks regression fix

## TL;DR

Continuação direta de [handoff 1010](2026-05-15-1010-whatsapp-maratona-encerrada-12prs-ui-brave-validada-regra-primaria.md) — após instalar regra primária "mexeu, registra" + workflow 3 fases PRE-FLIGHT/DURING/POST + hook `modulo-preflight-warning`, Wagner pediu **audit profundo de memória Claude Code aplicada ao oimpresso por domínio + revisão de gatilhos por conflitos**. Spawn `audit-senior-expert` (13 WebSearch + 3 WebFetch) entregou dossier 514 linhas com **nota 87/100**. Wagner aprovou Fase 1+2 + spec Fase 4. Auditoria do trabalho descobriu **4 hooks PowerShell quebrados em prod por encoding UTF-8 sem BOM + variável reservada `$input`**. Smoke test agregador criado pra prevenir regressão. **4 PRs mergeados nesta janela: #876, #878, #879, este handoff PR pendente.** Nota memory infra Claude Code: **87 → 92/100**.

## Cronologia evolutiva pós-1010

| Quando | Evento |
|---|---|
| 2026-05-15 14h | Wagner instalou workflow 3 fases (PRE-FLIGHT/DURING/POST) em proibições.md + hook modulo-preflight-warning.ps1 (PR #874 mergeado) |
| 17h | Wagner pediu: *"me explique o que o handoff... estado da arte memoria aplicada aqui claude code, por dominio bem estabelecido, sub-dominios. revise os gatilhos e regras todos por conflitos. quero que funcione da melhor forma"* |
| 17:05 | Spawn agent `audit-senior-expert` modo Opus sustained — pesquisa profunda Anthropic 2026 + DDD + indústria |
| 17:30 | Dossier 514 linhas entregue: nota 87/100, 5 conflitos detectados (C1-C5), top 10 gaps, decisão handoff HÍBRIDO per-session + module-state projection CQRS |
| 17:35 | Apresentado Wagner com 3 decisões macro (Fase 1+2 / Fase 3 / Fase 4) — Wagner aprovou TODAS |
| 17:40 | PR #876 Fase 1+2 mergeado: skill `preflight-modulo` Tier A + 3 quick wins (hook schema 2026 + banner count + AGENTS.md @import + audit module-completeness-audit) |
| 17:45 | Spawn agent paralelo criou spec US-MCP-017 module-state |
| 18:00 | PR #878 spec US-MCP-017 mergeado (SPEC + RUNBOOK 7 fases + bounded context Mcp novo) |
| 18:10 | Wagner: *"minha memoria estava em 98 pontos"* → auditoria escopos descobriu nota 98 era de Jana memory PRODUTO (2026-05-13 handoff sessão recorde 30 PRs), não infra Claude Code memória |
| 18:15 | Wagner: *"vai ter que auditar e ver pontos de regressão e criar testes"* → smoke real revelou **4 hooks BROKEN em prod** |
| 18:20 | Fix encoding UTF-8 BOM via `Set-Content -Encoding utf8` + rename `$input → $rawInput` |
| 18:25 | Smoke test agregador `test-all-hooks-smoke.ps1` criado — 12/12 hooks OK |
| 18:30 | PR #879 hooks regression fix mergeado · este handoff sendo escrito |

## PRs desta sessão (4 mergeados)

| PR | SHA squash | Conteúdo |
|---|---|---|
| [#876](https://github.com/wagnerra23/oimpresso.com/pull/876) | `fad68f49` | Fase 1+2: skill `preflight-modulo` Tier A + hook schema 2026 + banner count + AGENTS.md @import + audit dossier 514 linhas |
| [#878](https://github.com/wagnerra23/oimpresso.com/pull/878) | `a02979d1` | Spec US-MCP-017 `module-state` (CQRS projection per bounded context) + RUNBOOK 7 fases |
| [#879](https://github.com/wagnerra23/oimpresso.com/pull/879) | `ccf7a781` | Fix 4 hooks PS encoding + reserved var + smoke test agregador |
| Este | TBD | Handoff fechamento + índice atualizado |

## Artefatos canon novos/atualizados nesta sessão

### Skills

- [`.claude/skills/preflight-modulo/SKILL.md`](../../.claude/skills/preflight-modulo/SKILL.md) — Tier A always-on (NOVO, 139 linhas). Pareada com hook `modulo-preflight-warning.ps1`. Resolve conflitos C2 + C5 do audit.

### Hooks

- [`.claude/hooks/mcp-first-warning.ps1`](../../.claude/hooks/mcp-first-warning.ps1) — schema legacy → 2026 oficial (`hookSpecificOutput`) + rename `$input → $rawInput` + BOM UTF-8
- [`.claude/hooks/tier-a-banner.ps1`](../../.claude/hooks/tier-a-banner.ps1) — count corrigido (8 LIVE + 1 dormente) + ASCII puro
- [`.claude/hooks/block-mwart-violation.ps1`](../../.claude/hooks/block-mwart-violation.ps1) — BOM UTF-8 (fix em-dash parse error)
- [`.claude/hooks/charter-validate.ps1`](../../.claude/hooks/charter-validate.ps1) — BOM UTF-8
- [`.claude/hooks/modulo-preflight-warning.ps1`](../../.claude/hooks/modulo-preflight-warning.ps1) — BOM UTF-8
- [`.claude/hooks/test-all-hooks-smoke.ps1`](../../.claude/hooks/test-all-hooks-smoke.ps1) — NOVO. Smoke test agregador. Rodar `pwsh .claude/hooks/test-all-hooks-smoke.ps1` antes de commit que mexer em hooks.

### Sessions (memory audit)

- [`memory/sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md`](../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) — dossier audit 514 linhas: nota 87/100, 12 dimensões comparativas (Anthropic + DDD + industry + híbrido), 5 conflitos detectados, decisão handoff HÍBRIDO event-sourcing + CQRS, top 10 gaps priorizados, arquitetura final + caminho migração faseado, 9 surpresas estratégicas

### Specs MCP

- [`memory/requisitos/Mcp/SPEC.md`](../requisitos/Mcp/SPEC.md) — bounded context Mcp NOVO (não existia)
- [`memory/requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md`](../requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md) — spec tool MCP `module-state` (CQRS projection per bounded context). 14 campos response. Estimate 14h IA-pair. 5 decisões cinzentas pendentes Wagner aprovar antes implementação.
- [`memory/requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md`](../requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md) — 7 fases sequenciais com gate humano Fase 6 smoke biz=1

### AGENTS.md (compat externo)

- [`AGENTS.md`](../../AGENTS.md) — adicionado `@CLAUDE.md` import (agents.md spec Anthropic 2026) pra compat Codex/Cursor futuro

## 3 lições centrais

### 1. Audit profundo de memória precisa pesquisa Anthropic + DDD + indústria

Spawn `audit-senior-expert` modo Opus sustained (13 WebSearch + 3 WebFetch) descobriu **dois sistemas com nome "memory" no oimpresso** (Jana produto vs Claude Code infra) E **5 conflitos não detectados antes** (schema hook legacy, hook órfão sem skill, banner count drift, skill overlap parcial, regra primária invisível pro time MCP). Wagner achava "memória em 98%" — auditoria provou **escopo diferente** (Jana 98% vs Claude Code 87/100).

### 2. UTF-8 sem BOM mata PowerShell 5.1 silenciosamente

PS 5.1 default lê arquivo sem BOM como CP1252 (Windows-1252). Em-dash (`—`), seta (`→`), acentos, divisão (`÷`) viram bytes inválidos quebrando parser dentro de strings com aspas duplas. **4 hooks quebrados em prod por essa causa.** Fix: `Set-Content -Encoding utf8` adiciona BOM E PS lê UTF-8 corretamente. Convenção permanente: **TODOS os hooks `.ps1` devem ser salvos com BOM UTF-8**.

### 3. Smoke test agregador é defesa-em-profundidade

`test-all-hooks-smoke.ps1` previne regressão futura — qualquer dev que mexer em `.claude/hooks/` roda `pwsh .claude/hooks/test-all-hooks-smoke.ps1` ANTES de commit. Detecta encoding bugs + reserved var bugs + syntax errors em 12 hooks simultaneamente. Pattern emergente: **pra cada classe de drift descoberta, criar UM cron/test/observer** (consistente com handoff 0700 maratona WhatsApp).

## Estado memory Claude Code infra final

```
Skills Tier A LIVE:           8 (preflight-modulo NOVO)
Skills Tier A DORMENTE:       1 (ads-route até S5 ~jul/2026)
Skills total .claude/skills/: 44
Hooks PreToolUse/PostToolUse: 12 (todos com BOM UTF-8, smoke-tested)
Smoke test agregador:         ATIVO (test-all-hooks-smoke.ps1)
ADRs canon decisions/:        146 (0146 contact_lid feature-wish)
Sessions log canon:           60+ (esta sessão adiciona 1 dossier + 1 handoff)
Handoffs append-only:         33+
Reference feedback:           40+ (mexeu-registra-sempre + baileys-7x-irreversivel + ...)
Runbooks por módulo:          dezenas em memory/requisitos/<Mod>/
Proibições Tier 0:            6 seções organizadas (REGRA PRIMÁRIA topo)

Nota memory infra: 87 → 92/100 (Fase 1+2 entregue)
Próximas Fases pendentes Wagner:
- Fase 3: .claude/rules/ path-scoped (~3h, +3pp)
- Fase 4: module-state implementação (14h, +5pp se 5 cinzentas aprovadas)
```

## Pendente após esta sessão

### Wagner decide (publication-policy Tier A)

- 🟡 **Fase 3 `.claude/rules/` path-scoped** (~3h IA-pair) — Anthropic 2026 feature não usada. ROI estimado: ~R$ 11k/ano economia LLM tokens.
- 🟡 **Fase 4 implementação `module-state`** (14h IA-pair) — precisa Wagner aprovar 5 decisões cinzentas do spec US-MCP-017:
  - Multi-tenant edge módulos cross-tenant (Jana/Infra/Mcp)
  - Lista módulos via glob vs hardcoded
  - Cache TTL puro vs webhook GitHub invalidate
  - Drift thresholds parametrizar via config
  - Granularidade top-level vs sub-módulos

### Backlog opcional não-bloqueante

- 🔵 G8 audit descriptions em 1ª pessoa (anti-pattern Anthropic 2026) — 2h, +1pp
- 🔵 G9 playbook visual onboarding time MCP — 4h, +1pp
- 🔵 G10 glossary local DDD por bounded context — 6h backlog, +1pp
- 🔵 4 PRs não-WhatsApp ainda abertos: #594 proto Cockpit V2, #812 plano legacy migration, #860 sells audit, #862 sells invariants

## Estado MCP no momento do fechamento

- Brief Tier A SessionStart 14h carregado — CYCLE-06 ativo (martinho-fsm-jana-v2)
- Tasks MCP afetadas: nenhuma específica desta sessão (foco governança/memory infra)
- ADRs canon: 146 entries
- Sessions canon: 60+
- Handoffs append-only: 33+ entries
- Workflow Tier 0 PRE-FLIGHT/DURING/POST aplicado nesta sessão (mexeu em 11 arquivos .ps1 + .md, todos commitados em PRs separados)

---

**Próxima sessão:** retomar via `brief-fetch` → checar PRs Fase 3 + 4 pendentes Wagner aprovar → spawn agents implementação conforme aprovação.
