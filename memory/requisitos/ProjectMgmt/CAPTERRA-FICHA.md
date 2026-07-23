---
id: requisitos-project-mgmt-capterra-ficha
---

# CAPTERRA-FICHA — ProjectMgmt

> **Ficha canônica de benchmark do módulo ProjectMgmt** — fonte de verdade para a skill `comparativo-do-modulo`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).
> ADR mãe redesign: [0100](../../decisions/0100-projectmgmt-ui-redesign.md).
> Charter da Board: [CHARTER-board.md](CHARTER-board.md).
> Inventário ✅🟡❌ atualizado: [INVENTARIO.md](INVENTARIO.md).

---

## Identidade do módulo

- **Nome interno**: `ProjectMgmt` (rename pra `Project` em Fase 3.9 do ADR 0079, após delete legacy em Fase 3.8)
- **Domínio de negócio**: gerenciamento de **trabalho do TIME INTERNO** estilo Jira (Project → Epic → Cycle → Story → Subtask + Components transversais + Custom fields + Saved views + Inbox + Bidirectional git sync). Cliente alvo: time oimpresso (Wagner / Maíra / Felipe / Luiz / Eliana). **NÃO** é gestão de projetos de cliente externo.
- **Status atual**: **em prod desde 2026-05-04** (PRs #91/#92, ADR 0070 entregue). 6 telas Inertia/React (1935 LoC), 7 controllers, 15 tabelas `mcp_*`, padrão MWART.
- **Concorrentes-alvo direto** (6):
  - **Linear** — linear.app — UX state-of-the-art (atalhos teclado, navegação <100ms, command palette). Benchmark de **fluidez** que Wagner pediu como base do redesign.
  - **Jira Cloud** — atlassian.com/software/jira — referência mundial Kanban + Backlog + Sprints + Roadmap + JQL. Padrão de **completude funcional**.
  - **Asana** — asana.com — multi-vertical, popular em PMEs BR. Views Lista + Board + Timeline + Workload.
  - **ClickUp** — clickup.com — one-tool-rules-all, custom fields ricos. Popular em SMB BR.
  - **Plane.so** — plane.so — open-source Linear-clone; arquitetura inspiração porque é self-hostable como ProjectMgmt.
  - **Productive.io** — productive.io — agências/gráficas; **time tracking + invoicing integrado** (pode inspirar US-PROJ-015 invoice-from-timelogs do legacy se Wagner reaproveitar).

## Comparativos de referência

- _(adicionar aqui ao gerar comparativo dedicado a "PM tools self-host BR 2026")_

## Capacidades baseline com score

```yaml
capacidades:
  # ============= P0 — bloqueadores =============

  - nome: "Kanban board drag-drop completo (droppable funcional)"
    score: P0
    descricao: "Cards arrastáveis (já tem) + colunas droppable atomic + optimistic UI + 409 conflict + revert em erro. Hoje só draggable existe — drop não persiste."
    quem_tem: ["Linear", "Jira", "Asana", "ClickUp", "Plane", "Trello"]
    referencias: ["https://atlassian.design/components/board"]
    evidencia_de_pronto: "Page /project-mgmt/board com BoardColumn droppable + endpoint PATCH /project-mgmt/board/{taskId}/status atomic + optimistic UI + revert em 4xx + teste Pest cobrindo happy/permission/conflict"

  - nome: "Backlog priorização visual + bulk operations"
    score: P0
    descricao: "Tela /project-mgmt/backlog com 7 dimensões filtros + bulk select + bulk edit (status/priority/owner/epic). Já implementado."
    quem_tem: ["Linear", "Jira", "Asana", "ClickUp", "Plane"]
    evidencia_de_pronto: "Page /project-mgmt/backlog renderiza + POST /backlog/bulk com permission check + audit log"

  - nome: "My Work + Inbox unread badges (cockpit pessoal)"
    score: P0
    descricao: "Tela /project-mgmt/my-work consolidada: tasks owned + inbox notifications + mark-read actions. Linear/Jira têm equivalente."
    quem_tem: ["Linear (canonical)", "Jira (Plans for you)", "Asana (My Tasks)", "ClickUp"]
    evidencia_de_pronto: "Page /project-mgmt/my-work com myWork[] + inbox[] + endpoints mark-read + bumpStatus"

  - nome: "Multi-tenant + Permissions Spatie cobertas por testes Pest"
    score: P0
    descricao: "Permission `copiloto.mcp.usage.all` (pattern UltimatePOS); todos controllers checam + UI esconde botões sem perm + tests cobrindo 403/404 cross-tenant."
    quem_tem: ["Jira (project roles)", "Asana", "ClickUp", "Linear (workspace)"]
    evidencia_de_pronto: "Modules/ProjectMgmt/Tests/Feature/PermissionsTest.php (a criar) + suite verde em CI + isolation cross-tenant 404"

  - nome: "Filters URL state-driven (compartilhável + voltar via back button)"
    score: P0
    descricao: "Estado dos filtros (cycle, epic, owner, search) persistido via URL ?key=val + localStorage. Já parcialmente implementado."
    quem_tem: ["Linear", "Jira", "Asana", "ClickUp", "Plane"]
    evidencia_de_pronto: "router.get com URL state preservado entre navegações; localStorage como cache de sessão; teste E2E cobrindo back/forward"

  - nome: "Search global Cmd+K (command palette)"
    score: P0
    descricao: "Atalho Cmd+K abre command palette que busca tasks/epics/projects do business. Linear é benchmark."
    quem_tem: ["Linear (canonical)", "Jira", "Asana", "ClickUp", "Plane", "Notion"]
    evidencia_de_pronto: "Component CommandPalette via lib cmdk (já em package.json) + endpoint GET /project-mgmt/search?q= + multi-tenant scoped + atalho global registrado em AppShellV2"

  # ============= P1 — mercado tem, time vai pedir =============

  - nome: "Cycle close UI (fechar cycle + rollover incompletas)"
    score: P1
    descricao: "Modal/page pra fechar cycle ativo: lista incompletas, opção rollover pra próximo cycle, retro inline (markdown). Tool MCP `cycles-close --rollover` existe — sem UI."
    quem_tem: ["Jira (Sprint close)", "Linear (Cycle close)", "Plane"]
    evidencia_de_pronto: "Page /project-mgmt/cycle/{id}/close + Sheet com tabs (Incompletas / Retro / Confirm) + endpoint POST + teste"

  - nome: "Sprint/Cycle planning UI (alocar tasks ao cycle ativo)"
    score: P1
    descricao: "Modal pra puxar tasks do backlog → cycle ativo (drag-drop ou multi-select + Add to Cycle). Falta UI dedicada."
    quem_tem: ["Jira (Backlog → Sprint)", "Linear", "Plane"]
    evidencia_de_pronto: "Sheet 'Add to cycle' acessível do Backlog + endpoint POST /project-mgmt/cycle/{id}/add-tasks + teste"

  - nome: "Comments com @mentions (autocomplete + Notification dispatch)"
    score: P1
    descricao: "Digitar @ em comment abre autocomplete dos members do project. User mencionado recebe `mcp_inbox_notifications` row + email opcional."
    quem_tem: ["Linear", "Jira", "Asana", "ClickUp", "Plane", "Notion"]
    evidencia_de_pronto: "Component MentionInput (autocomplete) + parser server-side + Notification dispatcher + tabela mcp_inbox_notifications já existe"

  - nome: "Watchers UI (follow/unfollow task)"
    score: P1
    descricao: "Botão Follow no card/sheet detalhe; watchers recebem notification de mudanças. Tabela `mcp_task_watchers` já existe — falta UI."
    quem_tem: ["Jira", "Linear", "ClickUp", "Plane"]
    evidencia_de_pronto: "Botão Follow/Unfollow + endpoint POST /project-mgmt/task/{id}/watch + Notification dispatch pra members + watchers"

  - nome: "Centrifugo presence — quem está vendo a tela"
    score: P1
    descricao: "Avatar stack no TopBar mostra outros usuários conectados na mesma URL em tempo real. Centrifugo já provisionado (ADR 0058) — falta integração nas pages."
    quem_tem: ["Linear", "Notion", "Figma (canonical)", "Plane (parcial)"]
    referencias: ["ADR 0058 — Reverb substituído por Centrifugo+FrankenPHP"]
    evidencia_de_pronto: "Hook usePresence() + canal `project-mgmt:board:{cycle_id}` + avatar stack + teardown em unmount + teste"

  - nome: "Atalhos keyboard completos (J/K/E/A documentados — implementar)"
    score: P1
    descricao: "Doc no header da Board.tsx menciona J/K/E/A — atalhos NÃO implementados. Linear é benchmark de produtividade keyboard-first."
    quem_tem: ["Linear (canonical)", "Jira", "Plane"]
    evidencia_de_pronto: "Hook useHotkeys + atalhos: J/K (next/prev card), E (advance status), A (back status), C (create), / (focus search), Esc (close sheet), ? (show shortcuts overlay)"

  - nome: "Subtasks (1 nível de hierarquia + completion bar)"
    score: P1
    descricao: "Coluna `parent_task_id` em mcp_tasks já existe. Falta UI: árvore no card detail + completion percentage + cascade close opcional."
    quem_tem: ["Linear", "Jira", "Asana", "ClickUp", "Plane"]
    evidencia_de_pronto: "Section Subtasks no Detail Sheet + render hierárquico + endpoint create subtask + completion bar"

  - nome: "Saved views backend (não só localStorage)"
    score: P1
    descricao: "Hoje filters salvos vivem em localStorage (per-browser). Mover pra `mcp_views` (tabela existe) com sharing entre members + URL clean."
    quem_tem: ["Linear (Views canonical)", "Jira (Saved filter JQL)", "ClickUp", "Plane"]
    evidencia_de_pronto: "Tabela mcp_views populada + UI 'Save view' / 'My views' / 'Shared' + endpoint CRUD + multi-tenant"

  - nome: "Triage view (tasks novas sem owner/priority)"
    score: P1
    descricao: "Tela dedicada listando `tasks` com owner=null OR priority=null. Pra triagem semanal. SCOPE.md menciona como flow esperado."
    quem_tem: ["Linear (Triage canonical)", "Jira (board with filter)", "Plane"]
    evidencia_de_pronto: "Page /project-mgmt/triage + filtros embutidos + teste"

  - nome: "Activity feed timeline (já implementada — refinar)"
    score: P1
    descricao: "Tela /project-mgmt/activity já existe. Refinar com filtros (tipo evento, owner, range data) + permalinks pra task referenciada."
    quem_tem: ["Linear (canonical)", "Jira", "Asana", "Plane"]
    evidencia_de_pronto: "Page existente + filtros aprimorados + lazy load se >100 eventos"

  - nome: "Burndown chart (já implementado — refinar)"
    score: P1
    descricao: "Tela /project-mgmt/burndown existe (Line chart ideal vs real). Refinar: comparação multi-cycle + projection line + scope creep highlight."
    quem_tem: ["Jira (canonical)", "Linear (Cycle reports)", "ClickUp", "Plane"]
    evidencia_de_pronto: "Page existente + scope_creep + projection line + cycles selector multi"

  # ============= P2 — diferenciação =============

  - nome: "Dependencies graph (blocks / blocked_by visual)"
    score: P2
    descricao: "Tabela `mcp_task_dependencies` existe. Falta UI gráfica + validação 'não pode mover task se bloqueador não está done'."
    quem_tem: ["Linear (Dependencies)", "Jira (advanced)", "Asana"]
    evidencia_de_pronto: "Section Dependencies no Detail Sheet + grafo simples (D3 ou SVG manual) + validação no PATCH status"

  - nome: "Custom fields per project (campos custom UI)"
    score: P2
    descricao: "Tabela `mcp_components` permite categorizações; custom fields completos exigem nova arquitetura (`mcp_custom_fields`?)."
    quem_tem: ["Jira (canonical)", "ClickUp", "Asana"]
    evidencia_de_pronto: "Migration `mcp_custom_fields` + UI cadastro per project + render dinâmico no Detail + teste"

  - nome: "Workload view (capacidade do time)"
    score: P2
    descricao: "Tela mostrando barras de capacity per owner/cycle (estimate_h vs limite). Detecta over-allocation."
    quem_tem: ["Jira (Plans)", "Asana (Workload)", "ClickUp", "Linear (parcial)"]
    evidencia_de_pronto: "Page /project-mgmt/workload + agregação SQL + visualização + cycle selector"

  - nome: "Time tracking interno (horas trabalhadas por task)"
    score: P2
    descricao: "Time tracking pro time INTERNO (ex: quanto Felipe gastou em US-NFSE-005). Diferente do TimeLog do Project legacy (clientes)."
    quem_tem: ["Linear (Insights)", "Jira (Tempo plugin)", "ClickUp", "Productive (canonical)"]
    evidencia_de_pronto: "Migration nova `mcp_time_logs` (não confundir com pjt_project_time_logs do legacy) + Start/Stop UI + report"

  - nome: "Templates de epic/cycle (clone)"
    score: P2
    descricao: "Cycle/Epic-tipo 'Sprint quinzenal padrão' = N tasks padrão. Criar from template instancia tudo."
    quem_tem: ["Jira", "Linear", "ClickUp"]
    evidencia_de_pronto: "Flag `is_template` em mcp_cycles/mcp_epics + endpoint POST /from-template/{id} + UI seletor"

  - nome: "Automation rules (when X then Y)"
    score: P2
    descricao: "Quando task move pra 'review', notify @reviewer; quando cycle fecha, mover incompletas pra próximo. Rules engine simples."
    quem_tem: ["Jira (canonical Automation)", "Linear (Workflows)", "ClickUp", "Plane (parcial)"]
    evidencia_de_pronto: "Migration `mcp_automation_rules` + engine PHP + UI cadastro + teste cobrindo 3 rules base"

  # ============= P3 — diferenciação opcional =============

  - nome: "Mobile responsive otimizado (Wagner em celular)"
    score: P3
    descricao: "Hoje desktop-first. Wagner às vezes consulta board no celular durante trânsito. Touch-friendly + cards stackados."
    quem_tem: ["Linear (mobile app native)", "Jira (mobile)", "Asana (mobile)", "ClickUp"]
    evidencia_de_pronto: "Breakpoint <768px com layout stack + touch drag-drop OK + audit Lighthouse mobile"

  - nome: "Dark mode + theme persisted"
    score: P3
    descricao: "Toggle light/dark + persistência per-user. Tailwind 4 já suporta `dark:` classes."
    quem_tem: ["Linear (canonical)", "Jira", "Asana", "ClickUp", "Plane"]
    evidencia_de_pronto: "Hook useTheme + toggle no AppShellV2 + persist localStorage"

  - nome: "Roadmap timeline drag-and-drop (mover datas)"
    score: P3
    descricao: "Tela /project-mgmt/roadmap existe (quarter grouping). Adicionar drag horizontal pra mudar `target_quarter` + hover preview."
    quem_tem: ["Jira (Plans)", "Asana (Timeline)", "ClickUp (Gantt)", "Productive"]
    evidencia_de_pronto: "Page roadmap melhorada com drag horizontal + endpoint PATCH target_quarter + teste"

  - nome: "Public share link (read-only do board pra stakeholders)"
    score: P3
    descricao: "Compartilhar link de board sem auth pra mostrar progresso pra Eliana / Wagner externamente."
    quem_tem: ["Linear (Public views)", "Jira (Public access)", "Notion"]
    evidencia_de_pronto: "Endpoint público /p/{token} + UI read-only + revoke token + LGPD review"
```

## Como auditar este módulo (etapa específica)

> Esta seção é **lida pela skill** no passo 2.5.

**Locais a inspecionar (paths exatos):**

- Controllers: `Modules/ProjectMgmt/Http/Controllers/{Board,Backlog,Roadmap,MyWork,Burndown,Activity}Controller.php` + `Admin/ProjectsController.php` + `DataController.php` + `InstallController.php`
- Pages React: `resources/js/Pages/ProjectMgmt/{Board,Backlog,Roadmap,MyWork,Burndown,Activity}/Index.tsx`
- Componentes board: `resources/js/Components/board/{BoardColumn,TaskCard,badges}.tsx`
- Routes: `Modules/ProjectMgmt/Http/routes.php` (prefixo `/project-mgmt`)
- SCOPE.md: `Modules/ProjectMgmt/SCOPE.md`
- SPEC funcional histórico: `memory/requisitos/TaskRegistry/SPEC.md` (US-TR-NNN — nome legado, content vivo)
- Tabelas: `mcp_jira_projects`, `mcp_epics`, `mcp_cycles`, `mcp_cycle_goals`, `mcp_tasks`, `mcp_task_attachments`, `mcp_task_comments`, `mcp_task_dependencies`, `mcp_task_events`, `mcp_task_memory_links`, `mcp_task_watchers`, `mcp_components`, `mcp_views`, `mcp_inbox_notifications`, `mcp_issue_templates`
- Tools MCP relacionados: `tasks-list`, `tasks-detail`, `tasks-create`, `tasks-update`, `cycles-active`, `cycles-close`, `cycle-goals-track`, `my-work`, `my-inbox`, `triage`
- Permission: `copiloto.mcp.usage.all`
- Tests: `Modules/ProjectMgmt/Tests/` (a criar — diretório atualmente sem registro em `phpunit.xml`)

**Critérios customizados de classificação (resumo — detalhe completo no INVENTARIO.md):**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita | ❌ AUSENTE |
|---|---|---|---|
| Kanban drag-drop | BoardColumn droppable + atomic PATCH + 409 + teste E2E | só draggable card sem drop | sem board |
| Backlog bulk | Multi-select + bulk POST + audit log + perm check | bulk endpoint sem audit | sem bulk |
| My Work + Inbox | Page + unread badges + mark-read + grouped | sem grouping | sem page |
| Search Cmd+K | Palette + endpoint + multi-tenant + teste | só search inline por page | sem search |
| Cycle close UI | Sheet + retro markdown + endpoint + rollover | só tool MCP CLI | sem UI |
| @mentions | MentionInput + parser + Notification + teste | comments sem mention | sem mention |
| Watchers | Tabela + UI + Notification dispatch + teste | tabela existe sem UI | sem watcher |
| Centrifugo presence | Hook + canal + avatar stack + teardown + teste | infra Centrifugo OK sem integração page | sem real-time |
| Atalhos keyboard | useHotkeys + 7+ shortcuts + overlay help | doc menciona sem implementar | sem atalho |
| Subtasks | parent_task_id UI tree + completion bar + teste | coluna existe sem UI | sem subtask |
| Saved views | mcp_views populada + UI CRUD + sharing | só localStorage | sem persistência |
| Triage | Page dedicada + filtros embutidos + tool MCP `triage` | tool MCP só | sem page |
| Dependencies | Tabela + UI gráfica + validação PATCH | tabela existe sem UI | sem dependência |
| Custom fields | Migration + UI + render dinâmico + teste | só `mcp_components` | sem custom |
| Workload | Page + agregação + viz + cycle selector | dado disponível sem viz | sem workload |
| Time tracking | mcp_time_logs + Start/Stop UI + report | só estimate_h | sem time tracking |
| Mobile | Lighthouse mobile >85 + touch drag OK | breakpoints médios | desktop-only |

**Métricas de prod relevantes (a coletar pós-MVP):**

- Adoção time interno — meta: ≥5 usuários distintos abrem `/project-mgmt/board` semanalmente
- Latência drag-drop status change — meta: <300ms p95
- % de tasks com TimeLog interno — meta: ≥40% (sinal de uso real do time tracking)
- Taxa de tasks completadas via Cmd+K shortcut vs UI mouse — meta: >20% (sinal de produtividade keyboard)

## Métricas de adoção

- **Última auditoria**: nunca (1ª execução pós-pivot do PR #197 — INVENTARIO criado em sessão paralela)
- **Capacidades P0 cobertas**: estimativa otimista 4 de 6 (Backlog/MyWork/Filters URL parcialmente OK; Drag-drop/Cmd+K/Permissions tests faltam)
- **Gap P0+P1 atual**: estimativa 11 de 17
- **Próxima reauditoria sugerida**: após Fase 1 (drag-drop + Cmd+K) — reavaliar gaps P1

## Histórico de revisão da ficha

- `2026-05-07` — [W+C] — criação da ficha pós-pivot do PR #197 (que mirou no módulo errado `Modules/Project` legacy). Mira `Modules/ProjectMgmt` em prod desde 2026-05-04 PRs #91/#92.

## Referências externas

- Linear method: https://linear.app/method
- Linear API: https://developers.linear.app/docs
- Jira UI patterns: https://atlassian.design/components/board
- Jira API: https://developer.atlassian.com/cloud/jira/platform/rest/v3/
- Asana API: https://developers.asana.com/reference
- ClickUp API: https://clickup.com/api
- Plane.so API: https://docs.plane.so/api-reference/
- Productive.io API: https://developer.productive.io/index.html
- Atlassian Design System (Board / Inline create / etc): https://atlassian.design/

---

## UX heuristics (Capterra v2 — eixo Usabilidade)

> Capterra v2 ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) §3 eixos): além de medir features, mede **como** o concorrente entrega — cliques, tempo, recuperação de erro.
> ⚠️ **TODO Wagner pesquisar/curate** — placeholder vazio até inventariar 3-5 heurísticas P0 do módulo.

```yaml
ux_heuristics: []
  # - id: example-clicks
  #   nome: "Cliques pra ação X"
  #   score: P0
  #   benchmark: "Concorrente A: 1 clique. Concorrente B: 5."
  #   target: "<= 2 cliques"
  #   metrica: "navegacao_steps_X"
```

## Automation targets (Capterra v2 — eixo Automação)

> O que mercado faz **sem humano**? Listener? Cron? Job? Webhook?
> ⚠️ **TODO Wagner pesquisar/curate** — placeholder vazio até inventariar 3-5 automações P0 do módulo.

```yaml
automation_targets: []
  # - id: example-auto-action
  #   nome: "Auto-disparar X quando Y"
  #   score: P0
  #   benchmark: "Concorrente A SIM, B SIM, C PARCIAL"
  #   target: "Listener event Y → JobDoX, p95 < 30s"
  #   metrica: "auto_X_p95_seconds"
```
