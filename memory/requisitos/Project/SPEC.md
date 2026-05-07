# Especificação funcional — Project (legacy UltimatePOS) — ⚠️ PIVOTADO

> ⚠️ **PIVOTADO 2026-05-07.** As 15 US-PROJ-001..015 abaixo miram `Modules/Project/` Blade legacy UltimatePOS. Wagner pediu redesign do `Modules/ProjectMgmt` (Jira-style time interno) — módulo diferente em prod desde PRs #91/#92.
>
> **Não criar tasks dessas US no MCP.** Migração desta SPEC foi **cancelada**. SPEC NOVO mira ProjectMgmt em `memory/requisitos/ProjectMgmt/SPEC.md` com IDs `US-PMG-NNN` ou aprenda no `memory/requisitos/TaskRegistry/SPEC.md` legado (US-TR-NNN).
>
> Este artefato fica preservado como **insumo da Fase 3.8** ([SCOPE.md ProjectMgmt](../../../Modules/ProjectMgmt/SCOPE.md)) — antes de `git rm -rf Modules/Project/`, validar quais US têm dado real em prod (TimeLogs / Invoices) que precisa ser extraído.
>
> **Convenção do ID**: `US-PROJ-NNN` (legacy)
> **Charter**: ver [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (24 capacidades) e [CHARTER-board.md](CHARTER-board.md) (anatomia).
> **ADR pivot**: [0099](../../decisions/0099-project-mwart-migration.md).

---

## 3. User stories

### Fase 1 — MVP Kanban (P0)

#### US-PROJ-001 · Lista de projetos com filtros e busca (MWART)

**Como** Larissa (gerente operacional gráfica)
**Quero** ver todos os projetos do meu business em uma tela com filtros (status, lead, cliente, categoria) e busca por nome
**Para** localizar rapidamente "o pedido daquele hotel" sem rolar pela lista inteira

**Implementado em:** _resources/js/Pages/Project/Index.tsx_ (a criar — Fase 1)

**Definition of Done:**
- [ ] Page Inertia/React substitui `Resources/views/project/index.blade.php` em rota nova `/project` com Page React
- [ ] Filtros via URL state (`?status=in_progress&search=hotel`) preservados em refresh
- [ ] Server-side query com `business_id` global scope (R-PROJ-001)
- [ ] Permission check `project.view_project` (R-PROJ-002 derivada)
- [ ] Skeleton loading <200ms até primeiro paint
- [ ] Teste Pest: list visible só do business correto, 403 sem permission

#### US-PROJ-002 · Kanban board drag-drop por status

**Como** Larissa
**Quero** arrastar cards entre 5 colunas de status (Não iniciado / Em progresso / Pausado / Cancelado / Concluído) na tela do projeto
**Para** atualizar o status visualmente sem abrir modal

**Implementado em:** _resources/js/Pages/Project/Board.tsx_ (a criar — Fase 1)

**Definition of Done:**
- [ ] Page React `Board.tsx` em `/project/{id}/board` segue [CHARTER-board.md](CHARTER-board.md) §3 anatomia
- [ ] Drag-drop com `@hello-pangea/dnd` ou similar (não inventar — ver Repair como referência)
- [ ] Optimistic UI move card imediatamente; reverte em erro com ToastError
- [ ] PATCH `/project-task/{id}/post-status` atomic (rota legacy reaproveitada)
- [ ] Spatie ActivityLog registra `status changed from X to Y by user Z`
- [ ] Tratamento de 409 Conflict (race condition) com refetch silencioso
- [ ] Permission check `project.edit_project` — read-only quando sem
- [ ] Latência drag→server confirmation <300ms p95 em ambiente Hostinger
- [ ] Teste Pest Feature: drag em direção válida, drag bloqueado sem permission, 409 quando conflito

#### US-PROJ-003 · Issue/task detail Sheet à direita (Jira-style)

**Como** operador (Wagner/Maíra/Felipe/Eliana)
**Quero** clicar num card e abrir um Sheet à direita com detalhes (description, members, due date, priority) sem perder o board de fundo
**Para** acessar contexto completo da task sem reload

**Implementado em:** _resources/js/Pages/Project/Detail.tsx_ ou `components/DetailSheet.tsx` (Fase 2)

**Definition of Done:**
- [ ] Click no card → URL muda para `/project/{id}/board?task={tid}` com `preserveState=true`
- [ ] Sheet anima slide-in <150ms; Esc fecha
- [ ] Tabs: Description (default), Comments, Time Logs, Activity (Subtasks fica P1)
- [ ] Description rich-text (TipTap ou similar) com save em blur
- [ ] Avatar stack mostra members com tooltip
- [ ] Botão "Watch/Unwatch" (capacidade #12 — Fase 3)
- [ ] Mobile: Sheet vira fullscreen <768px (P2 — não bloqueador MVP)
- [ ] Teste Pest: detail loads, comment round-trip, permission denied retorna 403

#### US-PROJ-004 · Time tracking (manual + start/stop) por task

**Como** operador
**Quero** registrar tempo trabalhado em uma task (botão Start/Stop OU entrada manual de horas)
**Para** ter histórico fiel de quanto custa cada projeto

**Implementado em:** _resources/js/Pages/Project/Detail.tsx → tab TimeLogs_ (Fase 2)

**Definition of Done:**
- [ ] Tab Time Logs no DetailSheet lista entries existentes (`pjt_project_time_logs`)
- [ ] Botão Start cria entry com `started_at = now()`; botão Stop atualiza `ended_at` e `duration` calculado
- [ ] Apenas 1 timer ativo por user por vez (constraint UI + server)
- [ ] Manual entry: input "horas + minutos" + descrição opcional
- [ ] Total agregado por task + projeto + user visível em report
- [ ] Permission `project.edit_project` ou ser member da task
- [ ] Teste Pest: start/stop round-trip, manual create, total agregação correta, multi-tenant scoped

#### US-PROJ-005 · Multi-tenant + Permissions Spatie cobertas por testes

**Como** dev/auditor
**Quero** garantir que todo controller MWART do Project respeita business_id scope e checa permission Spatie
**Para** que vazamento entre tenants seja **impossível** (Tier 0 IRREVOGÁVEL — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

**Implementado em:** _Modules/Project/Tests/Feature/PermissionsTest.php_ (a criar — Fase 1, junto com US-PROJ-001..002)

**Definition of Done:**
- [ ] `Modules/Project/Tests/` registrado em `phpunit.xml` (proibição CLAUDE.md: senão CI nunca roda)
- [ ] Suite cobre: index/show/edit/store/destroy de Project + Task + Comment + TimeLog
- [ ] Cada test verifica: 403 sem permission, 404 cross-tenant, 200 com permission
- [ ] Skill `multi-tenant-patterns` Tier A respeitada
- [ ] Suite verde em CI

### Fase 2 — Detail + TimeLogs (P0)

#### US-PROJ-006 · Backlog separado do Board

**Como** Larissa
**Quero** ver tasks `not_started` num pane separado (Backlog) para que não poluam o board e eu possa "puxar" pra produção quando começo
**Para** ter foco no que está em fluxo vs no que está esperando

**Implementado em:** _resources/js/Pages/Project/Board.tsx → drawer/tab Backlog_ (Fase 2)

**Definition of Done:**
- [ ] Tab "Backlog" ao lado de "Board" mostra só `not_started` em lista
- [ ] Drag bidirecional Backlog ↔ Board (`not_started` → `in_progress`)
- [ ] Count badge dinâmico
- [ ] Quick add inline também presente no Backlog
- [ ] Teste Pest: drag-drop bidirecional persiste

#### US-PROJ-007 · Notifications email (assigned/comment/status) — validação

**Como** Larissa
**Quero** receber e-mail quando alguém me atribuir uma task ou comentar numa que sigo
**Para** não precisar abrir o sistema constantemente pra acompanhar

**Implementado em:** _Modules/Project/Notifications/* (já existem 3 classes)_ — fase 2 valida + adiciona @mention

**Definition of Done:**
- [ ] `NewProjectAssignedNotification`, `NewTaskAssignedNotification`, `NewCommentOnTaskNotification` validados em prod (≥1 entrega real)
- [ ] Templates Blade renderizando corretamente
- [ ] Logs de envio em `mcp_audit_log` ou `notification_log` tabela
- [ ] Larissa confirma que recebeu e-mail dos 3 tipos pelo menos 1 vez
- [ ] Teste Pest com `Notification::fake()` cobrindo 3 disparos

### Fase 3 — Bulk + Filtros + @mentions (P1)

#### US-PROJ-008 · Quick add inline na coluna Kanban

**Como** Larissa
**Quero** clicar `+ adicionar` no footer de uma coluna e digitar o título sem abrir modal
**Para** capturar tasks no fluxo do pensamento sem fricção

**Definition of Done:**
- [ ] Component `QuickAddInput` em cada coluna; foco automático ao clicar
- [ ] Enter → POST otimista; Esc cancela; click-outside cancela
- [ ] Multiple-add: após criar, foco volta no input pra task seguinte
- [ ] Teste: criação otimista renderiza antes do response

#### US-PROJ-009 · Bulk edit (multi-select → status/priority/assignee em massa)

**Como** Larissa
**Quero** selecionar várias tasks via checkbox e mudar todas de status, priority ou assignee de uma vez
**Para** organizar 20 tasks em 30s sem clicar 60 vezes

**Definition of Done:**
- [ ] Checkbox em cada card; selection state visível
- [ ] Barra flutuante com ações + count selecionadas
- [ ] Endpoint `PATCH /project-task/bulk` com payload `{ids: [], changes: {status, priority, assignee_ids}}`
- [ ] Permission check **por task** (uma sem permission aborta toda operação)
- [ ] Audit log com count afetadas + lista de IDs
- [ ] Teste: bulk com 1 sem permission → 403 + nenhuma persiste

#### US-PROJ-010 · Comments com @mentions

**Como** operador
**Quero** mencionar `@João` num comment e ele receber notification
**Para** chamar atenção sem precisar mandar mensagem fora do sistema

**Definition of Done:**
- [ ] Component MentionInput com autocomplete dos members do projeto
- [ ] Parser server-side extrai mentions e dispara `NewCommentMentionNotification`
- [ ] Render: `@João` vira link clicável no comment
- [ ] Teste: mention dispara notification ao user mencionado, não a outros

#### US-PROJ-011 · Watchers (followers não-assignees)

**Como** auditor
**Quero** "seguir" uma task crítica sem ser assignee
**Para** receber notifications de mudanças sem ter responsabilidade direta

**Definition of Done:**
- [ ] Migration `pjt_project_task_watchers` (user_id, task_id, business_id, created_at)
- [ ] Botão Follow/Unfollow no DetailSheet
- [ ] Notifications disparam pra members + watchers
- [ ] Teste: watch/unwatch round-trip + notification

#### US-PROJ-012 · Filters salvos / quick-views

**Como** Larissa
**Quero** atalhos pra "Minhas tasks", "Atrasadas", "Esta semana"
**Para** não digitar filtros toda manhã

**Definition of Done:**
- [ ] Sidebar com 3 quick-views por padrão
- [ ] URL state-driven; click muda URL e re-renderiza
- [ ] Teste: cada quick-view filtra corretamente

#### US-PROJ-013 · Search global (Cmd+K)

**Como** operador
**Quero** Cmd+K abrir command palette e buscar em todos os projetos/tasks do business
**Para** navegar sem clicar nos menus

**Definition of Done:**
- [ ] Atalho keyboard global registrado
- [ ] Component CommandPalette via lib `cmdk`
- [ ] Endpoint `GET /project/search?q=` com Meilisearch ou LIKE fallback
- [ ] Resultados mostram projeto + task com path
- [ ] Multi-tenant scoped
- [ ] Teste: search retorna só do tenant; 0 results se nada combina

#### US-PROJ-014 · Activity log visível no DetailSheet

**Como** auditor
**Quero** aba "Activity" mostrando histórico (status, assignee, due_date) com user + timestamp
**Para** rastrear quem mudou o quê e quando (LGPD compliance)

**Definition of Done:**
- [ ] Tab Activity no DetailSheet renderiza Spatie ActivityLog formatado em PT-BR
- [ ] Eventos: status changed, priority changed, assignee added/removed, due_date changed, comment added
- [ ] Lazy load se >50 eventos
- [ ] Teste: ações disparam log corretamente

### Fase 4 — Diferencial vertical (P1)

#### US-PROJ-015 · Invoice from TimeLogs (gera fatura parcial das horas trabalhadas)

**Como** Larissa
**Quero** selecionar TimeLogs não-faturados de um período e gerar Invoice automaticamente que vira Receivable no Financeiro
**Para** faturar horas trabalhadas sem digitar tudo de novo no módulo financeiro

**Implementado em:** _resources/js/Pages/Project/Invoice/Generate.tsx + Modules/Project/Http/Controllers/InvoiceController_ (Fase 4)

**Definition of Done:**
- [ ] Botão "Gerar Invoice" no DetailSheet do projeto
- [ ] Modal lista TimeLogs não-faturados (flag `invoiced_at` na tabela `pjt_project_time_logs`)
- [ ] Seleção múltipla + cálculo automático: `sum(duration) × hourly_rate`
- [ ] Cria `Invoice` (já existe em `Modules/Project/Entities/`) + InvoiceLines
- [ ] Marca TimeLogs selecionados com `invoiced_at = now()`
- [ ] Cria `Receivable` no Modules/Financeiro (cliente do Invoice = `customer_id`)
- [ ] Permission check + multi-tenant
- [ ] Teste E2E: 5 timelogs → invoice → receivable cadastrado

### P2 — Backlog (não comprometido nesta migração)

#### US-PROJ-016 · Subtasks 1-nível
#### US-PROJ-017 · Time estimates vs actuals
#### US-PROJ-018 · Templates de projeto (clone)
#### US-PROJ-019 · Roadmap/Gantt timeline
#### US-PROJ-020 · Custom fields por business
#### US-PROJ-021 · Dependencies (blocks/blocked_by)

_Detalhes em [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (capacidades P2)._

### P3 — Diferenciação opcional

#### US-PROJ-022 · Customer view (read-only pelo cliente final)
#### US-PROJ-023 · Burndown / Velocity charts
#### US-PROJ-024 · WIP limits por coluna

---

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser **testável** — idealmente tem 1 teste Feature que a valida.

### R-PROJ-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Project
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))` + global scope no `Project::class` (Tier 0 — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
**Testado em:** `Modules/Project/Tests/Feature/PermissionsTest::cross_tenant_returns_404` (a criar Fase 1)

### R-PROJ-002 · Autorização Spatie `project.create_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.create_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.create_project')`
**Testado em:** `Modules/Project/Tests/Feature/PermissionsTest::create_without_permission_returns_403` (a criar Fase 1)

### R-PROJ-003 · Autorização Spatie `project.edit_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.edit_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.edit_project')`; UI esconde botões drag/edit
**Testado em:** `Modules/Project/Tests/Feature/PermissionsTest::edit_without_permission_returns_403` (a criar Fase 1)

### R-PROJ-004 · Autorização Spatie `project.delete_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.delete_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.delete_project')`
**Testado em:** `Modules/Project/Tests/Feature/PermissionsTest::delete_without_permission_returns_403` (a criar Fase 1)

### R-PROJ-005 · Drag-drop concorrente preserva integridade (Fase 1)

```gherkin
Dado que dois usuários têm `/project/{id}/board` aberto
Quando ambos arrastam o mesmo card simultaneamente para colunas diferentes
Então o segundo PATCH retorna 409 Conflict com estado atual
E o frontend do segundo usuário refeta a coluna afetada e mostra toast informativo
```

**Implementação:** PATCH `/project-task/{id}/post-status` valida `version` ou `updated_at` esperado; se difere, retorna 409
**Testado em:** `Modules/Project/Tests/Feature/BoardTest::concurrent_drag_returns_409` (Fase 1)

### R-PROJ-006 · Apenas 1 timer ativo por user por vez (Fase 2)

```gherkin
Dado que um usuário já tem um TimeLog com `ended_at IS NULL` em qualquer task
Quando ele clica Start em outra task
Então o timer anterior é finalizado automaticamente (`ended_at = now()`)
E o novo timer é iniciado
```

**Implementação:** `ProjectTimeLogController::start()` checa active timer + finaliza antes de criar novo
**Testado em:** `Modules/Project/Tests/Feature/TimeTrackingTest::start_finalizes_previous_timer` (Fase 2)

### R-PROJ-007 · TimeLog faturado é imutável (Fase 4)

```gherkin
Dado que um TimeLog tem `invoiced_at IS NOT NULL`
Quando alguém tenta editar `duration`, `started_at`, `ended_at` ou `description`
Então o sistema retorna 422 com mensagem "TimeLog faturado não pode ser editado"
```

**Implementação:** `ProjectTimeLog::saving` event throws se `invoiced_at` setado e dirty fields são protegidos
**Testado em:** `Modules/Project/Tests/Feature/InvoiceFromTimeLogsTest::invoiced_timelog_is_immutable` (Fase 4)

---

## 5. Status

- **Última atualização**: 2026-05-07 — fase 0 discovery (CAPTERRA-FICHA + CHARTER + ADR 0099 + 15 US priorizadas)
- **Owner produto**: [W]
- **Próximo passo**: Wagner aprovar batch de tasks MCP listadas no session log → criar via `tasks-create`
