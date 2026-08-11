---
id: requisitos-project-mgmt-capterra-ficha
---

# CAPTERRA-FICHA — Forja

> **Ficha canônica de benchmark do módulo Forja** — fonte de verdade para a skill `comparativo-do-modulo`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).
> ADR mãe redesign: [0100](../../decisions/0100-projectmgmt-ui-redesign.md).
> Charter da Board: [CHARTER-board.md](CHARTER-board.md).
> Inventário ✅🟡❌ atualizado: [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md).

---

## Identidade do módulo

- **Nome interno**: `Forja` desde 2026-07-30 (era `ProjectMgmt`; a Fase 3.9 do ADR 0079 previa `Project`, plano ABANDONADO — ver errata em memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md §5). **URLs web seguem `/project-mgmt/*` por compat** — o nome do módulo mudou, o prefixo de rota não (`Modules/Forja/Http/routes.php`; renomear é decisão [W] separada, SCOPE `url_prefixes`).
- **Domínio de negócio** (reescrito 2026-08-04 — a redação anterior "só Jira interno" ficou estreita): **cockpit de trabalho do time interno + host da infraestrutura MCP da plataforma**. Duas metades, uma casa:
  1. **Trabalho do time** estilo Jira — Project → Epic → Cycle → Story → Subtask + Inbox + Triage + Roadmap + Burndown + Activity. Cliente alvo: time oimpresso ([W]/[M]/[F]/[L]/[E]). **NÃO** é gestão de projetos de cliente externo.
  2. **Infra MCP** absorvida em jul/2026 sem mudar URL: identidade e emissão de token (`mcp_actors`), endpoints `/api/mcp` e `/api/cc`, **Daily Brief** (ex-`Modules/Brief`, ADR 0091), loop de handoff Cowork↔Code (ADR 0283), ingest de sessões Claude Code, hub Equipe (`/team-mcp/*`), Admin do MCP (`/ads/admin/*`) e o núcleo de registro/decompose do ADS. **`Modules/TeamMcp` foi DELETADO** (PR #5122) — as capacidades vieram MOVIDAS, não fundidas.
- **Fronteira e proveniência de cada peça**: [`memory/requisitos/Forja/SCOPE.md`](SCOPE.md) (dono único — não recopiar aqui).
- **Superfície de código**: **não fixar contagem nesta ficha** — número solto apodrece. Dono vivo: [`SUPERFICIE.md`](SUPERFICIE.md), regenerável por `node scripts/governance/module-surface.mjs Forja --write` (frescor via `--check`). Estado consolidado: [`BRIEFING.md`](BRIEFING.md).
- **Status atual**: em prod desde 2026-05-04 (PRs #91/#92, ADR 0070); **uso interno diário**, não cliente-facing. Nota do módulo: rodar `php artisan module:grade Forja --detail` (não fixar aqui).
- **⚠️ Sobreposição conhecida (aberta)**: as abas do cockpit `/forja` (triagem/backlog/quadro/changelog) sobrepõem Triage/Backlog/Board/Activity nativos. MOVIDO, não fundido — fundir = deletar uma implementação = decisão [W] (SCOPE §cockpit). Enquanto durar, **auditar as duas** ao classificar uma capacidade como ✅/🟡/❌.
- **Concorrentes-alvo direto** — a lista abaixo mudou em 2026-08-04 porque a identidade mudou: as 4 primeiras cobrem a metade "trabalho do time"; as 2 últimas entram porque o módulo virou **host de infra de agente**, e não havia benchmark pra essa metade.
  - **Linear** — linear.app — UX state-of-the-art (atalhos teclado, navegação <100ms, command palette, Triage em lote). Benchmark de **fluidez** que [W] pediu como base do redesign.
  - **Jira Cloud** — atlassian.com/software/jira — referência mundial Kanban + Backlog + Sprints + Roadmap + JQL. Padrão de **completude funcional** (e de peso: ver §"Premissa que NÃO importamos").
  - **Asana** — asana.com — multi-vertical, popular em PMEs BR. Views Lista + Board + Timeline + Workload.
  - **ClickUp** — clickup.com — one-tool-rules-all, custom fields ricos. Popular em SMB BR.
  - **Plane.so** — plane.so — open-source Linear-clone; **self-hostable como a Forja**, então é o único cuja premissa de deploy (1 tenant, 1 time, sem SaaS no meio) bate com a nossa.
  - **Backstage (Spotify)** — backstage.io — **novo em 2026-08-04**. Premissa que vale aqui: portal interno que é ao mesmo tempo catálogo de serviços e **host de plugins/infra do time** — exatamente a forma que a Forja assumiu ao absorver identidade MCP + Admin + Equipe. Serve de benchmark pra *"cockpit interno não é produto, é plataforma do time"*, não pra features de Kanban.
  - ~~**Productive.io**~~ — removido da lista-alvo em 2026-08-04. Premissa não bate: o valor dele é **time tracking humano faturável → invoice** (agência cobra hora do cliente). A Forja mede trabalho de time interno que **não é faturado por hora** — ver §"Premissa que NÃO importamos". Fica citado só como referência histórica do legado `pjt_project_time_logs`.

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
    descricao: "Permission `jana.mcp.usage.all` (pattern UltimatePOS); todos controllers checam + UI esconde botões sem perm + tests cobrindo 403/404 cross-tenant."
    quem_tem: ["Jira (project roles)", "Asana", "ClickUp", "Linear (workspace)"]
    evidencia_de_pronto: "Modules/Forja/Tests/Feature/PermissionsTest.php (a criar) + suite verde em CI + isolation cross-tenant 404"

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

## Premissa que NÃO importamos

> **Por que esta seção existe** (nova em 2026-08-04): uma ficha de benchmark, lida sem contexto, vira lista de compras — o concorrente tem, logo falta. A lápide §5 [2026-07-16](../../proibicoes.md) catalogou 3 transplantes crus no MESMO dia (Odoo/Shopify/Akeneo), e em 2 deles o anti-padrão inventado virou **lei no charter** — pior que ausente, porque parece canon. Aqui fica o registro do inverso: capacidade que o concorrente tem, cuja **premissa não vale aqui**.
> **Escopo:** o "porquê não" é desta ficha (foco no concorrente). O status ✅🟡❌ por capacidade é do [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) — não recopiar. ⚠️ O INVENTARIO está carimbado `generated_at: 2026-05-09` e **envelheceu** (lista Triage como ❌ quando a tela existe): cruze com [`SUPERFICIE.md`](SUPERFICIE.md) antes de citá-lo como estado atual.
> Sair desta lista é decisão [W] — não é o agente que reclassifica.

| Capacidade | Quem tem | A premissa DELES | Por que não vale aqui |
|---|---|---|---|
| **Custom fields per project** (hoje P2 nesta ficha) | Jira (canonical), ClickUp, Asana | SaaS multi-cliente: cada empresa modela um processo diferente e o vendor **não pode** prever o schema — então delega o schema ao usuário. | A Forja tem **1 tenant e 5 pessoas**, e o schema é nosso: precisou de campo, é migration + PR. Custom fields aqui compram flexibilidade que já temos e vendem o preço que o Jira paga por ela — query, índice e UI genéricos sobre EAV. Se um campo aparecer 3× no `mcp_components`, o caminho é coluna, não motor de campo. |
| **JQL / linguagem de query** (não listado nas capacidades — e é de propósito) | Jira (canonical) | Backlog de dezenas de milhares de issues com centenas de projetos: filtro por UI não alcança, então nasce uma linguagem. | Nosso volume não pede linguagem, e já existem **dois** caminhos de consulta consolidados: os filtros do Backlog (7 dimensões) e as tools MCP (`tasks-list module: status: owner:`). Uma 3ª sintaxe seria um terceiro juiz pro mesmo fato. |
| **Time tracking humano faturável** (hoje P2 nesta ficha) | Productive.io (canonical), Jira+Tempo, ClickUp | Agência **fatura hora do cliente** — a hora registrada É a receita, então rastrear é obrigação contratual. | A Forja mede trabalho **interno não faturado por hora**. O sinal de esforço que o time realmente usa já é outro: `estimate_h` + burndown + o **custo por PR em USD** (`scripts/governance/agent-cost-per-pr.mjs`, advisory). Cronômetro humano aqui mede a pessoa, não o trabalho — e ainda entra em rota de colisão com a regra Tier 0 de não commitar valor. O legado `pjt_project_time_logs` é do `Modules/Project` (em DELETE, Fase 3.8), não desta casa. |
| **Presence real-time "quem está vendo esta tela"** (hoje **P1** nesta ficha — proponho rebaixar, decisão [W]) | Figma (canonical), Linear, Notion | Edição **simultânea do mesmo documento**: sem avatar ao vivo, dois usuários se sobrescrevem sem perceber. | Aqui ninguém co-edita um card: a colisão real é conflito de transição, e ela **já tem defesa melhor que presence** — `expected_updated_at` → 409 + revert + estado do servidor no aviso (BoardController::updateStatus). Presence mostraria que alguém *está lá*; o 409 prova o que *aconteceu*. Com 5 pessoas e Centrifugo custando canal + teardown por página, o segundo mecanismo compra pouco. ⚠️ Continua P1 na lista de capacidades acima — **não reclassifiquei sozinho**; a demoção é decisão [W]. |
| **Workflow designer visual (arrastar estados)** (não listado — e é de propósito) | Jira (canonical), ClickUp | Cada cliente do SaaS tem um processo diferente e o vendor precisa que o **admin** configure sem deploy. | Estado de trabalho no oimpresso é **decisão de arquitetura, não preferência de tela** — o canon é o FSM tabular custom ([ADR 0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md)/[0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)), com transição por `ExecuteStageActionService` e auditoria append-only. Um designer visual que gere transição fora desse gateway não é feature: é a fuga do gateway que a proibição Tier 0 nomeia. |
| **Marketplace / plugins de terceiro** (não listado — e é de propósito) | Jira/Atlassian (canonical), ClickUp, Backstage | Plataforma com milhares de clientes: o vendor não escala pra cobrir cada nicho, então terceiriza a long tail. | A Forja tem **um** cliente (o time) e código-fonte aberto pra ele. Extensão aqui é PR, e o custo do marketplace é o oposto do que este projeto persegue: superfície de terceiro dentro de um host que guarda **identidade MCP e emissão de token** (`mcp_actors`, cross-business POR DESIGN). Do Backstage importamos a forma "portal interno é plataforma do time" — **não** o mercado de plugins. |

---

## Como auditar este módulo (etapa específica)

> Esta seção é **lida pela skill** no passo 2.5.

**Locais a inspecionar (paths exatos):**

- **Controllers / Services / Pages / charters / testes**: NÃO enumerar aqui (a lista de 2026-05 já ficou falsa quando o módulo absorveu a infra MCP). Fonte única e regenerável: [`SUPERFICIE.md`](SUPERFICIE.md) — `node scripts/governance/module-surface.mjs Forja --write`.
- Componentes board: `resources/js/Components/board/{BoardColumn,TaskCard,badges}.tsx`
- Routes: `Modules/Forja/Http/routes.php` — **6 prefixos**, não 1: `/project-mgmt` (web + `/install`), `/api/mcp`, `/api/cc`, `/team-mcp`, `/forja`, `/ads` (verificado 2026-08-04 por `grep -nE "prefix" Modules/Forja/Http/routes.php`)
- SCOPE.md: `memory/requisitos/Forja/SCOPE.md`
- SPEC funcional histórico: `memory/requisitos/TaskRegistry/SPEC.md` (US-TR-NNN — nome legado, content vivo)
- Tabelas: `mcp_jira_projects`, `mcp_epics`, `mcp_cycles`, `mcp_cycle_goals`, `mcp_tasks`, `mcp_task_attachments`, `mcp_task_comments`, `mcp_task_dependencies`, `mcp_task_events`, `mcp_task_memory_links`, `mcp_task_watchers`, `mcp_components`, `mcp_views`, `mcp_inbox_notifications`, `mcp_issue_templates`
- Tools MCP relacionados: `tasks-list`, `tasks-detail`, `tasks-create`, `tasks-update`, `cycles-active`, `cycles-close`, `cycle-goals-track`, `my-work`, `my-inbox`, `triage`
- Permission: `jana.mcp.usage.all` (legacy herdada — o BRIEFING pede pra NÃO criar permission própria da Forja)
- Tests: `Modules/Forja/Tests/Feature` **está registrado** em `phpunit.xml:33` (verificado 2026-08-04 — a redação anterior "a criar / sem registro" era de 2026-05 e ficou falsa). Contagem de arquivos: `SUPERFICIE.md`; cobertura por tela: `casos-gate`/`screen-coverage`, nunca esta ficha.

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

- **Última auditoria de capacidades**: `2026-05-09` — [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) (`generated_at`). ⚠️ **envelheceu** — foi gerada antes do rename, antes da Triage e antes da absorção do TeamMcp; os buckets ✅🟡❌ e o "27 capacidades" de lá são retrato de maio, não estado atual.
- **Última revisão de identidade/eixos v2**: `2026-08-04` — esta ficha (identidade + ux_heuristics + automation_targets + premissas não-importadas). **Não reauditou as 27 capacidades** — o placar por bucket segue sendo do INVENTARIO.
- **Cobertura P0/P1**: não fixar número aqui (as estimativas de 2026-05 apodreceram). Dono do placar: INVENTARIO, quando regerado.
- **Próxima reauditoria sugerida**: regerar o INVENTARIO contra `SUPERFICIE.md` — a divergência conhecida é grande o bastante pra que citar o placar velho induza erro.

## Histórico de revisão da ficha

- `2026-08-04` — [C] — reauditoria pós-rename e pós-absorção do TeamMcp; ux_heuristics e automation_targets preenchidos; premissas não-importadas registradas.
- `2026-05-07` — [W+C] — criação da ficha pós-pivot do PR #197 (que mirou no módulo errado `Modules/Project` legacy). Mira `Modules/Forja` em prod desde 2026-05-04 PRs #91/#92.

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
> Preenchido em **2026-08-04** [C] (era placeholder vazio desde a criação da ficha). Cada entrada carrega `benchmark` com concorrente nomeado + `premissa` dizendo por que o problema dele é o nosso (lápide §5 2026-07-16 — 3 transplantes crus no mesmo dia: Odoo/Shopify/Akeneo).
> ⚠️ **`atual` é retrato de leitura de código datado 2026-08-04, não medição de uso.** Onde a métrica pede número de produção (tempo, p95), o campo diz `baseline: a medir` — não invente o número.

```yaml
ux_heuristics:
  - id: triagem-cliques-por-task
    nome: "Cliques pra triar 1 task órfã (dar owner + prioridade)"
    score: P0
    benchmark: "Linear (Triage canonical): seleção MÚLTIPLA na fila + aceitar/atribuir em lote por atalho. Jira: bulk change no board filtrado."
    premissa: "Vale aqui porque a fila de órfãs do oimpresso NASCE em lote — o cron `mcp:tasks:unassigned` mediu ≥50 pendências (Kernel.php:614, 2026-07-27). Fila que chega em lote e só sai 1-a-1 acumula por construção; é o mesmo problema do Linear, não uma feature copiada."
    atual: "1-a-1. `Triage/Index.tsx` tem `selectedId` SINGULAR (linha 118) — não há checkbox nem seleção múltipla. O Backlog JÁ tem o padrão certo (Checkbox + `bulk()` → POST /project-mgmt/backlog/bulk, linhas 149/328/357), então o desenho existe na casa: é reuso, não invenção."
    target: "<= 2 cliques por task no caminho 1-a-1 E ação em lote na Triage reusando o padrão do Backlog (nunca um 2º mecanismo de bulk)"
    metrica: "triagem_cliques_por_task"

  - id: atalho-significa-o-mesmo-em-toda-tela
    nome: "Mesma tecla = mesma ação nas 4 telas de fila"
    score: P0
    benchmark: "Linear: um mapa de atalhos ÚNICO pro app inteiro; `?` abre a lista de qualquer tela."
    premissa: "Vale aqui porque o time transita entre Board → Triage → Inbox → MyWork no mesmo bloco de trabalho. Atalho que muda de significado (ou some) entre telas irmãs treina o usuário a não usar atalho nenhum — e aí o investimento já feito em J/K/E/A vira custo morto."
    atual: "J/K/Enter existem nas 4, mas em 4 listeners `keydown` REIMPLEMENTADOS (Board via `_components/useBoardShortcuts.ts`; Triage/Index.tsx:195; Inbox/Index.tsx:~195; MyWork/Index.tsx:~230). Só o Board tem o overlay `?` (`ShortcutsOverlay.tsx`) — nas outras 3 o atalho existe e é INDESCOBRÍVEL. Inbox/MyWork têm `r`/`R` (marcar lido); Triage não tem atalho de atribuir."
    target: "1 hook compartilhado + `?` disponível nas 4 telas; nenhuma tecla com 2 significados"
    metrica: "telas_com_overlay_de_atalho"

  - id: tempo-ate-achar-uma-task
    nome: "Passos até abrir uma task cujo id você não lembra"
    score: P0
    benchmark: "Linear/Notion: Cmd+K → digitar → Enter, sem sair da tela atual."
    premissa: "Vale aqui porque o time referencia task por id no chat e no commit (`Refs: US-...`), então o caminho 'lembro do assunto, não do id' é o caminho REAL — não um luxo de produtividade."
    atual: "Cmd+K entregue e é o padrão certo — `Components/CommandPalette.tsx` (cmdk) com debounce 220ms + grupos Tasks/Epics/Cycles/Projects, backend `SearchController` com gate de permission. Trigger global no AppShellV2."
    baseline: "a medir — não há número de produção pro tempo/p95 desta busca; NÃO citar número sem rodar"
    target: "<= 2 teclas pra abrir a busca + resultado p95 <= 1s (medir antes de comparar)"
    metrica: "search_palette_p95_ms"

  - id: recuperacao-de-erro-em-acao-otimista
    nome: "O que acontece quando a ação otimista falha"
    score: P1
    benchmark: "Linear/Jira: mudança aplica na hora e, em conflito, reverte com aviso — o usuário nunca fica com a tela mentindo."
    premissa: "Vale aqui porque a Forja tem 5 pessoas mexendo na MESMA fila (não é single-player), e o Board já sofre o caso: dois vendo o mesmo card. É o problema deles, com um agravante nosso — parte das transições passa por regra de negócio, então falhar é normal, não exceção."
    atual: "Board tem o caminho completo: `expected_updated_at` → 409 Conflict com `current` + revert + banner (BoardController::updateStatus + Board/Index.tsx). Fora do Board o padrão não está replicado — Inbox/MyWork usam `optimisticRead` sem conflito de servidor."
    target: "toda mutação otimista da Forja com revert visível + estado do servidor no aviso (nunca 'falhou' mudo)"
    metrica: "mutacoes_otimistas_com_revert"
```

## Automation targets (Capterra v2 — eixo Automação)

> O que mercado faz **sem humano**? Listener? Cron? Job? Webhook?
> Preenchido em **2026-08-04** [C] (era placeholder vazio desde a criação da ficha).
> ⛔ **Regra dura desta seção — a máquina pode já existir.** Antes de escrever "criar alerta X", conferir o dono vivo em `app/Console/Kernel.php` + `memory/governance/AUTOMATIONS.md`. Duas das entradas abaixo nasceriam DUPLICANDO régua consolidada (lápide §5 2026-07-09) — por isso dizem **ESTENDER**, não "criar". Máquina órfã é bug; máquina duplicada é pior, porque cria dois juízes pro mesmo fato.

```yaml
automation_targets:
  - id: orfa-sem-dono-chega-no-humano
    nome: "US órfã (sem owner e/ou sem cycle) vira sinal que alguém lê"
    score: P0
    benchmark: "Linear: fila de Triage é estado de primeira classe — item sem dono não fica invisível, ele fica NA FILA. Jira: filtro salvo + subscription por email."
    premissa: "Vale aqui porque o oimpresso tem um sintoma que o Linear resolve por desenho: US que nasce sem `cycle_id` some do roadmap (a Jana filtra por cycle). O item não é 'ignorado', é INVISÍVEL — que é exatamente o buraco que a fila de triagem do Linear fecha."
    dono_existente: "⚠️ NÃO CRIAR — já existe. `mcp:tasks:unassigned`, agendado daily 06:45 BRT em `app/Console/Kernel.php`, bloco `command('mcp:tasks:unassigned')` (US-INFRA-043, 2026-07-27), `environments(['live'])`, ADVISORY de propósito. A visibilidade humana vem do `TasksSemDonoBriefLineService` (Modules/Jana) no Daily Brief."
    target: "ESTENDER o existente — a tela Triage da Forja consumir o MESMO predicado do comando (uma fonte, dois consumidores). Promover a --strict só com mordida provada (ADR 0336), nunca por reflexo."
    metrica: "mcp_tasks_unassigned_total (série do log — é a série que autoriza o flip, não um número isolado)"

  - id: parada-em-review-vira-sinal-na-tela
    nome: "Task parada em `review` deixa de depender de alguém lembrar"
    score: P0
    benchmark: "Jira Automation (canonical): regra 'se transição X há N dias, notifica'. Linear: SLA/auto-nudge por estado."
    premissa: "Vale aqui e o número prova: medido 2026-08-04 via `tasks-list module:Forja` (tool MCP), das 7 tasks ativas do próprio módulo **6 estão em `review`** e 1 em `todo` — zero em `doing`. Fila de revisão que só esvazia por memória humana é o gargalo que a automação do Jira ataca; aqui ele já é o estado dominante."
    dono_existente: "⚠️ A DETECÇÃO JÁ EXISTE — `mcp:tasks:health-check`, daily 06:20 BRT (`app/Console/Kernel.php:591`), flagga `stale_review >5d` (além de stale_todo >21d, stale_blocked >30d, stale_doing >7d sem commit). Roda SEM --auto-comment: o sinal morre no log."
    target: "ESTENDER — expor o veredito que o comando JÁ produz na superfície da Forja (badge no card / linha no MyWork / Inbox), sem um 2º detector e sem mudar o limiar por conta própria (>5d é do dono; 7d seria um 3º número pro mesmo fato)."
    metrica: "stale_review_count (dono: mcp:tasks:health-check)"

  - id: pr-mergeado-realimenta-a-task
    nome: "PR mergeado que cita a US realimenta a task sozinho"
    score: P1
    benchmark: "Linear (canonical): magic words no PR movem o issue no merge. GitHub Issues: `Closes #N`."
    premissa: "Vale aqui porque metade do dado já está na casa e não é usada pra isso: o `PrChecksResolver` (Modules/Forja/Services) já lê o estado REAL dos required checks do PR via GitHub API pro loop de handoff (ADR 0283), e os commits do projeto já carregam `Refs:` por disciplina (skill commit-discipline). A aresta PR→task é o pedaço que falta, não a integração."
    atual: "AUSENTE — `PrChecksResolver` resolve green/red/pending do PR, mas nada escreve de volta em `mcp_tasks`/`mcp_task_events` a partir do merge."
    target: "PR mergeado citando a US → EVENTO em `mcp_task_events` + sugestão de transição. **Nunca transição automática pra `done`** — aqui quem fecha é o dono da task ([W]/owner), então importamos o gatilho, não o desfecho."
    metrica: "pr_merge_events_vinculados_a_task"

  - id: cycle-close-rollover-sem-cli
    nome: "Fechamento de cycle move as incompletas sem alguém rodar comando"
    score: P1
    benchmark: "Jira: sprint close com rollover é passo do fluxo, não CLI. Linear: cycle vira automaticamente e o não-feito escorre pro próximo."
    premissa: "Vale aqui porque o cycle do oimpresso é quinzenal e o rollover é REGRA (regras-time.md §Ciclo: 'Sex final cycle: cycles-close --rollover'). Regra escrita que depende de alguém lembrar de rodar CLI é a definição de conhecimento que apodrece (ADR 0256) — o mesmo motivo pelo qual o Jira tirou isso da mão do usuário."
    atual: "PARCIAL — a tool MCP `cycles-close --rollover` existe (CLI/MCP), sem UI e sem cadência. `CAPTERRA-INVENTARIO` lista Cycle close UI como ❌ (PMG-009)."
    target: "UI de fechamento (rollover + retro) + lembrete na data de fim do cycle. Disparo do fechamento segue humano — o cycle é ato de gestão, não de cron."
    metrica: "cycles_fechados_com_rollover_no_prazo"
```
