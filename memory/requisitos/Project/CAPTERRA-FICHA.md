# CAPTERRA-FICHA — Project (legacy UltimatePOS)

> ⚠️ **PIVOTADA 2026-05-07.** Esta ficha mira `Modules/Project/` Blade legacy UltimatePOS (gestão de projetos de **cliente** — TimeLogs, Invoices). Wagner pediu redesign do `Modules/ProjectMgmt` (Jira-style time interno, em prod desde PRs #91/#92), módulo diferente.
>
> Este artefato fica preservado como **insumo da Fase 3.8** ([SCOPE.md ProjectMgmt](../../../Modules/ProjectMgmt/SCOPE.md): "Fase 3.8 — DELETE Project legado UltimatePOS"). Antes do `git rm -rf Modules/Project/`, este inventário ajuda decidir o que extrair (Invoice from TimeLogs → Financeiro? ClientProjects → ProjectMgmt? timesheet → outro lugar?).
>
> **Não usar como roadmap de implementação.** Migração Blade→MWART desta ficha foi **cancelada**. Discovery NOVO mira ProjectMgmt em `memory/requisitos/ProjectMgmt/CAPTERRA-FICHA.md` (próxima sessão).
>
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).
> ADR pivot: [0099](../../decisions/0099-project-mwart-migration.md).

---

## Identidade do módulo

- **Nome interno**: `Project`
- **Domínio de negócio**: gestão de projetos de produção gráfica — pedidos grandes/multi-etapa (fachada hotel, lona outdoor, kit brindes evento) que duram dias ou semanas, exigem acompanhamento de status, time tracking, anexos visuais e custos vinculados.
- **Cliente principal alvo**: ROTA LIVRE (Larissa, biz=4) operando jobs grandes — hoje provavelmente **não usado** (módulo herdado UltimatePOS, Blade legacy, sem migração MWART).
- **Diferencial vertical pretendido**: integração nativa com Sells/Invoice (gerar faturamento parcial a partir de TimeLogs) + categorização por tipo de serviço gráfico (impressão digital / plotter / acabamento / instalação).
- **Concorrentes-alvo direto** (6):
  - **Jira** — atlassian.com/software/jira — referência mundial Kanban + Backlog + Sprints; UX que Wagner pediu como base
  - **Linear** — linear.app — UX state-of-the-art (atalhos teclado, navegação <100ms, command palette); benchmark de fluidez
  - **Asana** — asana.com — multi-vertical; popular em PMEs BR; views Lista + Board + Timeline
  - **ClickUp** — clickup.com — one-tool-rules-all; custom fields ricos; popular em SMB BR
  - **Trello** — trello.com — Kanban simples; baseline mínimo do mercado
  - **Productive.io** — productive.io — agências/gráficas; **time tracking + invoicing integrado** — caso de uso mais próximo do oimpresso

## Comparativos de referência

- _(adicionar aqui ao gerar comparativo dedicado a "PM tools para gráfica/produção BR")_
- `memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md` — análise geral (não específica a PM)

## Capacidades baseline com score

```yaml
capacidades:
  - nome: "Lista de projetos com filtros + busca"
    score: P0
    descricao: "Tela index com filtros (status, lead, customer, categoria) + search por nome + paginação + multi-tenant scoped"
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello", "Productive"]
    evidencia_de_pronto: "Page Inertia /project com server-side filters via URL state + teste Pest cobrindo R-PROJ-001 multi-tenant + Larissa consegue filtrar 'Em produção' da lista geral"

  - nome: "Kanban board drag-drop por status"
    score: P0
    descricao: "Board com colunas dos 5 status (not_started/in_progress/on_hold/cancelled/completed). Drag-drop card → muda status atomicamente + optimistic UI + audit log."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello", "Productive"]
    referencias: ["https://atlassian.design/components/board"]
    evidencia_de_pronto: "Page /project/{id}/board com @hello-pangea/dnd ou similar + endpoint PATCH /project-task/{id}/status atomic + ActivityLog registra mudança + teste E2E drag-drop"

  - nome: "Issue/task detail Jira-style (description + comments + attachments)"
    score: P0
    descricao: "Modal/page de task com description rich-text + thread de comments + attachments (dropzone) + activity log + members + due date + priority"
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello", "Productive"]
    evidencia_de_pronto: "Modal Inertia abre em /project/{id}/task/{tid} sem reload (preserveState) + comment-thread carrega + dropzone upload + teste cobrindo round-trip de comment"

  - nome: "Time tracking (manual + start/stop) por task"
    score: P0
    descricao: "Usuário registra horas manualmente OU usa timer start/stop. Total por task + total por projeto + total por usuário."
    quem_tem: ["Jira (Tempo plugin)", "Asana", "ClickUp", "Productive", "Trello (Power-Up)"]
    evidencia_de_pronto: "Aba Time Logs no detail mostra entries + botão Start/Stop persiste em pjt_project_time_logs + relatório agrega por user/project + teste"

  - nome: "Multi-tenant + Permissions Spatie por ação"
    score: P0
    descricao: "Toda query scoped por business_id (R-PROJ-001) + 4 permissões Spatie (project.create_project / edit_project / delete_project / view_project) checadas em controllers e UI condicional"
    quem_tem: ["Jira (project roles)", "Asana", "ClickUp", "Linear (workspace)"]
    evidencia_de_pronto: "Tests/Feature/PermissionsTest cobre 403 sem permissão + scope obrigatório em ProjectController::index + UI esconde botões sem permissão"

  - nome: "Notifications email automático (assigned/comment/status)"
    score: P0
    descricao: "Eventos disparam e-mail: NewProjectAssigned (já existe), NewTaskAssigned (já existe), NewCommentOnTask (já existe). Falta validar template + delivery."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello", "Productive"]
    evidencia_de_pronto: "3 Notification classes em uso + template Blade renderiza + teste Notification::fake() + log de envio em prod >0"

  - nome: "Multi-assignee + Lead designado"
    score: P0
    descricao: "Project tem `lead_id` (responsável) + members (executores). Task herda + pode ter members próprios. UI mostra avatares."
    quem_tem: ["Jira (assignee)", "Asana", "ClickUp", "Productive"]
    evidencia_de_pronto: "Tabelas pjt_project_members + pjt_project_task_members já existem; UI mostra avatar stack (Pages React) + endpoint atribuir/remover member sem reload"

  - nome: "Backlog separado do Board"
    score: P1
    descricao: "Tasks com status=not_started ficam num pane de Backlog (não poluem o Board). Drag pro Board ativa task."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Productive"]
    evidencia_de_pronto: "Page /project/{id}/board com drawer/tab Backlog + count badge + drag-drop bidirecional Backlog↔Board"

  - nome: "Quick add inline (criar task sem abrir modal)"
    score: P1
    descricao: "Footer da coluna Kanban tem campo 'Adicionar task'. Enter cria + persiste + aparece otimisticamente."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello"]
    evidencia_de_pronto: "Component QuickAddInput em cada coluna + endpoint POST /project-task otimista + teste de criação sem abrir modal"

  - nome: "Bulk edit (selecionar N tasks → mudar status/priority/assignee)"
    score: P1
    descricao: "Multi-select via checkbox + barra de ação flutuante com mudanças em massa"
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp"]
    evidencia_de_pronto: "Selection state em React + endpoint PATCH /project-task/bulk + audit log com count afetadas + teste cobrindo permission check (não pode bulk-edit sem permission)"

  - nome: "Comments com @mentions"
    score: P1
    descricao: "Digitar @ no comment abre dropdown de members do projeto. Member mencionado recebe notification."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello", "Productive"]
    evidencia_de_pronto: "Component MentionInput com lookup de members + parser salva mention_user_id no payload + Notification::send aos mencionados + teste"

  - nome: "Watchers/followers (não-assignees recebem notificações)"
    score: P1
    descricao: "Usuário pode 'seguir' uma task sem ser assignee. Recebe notifications de comentários/mudanças."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp"]
    evidencia_de_pronto: "Tabela pjt_project_task_watchers + botão Follow/Unfollow no detail + Notification dispara pra watchers além de members"

  - nome: "Subtasks (1 nível de hierarquia)"
    score: P1
    descricao: "Task pode ter subtasks que se completam separadamente. UI mostra checkbox progress."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Trello (checklist)"]
    evidencia_de_pronto: "Coluna parent_task_id em pjt_project_tasks + UI mostra árvore + completion bar + teste de cascade soft-delete"

  - nome: "Filters salvos / quick-views (My Open / Overdue / Recent)"
    score: P1
    descricao: "Atalhos pra filtros comuns sem digitar (sidebar com itens 'Minhas / Atrasadas / Esta semana')"
    quem_tem: ["Jira (saved filter JQL)", "Linear (views)", "Asana", "ClickUp"]
    evidencia_de_pronto: "Sidebar de filtros + URL state-driven + 3 quick-views por padrão + teste"

  - nome: "Search global (Cmd+K) por título/descrição"
    score: P1
    descricao: "Atalho keyboard abre command palette que busca em todos os projects/tasks do business"
    quem_tem: ["Linear (canonical)", "Jira", "Asana", "ClickUp"]
    evidencia_de_pronto: "Component CommandPalette via cmdk lib + endpoint GET /project/search?q= scoped business_id + index Meilisearch ou LIKE fallback + teste"

  - nome: "Activity log visível na issue (auditoria)"
    score: P1
    descricao: "Aba Activity mostra todas mudanças (status / assignee / due_date) com user + timestamp"
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp", "Productive"]
    evidencia_de_pronto: "Spatie ActivityLog já configurado em Project/ProjectTask + tab Activity renderiza eventos formatados + teste"

  - nome: "Templates de projeto (clone para tipo recorrente)"
    score: P2
    descricao: "Projeto-tipo 'Fachada Hotel' tem 12 tasks padrão. Criar novo from template instancia tudo."
    quem_tem: ["Jira", "Asana", "ClickUp", "Productive"]
    evidencia_de_pronto: "Flag is_template em pjt_projects + endpoint POST /project/from-template/{id} + UI seletor + teste"

  - nome: "Time estimates vs actuals (estimado × realizado)"
    score: P2
    descricao: "Cada task tem estimated_hours; comparado com soma de TimeLogs no relatório"
    quem_tem: ["Jira", "Asana", "ClickUp", "Productive"]
    evidencia_de_pronto: "Coluna estimated_hours em pjt_project_tasks + report mostra delta + alerta visual quando >150% estimativa"

  - nome: "Dependencies (blocks / blocked_by)"
    score: P2
    descricao: "Task A bloqueia Task B. UI mostra ícone de bloqueio + impede mover B se A não está done."
    quem_tem: ["Jira", "Linear", "Asana", "ClickUp"]
    evidencia_de_pronto: "Tabela pjt_project_task_dependencies + relation Eloquent + UI gráfica + validação no PATCH status"

  - nome: "Roadmap/Gantt timeline"
    score: P2
    descricao: "View de timeline com barras dos projects/tasks ao longo do tempo (start_date → due_date)"
    quem_tem: ["Jira (Plans)", "Asana (Timeline)", "ClickUp (Gantt)", "Productive"]
    evidencia_de_pronto: "Page /project/roadmap com lib gantt-task-react ou similar + drag horizontal pra mover datas + teste"

  - nome: "Custom fields por projeto (m², tipo material, fornecedor)"
    score: P2
    descricao: "Adicionar campos do tipo gráfica: m² produzidos, tipo de material (lona/adesivo/galvanizado), fornecedor de impressão. Vínculo com produtos da Sells."
    quem_tem: ["Jira (custom fields)", "Asana", "ClickUp"]
    evidencia_de_pronto: "Tabela pjt_project_custom_fields + UI cadastro per business + render dinâmico no detail + teste"

  - nome: "Customer view (read-only — cliente acompanha o projeto dele)"
    score: P3
    descricao: "Link compartilhável com cliente externo mostrando status + previsão + comentários públicos (não-internos)"
    quem_tem: ["Productive (Client portal)", "Asana (proofing)", "ClickUp"]
    evidencia_de_pronto: "Endpoint público /p/{token} sem auth + UI read-only + flag is_public_comment + LGPD review"

  - nome: "Burndown / Velocity charts (analytics produção)"
    score: P3
    descricao: "Gráfico mostra throughput semanal/mensal de tasks completadas + tempo médio + projeção de conclusão"
    quem_tem: ["Jira (canonical)", "Linear (cycles)", "ClickUp"]
    evidencia_de_pronto: "Página /project/{id}/analytics com Recharts + queries agregadas + teste"

  - nome: "Invoice from TimeLogs (gera fatura parcial das horas trabalhadas)"
    score: P1
    descricao: "Selecionar TimeLogs de um período + gerar Invoice (já existe em Modules/Project/InvoiceController) — mas com UI integrada ao MWART e linking à Sells/Financeiro"
    quem_tem: ["Productive (canonical)", "Harvest", "Asana"]
    evidencia_de_pronto: "Botão Generate Invoice no detail + select TimeLogs não-faturados + cria Invoice + abate horas + integra Modules/Financeiro (Receivable) + teste end-to-end"
```

## Como auditar este módulo (etapa específica)

> Esta seção é **lida pela skill** no passo 2.5.

**Locais a inspecionar (paths exatos):**

- Controllers atuais (Blade): `Modules/Project/Http/Controllers/{Project,Task,TaskComment,ProjectTimeLog,Invoice,Report,Activity}Controller.php`
- Controllers MWART alvo: `Modules/Project/Http/Controllers/Inertia/{Board,ProjectIndex,TaskDetail}Controller.php` _(criar)_
- Entidades: `Modules/Project/Entities/{Project,ProjectTask,ProjectTaskMember,ProjectTaskComment,ProjectTimeLog,InvoiceLine,ProjectMember,ProjectCategory,ProjectTransaction,ProjectUser}.php`
- Migrations existentes: `Modules/Project/Database/Migrations/2019_*` + `2020_*`
- Routes: `Modules/Project/Routes/web.php` (atual Blade) + alvo `routes/inertia.php` ou prefixo `/project/v2`
- Telas Blade legacy: `Modules/Project/Resources/views/{project,task,activity,invoice,reports,time_logs,my_task}/*.blade.php`
- Telas MWART alvo: `resources/js/Pages/Project/{Index,Board,Detail,Backlog,Roadmap}.tsx` _(criar)_
- Charter MWART: `memory/requisitos/Project/CHARTER-board.md` (criado nesta sessão)
- Tests: `Modules/Project/Tests/Feature/*.php` _(diretório vazio hoje — criar)_
- DataController: `Modules/Project/Http/Controllers/DataController.php` (já tem hooks UltimatePOS)

**Critérios customizados de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita | ❌ AUSENTE |
|---|---|---|---|
| Lista projetos | Page Inertia + filtros URL-state + multi-tenant test verde | Blade legacy ainda em uso | Nenhuma tela |
| Kanban drag-drop | Page React + drag-drop atomic + ActivityLog + teste E2E | Mock visual sem persistência | Nenhuma tela board |
| Issue detail | Modal/page Inertia + comments + attachments + activity tab | Blade legacy `task/show.blade.php` | Sem UI detail |
| Time tracking | Start/stop UI + manual entry + persistência + report agregado | Manual entry só, sem timer | Sem TimeLog visível |
| Permissions Spatie | Tests/Feature/PermissionsTest verde + UI esconde botões | Permissions checadas em controller, UI não esconde | Sem permission check |
| Notifications | 3 Notification classes + template Blade + log envio prod | Notification class existe, template missing | Sem Notification |
| Multi-assignee | Avatar stack UI + endpoint atribuir/remover + member modal | Tabela pivot existe, UI ausente | Sem suporte member |
| Backlog separado | Drawer/tab Backlog com drag bidirecional + count badge | Lista filtrada por status | Sem conceito de backlog |
| Quick add inline | Footer coluna + endpoint otimista + teste | Modal create existe | Sem create rápido |
| Bulk edit | Multi-select + bulk endpoint + audit log + permission check | Endpoint existe sem UI | Sem bulk |
| @mentions | MentionInput + parser + Notification + teste | Comments sem mention | Sem mention |
| Watchers | Tabela + UI + Notification dispara | Tabela existe, sem UI | Sem watcher |
| Subtasks | parent_task_id + UI árvore + cascade test | Coluna existe, UI flat | Sem subtask |
| Filters salvos | Sidebar 3 quick-views + URL state + teste | Filters mas não salvos | Sem filtros |
| Search global | Cmd+K + Meilisearch ou LIKE + teste | Search por página | Sem search |
| Activity log | Tab Activity + render formatado + teste | Spatie loga, UI não mostra | Sem ActivityLog |
| Templates | is_template flag + endpoint clone + UI seletor + teste | Migration prep, sem UI | Sem conceito |
| Time estimates | estimated_hours + delta report + alerta | Coluna existe, sem report | Sem estimativa |
| Dependencies | Tabela + UI gráfica + validação PATCH | Coluna FK existe, sem UI | Sem dependência |
| Roadmap/Gantt | Page timeline + lib gantt + drag horizontal | Lista por data | Sem timeline |
| Custom fields | Tabela + UI cadastro + render dinâmico + teste | Schema preparado, UI ausente | Sem custom field |
| Customer view | Endpoint público + UI read-only + LGPD review | URL pública sem token | Sem compartilhamento |
| Burndown | Page analytics + Recharts + queries agregadas | Stub Page | Sem analytics |
| Invoice from TimeLogs | Botão detail + select logs + Invoice gera + abate horas + integra Financeiro + teste E2E | InvoiceController Blade existe, sem integração MWART/Financeiro | Sem invoice |

**Métricas de prod relevantes (a coletar pós-MVP):**

- Adoção em ROTA LIVRE — meta: ≥3 projetos ativos com ≥10 tasks cada (hoje provavelmente 0)
- Tempo médio entre criação e completion de uma task — meta: <14 dias para tasks `in_progress`
- % de tasks com TimeLog registrado — meta: ≥70% (sinal de uso real)
- Latência drag-drop status change — meta: <300ms p95

## Métricas de adoção

- **Última auditoria**: nunca (1ª execução pendente — fase 0 desta sessão prepara FICHA, INVENTARIO virá depois)
- **Capacidades P0 cobertas**: a determinar (estimativa otimista: 2-3 de 7 — estado Blade legacy)
- **Gap P0+P1 atual**: a determinar (estimativa: 13-15 de 22)
- **Próxima reauditoria sugerida**: após Fase 1 (MVP Kanban) → reavaliar gaps P0/P1

## Histórico de revisão da ficha

- `2026-05-07` — [W] — criação da ficha como input pra ADR 0099 Project MWART Migration

## Referências externas

- Jira UI patterns: https://atlassian.design/components/board
- Linear UI patterns: https://linear.app/method
- Asana API: https://developers.asana.com/reference
- ClickUp API: https://clickup.com/api
- Productive.io API: https://developer.productive.io/index.html
- Trello API: https://developer.atlassian.com/cloud/trello/rest/
