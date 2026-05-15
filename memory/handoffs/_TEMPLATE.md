<!--
  USE COMO BASE — NÃO EDITAR (canônico).
  Copie pra `memory/handoffs/{{YYYY-MM-DD-HHMM}}-{{slug-kebab}}.md`.
  Validado pelo CI gate `memory-schema-gate-extended.yml` (ADR 0130).

  REGRAS Tier 0 IRREVOGÁVEIS (ADR 0130):
  1. APPEND-ONLY — NUNCA modificar handoff antigo. Cria arquivo novo.
  2. Filename `^YYYY-MM-DD-HHMM-<slug-kebab>.md$`
     OK:    2026-05-15-2030-transicao-wagner-felipe-fase3.md
     ❌:    2026-05-15-transicao-wagner.md           (faltou HHMM)
     ❌:    2026-05-15-20-30-foo.md                   (HHMM com dashes)
  3. Seção `## Estado MCP no momento do fechamento` OBRIGATÓRIA (prova MCP-first).
  4. ANTES de escrever, rodar:
       - `cycles-active`
       - `my-work`
       - `sessions-recent limit:3`
       - `decisions-search since:<data-último-handoff>`
       - `whats-active` (se suspeita sessão paralela — ADR 0119)

  Override emergencial: `<!-- schema-allowlist: <razão> -->`.
-->
---
date: {{YYYY-MM-DD}}
time: "{{HHMM BRT}}"
slug: "{{slug-kebab}}"
tldr: "{{1-2 frases do estado pro próximo agente — leia primeiro item ao retomar}}"
decided_by: [W]            # W F M L E
cycle: "{{CYCLE-NN}}"      # ou null
prs: []                    # [881, 882]
us:  []                    # ["US-SELL-008"]
next_steps:
  - "{{próxima ação}}"
related_adrs: []           # ["0130-handoff-append-only-mcp-first"]
---

# Handoff {{YYYY-MM-DD HH:MM BRT}} — {{título curto pro próximo agente}}

## TL;DR

{{1-2 frases. Primeiro item lido ao retomar trabalho. NÃO encha de drama — só estado real}}

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| {{HH:MM}} | {{evento}} |
| {{HH:MM}} | {{evento}} |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Linhas | Notas |
|---|---|---|---|
| {{caminho}} | ✅ pronto | {{N}} | {{...}} |
| {{caminho}} | 🟡 WIP | {{N}} | {{próxima ação}} |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| #{{N}} | merged/aberto/draft | {{título}} |

## Decisões tomadas

| Pergunta | Decisão Wagner | Justificativa | Referência |
|---|---|---|---|
| {{...}} | {{...}} | {{...}} | {{link}} |

## Bloqueios / pendências

- [ ] {{bloqueio}} — owner: {{W}}
- [ ] {{pendência}} — owner: {{F}}

## Próximos passos (ordem)

1. {{passo 1}}
2. {{passo 2}}
3. {{passo 3}}

## Estado MCP no momento do fechamento

> **Obrigatório (ADR 0130 §6)** — snapshot do que tools MCP devolveram, NÃO promessa.

### cycles-active
```
{{output `cycles-active` — cycle ativo, dia N de M, goals}}
```

### my-work
```
{{output `my-work` — tasks ativas, owner, status}}
```

### sessions-recent limit:3
```
{{output `sessions-recent limit:3` — últimas 3 session logs}}
```

### decisions-search since:{{YYYY-MM-DD}}
```
{{output `decisions-search` — ADRs aceitas desde último handoff}}
```

### whats-active (se houver sessão paralela)
```
{{output `whats-active` ou "N/A — única sessão ativa"}}
```

## Referências

- Session log: [{{YYYY-MM-DD-slug}}.md](../sessions/{{YYYY-MM-DD-slug}}.md)
- Handoff anterior: [{{YYYY-MM-DD-HHMM-slug}}.md]({{YYYY-MM-DD-HHMM-slug}}.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
