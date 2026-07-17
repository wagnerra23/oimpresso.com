---
slug: 0095-skills-tiers-convencao-interna
number: 95
title: "Skills Tier A/B/C — convenção interna pra controle de always-on"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-06"
decided_by: [W]
module: governance
tier: CANON
related_adrs: [0094, 0091, 0093]
parent_charter: mission.constituicao-v2
parent_adr: 0094
supersedes: []
referenced_by: []
authors: [wagner, sonnet]
accepted_at: 2026-05-06
accepted_by: wagner
---

# ADR 0095 — Skills Tier A/B/C (convenção interna)

> **Status:** ✅ ACEITA em 2026-05-06 por Wagner.
> Camada L3 da Constituição v2 ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)).

---

## Contexto

Anthropic Skills (oficial) suporta 4 campos no frontmatter SKILL.md: `name`, `description`, `allowed-tools`, `disable-model-invocation` ([Anthropic docs](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)). **Não existe** campo `tier` canônico.

Mas com 19 skills no projeto e mais por vir, precisamos formalizar:
- Quais skills SEMPRE devem influenciar Claude (always-on)
- Quais ativam só por contexto detectado pela `description`
- Quais só rodam via slash command explícito

Este ADR cria **convenção interna `tier: A/B/C`** — não é frontmatter Anthropic-padrão, é metadata documental + controlada por mecanismos reais (hooks + description).

## Decisão

### Convenção tier (interna oimpresso)

| Tier | Comportamento esperado | Mecanismo técnico real |
|---|---|---|
| **A — always-on** | Influencia TODA sessão | **Hook `SessionStart`** força chamada à tool MCP correspondente + `description` agressiva |
| **B — auto-trigger** | Dispara quando `description` casa com tarefa | `description` começa com "Use ao/quando..." (Anthropic best practice) |
| **C — on-demand** | Só dispara via slash command `/<skill>` ou referência explícita | `disable-model-invocation: false` + invocação humana |

### Frontmatter SKILL.md proposto (com tier)

```yaml
---
name: brief-first
description: "ATIVAR PRIMEIRO em toda sessão — força chamada brief-fetch antes de qualquer outra tool"
allowed-tools: mcp__oimpresso__brief-fetch
# Convenção interna oimpresso (NÃO canônico Anthropic):
tier: A
tier_enforce: hook-session-start
parent_adr: 0095
---
```

### Critério pra promover/rebaixar tier

Avaliação trimestral baseada em telemetria (`mcp_skill_telemetry`):

| De → Para | Critério |
|---|---|
| Tier B → A | Usada em ≥80% sessões em 30d **E** Wagner aprova **E** existe ADR justificando always-on |
| Tier B → C | Usada em <10% sessões em 60d (description não trigger — esconder) |
| Tier C → arquivar | Não usada em >90d **E** sem charter de mission referenciando |
| Tier A → B | Wagner detectou regressão atribuída à skill |

Promoções A precisam **ADR específica** (esta é mãe, não autoriza promoções automáticas).

### As 5 Tier A canônicas (decisão por skill)

Decisões individuais foram tomadas em [memory/sprints/s3-constituicao/03-skills-audit.md](../sprints/s3-constituicao/03-skills-audit.md) Bloco A. Resumo:

| Skill | Estado | Mecanismo enforcement |
|---|---|---|
| `brief-first` | ✅ JÁ Tier A | Hook SessionStart força brief-fetch |
| `mcp-first` (rename `oimpresso-mcp-first`) | NOVO Tier A | Description aggressive + hint SessionStart |
| `multi-tenant-patterns` | PROMOVIDA B → A | Hook se sessão toca Eloquent/Migration |
| `commit-discipline` | NOVA Tier A | Hook PreToolUse em git commit (>300 linhas alerta) |
| `charter-first` | DORMENTE até S4 | Hook PreToolUse em `.tsx` se houver `.charter.md` |
| `ads-route` | DORMENTE até S5 | Toda decisão custosa via `decide(domain,intent,payload)` |

### Telemetria obrigatória (já existe parcial)

Toda skill (todos tiers) emite entry em `mcp_skill_telemetry`:

```sql
-- Schema existente em prod (Sprint 1) — colunas críticas:
-- session_id, skill_name, fired_at, fired_first, triggered_by, helpful

-- Esta ADR reforça: cada skill DEVE emitir telemetria.
-- Skills Tier A/B sem telemetria 30d serão revisadas (drift detection).
```

### Mecanismo de enforcement Tier A

`.claude/settings.json` hook:

```jsonc
{
  "hooks": {
    "SessionStart": [
      {
        "name": "force-tier-a-brief-first",
        "command": "tools/check-brief-fetch.ps1"
      },
      {
        "name": "tier-a-multi-tenant-hint",
        "command": "tools/multi-tenant-warning.ps1"
      }
    ],
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "command": "tools/commit-discipline-check.ps1"
      }
    ]
  }
}
```

## Como propor skill nova

| Cenário | Caminho |
|---|---|
| Tier B/C nova | PR + SKILL.md description "Use ao/quando..." |
| Tier A nova | **PR + ADR específica + Wagner aprova promoção** |
| Migrar B → A | PR + telemetria 30d ≥80% + Wagner aprova ADR |
| Arquivar (telemetria 0) | PR mexe `_archive/` + ADR HISTORICAL |

## Consequências

### Positivas
- Tier explícito = expectativa clara pra dev novo (Felipe, Maíra)
- Hook `SessionStart` enforce Tier A mecanicamente (não confia em "lembrar")
- Telemetria permite decisão baseada em dado, não opinião
- Auditável trimestralmente

### Negativas
- Convenção interna ≠ Anthropic padrão — futuro Claude Code pode ignorar `tier:` no frontmatter
- Hook `SessionStart` adiciona ~2-3s no warm-up (custo: mínimo)
- Promoção pra Tier A requer ADR — atrito (intencional)

### Mitigações
- Documentar em CLAUDE.md que `tier:` é convenção interna
- Hook pode ser desabilitado via flag emergencial
- Telemetria retroativa permite reavaliar promoções

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-06 | Sonnet rascunho + Wagner aprovação | ADR proposta — convenção tier formalizada |
