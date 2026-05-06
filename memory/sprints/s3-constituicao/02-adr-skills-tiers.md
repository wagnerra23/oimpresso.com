# ADR — Skills Tier A/B/C (convenção interna oimpresso)

> **Status:** 🔴 ESQUELETO — preencher após aprovação §13 ROTEIRO.

---

## Frontmatter

```yaml
---
adr_id: NEXT+1
title: Skills Tier A/B/C — convenção interna pra controle de always-on
status: proposed
tier: CANON
related_adrs: [NEXT]  # constituicao-v2
parent_charter: mission.constituicao-v2
authors: [wagner, sonnet]
---
```

## Seções (a preencher)

### 1. Problema

`tier:` no SKILL.md frontmatter NÃO é canônico Anthropic — só `name`, `description`, `allowed-tools`, `disable-model-invocation` são oficiais. Mas precisamos formalizar quais skills "sempre" devem influenciar Claude vs. as que só ativam por contexto.

### 2. Convenção tier (interna)

| Tier | Comportamento | Mecanismo técnico real |
|---|---|---|
| **A — always-on** | SEMPRE consultada na sessão | Hook `SessionStart` + description aggressive |
| **B — auto-trigger** | Dispara quando `description` casa com tarefa | description com "Use ao/quando" |
| **C — on-demand** | Só dispara via slash command `/<skill>` | `disable-model-invocation: false` + invocação explícita |

### 3. Critério pra promover/rebaixar

(Define quando uma skill sobe ou desce de tier)

- Promover Tier B → A: usada em ≥80% das sessões em 30d **e** Wagner aprova
- Rebaixar Tier B → C: usada em <10% das sessões em 60d
- Arquivar (remover): não usada em >90d **e** sem charter de mission referenciando

### 4. Telemetria obrigatória

Toda skill tem entry em `mcp_skill_telemetry`:
- session_id, skill_name, tier, fired_at, tokens_saved_estimate, helpful (bool — auto-feedback)

### 5. Mecanismo de enforcement Tier A

Hook `SessionStart` em `.claude/settings.json` força chamada à tool MCP correspondente da skill Tier A — exemplo: `brief-first` chama `brief-fetch` automaticamente antes de qualquer outra tool.

### 6. Lista atual proposta

| Skill | Tier proposto | Justificativa |
|---|---|---|
| `brief-first` | **A** | já always-on via S1 |
| `oimpresso-mcp-first` | **A** (rename → `mcp-first`) | usar tools MCP antes de Read |
| `multi-tenant-patterns` | **A** (promover de B) | Tier 0 — pior bug possível |
| `commit-discipline` | **A** (criar) | 1 PR = 1 intent, ≤300 linhas |
| `charter-first` | **A dormente** | aguarda S4 |
| `ads-route` | **A dormente** | aguarda S5 |
| `criar-modulo` | B | só ativa em criação módulo |
| `migrar-modulo` | B | só ativa em mover/renomear |
| `ads-decision-flow` | B | trigger por intent ADS |
| `memoria-recall-flow` | B | trigger por retrieval Copiloto |
| `copiloto-arch` | B | trigger por work em Copiloto |
| `oimpresso-stack` | C | one-time onboarding |
| `oimpresso-team-onboarding` | C | one-time onboarding |
| ...resto (pré-classificação na auditoria 03) | | |

---

## Notas pra Sonnet preencher (quando autorizado)

- Ler [s3-constituicao-deep-dive.md achado #3](../research/s3-constituicao-deep-dive.md) — `tier:` não é canônico
- Definir explicitamente que `tier:` no frontmatter é DOCUMENTAÇÃO, não trigger
- Trigger real é via description + hooks
