---
slug: 0070-jira-style-task-management-current-md-removed
number: 70
title: "Jira-style task management no MCP — CURRENT.md/TASKS.md removidos"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-04"
decided_by: [W]
module: governance
supersedes: [0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated]
superseded_by: []
related: [0027-gestao-memoria-roles-claros, 0053-mcp-server-governanca-como-produto, 0055-self-host-team-plan-equivalente-anthropic, 0064-modularizacao-split-teammcp-kb-superadmin360, 0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated]
tags: [governance, tasks, mcp, taskregistry, jira, linear, kanban, policy]
---

# ADR 0070 — Jira-style task management no MCP, CURRENT.md/TASKS.md removidos

## Status

Aceito — 2026-05-04. Supersede ADR 0069.

## Contexto

ADR 0069 (2026-05-04) declarou TaskRegistry MCP tools como source-of-truth e
deprecated `TASKS.md`, mas manteve `CURRENT.md` vivo como "foto do cycle". Na
prática, dentro de horas após adoção:

1. **TaskRegistry virou casca vazia.** `tasks-list status:doing` retornava
   zero. As tasks ativas do Cycle 01 (MEM-KB-3, MEM-MEM-MCP-1.b, MEM-HOT-*,
   MEM-MET-*, MEM-OTEL-1) **nunca foram criadas via `tasks-create`** — só
   viveram em linhas de tabela markdown no `CURRENT.md`.
2. **`mcp_sessions` parou de receber sync** em 23-abr — 8 dias de session
   logs (28-abr a 01-mai) ausentes via tool `sessions-recent`.
3. **CURRENT.md continuou sendo o source-of-truth real**, com Wagner editando
   tabelas markdown direto enquanto `mcp_tasks` apodrecia.
4. **Drift garantido por design**: ADR 0069 listou CURRENT.md como "foto" mas
   o cycle inteiro (goal, métricas, WIP, on-deck, mudanças, métricas) cabe lá
   — não há separação real.

Wagner identificou no chat 2026-05-04: "isso foi um teste de conceito, acho
que deu errado", pediu remoção total de CURRENT.md/TASKS.md, e adoção
profissional estilo Jira/Linear.

Avaliação do estado da arte 2026 (Linear, Jira Cloud, Plane.so, Height,
GitHub Projects v2):

- Hierarquia 4+ níveis (Project → Epic → Cycle → Story → Subtask)
- Workflow customizável por projeto
- Estimates flexíveis (points/hours/tshirt)
- Custom fields tipados
- Saved views/filters
- Triage (sem owner/prio)
- Inbox unificado
- Bidirectional git sync (commit/PR ↔ status)
- Cycle auto-rollover
- Issue templates
- Bulk operations
- Identifier humano (`COPI-123` Linear-style)
- Real-time presence
- AI-native (summarize/auto-priority)

Tier 1 (baseline) e Tier 2 (gaps críticos) aplicados aqui. Tier 3
(Initiatives, Versions/Releases, SLA, Time tracking, Roadmap Gantt completo)
adiados — over-engineering pra time solo + 5 devs.

## Decisão

### Hierarquia canônica

```
Project (mcp_projects)
  └── Epic (mcp_epics) ┐
                       │
  └── Component (mcp_components) — cross-cut (frontend/infra/mem)
                       │
  └── Cycle (mcp_cycles) ┐
                         ├── Cycle Goals (mcp_cycle_goals)
                         │
                         └── Task (mcp_tasks)
                                 ├── Subtask (mcp_tasks parent_task_id)
                                 ├── Comments (mcp_task_comments)
                                 ├── Events (mcp_task_events) — audit append-only
                                 ├── Dependencies (mcp_task_dependencies)
                                 ├── Watchers (mcp_task_watchers)
                                 ├── Attachments (mcp_task_attachments)
                                 ├── Memory Links (mcp_task_memory_links) — D1 oimpresso-only
                                 └── Git Links (mcp_git_links) — commit/PR bidir
```

### Tasks têm identifier humano Linear-style

Formato: `<PROJECT_KEY>-<NNNN>` (ex: `COPI-123`, `NFSE-7`, `INF-42`).

Geração: contador por projeto (`mcp_projects.next_task_number`); `task_id`
legacy `US-COPI-001` continua aceito como alias durante migração.

### Estimates flexíveis

Campo `estimate_unit` ENUM(`points`/`hours`/`days`/`tshirt`/`fibonacci`) +
`estimate_value` DECIMAL. Default por projeto em `mcp_projects.settings`.

### Workflow customizável por projeto

`mcp_workflows` define statuses (JSON array) e transitions (JSON map) por
projeto. Default global `[backlog, todo, doing, review, blocked, done,
cancelled]` (compatível com `mcp_tasks.status` ENUM atual + 1 novo state
`backlog`).

### Custom fields

Coluna `custom_fields` JSON em `mcp_tasks`. Schema validável por projeto
(`mcp_projects.custom_field_schema` JSON com tipos text/number/date/select/
multi-select/user/url).

### Saved views

`mcp_views` (project_id OR user_id, name, filter JSON, sort, scope:
personal/shared). UI mostra como tabs no Kanban/Backlog.

### Inbox unificado

`mcp_inbox_notifications` (user_id, type: mention/assigned/review_requested/
status_changed/commented, task_id, actor_id, body, read_at, created_at). Tool
`my-inbox` retorna unread.

### Cycle auto-rollover

Tool `cycles-close --rollover` move tasks `status NOT IN (done,cancelled)`
pro `cycle_id` do próximo cycle ativo. Idempotente; emite evento
`cycle_rolled_over` por task.

### Bidirectional git sync

Webhook GitHub `push` event detecta `(?i)(refs|fixes|closes|resolves)\s+
(<KEY>-\d+)` em commit messages e `(<KEY>-\d+)` em branch names.

- `refs <KEY>` → `git_links` row + comment "linked commit"
- `fixes`/`closes`/`resolves <KEY>` → status auto pra `review` (ou config
  por projeto: `done` se merge to default branch)
- PR aberto → `review`, PR merged em main → `done`

### Issue templates

`mcp_issue_templates` (project_id, type, name, body_template Markdown,
default_fields JSON). Tool `tasks-create --template=bug` aplica.

### Bulk operations

Tool `tasks-bulk-update` (task_ids array + fields). Audit gera 1 evento por
task com `bulk_op_id` correlacionando.

### Triage view

Default filtro `WHERE owner IS NULL OR priority IS NULL OR status='backlog'`
salvo em `mcp_views` como view sistema (não editável).

### AI-native (oimpresso-only — Linear/Jira NÃO têm out-of-box)

Tools que usam Copiloto LLM em prod:

- `tasks-summarize` — resume threads de comments longas
- `tasks-suggest-priority` — analisa contexto + due_date + deps
- `tasks-suggest-blockers` — varre dependencies graph + SPEC.md
- `tasks-link-memory` — sugere ADRs relevantes via `mcp_memory_documents`

### Memory-linked tasks (oimpresso-only — D1)

`mcp_task_memory_links` (task_id, memory_doc_id, link_type ENUM
`relates`/`spec`/`adr`/`session`). Tool MCP `tasks-detail` retorna
links como sidebar; UI mostra "ADRs relacionados" automaticamente.

### Hierarquia documental (atualizada — supersede ADR 0069 §Hierarquia documental)

| Arquivo | Papel após este ADR |
|---|---|
| `mcp_projects/epics/cycles/tasks/...` (DB + tools MCP) | **Source-of-truth** absoluto pra status, estado, comentários, prazos |
| `memory/requisitos/<Mod>/SPEC.md` | **Source-of-truth** dos US-XXX-NNN canônicos (parser + webhook → mcp_tasks); mutação via `tasks-update`/`tasks-create` |
| `CURRENT.md` | **REMOVIDO** — substituído por tools `cycles-active`, `cycle-goals-track`, `my-work` |
| `TASKS.md` | **REMOVIDO** — substituído por `tasks-list` + saved views |
| `memory/cycles/README.md` | **REMOVIDO** — cycles fechados são entidades em `mcp_cycles` (status='closed'); retro vai em `mcp_cycles.retro` JSON |
| `memory/08-handoff.md` | Continua relevante — handoff narrativo entre sessões |
| `memory/sessions/*.md` | Continua relevante — session logs cronológicos (ADR 0069 manteve) |
| `memory/decisions/*.md` | Continua canônico — ADRs Nygard imutáveis |
| `TEAM.md` | Continua canônico — perfis + WIP + matriz |

### Fluxo padrão fim-de-sessão (após este ADR)

1. Task entregue → `tasks-update <ID> status:done` (ou `tasks-comment` se ainda em progresso)
2. Apenda em `memory/08-handoff.md` (estado narrativo)
3. Cria session log em `memory/sessions/YYYY-MM-DD-*.md`
4. Se decisão arquitetural nova → ADR em `memory/decisions/`
5. Push → webhook GitHub sincroniza ADR/SPEC/sessions com MCP em <60s
6. **NÃO atualiza CURRENT.md/TASKS.md** — não existem mais

### Fluxo "qual o estado de hoje?"

| Pergunta | Tool MCP |
|---|---|
| "O que estou fazendo hoje?" | `my-work` |
| "Tem algo na minha caixa de entrada?" | `my-inbox` |
| "Estado do cycle ativo" | `cycles-active` |
| "Goals do cycle batendo?" | `cycle-goals-track cycle:current` |
| "Backlog do módulo X" | `tasks-list module:X` |
| "Bloqueado por o quê?" | `tasks-detail <ID>` (mostra deps) |
| "Tasks novas sem owner" | `triage` |
| "Velocity do time" | `dashboard-velocity project:X` |
| "Burndown deste cycle" | `dashboard-burndown` |

## Consequências

### Positivas

- **Source-of-truth único**: nada de markdown paralelo desincronizando
- **Backlog queryable** com filtros profissionais (módulo + cycle + epic + component + custom field)
- **Audit total** em `mcp_task_events` (já existia, agora valor real)
- **AI-native** via Copiloto LLM já em prod (D2)
- **Memory-linked** (D1): task ↔ ADR/SPEC/session com 1 click — diferencial vs Linear/Jira
- **Multi-tenant via business_id**: futuro suporte cliente B2B com board próprio
- **MCP-first**: agentes IA (Claude Code, Cursor, futuro) leem/escrevem antes de UI existir
- **Self-host equivalent** Linear/Jira (cf. ADR 0055 e 0059)

### Negativas / Riscos

- **Migração**: backfill manual de CURRENT.md + TASKS.md → mcp_tasks (~2h, automatizado por script)
- **Curva**: time precisa parar de editar markdown e usar tools (mas só Wagner solo agora — risco baixo)
- **UI Web ausente**: Fase 7 (Kanban + Backlog + Roadmap + Triage + Inbox) é trabalho de 8-12h fora desta sessão; até lá, todo acesso via tools MCP ou direto no DB
- **Webhook GitHub depende de uptime**: fallback `mcp:tasks:sync` cron 5min (já existe)

### Trade-offs explícitos NÃO incorporados (Tier 3 deferred)

- **Initiatives**: 4 níveis Project/Epic/Cycle/Task bastam pra solo+5
- **Versions/Releases**: sem release process formal hoje
- **SLA tracking**: due_date alerta basta; SLA contratual entra com primeiro cliente B2B
- **Time tracking**: Wagner foca em prazo, não horas
- **Roadmap Gantt**: `cycles-list` cronológico cobre 90% do uso
- **OAuth/SCIM**: Spatie + sessões Laravel cobrem
- **Public sharing**: sem demanda hoje
- **Multi-team workspaces**: 1 workspace = 1 oimpresso (Module ≈ Project)

## Alternativas descartadas

- **Linear self-host**: não existe (Linear é SaaS-only). Custo: $8-16/usuário/mês × 5+ devs + futuros clientes B2B. Bate em ADR 0055 (self-host equivalent).
- **Jira self-host (Server EOL, Data Center)**: $42k/ano mínimo. Inviável.
- **Plane.so self-host**: opção viável, mas adiciona stack PostgreSQL+Redis+Python+Node externa. Bate em ADR 0044 (Docker-only) + ADR 0058 (FrankenPHP runtime canônico). Re-implementar em Laravel é ~12h vs operar Plane = manutenção contínua.
- **GitHub Projects v2**: limitado (sem custom workflows, sem SLA, sem AI), e amarra ao GitHub.
- **Manter CURRENT.md/TASKS.md + tools híbrido**: ADR 0069 tentou e falhou em 4 dias. Drift garantido.

## Plano de implementação

Detalhado neste ADR como referência canônica:

| Fase | Escopo | Estimativa |
|------|--------|------------|
| **Fase 0** | Este ADR + atualizar refs em CLAUDE.md/skills/ADRs antigos | 30min |
| **Fase 1** | Schema (14 migrations + alter mcp_tasks) + 14 Entities + factories + Pest | 3h |
| **Fase 2** | TaskParserService atualizado (frontmatter + epic + cycle + custom_fields) | 1h |
| **Fase 3** | 18 tools MCP novas + permissions Spatie | 4h |
| **Fase 4** | Backfill — Cycle 01 + tasks de CURRENT.md/TASKS.md → mcp_tasks | 2h |
| **Fase 5** | Apagar CURRENT.md/TASKS.md/cycles/README + atualizar SPEC TaskRegistry | 15min |
| **Fase 6** | Git sync bidirectional (GitTaskLinkerService + webhook commits/PR → tasks-update auto + 9 Pest tests) | ✅ |
| **Fase 7** | UI Web (Kanban + Backlog + Roadmap + Triage + Inbox) | 8-12h (sessão futura) |

## Referências

- ADR 0027 — Gestão de memória: roles claros (define git como source-of-truth)
- ADR 0053 — MCP server governança como produto (cache pattern)
- ADR 0055 — Self-host equivalent Anthropic Team plan
- ADR 0059 — 10 pilares governança self-host
- ADR 0064 — Modularização split TeamMcp + KB + Superadmin 360
- ADR 0069 — TaskRegistry MCP tools canônico (superseded por este ADR)
- `memory/requisitos/TaskRegistry/SPEC.md` — formato canônico US-XXX-NNN (mantido + estendido com epic/cycle/component)
- Linear docs (2026): https://linear.app/docs
- Jira Cloud docs (2026): https://support.atlassian.com/jira-software-cloud/
- Plane.so (open-source): https://plane.so
