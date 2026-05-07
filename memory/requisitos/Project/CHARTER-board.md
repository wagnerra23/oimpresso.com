# Charter — `/project/{id}/board` (legacy UltimatePOS) — ⚠️ PIVOTADO

> ⚠️ **PIVOTADO 2026-05-07.** Wagner pediu redesign do `Modules/ProjectMgmt` (Jira-style time interno, em prod desde PRs #91/#92), não do `Modules/Project` legacy UltimatePOS — que será deletado em Fase 3.8.
>
> **Não usar como contrato de implementação.** Charter NOVO mira `Modules/ProjectMgmt` em `memory/requisitos/ProjectMgmt/CHARTER-board.md` (próxima sessão) — tela já existe em `resources/js/Pages/ProjectMgmt/Board/Index.tsx` (441 LoC); redesign é incremental.
>
> Este artefato fica preservado **como referência de critérios UX**: anatomia 4 regiões / 6 fluxos críticos / 8 estados / regras canônicas / anti-padrões. Pode ser reaproveitado parcialmente no Charter novo.
>
> Charter > Spec ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3). ADR pivot: [0099](../../decisions/0099-project-mwart-migration.md).

---

## 1. Identidade

- **URL canônica**: `/project/{project_id}/board`
- **Page React**: `resources/js/Pages/Project/Board.tsx` (a criar)
- **Controller**: `Modules/Project/Http/Controllers/Inertia/BoardController.php` (a criar)
- **Layout persistente**: `AppShellV2` (NÃO envolver em `<AppShell>` — `Board.layout` pattern, [preferência preservada](../../../CLAUDE.md))
- **Substitui Blade legacy**: nada (Board é tela nova; lista atual em `task/index.blade.php` continua funcionando até deprecation final)

## 2. Personas

### 2.1. Persona principal — Larissa (ROTA LIVRE, biz=4)

- **Papel**: gerente operacional gráfica; única usuária com login admin diário
- **Objetivo**: olhar o board de manhã e saber **o que está em produção, o que travou, o que precisa entregar hoje**
- **Hardware**: monitor 1280px (cuidado com larguras grandes)
- **Mobile**: ainda não — tablet/celular é P2
- **Frustração com Blade legacy**: lista de tasks sem visualização do fluxo, drag-drop ausente, status muda só via dropdown abrindo modal

### 2.2. Persona secundária — Operador interno (Wagner, Maíra, Felipe)

- **Papel**: executar/atualizar tasks de projetos internos do ERP
- **Objetivo**: arrastar card pra "Em progresso" ao começar, "Concluído" ao terminar; comentar quando trava
- **Atalhos esperados**: Cmd+K busca, Cmd+Enter envia comentário, Esc fecha modal

### 2.3. Não-persona (declarada explícita)

- **Cliente externo (gráfica → cliente final)**: NÃO usa esta tela. Customer view é P3 (capacidade #22) — outra rota `/p/{token}` read-only no futuro.

## 3. Anatomia (regiões da tela)

```
┌────────────────────────────────────────────────────────────────┐
│ TopBar: [breadcrumb: Projetos / {nome}] [tabs: Board│Backlog│… ]│ ◄ R1
│         [presence avatars]  [Edit] [⋯ menu]                    │
├────────────────────────────────────────────────────────────────┤
│ FilterBar: [Search🔍] [Priority▼] [Assignee▼] [Due▼] [Reset]    │ ◄ R2
│            (chips removíveis quando ativos)                    │
├────────────────────────────────────────────────────────────────┤
│  Not Started │ In Progress │ On Hold │ Cancelled │ Completed   │ ◄ R3 (Kanban)
│   [+ add]    │   [+ add]   │  [+add] │  [+add]   │   [+add]    │
│   ┌────────┐ │  ┌────────┐ │ ┌─────┐ │           │             │
│   │ card 1 │ │  │ card 4 │ │ │ c.5 │ │           │             │
│   ├────────┤ │  ├────────┤ │ └─────┘ │           │             │
│   │ card 2 │ │  │ card 6 │ │         │           │             │
│   └────────┘ │  └────────┘ │         │           │             │
│   • count: 2 │  • count: 2 │ • 1     │ • 0       │ • 3         │
└────────────────────────────────────────────────────────────────┘
                                      ┌───────────────────────────┐
                                      │ R4: Detail Sheet (right)  │
                                      │  ao clicar card           │
                                      │ ─ Title (editable)        │
                                      │ ─ [Status][Priority][Due] │
                                      │ ─ Assignees + Watch       │
                                      │ ─ Tabs:                   │
                                      │   Description (rich)      │
                                      │   Comments (thread)       │
                                      │   Time Logs               │
                                      │   Activity                │
                                      │   Subtasks (P1)           │
                                      └───────────────────────────┘
```

| Região | Nome | Slot type | Conteúdo |
|---|---|---|---|
| R1 | TopBar | static + dynamic | Breadcrumb + tabs (Board/Backlog/Roadmap/List) + presence + Edit/menu |
| R2 | FilterBar | dynamic | Search debounced + 3 dropdowns + reset chip |
| R3 | KanbanColumns | dynamic | 5 colunas fixas (status), drag-drop entre colunas |
| R4 | DetailSheet | overlay/sheet | Slide-in à direita ao clicar card; preserveState=true (não recarrega board) |

**Regra chave de UX:** R3 e R4 coexistem (board fica visível com sheet aberto). Não usar modal full-screen.

## 4. Slots de dados (props da Page)

```typescript
interface BoardPageProps {
  project: {
    id: number
    name: string
    project_id: string  // human ID tipo "PRJ-2026-001"
    status: ProjectStatus
    lead: { id, name, avatar_url } | null
    customer: { id, name } | null
    members: User[]
    start_date: string | null
    end_date: string | null
    description: string | null
    settings: Record<string, unknown>
  }

  tasks: ProjectTask[]  // todas as tasks do projeto, server filtra por business_id

  statuses: Record<ProjectStatus, string>     // status → label traduzido
  priorities: Record<Priority, string>        // priority → label

  can: {
    edit_project: boolean
    delete_project: boolean
    create_task: boolean
    edit_task: boolean
    delete_task: boolean
    manage_members: boolean
  }

  filters: {  // estado dos filtros vem da URL via Inertia
    search: string
    priority: Priority[] | null
    assignee_ids: number[] | null
    due: 'overdue' | 'today' | 'less_than_one_week' | null
  }

  // P2 — apenas quando Centrifugo presence ativo
  presence?: { users: User[]; channel: string }
}

type ProjectStatus = 'not_started'|'in_progress'|'on_hold'|'cancelled'|'completed'
type Priority = 'low'|'medium'|'high'|'urgent'
```

**Server-side query rule (Tier 0):** `ProjectTask::where('project_id', $project->id)` é redundante mas obrigatório — global scope `business_id` JÁ aplica via Eloquent (R-PROJ-001). Comentário `// SUPERADMIN: ...` proibido aqui.

## 5. Fluxos críticos (golden path + edge)

### 5.1. Golden path — Larissa abre o board pela manhã

1. URL `/project/12/board` carrega
2. Server: `BoardController::show(12)` → autoriza `$user->can('project.view_project')` → carrega `Project::with('tasks.members','tasks.comments_count','tasks.timeLogs:project_task_id,duration')->find(12)` scoped business_id
3. Inertia render `Project/Board` com props
4. Cliente: skeleton de 5 colunas <200ms; cards animam-in
5. Larissa identifica visualmente as tasks travadas (coluna `on_hold`) + tasks de hoje (badge vermelho overdue)

### 5.2. Drag-drop status change

1. Larissa segura card "Imprimir lona 6×3" da coluna `not_started` e arrasta pra `in_progress`
2. Frontend: optimistic UI move card imediatamente
3. Frontend: PATCH `/project-task/{id}/post-status` body `{status: 'in_progress'}` (rota existente em `Routes/web.php`)
4. Server: valida permission `project.edit_project` → atualiza coluna `status` → loga via Spatie ActivityLog → retorna 204
5. Frontend: confirma posição. Se 4xx/5xx → reverte + ToastError com retry
6. (P2 com Centrifugo) Server publica evento `project:{id}:task:moved` → outros usuários conectados veem o card migrar em tempo real

**Regra de atomicidade:** se outro usuário moveu o mesmo card primeiro (race), server retorna 409 Conflict + estado atual; cliente faz refetch silencioso da coluna afetada.

### 5.3. Quick add inline

1. Larissa clica `+ add` no footer da coluna `in_progress`
2. Coluna abre input inline ao invés de modal
3. Larissa digita "Acabamento da fachada hotel" + Enter
4. POST `/project-task` body `{project_id, status:'in_progress', subject, priority:'medium'}` otimista
5. Card aparece com spinner; ao 200 spinner some + foco volta no input pra task seguinte
6. Esc ou click-outside fecha o input

### 5.4. Click card → DetailSheet

1. Click no card abre R4 sheet à direita (animação slide ~150ms)
2. URL muda pra `/project/12/board?task=45` (preserveScroll, preserveState — sem reload)
3. Sheet carrega tabs: Description (default) | Comments | Time Logs | Activity | Subtasks
4. Esc ou click-outside fecha sheet (URL volta pra `/project/12/board`)

### 5.5. Edge — sem permissão

- Usuário sem `project.view_project` em qualquer projeto → redirect `/home` com flash error
- Usuário com view mas sem `edit_project` → cards são read-only (drag desabilitado), botões `+ add` escondidos
- Tentativa de drag por hack → server retorna 403 + frontend reverte + toast "Sem permissão"

### 5.6. Edge — projeto sem tasks (empty state)

- Coluna `not_started` mostra ilustração `<EmptyState>` com CTA "Criar primeira task"
- Outras colunas mostram texto cinza "Sem tasks aqui" sem CTA

## 6. Estados de UI

| Estado | Trigger | UI |
|---|---|---|
| Loading inicial | Inertia visit pendente | Skeleton: TopBar real + FilterBar real + 5 colunas com 3 placeholders |
| Empty global | `tasks.length === 0` | Centro do board: ilustração + CTA "Criar primeira task" |
| Empty coluna | tasks daquele status === 0 | Texto cinza "Sem tasks" no meio da coluna |
| Loading drag | otimista durante PATCH | Card com `opacity-70` + spinner pequeno no canto |
| Error drag | response 4xx/5xx | Reverte posição + ToastError com botão Retry |
| Conflict 409 | server diz "outro user moveu" | Refetch silencioso da coluna + toast informativo "Atualizado por {nome}" |
| Permission denied | server 403 | Toast "Sem permissão" + reverte |
| Connection lost (P2) | Centrifugo disconnect | Banner amarelo no topo "Sincronização pausada" + retry auto |

## 7. Regras de UI canônicas

### 7.1. Cores de prioridade (preservar do Blade legacy)

| Priority | Class Tailwind |
|---|---|
| low | `bg-green-100 text-green-700` |
| medium | `bg-yellow-100 text-yellow-700` |
| high | `bg-orange-100 text-orange-700` |
| urgent | `bg-red-100 text-red-700` |

### 7.2. Cores de status (consistência com Project::statusDropdown)

| Status | Header column class |
|---|---|
| not_started | `bg-gray-100 text-gray-700` |
| in_progress | `bg-blue-100 text-blue-700` |
| on_hold | `bg-yellow-100 text-yellow-700` |
| cancelled | `bg-red-100 text-red-700` |
| completed | `bg-emerald-100 text-emerald-700` |

### 7.3. Avatar stack

- Mostra até 3 avatares; resto vira `+N` com tooltip listando nomes
- Tamanho card: 24×24px; tamanho sheet: 32×32px

### 7.4. Due date badge

- `overdue`: pill vermelho "Atrasada N dias"
- `today`: pill laranja "Hoje"
- `<7 dias`: pill amarelo "Vence em N dias"
- `>7 dias`: data simples cinza

### 7.5. Atalhos teclado (P1)

| Combo | Ação |
|---|---|
| Cmd/Ctrl+K | Abre Search global (capacidade #15) |
| Cmd/Ctrl+Enter | Envia comentário no DetailSheet |
| Esc | Fecha DetailSheet |
| C | Cria task na coluna ativa (foco no input) |
| / | Foca no Search |

## 8. Limites de escopo (NÃO fazer no MVP)

> Anti-scope explícito. Charter ortogonal evita feature creep ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 SoC brutal).

- ❌ Sprints/Cycles (P2; gráfica não é dev iterativo, escopo a confirmar)
- ❌ Roadmap/Gantt timeline (P2; tela separada `/project/roadmap`)
- ❌ Dependencies bloqueadoras (P2)
- ❌ Subtasks (P1; entra na fase 2 com DetailSheet)
- ❌ Custom fields (P2)
- ❌ Customer view (P3)
- ❌ Burndown charts (P3)
- ❌ Mobile responsive otimizado (P2 — desktop-first em monitor 1280px Larissa)
- ❌ WIP limits por coluna (P3)
- ❌ Templates de projeto (P2)
- ❌ Bulk edit (P1; entra após drag-drop e detail estarem maduros)

## 9. Métricas de sucesso (validar pós-MVP)

| Métrica | Como medir | Meta |
|---|---|---|
| Larissa abre `/project/.../board` ≥3×/semana | Server log `inertia:visit` agregado | Alcançado em 30 dias após deploy |
| Tempo médio drag-drop status (UX) | Front telemetria `task.moved.duration_ms` | <300ms p95 |
| Taxa de erro nos PATCHs status | `mcp_audit_log` ou laravel.log filter | <0.5% das tentativas |
| % de tasks com pelo menos 1 TimeLog | Query `count(distinct project_task_id) / count(tasks)` | ≥40% após 60d |
| ROTA LIVRE migra de Blade legacy | Compara hits `/project/{id}` (Blade) vs `/project/{id}/board` | Board >70% após 30d |

## 10. Anti-padrões a evitar (lições do projeto)

- ❌ **Modal full-screen ao clicar card** — quebra contexto do board. Usar Sheet à direita.
- ❌ **Reload completo ao mudar filtro** — Wagner exigiu cache/estado preservado em telas Inertia ([preference_cache_estado_preservado](../../../CLAUDE.md)). Use `router.get` com `only:[...]` ou `useForm forceFormData:false`.
- ❌ **Drag-drop sem optimistic UI** — espera de 300ms+ por response do servidor mata UX (Linear é benchmark).
- ❌ **Toast de sucesso pra cada drag** — poluente; sucesso é silencioso, só erro grita.
- ❌ **Cores do shadcn padrão** — usar tokens do design system oimpresso (atual ainda em construção; manter Tailwind colors mas docar tokens depois).
- ❌ **`window.location.reload()`** — proibido ([feedback memória](../../../CLAUDE.md)). Sempre Inertia.
- ❌ **`Inertia::render` sem `can: [...]`** — UI sem dados de permission vira fonte de bugs visuais (botão aparece e dá 403 ao clicar).

## 11. Histórico de revisão

- `2026-05-07` — [W] — Charter inicial — fase 0 discovery do redesign Jira-like

## 12. Referências

- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — capacidades-alvo do mercado
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3 Charter > Spec
- [ADR 0099 — Project MWART Migration](../../decisions/0099-project-mwart-migration.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- Skill `mwart-quality` — 9 pré-flight checks pra criar `.tsx` em `Modules/<X>/`
- Skill `cockpit-runbook` — gerar RUNBOOK desta tela após MVP estabilizar
- [Linear method](https://linear.app/method) — referência de fluidez UX
- [Atlassian Design System — Board](https://atlassian.design/components/board)
