---
slug: 0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated
number: 69
title: "Governança de tasks: TaskRegistry MCP tools canônico, TASKS.md ASCII deprecated"
type: adr
status: superseded
authority: canonical
lifecycle: substituido
quarter: Q2-2026
decided_at: "2026-05-04"
decided_by: [W]
module: governance
supersedes: []
superseded_by: ["0070-jira-style-task-management-current-md-removed"]
related: ["0027-gestao-memoria-roles-claros", "0053-mcp-server-governanca-como-produto", "0064-modularizacao-split-teammcp-kb-superadmin360", "0070-jira-style-task-management-current-md-removed"]
tags: [governance, tasks, mcp, taskregistry, policy]
---

# ADR 0069 — TaskRegistry MCP tools canônico, TASKS.md ASCII deprecated

## Status

**SUPERSEDED por [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — 2026-05-04 (mesmo dia).**

Em poucas horas após adoção, ficou claro que CURRENT.md continuou sendo o
source-of-truth real enquanto `mcp_tasks` apodrecia (tasks MEM-* nunca foram
criadas via tool). ADR 0070 mata CURRENT.md/TASKS.md de vez e adota Jira/Linear-style.

---

Aceito — 2026-05-04

## Contexto

O projeto historicamente usava `TASKS.md` (arquivo markdown ASCII) como backlog
canônico. Daily updates de status (✅, 🔄, ⏳) ficavam neste arquivo.

Em 2026-04-30 / 2026-05-01, o **TaskRegistry F0+F1** entrou em produção (commits
`009dc127` + `75d03d3c` + ADR 0064):

- **SPECs canônicos** em `memory/requisitos/<Mod>/SPEC.md` com formato `### US-XXX-NNN`
- **Cache governado** em `mcp_tasks` (sync via webhook GitHub)
- **MCP tools**: `tasks-list`, `tasks-detail`, `tasks-create`, `tasks-update`, `tasks-comment`
- **Audit log** em `mcp_task_events` + comentários em `mcp_task_comments`
- **UI futura** em `/copiloto/admin/tasks` (Kanban — Fase 2)

ADR 0064 já documenta que `ContextForTaskService` foi migrado pra consumir
`mcp_tasks` (US-COPI-077), eliminando "a dependência circular do CURRENT.md
filesystem que rotineiramente ficava desatualizado".

O `TaskRegistry/SPEC.md` declara explicitamente como goal:
> substituir TASKS.md ASCII por sistema de tasks queryable via MCP, sem perder git como SoT

Mesmo assim, instruções no `CLAUDE.md` ainda mandavam atualizar `TASKS.md` no
fim da sessão — gerando drift entre o sistema canônico (mcp_tasks via tools) e
o legado (TASKS.md ASCII).

## Decisão

### Source-of-truth para status de tasks: TaskRegistry MCP tools

| Operação | Tool MCP canônica | Não usar (legado) |
|---|---|---|
| Listar backlog | `tasks-list` (filtra owner/módulo/sprint/status) | Ler TASKS.md |
| Detalhar US | `tasks-detail US-XXX-NNN` | Grep no SPEC.md |
| Criar nova US | `tasks-create module:Mod title:"..."` | Editar TASKS.md/SPEC.md à mão |
| Atualizar status | `tasks-update US-XXX-NNN status:done` | Mudar emoji em TASKS.md |
| Registrar progresso/decisão | `tasks-comment task_id:US-XXX-NNN comment:"..."` | Adicionar linha em TASKS.md§Concluído |

### Hierarquia documental (atualizada)

| Arquivo | Papel após este ADR |
|---|---|
| `memory/requisitos/<Mod>/SPEC.md` | **Source-of-truth** — US-XXX-NNN com format canônico (parser + webhook → mcp_tasks) |
| `mcp_tasks` table | Cache governado (sync via webhook GitHub `mcp:tasks:sync`) |
| MCP tools `tasks-*` | Acesso programático (humanos + agentes IA) |
| `CURRENT.md` | Foto do cycle — goal + Active WIP + On-deck (NÃO recebe status diário) |
| `TASKS.md` | **Deprecated** — mantido só pra histórico até remoção. Não receber novas entradas |
| `memory/08-handoff.md` | Handoff narrativo (continua relevante — não substituído) |
| `memory/sessions/*.md` | Session logs cronológicos (continua relevante) |

### Fluxo padrão fim-de-sessão (após este ADR)

1. Task entregue → `tasks-comment` em US existente OU `tasks-create` se for trabalho novo
2. Apenda em `memory/08-handoff.md` (estado narrativo)
3. Cria session log em `memory/sessions/YYYY-MM-DD-*.md`
4. Se decisão arquitetural nova → ADR em `memory/decisions/`
5. CURRENT.md só atualiza se task ativa do cycle mudou de status macro

### Por que não deletar TASKS.md agora

- Ainda há entradas históricas valiosas (Concluído nas últimas 2 semanas)
- Migração full pra mcp_tasks ainda em curso (parser cobre US-XXX-NNN, mas
  há tasks legadas em outros formatos)
- Plano: deletar quando UI Kanban estiver em prod e migração completa

## Consequências

### Positivas

- **Backlog queryable**: filtros por owner/sprint/status sem grep manual
- **Audit log**: `mcp_task_events` registra toda mudança com timestamp + author
- **Comentários thread**: progresso/decisões/blockers em timeline (não diluído em commit messages)
- **Idempotência**: parser garante que sync ATUAL não acumula registros velhos
- **Acesso uniforme**: humanos (UI futura) + agentes IA (tools MCP) leem o mesmo dado

### Negativas / Riscos

- Curva de aprendizado: time precisa parar de editar TASKS.md à mão (hábito antigo)
- Webhook depende de GitHub estar UP — falha → drift mcp_tasks vs SPEC.md (mitigado por
  comando manual `mcp:tasks:sync` como fallback)
- Sem internet/MCP → fallback é ler `memory/requisitos/<Mod>/SPEC.md` direto

## Alternativas descartadas

- **Manter TASKS.md como canônico**: rotineiramente ficava desatualizado (ver ADR 0064)
- **Sistema externo (Linear/Jira)**: viola self-host equivalent (ADR 0059) e LGPD
- **Híbrido (TASKS.md + tools)**: drift garantido — uma das fontes sempre fica errada

## Referências

- ADR 0027 — Gestão de memória: roles claros (define git como source-of-truth dos docs)
- ADR 0053 — MCP server governança como produto (cache pattern)
- ADR 0064 — Modularização split TeamMcp + KB + Superadmin 360 (mcp_tasks como context source)
- `memory/requisitos/TaskRegistry/SPEC.md` — formato canônico US-XXX-NNN
- Commits TaskRegistry F0+F1: `009dc127`, `75d03d3c`
