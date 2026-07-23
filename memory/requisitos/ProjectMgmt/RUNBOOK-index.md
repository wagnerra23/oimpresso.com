---
id: requisitos-project-mgmt-runbook-index
title: "RUNBOOK — ProjectMgmt Triage + Inbox (`/project-mgmt/triage` · `/project-mgmt/inbox`)"
module: ProjectMgmt
tela: ProjectMgmt/Triage/Index + ProjectMgmt/Inbox/Index
owner: W
status: ativo
last_validated: "2026-05-29"
preconditions:
  - "Usuário autenticado com permission `copiloto.mcp.usage.all` (Spatie UPOS canon — mesmo gate do Board/MyWork)"
  - "Modules/ProjectMgmt + Modules/Jana ativos (tabelas mcp_tasks / mcp_inbox_notifications)"
  - "Projeto default `COPI` resolvível (config `projectmgmt.default_project_key`) — Triage"
preconditions_short: copiloto.mcp.usage.all, ProjectMgmt+Jana ativos, projeto COPI (Triage)
steps:
  - "GET /project-mgmt/triage carrega lista de tasks órfãs + 4 KPIs (Inertia::defer)"
  - "Triage: select inline owner/prioridade/cycle/epic → PATCH /triage/{taskId}/assign (otimista, rollback em erro)"
  - "Triage: task some da lista quando deixa de ser órfã (still_triage=false)"
  - "GET /project-mgmt/inbox carrega notificações do auth user agrupadas por tipo + 2 KPIs (Inertia::defer)"
  - "Inbox: marcar lida individual (PATCH /inbox/{id}/read) ou todas (PATCH /inbox/read-all), otimista"
  - "Inbox: click/Enter abre /project-mgmt/board?task=ID (DetailSheet) — deep-link"
  - "Ambas: J/K navega · Enter abre · ⌘K palette global (AppShellV2, PMG-002)"
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0100-projectmgmt-ui-redesign
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0058-reverb-substituido-por-centrifugo-frankenphp
---

# RUNBOOK — ProjectMgmt Triage + Inbox

> Rotas: `/project-mgmt/triage` (Triage) · `/project-mgmt/inbox` (Inbox)
> Componentes: `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` + `resources/js/Pages/ProjectMgmt/Inbox/Index.tsx`
> Controllers: `Modules/ProjectMgmt/Http/Controllers/TriageController@index` + `InboxController@index`
> Charters: `Triage/Index.charter.md` + `Inbox/Index.charter.md` (ambos `status: draft` — pendente gate visual)
> Última atualização: 2026-05-29 (PR #1940 — code-complete, DRAFT aguardando gate visual ADR 0107/0114)
> **Nota gate MWART:** ambas as telas são `Index.tsx` → o gate colapsa pra `tela_kebab=index`, então **este RUNBOOK cobre as duas**. Telas irmãs já em prod: Board (`RUNBOOK` via CHARTER-board.md), Backlog, MyWork, Activity, Burndown, Roadmap.

## 1. Objetivo

Duas superfícies humanas das tools MCP de governança de tasks (ADR 0070), parte do redesign ProjectMgmt (ADR 0100):

- **Triage** (`/project-mgmt/triage`, US-TR-301..303): lista as tasks **órfãs** (sem owner OU sem prioridade OU em backlog) e permite atribuir owner + prioridade (+ cycle/epic opcional) **inline**, sem abrir cada task. Paridade 1:1 com a tool MCP `triage` (mesmo scope `McpTask::triage()`).
- **Inbox** (`/project-mgmt/inbox`, US-TR-304..306): caixa de entrada **por-pessoa** com as notificações do usuário autenticado agrupadas por tipo, marcar-lido (individual + todas) e deep-link pra task no Board. Paridade com a tool MCP `my-inbox`.

Ambas substituem a necessidade de operar via CLI/MCP pra membros não-técnicos do time (e futuros clientes B2B — Board multi-tenant só com 1º cliente, sinal qualificado ADR 0105).

## 2. Persona principal

**Membro do time não-técnico** (Felipe / Maiara / Eliana / Luiz) ou **Wagner Admin** revisando o backlog: ver tasks que ninguém pegou e distribuir dono/prioridade num passe (Triage); abrir a Inbox de manhã pra ver o que mencionaram/atribuíram/pediram revisão e ir direto pra task (Inbox). Operação de teclado-first (J/K + Enter + ⌘K), igual Board/MyWork.

## 3. Pré-requisitos

- Permission `copiloto.mcp.usage.all` (middleware `can:` no constructor de ambos os Controllers) — sem ela: **403**
- `auth` middleware (sem sessão → redirect login)
- **Triage:** projeto resolvível via `?project=KEY` (default `COPI` por `config('projectmgmt.default_project_key')`). Sem projeto → `project=null`, lista vazia.
- **Inbox:** `auth()->id()` válido (a query base é `WHERE user_id = me`)
- `Modules/Jana` ativo: models `McpTask`, `McpCycle`, `McpEpic`, `McpProject`, `McpInboxNotification`
- (futuro) Centrifugo pro badge realtime da Inbox (ADR 0058) — **não** nesta entrega; polling 30s cobre

## 4. Fluxo principal (golden path)

### Triage (`/project-mgmt/triage`)

1. Usuário navega `/project-mgmt/triage`
2. PageHeader canon: título "<Projeto> — Triagem" + subtitle com contadores (`N pra triar · X sem dono · Y sem prioridade · Z em backlog`) + hint de atalhos (J/K · Enter · ⌘K) + link "Ver no Board →"
3. `<KpiGrid cols={4}>` com 4 KpiCards (`Inertia::defer` ~300-500ms): **Pra triar** / **Sem dono** / **Sem prioridade** / **Em backlog** (tone warning quando > 0)
4. Tabela de tasks órfãs (defer): colunas Task (display_id + título + chips de motivo) · Dono · Prioridade · Cycle · Epic
5. Cada linha tem selects inline (owner / prioridade / cycle / epic). `onValueChange` → `assign(taskId, patch)`:
   - UI **otimista** (aplica já via overlay `optimistic[taskId]`), select desabilita (`pending`)
   - `PATCH /project-mgmt/triage/{taskId}/assign` body `{owner?|priority?|cycle_id?|epic_id?}`
   - **Sucesso:** se `still_triage=false`, a task **some da lista** (`resolved` set local); `router.reload({only:['tasks','kpis']})` reconcilia contadores
   - **Erro:** rollback do otimismo + banner âmbar inline (auto-dismiss 5s)
6. Chips de motivo por linha (`sem dono` / `sem prioridade` / `backlog`) explicam por que a task caiu na fila
7. Navegação **J/K** seleciona linha (ring azul + `aria-current`); **Enter** abre a linha focada em `/project-mgmt/board?task=ID`
8. **Empty state:** "Nada pra triar" (sem emoji) quando a fila zera
9. Polling 30s + on-focus reload re-sincroniza a fila com o servidor

### Inbox (`/project-mgmt/inbox`)

1. Usuário navega `/project-mgmt/inbox`
2. PageHeader canon: título "Caixa de entrada" + subtitle (`N não-lidas · M nos últimos 30 dias`) + hint de atalhos (J/K · Enter · R · ⌘K) + botões "mostrar lidas / só não-lidas" e "marcar todas"
3. `<KpiGrid cols={2}>` com 2 KpiCards (defer): **Não-lidas** / **Últimos 30 dias**
4. Notificações **agrupadas por tipo** na ordem: mention → assigned → review_requested → status_changed → commented → due_soon → blocked_resolved; cada grupo tem ícone + título PT-BR + badge de contagem
5. Cada item: ator + label PT-BR do tipo + chip task_id + corpo + timeAgo. Click/Enter → `openTask`:
   - marca lido se ainda não-lido (`PATCH /inbox/{id}/read`, otimista)
   - se tem `task_id`, `router.visit('/project-mgmt/board?task=ID')` (DetailSheet — US-TR-306)
6. "marcar lida" por item (botão com dot azul) e "marcar todas" no header (`PATCH /inbox/read-all`)
7. Toggle `mostrar lidas / só não-lidas` via `?show_read=1`
8. Navegação **J/K** seleciona item (ring azul + `aria-current`); **Enter** abre; **R** marca lida; **Shift+R** marca todas
9. **Empty state:** "Caixa de entrada vazia" (sem emoji) no modo padrão; "Nada na caixa." no modo show_read
10. Polling 30s + on-focus reload re-sincroniza badge/contador

## 5. Sub-componentes

- `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` — page Triage (otimismo + J/K + assign inline)
- `resources/js/Pages/ProjectMgmt/Inbox/Index.tsx` — page Inbox (agrupamento + J/K/R + markRead)
- Shared: `@/Components/shared/PageHeader`, `@/Components/shared/KpiGrid`, `@/Components/shared/KpiCard`
- `@/Components/board/badges` — `PRIORITY_BADGE` / `Priority` (tokens de prioridade canon, reuso do Board)
- `@/Components/ui/{select,card,button,badge}` — shadcn primitives
- `@/Layouts/AppShellV2` — layout-mãe; **dono do ⌘K global** (PMG-002) que monta `@/Components/CommandPalette`
- Lucide icons (`CheckCircle2`, `BellOff`, `UserX`, `HelpCircle`, `Layers`, `AtSign`, etc)

## 6. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Loading (Triage) | KpiCards + tabela aparecem após defer resolver | `Inertia::defer` pendente |
| Empty (Triage) | "Nada pra triar" + CheckCircle2 verde + dashed box | `visible.length === 0` |
| Otimista (Triage) | linha mostra valor novo na hora; select disabled | `assign()` em vôo (`pending`) |
| Erro (Triage) | banner âmbar inline (auto-dismiss 5s) + rollback | `PATCH /assign` !ok / rede |
| Success (Triage) | task some se `still_triage=false`; KPIs reconciliam | `assign()` ok |
| Loading (Inbox) | KpiCards + grupos aparecem após defer | `Inertia::defer` pendente |
| Empty (Inbox) | "Caixa de entrada vazia" (ou "Nada na caixa.") + BellOff | `inbox.length === 0` |
| Otimista (Inbox) | item fica `opacity-60` + check na hora | `markRead()` em vôo |
| Erro (Inbox) | banner âmbar inline + rollback do read | `PATCH /read` !ok / rede |
| Linha/item focado | `ring-1 ring-blue-400/60` + `aria-current="true"` | navegação J/K |

## 7. Atalhos de teclado

| Tecla | Ação (Triage) | Ação (Inbox) |
|---|---|---|
| `J` / `K` | Navegar próxima/anterior linha | Navegar próximo/anterior item |
| `Enter` | Abrir linha focada no Board (`?task=ID`) | Abrir task focada no Board (`?task=ID`) |
| `R` | — | Marcar item focado como lido |
| `Shift+R` | — | Marcar todas como lidas |
| `⌘K` / `Ctrl+K` | Command palette global (AppShellV2 / PMG-002) | idem |

> Atalhos seguem PT-01 (§Atalhos canônicos da Lista) e espelham a mecânica **inline** de `Board/Index.tsx` + `MyWork/Index.tsx` (handler `keydown` próprio com guard `isTyping`). ⌘K **não** é re-registrado aqui — é dono do AppShellV2.

## 8. Dependências de API/backend

| Rota | Controller | Retorno |
|---|---|---|
| `GET /project-mgmt/triage` | `TriageController@index` | `project`, `tasks` (defer), `kpis` (defer), `cycles`/`epics`/`owners` (defer), `filters` |
| `PATCH /project-mgmt/triage/{taskId}/assign` | `TriageController@assign` | `{ok, task, still_triage}` — valida priority/cycle/epic; reusa `TaskCrudService::update()` |
| `GET /project-mgmt/inbox` | `InboxController@index` | `inbox` (defer), `inbox_stats` (defer), `filters` |
| `PATCH /project-mgmt/inbox/{id}/read` | `InboxController@markRead` | `{ok, id, read_at}` — scoped `user_id` |
| `PATCH /project-mgmt/inbox/read-all` | `InboxController@markAllRead` | `{ok, marked}` — scoped `user_id` |

- **Triage `assign`** passa pela MESMA via que a tool MCP `tasks-update` (`TaskCrudService::update`) → gera `mcp_task_events` (assigned/field_updated) + dispara `McpInboxNotification` pro novo owner (paridade UI ↔ tool MCP).
- **Defer pattern:** props pesadas via `Inertia::defer` + closures memoizadas por chave (`buildTriagePayload(projectId)` / `buildInboxPayload(userId, showRead)`) pra não duplicar query quando 2 props vêm na mesma render (RUNBOOK-inertia-defer-pattern).

## 9. Multi-tenant + LGPD

- **Triage (Tier 0 — ADR 0093 + 0070):** `mcp_tasks` é **governança GLOBAL repo-wide** (sem `business_id` por design — ADR 0070). O escopo é **por-projeto** (`resolveProject`, default `COPI`), idêntico a Board/Backlog/MyWork — **não** por business. Board B2B multi-tenant entra só com 1º cliente.
- **Inbox (Tier 0 — ADR 0093):** `mcp_inbox_notifications` é **por-pessoa**. Isolamento via `user_id` (não `business_id`). Toda query base é `WHERE user_id = auth()->id()`; `markRead` faz `where('id',$id)->where('user_id', auth)` → **404** se a notificação for de outro usuário (não 403 — evita enumeração); `markAllRead` faz `update` escopado por `user_id`. **Não vaza entre usuários.**
- **PII:** sem PII de cliente nessas telas (são tasks/notificações de governança interna). `actor_name` vem de `users.first_name` (nome do time, não cliente). Body de notificação é texto de governança (sem CPF/CNPJ).
- **Activity log:** o `assign` registra evento via `TaskCrudService` (governança auditável); leitura de lista não é logada.

## 10. Smoke check pós-deploy

> ⚠️ **NÃO executado ainda** — PR #1940 segue DRAFT aguardando gate visual do Wagner (ADR 0107/0114). Chrome MCP off → telas não renderizadas/vistas. Os comandos abaixo são o roteiro pra quando o gate liberar.

```bash
# 1. HTTP smoke Triage (curl real — skill smoke-prod-evidence, NÃO declaração otimista)
curl -sv https://oimpresso.com/project-mgmt/triage -H "Cookie: laravel_session=<sess>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"ProjectMgmt/Triage/Index"

# 2. HTTP smoke Inbox
curl -sv https://oimpresso.com/project-mgmt/inbox -H "Cookie: laravel_session=<sess>" 2>&1 | grep -E "(HTTP/|component)"
# Esperado: HTTP/2 200 + "component":"ProjectMgmt/Inbox/Index"

# 3. Defer props (SPA partial reload)
curl -sv 'https://oimpresso.com/project-mgmt/triage' -H 'X-Inertia: true' -H 'X-Inertia-Partial-Data: kpis,tasks' \
  -H 'Cookie: laravel_session=<sess>' 2>&1 | jq '.props.kpis'

# 4. Permissão: usuário SEM copiloto.mcp.usage.all → 403
# 5. Tier 0 Inbox: sessão de user A não enxerga notificação de user B (markRead de id alheio → 404)
# 6. Smoke INTERATIVO (Chrome MCP, quando ligar): J/K move foco, Enter abre Board, ⌘K abre palette, atribuir owner some da lista
```

## 11. Receitas alternativas

### Receita "distribuir backlog órfão" (Triage)
1. `/project-mgmt/triage` — fila já filtrada por órfãs
2. J/K pra percorrer; em cada linha definir Dono + Prioridade nos selects
3. Opcional: jogar no Cycle ativo / Epic
4. Cada atribuição completa (owner + prio + saiu do backlog) faz a linha sumir → fila esvazia até "Nada pra triar"

### Receita "inbox da manhã" (Inbox)
1. `/project-mgmt/inbox` — só não-lidas por default, agrupadas por tipo
2. Começar pelo grupo **Menções** / **Atribuições** (mais acionáveis)
3. Enter (ou click) abre a task no Board e marca lida no caminho
4. "marcar todas" zera o resto que é só ruído de status

## 12. O que NÃO fazer

- ❌ NÃO editar título/descrição/estimativa na Triage (isso é DetailSheet no Board — Triage só atribui)
- ❌ NÃO mudar status arbitrário pela Triage (status muda no Board/Backlog)
- ❌ NÃO divergir da fila da tool `triage` / resposta da tool `my-inbox` (UI e tool devem dar a MESMA resposta)
- ❌ NÃO mostrar notificação de outro usuário na Inbox (Tier 0 — `where user_id`)
- ❌ NÃO recarregar a página inteira a cada ação (canon = partial reload `only:[...]`)
- ❌ NÃO usar `sessionStorage` (anti-padrão; estado efêmero fica em React state, persistente em `oimpresso.*` localStorage quando aplicável)
- ❌ NÃO re-registrar um listener ⌘K nas telas (duplica toggle — ⌘K é dono do AppShellV2)
- ❌ NÃO emoji em empty-state (AP — PT-BR limpo: "Nada pra triar" / "Caixa de entrada vazia")

## 13. Diagnóstico/Troubleshoot

| Sintoma | Causa provável | Fix |
|---|---|---|
| 403 ao abrir Triage/Inbox | Usuário sem `copiloto.mcp.usage.all` | Conceder permission (Spatie UPOS) |
| Triage vazia mas há tasks órfãs | `project` não resolveu (KEY errada / sem COPI) | Conferir `?project=` e `config('projectmgmt.default_project_key')` |
| Atribuir não persiste / 422 | priority/cycle/epic inválido | Ver resposta JSON `error`; priority deve ∈ `McpTask::PRIORITIES`, cycle/epic devem existir |
| Atribuir → 404 | `task_id` inexistente (TaskCrudService lança) | Recarregar lista (task pode ter sido removida) |
| Task não some após atribuir | ainda é órfã (faltou owner OU prio OU está backlog) | Completar os 3 critérios; `still_triage` só vira false quando todos OK |
| Inbox marca lida de outro user | (impossível) scope `user_id` retorna 404 | Comportamento esperado (Tier 0) |
| KPIs/lista travados | defer falhou OU exception no build payload | Ver `storage/logs/laravel.log` |
| ⌘K não abre | AppShellV2 não montado / conflito browser | Conferir layout wrapper + preventDefault no AppShellV2 |

## 14. Integrações cross-module

- **Modules/Jana** (MCP) — tools `triage` (fila órfãs) e `my-inbox` (notificações) que estas telas espelham; `TaskCrudService` (mesma via de mutação)
- **Modules/ProjectMgmt/Board** — destino do deep-link (`?task=ID` abre DetailSheet); reuso de `badges` (PRIORITY_BADGE)
- **Modules/ProjectMgmt/MyWork** — fonte do padrão J/K + inbox payload espelhado (`MyWorkController::buildInboxPayload`)
- **AppShellV2 / CommandPalette** — ⌘K global (PMG-002 / ADR 0100) + `SearchController`
- **(futuro) Centrifugo** — badge realtime da Inbox (ADR 0058), canal `inbox.{user_id}` — documentado, não nesta entrega

## 15. Refs

- [ADR 0070 — Jira-style task management](../../decisions/0070-jira-style-task-management-current-md-removed.md) (escopo governança, sem business_id)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0100 — ProjectMgmt UI Redesign 4 fases](../../decisions/0100-projectmgmt-ui-redesign.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual comparison gate F1.5/F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — Cowork loop formalizado (aprovação por SCREENSHOT)](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0058 — Centrifugo realtime](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)
- [SPEC.md](SPEC.md) — US-TR-301..308 (apêndice Onda 2 / SPEC-UI-FASE7)
- [SPEC funcional histórico — TaskRegistry/SPEC-UI-FASE7.md](../TaskRegistry/SPEC-UI-FASE7.md) — origem das US-TR-301..308
- Charters: [`Triage/Index.charter.md`](../../../resources/js/Pages/ProjectMgmt/Triage/Index.charter.md) · [`Inbox/Index.charter.md`](../../../resources/js/Pages/ProjectMgmt/Inbox/Index.charter.md)
- Visual comparison: [`projectmgmt-index-visual-comparison.md`](projectmgmt-index-visual-comparison.md) (status: draft — aguardando SCREENSHOT Wagner)
- Telas irmãs (em prod): [CHARTER-board.md](CHARTER-board.md), Backlog/MyWork/Activity/Burndown/Roadmap
- PR #1940 — code-complete Triage+Inbox (segue DRAFT)
