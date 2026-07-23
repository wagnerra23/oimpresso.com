---
id: requisitos-project-mgmt-charter-board
---

# Charter — `/project-mgmt/board` (Kanban Jira-like — em prod)

> **Charter > Spec** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3).
> Contrato vivo da página **que JÁ EXISTE** em `resources/js/Pages/ProjectMgmt/Board/Index.tsx` (441 LoC, em prod desde 2026-05-04 PR #91).
> Quando entregue Fase S4 charter-fetch tool, este arquivo migra como `Board.charter.md` ao lado do `.tsx`.
> ADR mãe redesign: [0100](../../decisions/0100-projectmgmt-ui-redesign.md).
> Capacidades cobertas: ver [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — itens P0 #1, #2, #5, #6 + P1 #7-#15.
> Gap analysis: [INVENTARIO.md](INVENTARIO.md).

---

## 1. Identidade

- **URL canônica**: `/project-mgmt/board` (rename pra `/project/board` em Fase 3.9)
- **Page React**: [`resources/js/Pages/ProjectMgmt/Board/Index.tsx`](../../../resources/js/Pages/ProjectMgmt/Board/Index.tsx) (441 LoC, em prod)
- **Controller**: [`Modules/ProjectMgmt/Http/Controllers/BoardController.php`](../../../Modules/ProjectMgmt/Http/Controllers/BoardController.php) — métodos `index()` + `updateStatus(PATCH)`
- **Layout persistente**: `AppShellV2` via `Page.layout` pattern (preferência preservada do CLAUDE.md)
- **Escopo deste Charter**: redesign **incremental** das gaps. NÃO substitui Page existente — refina.

## 2. Personas

### 2.1. Persona principal — Wagner [W] (L2 admin, owner)

- **Papel**: dono do projeto, líder técnico, quem mais usa o board
- **Objetivo**: acompanhar progresso do cycle ativo + saber o que está bloqueado + arrastar suas tasks pra "doing"/"done"
- **Hardware**: monitor 1280px+ desktop primário; ocasionalmente celular em trânsito (P3 mobile)
- **Frustração atual**: drag-drop de card NÃO PERSISTE (só draggable, droppable não implementado); sem Cmd+K pra buscar; sem presence pra ver quem mais está olhando

### 2.2. Personas secundárias — Maíra [M], Felipe [F], Eliana [E], Luiz [L]

- **Papel**: contributors do time interno (dev + suporte + financeiro)
- **Objetivo**: ver tasks owned + atualizar status quando começam/terminam + comentar quando travam
- **Atalhos esperados**: J/K (next/prev card), E/A (advance/back status), C (criar), / (focar search), Esc (fechar sheet), ? (overlay help)

### 2.3. Não-persona

- **Cliente externo (ROTA LIVRE / Larissa)**: NÃO usa `/project-mgmt/*`. Time interno only. **Modules/Project legacy** (gestão de projetos de cliente) foi DELETADO na Fase 3.8.

## 3. Anatomia (regiões da tela — estado atual + gap)

```
┌────────────────────────────────────────────────────────────────────┐
│ R1: TopBar (já tem)                                                │
│   [breadcrumb: Project Mgmt / Board]   [Cycle ▼] [presence] [⋯]   │ ◄ presence = GAP P1
├────────────────────────────────────────────────────────────────────┤
│ R2: KPI Strip (já tem)                                             │
│   [Total: 47] [Doing: 12] [Review: 5] [Blocked: 2] [P0 aberto: 3]  │
├────────────────────────────────────────────────────────────────────┤
│ R3: FilterBar (já tem 7 dimensões)                                 │
│   [Search🔍] [Cycle▼] [Epic▼] [Owner▼] [Component▼] [Reset]        │
├────────────────────────────────────────────────────────────────────┤
│ R4: Kanban Columns (5 status) — drag implementado parcial          │
│   Backlog │ Todo  │ Doing │ Review │ Done                          │
│   ┌────┐  │ ┌──┐  │ ┌──┐  │       │ ┌──┐                          │
│   │ #1 │  │ │#3│  │ │#5│  │       │ │#7│ ← drop NÃO PERSISTE (GAP) │
│   ├────┤  │ └──┘  │ └──┘  │       │ └──┘                          │
│   │ #2 │  │       │       │       │                               │
│   └────┘  │       │       │       │                               │
└────────────────────────────────────────────────────────────────────┘
                                    ┌──────────────────────────────┐
                                    │ R5: Detail Sheet (GAP P1)    │
                                    │  ─ Description editable      │
                                    │  ─ Tabs: Comments / Time /   │
                                    │           Subtasks / Watchers│
                                    │           / Activity / Deps  │
                                    └──────────────────────────────┘
```

| Região | Nome | Status | Gap |
|---|---|---|---|
| R1 | TopBar | ✅ implementado | + presence avatars (P1) |
| R2 | KPI Strip | ✅ implementado | — |
| R3 | FilterBar | ✅ 7 dimensões + URL state | + Saved views backend (P1) |
| R4 | Kanban Columns | 🟡 cards draggable, droppable não implementado | **drag-drop atomic + 409 conflict + revert (P0)** |
| R5 | Detail Sheet | ❌ não existe | **criar Detail Sheet completo (P1)** |
| — | Command Palette Cmd+K | ❌ não existe | **Cmd+K global (P0)** |

## 4. Slots de dados (props da Page — estado atual)

```typescript
interface BoardPageProps {
  project: { id: number; key: string; name: string } | null;
  cycle: CycleHeader | null;
  kanban: Record<Status, BoardTask[]>;  // 5 colunas pre-grouped server-side
  kpis: { total: number; doing: number; review: number; blocked: number; p0_aberto: number };
  columns: Status[];
  epics: EpicOption[];
  cycles: CycleOption[];
  owners: string[];
  filters: {
    project: string | null;
    cycle: number | null;
    epic: number | null;
    component: number | null;
    owner: string | null;
    search: string | null;
  };

  // GAP P1 — quando Centrifugo presence implementado
  presence?: { users: User[]; channel: string };
}

type Status = 'backlog' | 'todo' | 'doing' | 'review' | 'done' | 'blocked' | 'cancelled';
```

**Server-side query rule (Tier 0 multi-tenant):** todas queries scoped por `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Permission `copiloto.mcp.usage.all` (pattern UltimatePOS).

## 5. Fluxos críticos (golden path + edge — incluindo gaps a fechar)

### 5.1. Golden path — Wagner abre o board pela manhã

1. URL `/project-mgmt/board` → load com cycle ativo + KPIs + tasks agrupadas por status
2. Skeleton <200ms até primeiro paint; cards animam-in
3. Wagner identifica visualmente: P0 aberto (urgente), Blocked (4), Doing (em fluxo)
4. Atalho `/` foca search; digita "nfse" → filtra tasks NFSe via URL `?search=nfse`

### 5.2. Drag-drop status change — **GAP P0 a fechar**

1. Wagner segura card "US-NFSE-005 Job assíncrono" da coluna `todo` e arrasta pra `doing`
2. **Estado atual**: card volta pra origem (drop não persiste — droppable column não implementado)
3. **Estado desejado**:
   - Frontend: optimistic UI move imediatamente
   - Frontend: `PATCH /project-mgmt/board/{taskId}/status` body `{status: 'doing', expected_updated_at: ts}`
   - Server: valida permission + global scope + optimistic-lock; atualiza `mcp_tasks.status` + insere `mcp_task_events` row
   - Frontend: confirma posição. Se 4xx/5xx → reverte + ToastError
   - 409 Conflict: refetch silencioso da coluna afetada + toast informativo "Atualizado por {nome}"

### 5.3. Click card → Detail Sheet — **GAP P1 a fechar**

1. **Estado atual**: click no card mostra apenas pop-up básico (ou nada)
2. **Estado desejado**: Sheet slide-in à direita (~150ms anim), URL `?task=US-NFSE-005`, tabs:
   - Description (rich-text editable)
   - Comments (thread + @mentions com autocomplete)
   - Subtasks (parent_task_id tree + completion %)
   - Time Logs (interno, P2)
   - Activity (Spatie ActivityLog formatted)
   - Watchers (follow/unfollow + lista)
   - Dependencies (blocks/blocked_by + grafo simples)

### 5.4. Cmd+K Search Global — **GAP P0 a fechar**

1. Wagner aperta Cmd+K em qualquer tela ProjectMgmt
2. Command palette abre overlay ~150ms
3. Busca por: tasks (id/title/description), epics, cycles, projects, owners
4. Setas navegam, Enter abre, Esc fecha
5. Multi-tenant scoped automaticamente via `mcp_tasks.business_id`

### 5.5. Atalhos Keyboard J/K/E/A — **GAP P1 a fechar**

Documentado no top do `Board/Index.tsx` mas NÃO implementado:

| Combo | Ação | Status |
|---|---|---|
| J | Next card | ❌ não impl |
| K | Prev card | ❌ não impl |
| E | Advance status (todo→doing→review→done) | ❌ não impl |
| A | Back status | ❌ não impl |
| C | Criar task na coluna ativa | ❌ não impl |
| / | Focar Search | ❌ não impl |
| Cmd+K | Command palette | ❌ não impl |
| Esc | Fechar Sheet | 🟡 dependente do gap 5.3 |
| ? | Overlay help shortcuts | ❌ não impl |

### 5.6. Edge — sem permissão `copiloto.mcp.usage.all`

- Usuário sem perm → middleware UltimatePOS bloqueia rota → redirect /home com flash error
- Usuário com perm mas sem `tasks.write` (granular) → cards são read-only (drag desabilitado), botões `+ add` escondidos

### 5.7. Edge — race condition concorrente (drag simultaneous)

- Usuário A move card "US-NFSE-005" todo→doing
- Usuário B (em outra aba) move o mesmo card todo→review (~mesmo momento)
- Server detecta `expected_updated_at` divergente → retorna 409
- Frontend B reverte + toast "Atualizado por A" + refetch da coluna

## 6. Estados de UI

| Estado | Trigger | UI |
|---|---|---|
| Loading inicial | Inertia visit pendente | Skeleton: TopBar real + KPI strip placeholder + 5 colunas com 3 cards placeholders |
| Empty global | sem cycle ativo OU sem tasks | Centro do board: ilustração + CTA "Criar primeira task" ou "Selecionar cycle" |
| Empty coluna | tasks daquele status === 0 | Texto cinza "Sem tasks" no meio da coluna |
| Loading drag | otimista durante PATCH | Card com `opacity-70` + spinner pequeno no canto |
| Error drag | response 4xx/5xx | Reverte posição + ToastError com botão Retry |
| Conflict 409 | server diz "outro user moveu" | Refetch silencioso da coluna + toast informativo "Atualizado por {nome}" |
| Permission denied | server 403 | Toast "Sem permissão" + reverte |
| Connection lost (P1 Centrifugo) | disconnect | Banner amarelo no topo "Sincronização pausada" + retry auto |

## 7. Regras de UI canônicas (já implementadas — preservar)

### 7.1. Cores de prioridade (`resources/js/Components/board/badges.ts`)

| Priority | Class Tailwind |
|---|---|
| p0 | `bg-red-100 text-red-700` |
| p1 | `bg-orange-100 text-orange-700` |
| p2 | `bg-yellow-100 text-yellow-700` |
| p3 | `bg-blue-100 text-blue-700` |

### 7.2. Cores de status

| Status | Header column class |
|---|---|
| backlog | `bg-gray-100 text-gray-700` |
| todo | `bg-slate-100 text-slate-700` |
| doing | `bg-blue-100 text-blue-700` |
| review | `bg-purple-100 text-purple-700` |
| done | `bg-emerald-100 text-emerald-700` |
| blocked | `bg-red-100 text-red-700` |
| cancelled | `bg-gray-100 text-gray-500` |

### 7.3. URL state-driven (já implementado — preservar)

- Filters via `?cycle=&epic=&owner=&search=` (compartilhável + back button funciona)
- localStorage `oimpresso.board.{cycle|epic|owner|search}` como cache de sessão (per-user/per-browser)
- Migrar pra backend `mcp_views` quando Saved views (P1) entrar — substitui localStorage

## 8. Limites de escopo (NÃO fazer no MVP do redesign)

> Anti-scope explícito. Charter ortogonal evita feature creep ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 SoC brutal).

- ❌ **Reorder dentro da coluna** (ordem por priority desc é suficiente — P2 se time pedir)
- ❌ **Mobile responsive otimizado** (P3 — desktop-first em monitor 1280px+)
- ❌ **Public share link** (P3)
- ❌ **Workload view** (P2 — tela separada `/workload`)
- ❌ **Custom fields** (P2 — exige migration nova mcp_custom_fields)
- ❌ **Templates de cycle** (P2)
- ❌ **Automation rules** (P2 — engine separado)
- ❌ **Dark mode toggle** (P3)
- ❌ **Roadmap timeline drag** (P3 — tela /roadmap separada)

## 9. Métricas de sucesso (validar pós-redesign Fase 1)

| Métrica | Como medir | Meta |
|---|---|---|
| Wagner usa drag-drop ≥10×/dia | Frontend telemetria `board.task.moved` event | Alcançado em 7 dias após deploy |
| Tempo médio drag-drop status (UX) | Front telemetria `task.moved.duration_ms` | <300ms p95 |
| Taxa de erro nos PATCHs status | `mcp_audit_log` filter | <0.5% das tentativas |
| Cmd+K usado ≥5×/dia per user ativo | Frontend telemetria `palette.opened` event | ≥80% dos users em 14 dias |
| Atalhos J/K/E usados ≥1×/sessão por dev | Frontend telemetria `hotkey.fired` event | ≥50% das sessões em 14 dias |

## 10. Anti-padrões a evitar (lições do projeto)

- ❌ **Modal full-screen ao clicar card** — quebra contexto do board. Usar Sheet à direita.
- ❌ **Reload completo ao mudar filtro** — Wagner exigiu cache/estado preservado em telas Inertia. Use `router.get` com `only:[...]` ou `useForm forceFormData:false`.
- ❌ **Drag-drop sem optimistic UI** — espera de 300ms+ por response do servidor mata UX (Linear é benchmark <100ms).
- ❌ **Toast de sucesso pra cada drag** — poluente; sucesso é silencioso, só erro grita.
- ❌ **`window.location.reload()`** — proibido. Sempre Inertia.
- ❌ **`Inertia::render` sem `can: [...]`** — UI sem dados de permission vira fonte de bugs visuais.
- ❌ **Lib drag-drop pesada (@hello-pangea/dnd, react-beautiful-dnd)** — package.json hoje sem essa dep. Usar HTML5 native primeiro; só adicionar lib se reorder dentro de coluna virar requisito (P2+).
- ❌ **Atalhos teclado conflitando com browser** — testar combos vs Chrome/Firefox/Safari (Cmd+K Chrome abre URL bar; preventDefault obrigatório).

## 11. Histórico de revisão

- `2026-05-07` — [W+C] — Charter inicial pós-pivot do PR #197. Mira tela em prod, não greenfield.

## 12. Referências

- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — capacidades-alvo do mercado (24 capacidades)
- [INVENTARIO.md](INVENTARIO.md) — gap analysis ✅🟡❌
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3 Charter > Spec
- [ADR 0100 — ProjectMgmt UI Redesign](../../decisions/0100-projectmgmt-ui-redesign.md)
- [ADR 0070 — Jira-style task management](../../decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0058 — Centrifugo+FrankenPHP](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- Skill `mwart-quality` — pré-flight checks pra `.tsx`
- Skill `cockpit-runbook` — gerar RUNBOOK desta tela após redesign Fase 1 estabilizar
- [Linear method](https://linear.app/method) — benchmark de fluidez UX
- [Atlassian Design System — Board](https://atlassian.design/components/board)
- [SCOPE.md ProjectMgmt](../../../Modules/ProjectMgmt/SCOPE.md)
- SPEC funcional histórico US-TR-NNN: [`memory/requisitos/TaskRegistry/SPEC.md`](../TaskRegistry/SPEC.md)
