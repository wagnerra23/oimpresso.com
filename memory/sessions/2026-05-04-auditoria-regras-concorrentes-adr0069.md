---
slug: session-2026-05-04-auditoria-regras-concorrentes-adr0069
title: "Sessão 2026-05-04 — Auditoria regras concorrentes pós-ADR 0069 + HOW_TO_ASK_CLAUDE estado-da-arte"
type: session
tags: [governance, adr-0069, taskregistry, audit, prompting, claude-code]
date: 2026-05-04
---

# Sessão 2026-05-04 — Auditoria regras concorrentes + HOW_TO_ASK_CLAUDE 2026

## Contexto

Após criar [ADR 0069](../decisions/0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated.md)
formalizando TaskRegistry MCP tools como source-of-truth (TASKS.md ASCII deprecated),
Wagner pediu para verificar se há regras concorrentes em outros docs de governança +
pesquisar estado da arte de prompting Claude Code 2026.

## Auditoria — regras concorrentes encontradas e fixadas

Varredura `Grep` por menções a `TASKS.md`, `atualiz.*CURRENT`, `sobrescrit`, `apenda`
em todos os `.md` do projeto. Encontradas **10 regras concorrentes**, fixadas:

| Arquivo | Conflito | Fix aplicado |
|---|---|---|
| `CLAUDE.md:87` | "Atualize CURRENT.md (sobrescrito) ... com novo estado" | Redireciona pra TaskRegistry MCP + ADR 0069 |
| `CLAUDE.md:100` | "TASKS.md = backlog completo" | Marcado como ⚠️ DEPRECATED (ADR 0069) |
| `CLAUDE.md:254` | "Daily async ... atualiza status no TASKS.md" | "atualiza status via TaskRegistry MCP tool `tasks-update`" |
| `CURRENT.md:3` | "Backlog completo: TASKS.md" | "Backlog completo: TaskRegistry MCP" |
| `TEAM.md:3` | "task de TASKS.md ou CURRENT.md" | "via tasks-list owner:NOME ou CURRENT.md" + bloco source-of-truth |
| `TEAM.md:117` | "Convenção em commits / PRs / TASKS.md" | "/SPEC.md" |
| `TEAM.md:148` | "task de CURRENT.md ou TASKS.md" | "via tasks-list owner:NOME ou CURRENT.md" |
| `TEAM.md:155` | "task em TASKS.md deveria ter DoD" | "US no SPEC.md tem campo Acceptance" |
| `TEAM.md:164` | "atualizar status no TASKS.md toma 30s" | "tasks-update US-XXX status:doing/done toma 10s" |
| `.claude/skills/oimpresso-stack` | "TASKS.md = backlog completo" | Marcado deprecated com pointer pra TaskRegistry |
| `.claude/skills/memory-sync` | description + patterns mencionam TASKS.md | description atualizada + ⚠️ explícito |
| `.claude/commands/sync-mem` | TASKS.md no checklist | Removido + adicionada nota deprecated |

**Não fixados (mantidos como histórico):**
- `TASKS.md:1` — header do próprio arquivo
- `TASKS.md:315` — entrada antiga "DOC-002 ✅ TASKS.md backlog completo por módulo"
- `MEMORY_TEAM_ONBOARDING.md:229` — "Acompanhe TASKS.md" (baixa severidade, doc de onboarding raramente acessado)

## HOW_TO_ASK_CLAUDE.md — reescrita estado-da-arte 2026

Substituído integralmente. Versão anterior tinha ~150 linhas focadas em "economia de
contexto após sessão de 970K". Versão nova tem **10 seções** baseadas em pesquisa
2025-2026:

- **Seção 0:** 5 princípios canônicos (context engineering > prompt engineering;
  specs > prompts; plan→execute→verify; single-thread > multi-agent; reviewer not typer)
- **Seção 1:** estrutura de prompt (template SPEC-CITE-VERIFY) + decomposição
  XS/S/M/L/XL + Plan mode + /compact/clear/continuar + regra dos 3 + 5 templates
- **Seção 2:** usabilidade (surgical correction, quando interromper, negociar escopo,
  ask before act, skills vs slash commands)
- **Seção 3:** segurança (hooks PreToolUse, ask-before-act, código compartilhado,
  LGPD/PII, /ultrareview, adr-review)
- **Seção 4:** anti-falha (don't tell show, show me don't tell, roleplay revisor,
  test-first, citar ADR, bouncer pattern)
- **Seção 5:** frameworks modernos (ReAct, Plan-and-Solve, Reflexion, multi-agent,
  Spec-Driven Development)
- **Seção 6:** config técnica (hierarquia memória, settings, MCP servers, skills,
  contexto longo)
- **Seção 7:** receitas copy-paste (5 templates: bug prod, feature, refactor,
  pesquisa, housekeeping)
- **Seção 8:** anti-padrões (10 mapeados)
- **Seção 9:** métricas pra saber se está funcionando
- **Seção 10:** fontes (Anthropic, Cognition, OpenAI, papers ICLR/NeurIPS/ACL/TACL)

Fontes principais: Anthropic "Claude Code Best Practices" (set/2025), Cognition AI
"Don't Build Multi-Agents" (jun/2025), Sean Grove (OpenAI) "The New Code" (out/2025),
Lance Martin "Context Engineering" (jun/2025), papers ReAct/Plan-and-Solve/Reflexion/
Self-Refine/Lost-in-the-Middle.

## Outputs

- **3 commits**: regras concorrentes + HOW_TO_ASK_CLAUDE reescrito + session log
- **0 ADRs novas** (ADR 0069 já cobria a política — só faltava propagar)
- **Time fica alinhado** entre fonte canônica (TaskRegistry MCP + ADR 0069) e docs
  de governança (CLAUDE/TEAM/skills/commands)

## Próximo passo

- Eventual: criar Skill `shared-code-touch` que ativa quando path matchar
  `app/View/Helpers/**` ou `app/Utils/**` (mencionada como sugestão no
  HOW_TO_ASK_CLAUDE §3.3)
- Eventual: hook `PreToolUse` em git commit pra escanear PII/CPF/CNPJ
  (HOW_TO_ASK_CLAUDE §3.4)
