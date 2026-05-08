---
slug: NEXT-skills-tiers
number: NEXT
title: "Skills Tier A/B/C — convenção interna pra controle de always-on"
type: adr
status: proposed
authority: [Wagner]
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-06
decided_by: [Wagner, Claude]
tier: CANON
related_adrs: [NEXT-constituicao-v2]
parent_charter: mission.constituicao-v2
supersedes: []
referenced_by: []
authors: [wagner, sonnet]
---

# ADR — Skills Tier A/B/C (convenção interna)

> **Status:** 📝 PROPOSTO — Wagner revisa cada seção e marca aprovação no PR.
> Camada L3 da Constituição v2 (ADR mãe NEXT-constituicao-v2).

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
| **A — always-on** | Influencia TODA sessão | **Hook `SessionStart`** força chamada à tool MCP correspondente + `description` escrita pra match agressivo |
| **B — auto-trigger** | Dispara quando `description` casa com tarefa em andamento | `description` começa com "Use ao/quando..." (Anthropic best practice) |
| **C — on-demand** | Só dispara via slash command `/<skill>` ou referência explícita | `disable-model-invocation: false` + invocação humana |

### Frontmatter SKILL.md proposto (com tier)

```yaml
---
name: brief-first
description: "ATIVAR PRIMEIRO em toda sessão — força chamada brief-fetch antes de qualquer outra tool"
allowed-tools: mcp__oimpresso__brief-fetch  # opcional
disable-model-invocation: false  # default
# Convenção interna oimpresso (NÃO canônico Anthropic):
tier: A
tier_enforce: hook-session-start  # como o tier é garantido
parent_adr: NEXT-skills-tiers
---
```

### Critério pra promover/rebaixar tier

Avaliação trimestral baseada em telemetria (`mcp_skill_telemetry`):

| De → Para | Critério |
|---|---|
| Tier B → A | Usada em ≥80% das sessões em 30d **e** Wagner aprova **e** existe ADR justificando always-on |
| Tier B → C | Usada em <10% das sessões em 60d (description não está triggerando — esconder) |
| Tier C → arquivar | Não usada em >90d **e** sem charter de mission referenciando |
| Tier A → B | Wagner detectou regressão de comportamento atribuída à skill |

Promoções A precisam **ADR específica** (esta ADR é mãe, não autoriza promoções automáticas).

### Mecanismos de enforcement por tier

**Tier A** (5 skills propostas — ver §03):

```jsonc
// .claude/settings.json
{
  "hooks": {
    "SessionStart": [{
      "name": "force-tier-a-skills",
      "command": "tools/check-tier-a-skills.ps1"
      // Lista cada Tier A:
      //  - brief-first: força call brief-fetch
      //  - mcp-first: avisa "use mcp tools antes de Read"
      //  - multi-tenant-patterns: se sessão toca Eloquent, exibe regras
      //  - commit-discipline: se sessão tem >300 linhas diff, alerta
      //  - charter-first: se editar .tsx com .charter.md ao lado, bloqueia
    }]
  }
}
```

**Tier B** — description aggressive:
```yaml
description: "Use ao criar/alterar Eloquent Model que toca dados de negócio (qualquer entidade com business_id). Garante global scope."
```

**Tier C** — slash command + opt-in:
```yaml
disable-model-invocation: false  # responde a /<skill> mas não auto-trigger
description: "Skill on-demand pra <X>. Invoque via /<skill>."
```

### Telemetria obrigatória

Toda skill (todos tiers) emite entry em `mcp_skill_telemetry`:

```sql
CREATE TABLE mcp_skill_telemetry (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) NOT NULL,
  skill_name VARCHAR(120) NOT NULL,
  skill_tier ENUM('A','B','C') NOT NULL,
  fired_at TIMESTAMP NOT NULL,
  fired_first BOOLEAN DEFAULT FALSE,  -- foi a 1a skill da sessão?
  triggered_by ENUM('hook','description','slash_command','manual') NOT NULL,
  tokens_saved_estimate INT NULL,  -- comparado com baseline sem skill
  helpful BOOLEAN NULL,  -- auto-feedback ou Wagner manual
  user_id INT NULL,
  business_id INT NULL,
  INDEX idx_skill_fired (skill_name, fired_at)
);
```

Schema **já existe parcialmente** em prod (S1 entregou). Esta ADR formaliza colunas faltantes.

## Auditoria 19 skills atuais

Ver **[03-skills-audit.md](03-skills-audit.md)** — tabela completa com decisão por skill.

Resumo da distribuição proposta:
- 3 Tier A ativas + 2 Tier A dormentes (esperando S4/S5) = 5 Tier A
- ~9 Tier B (trigger contextual)
- ~5 Tier C (slash command)
- 0 arquivadas (sem dados telemetria suficiente — esperar 30d)

## Como propor skill nova

| Cenário | Caminho |
|---|---|
| Tier B/C nova (uso contextual ou on-demand) | PR + SKILL.md com description "Use ao/quando..." |
| Tier A nova (always-on) | **PR + ADR específica + Wagner aprova promoção** |
| Migrar B → A | PR + telemetria 30d ≥80% + Wagner aprova |
| Arquivar (telemetria 0) | PR mexe `_archive/` + ADR HISTORICAL com razão |

## Consequências

### Positivas
- Tier explícito = expectativa clara pra dev novo (Felipe, Maiara)
- Hook `SessionStart` enforce Tier A mecanicamente (não confia em "lembrar")
- Telemetria permite decisão baseada em dado, não opinião
- Auditável trimestralmente — skill que não dispara é candidata a archive

### Negativas
- Convenção interna ≠ Anthropic padrão — futuro Claude Code pode ignorar `tier:` no frontmatter
- Hook `SessionStart` adiciona ~2-3s no warm-up de sessão (custo: mínimo)
- Promoção pra Tier A requer ADR — atrito (intencional)

### Mitigações
- Documentar claramente em CLAUDE.md que `tier:` é convenção interna
- Hook `SessionStart` pode ser desabilitado via flag emergencial
- Telemetria retroativa permite reavaliar promoções

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-06 | Sonnet + Wagner | ADR proposta — convenção tier formalizada |
