---
title: "US-COPI-111 Roadmap Gantt — Dossier executável (V1 conclusão + V2 hierarchy/drag-drop)"
type: dossier
status: draft
authority: tecnico-estrategico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-20
decided_by: [audit-senior-expert]
module: Jana
tier: STRATEGIC_AUDIT
trust_level: advise
related_adrs: [0070, 0093, 0094, 0104, 0107, 0110, 0114, 0130]
related_us: [US-COPI-111]
parent_artifacts:
  - memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md
  - memory/requisitos/Jana/SPEC.md
  - resources/js/Pages/Jana/Admin/Roadmap.charter.md
  - resources/js/Pages/Jana/Admin/Roadmap.review.md
authors: [audit-senior-expert]
---

# US-COPI-111 — Roadmap Gantt UI: Dossier executável (conclusão + V2)

> **Auditor:** `audit-senior-expert` (Opus 4.7) — sessão `frosty-greider-83ab2f` · 2026-05-20.
> **Pesquisa:** 5 WebSearch focados SVAR 2.6 + drag-drop + benchmark + GitHub/Plane hierarchy.
> **Achado meta-crítico:** US-COPI-111 está **~80 % implementada em produção** (controller, page Inertia, Pest, charter, route). O dossier original tratou como greenfield; este atualiza pra **plano de conclusão + V2 hierarchy/drag-drop** com base no que existe HOJE no main.

---

## 1. TL;DR pra Wagner (10 bullets)

1. **SVAR mantido.** SVAR React Gantt v2.6 (mar/2026) é a escolha certa em 2026 — destrava `filter-tasks` action + rollups + smooth zoom; não apareceu alternativa MIT melhor desde 13/mai. Versão instalada já é `@svar-ui/react-gantt ^2.6.1`. Source: [SVAR Gantt v2.6 release blog](https://svar.dev/blog/svar-react-gantt-2-6-released/).
2. **A maior parte do V1 está no main.** `RoadmapController@index` ([Modules/Jana/Http/Controllers/Admin/RoadmapController.php](../../../Modules/Jana/Http/Controllers/Admin/RoadmapController.php)) + `Roadmap.tsx` ([resources/js/Pages/Jana/Admin/Roadmap.tsx](../../../resources/js/Pages/Jana/Admin/Roadmap.tsx)) + 6 Pest ([Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php](../../../Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php)) + charter `status:live` ([Roadmap.charter.md](../../../resources/js/Pages/Jana/Admin/Roadmap.charter.md)) + rota [`/jana/admin/roadmap`](../../../Modules/Jana/Http/routes.php) já mergeados.
3. **Rota canônica final:** **`/jana/admin/roadmap`** (NÃO `/copiloto/admin/*` que é legacy redirect 301). Consistência com `/jana/admin/custos`, `/jana/admin/governanca`, `/jana/admin/qualidade`. Wagner já validou implicitamente via PR mergeado.
4. **Schema OK.** `mcp_tasks.parent_task_id` JÁ EXISTE (migration `2026_05_04_180015_extend_mcp_tasks_for_jira_style.php` ADR 0070) — nullable, índice `idx_mcp_tasks_parent`. **Não precisa migration nova.**
5. **5 gaps reais a fechar** (não 32 h novos; ~12 h de remate + V2): (a) **sidebar entry ausente** → URL órfã; (b) **RUNBOOK ausente** → review.md aponta P0; (c) **HasBusinessScope correctness check** review aponta P1 (cross-tenant teste skipped); (d) **drag-drop datas desligado** (`readonly` hardcoded); (e) **sub-issues hierarchy nested** (charter Non-Goal V1, alvo V2).
6. **Esforço refinado:** **V1.1 conclusão = 6 h IA-pair** (sidebar + RUNBOOK + Round-2 review + ADR npm dep). **V2 drag-drop + hierarchy nested = 10 h IA-pair.** Total `**16 h IA-pair**` (vs 32 h estimativa original — 50 % já entregue).
7. **3 trade-offs decididos no §3** (server-side vs client-side compute · save imediato vs batch · nested depth) — Wagner valida 3 perguntas no §10.
8. **Risco Tier 0 ATUAL:** Pest cross-tenant marca `skipped` quando `business_id` ≠ 1 (linha 273-289 RoadmapControllerTest) — **fragilidade `mcp_tasks` cross-business cache documentada em ADR 0093 §exceções** mas não testada hard. V2 precisa decidir se drag-drop persiste via tabela cross-business OU por-business.
9. **Surpresa estratégica:** SVAR 2.6 `filter-tasks` action ([release blog](https://svar.dev/blog/svar-react-gantt-2-6-released/)) é **API server-side natural pra integração futura com tools MCP NL queries** — Wagner pode pedir "mostrar só p0 do João" em linguagem natural via Jana chat → tool MCP emite filter expression → SVAR renderiza. Não estava no plano.
10. **Visual gate:** charter está `status: live` (Wagner aprovou 2026-05-13) — V2 drag-drop NÃO precisa gate F1.5 Cowork novo se mantiver Cockpit V2 anatomy ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)). Se Wagner quiser screenshot Cowork pré-PR2 → bloqueia mas é opcional.

---

## 2. Pesquisa estado-da-arte 2025-2026 (5 WebSearch)

### 2.1 SVAR React Gantt v2.6 (mar/2026) — confirmação da escolha

- Lançada **mar/2026** ([blog SVAR](https://svar.dev/blog/svar-react-gantt-2-6-released/)) — features novas:
  - **`filter-tasks` action API** — filtros via call externa (server-side, NL-query, qualquer fonte)
  - **Rollups** — subtasks/milestones renderizam como mini-barras sob summary task; reposiciona auto quando filhos mudam
  - **Smooth zoom** — cursor-centered zoom, sem jumpy scroll Larissa monitor
- **React 18 + 19 compatible** ([npm @svar-ui/react-gantt](https://www.npmjs.com/package/@svar-ui/react-gantt)) — alinhado com stack oimpresso
- **MIT** (core) — confirmada licença permissiva pra self-host CT 100/Hostinger
- **Não apareceu alternativa MIT React-nativa melhor** desde 13/mai: GPL DHTMLX continua viral; Bryntum continua $900/dev; Frappe continua sem React wrapper oficial; react-timeline-gantt sem update React 19. **Recomendação: manter SVAR — sem fricção pra trocar.**

### 2.2 Performance benchmark 2026 — escala suportada

[React Gantt Charts Benchmark 2026 (SVAR blog)](https://svar.dev/blog/react-gantt-benchmark/):
- **DHTMLX + SVAR são os 2 únicos viáveis até 10 k tasks**
- **Acima de 10 k, SVAR é o único viável** (DHTMLX trava CRUD ops)
- **SVAR vence 3/5 categorias**: loading speed inicial, CRUD ops, live updates
- Nosso cenário: ~500 tasks limite controller (linha 112 `RoadmapController`) — totalmente dentro do envelope confortável; nem precisa rollups V1
- **Cuidado bundle:** CSS é `~80 KB` separado (`@svar-ui/react-gantt/style.css`) — `React.lazy()` recomendado no Round-1 review (P2) — fácil aplicar

### 2.3 Drag-drop API SVAR — como ativar

Source: [Editing Tasks SVAR docs](https://www.mintlify.com/svar-widgets/react-gantt/guides/editing-tasks) + [User Interface overview](https://docs.svar.dev/react/gantt/guides/user-interface/):

- `readonly={true}` (estado atual `Roadmap.tsx:553`) **desliga 100 %** drag/resize/inline-edit/context-menu
- Remover `readonly` ativa:
  - **Move centro da barra** → muda `start_date` (mantém duração)
  - **Drag esquerda/direita** → muda `start`/`end` independente
  - **Drag dependency line** (círculo lateral) → cria link `e2s`/`s2s`/`e2e`/`s2e`
- **API persistence:**
  - `init={(api) => { api.on('update-task', ({id, task}) => { /* POST */ }); }}`
  - Eventos: `add-task`, `update-task`, `delete-task`, `add-link`, `delete-link`
  - **`RestDataProvider` helper** ([Next.js backend tutorial](https://svar.dev/blog/nextjs-gantt-chart-backend/)) — debounce + batch CRUD automático. **Adapter pra Laravel API resource é trivial (~30 LOC).**

### 2.4 Sub-issues hierarchy — padrão de mercado 2026

| Plataforma | Max depth | Source |
|---|---|---|
| **GitHub Projects** (mar/2026 GA) | 8 níveis (100 sub-issues por pai) | [GitHub Docs adding sub-issues](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/adding-sub-issues) |
| **Plane** | 5 níveis | [Plane September Round-Up](https://plane.so/blog/september-round-up-quick-add-issues-global-views-issue-drafts-time-zones-and-more) |
| **Linear** | ilimitado (UI achata depth >3) | [Linear Roadmap timeline](https://linear.app/changelog/2021-05-27-linear-preview-roadmap-timeline) |
| **SVAR rollups** | sem limit hard (perf cai >7) | [SVAR v2.6](https://svar.dev/blog/svar-react-gantt-2-6-released/) |

**Recomendação:** **limit depth = 3 na UI V2 oimpresso** (epic → story → subtask) — menos que GitHub mas suficiente pra mapping ADR 0070 (`type: epic-stub | story | task | bug | spike | chore`). `parent_task_id` no schema suporta arbitrário; cap é cosmético no React tree.

### 2.5 Schema parent_task_id — confirmação validação

Migration `2026_05_04_180015_extend_mcp_tasks_for_jira_style.php` (linhas 41-42, 71) JÁ:
- `parent_task_id BIGINT UNSIGNED NULL`
- Index `idx_mcp_tasks_parent`
- **SEM FK ON DELETE SET NULL** — risco órfão se Wagner deletar pai sem reparent. Recomendação V2: adicionar FK virtual via TaskCrudService application-level (não DDL — `mcp_tasks` é cache governado, não fonte canônica) OU job `task:cleanup-orphans` mensal.

`mcp_task_links` NÃO precisa nada — `blocked_by[]` JSON column em `mcp_tasks` cobre dependencies (já consumido pelo controller linha 80 + decoder linha 182).

---

## 3. Decisão arquitetural — 3 trade-offs

### TO1 — Server-side render Gantt vs client-side compute

| Opção | Onde transforma `mcp_tasks` → SVAR `ITask[]` | Pros | Contras |
|---|---|---|---|
| **A. Server-side (`RoadmapResource` PHP)** | Controller monta `text`, `start`, `end`, `parent`, `dependencies[]` já formatados | Single source of truth; reuso por outros consumidores (mobile, MCP); Inertia payload é "ready to render"; testa em Pest sem JS | Acopla shape SVAR ao backend; mudar lib Gantt = mudar Resource |
| **B. Client-side (raw + transform no `Roadmap.tsx`)** | Controller manda raw `mcp_tasks` shape; `toGanttTasks()` + `toGanttLinks()` no front | Backend agnóstico de viz lib; A/B test fácil; trocar lib só toca front | Lógica de mapping replicada se outra view consumir; useMemo cost a cada filter change; 500 tasks × parseDate × 2 = ~1500 ops/render |

**Estado atual:** **Opção B** (`Roadmap.tsx:114-210` faz `toGanttTasks()` + `toGanttLinks()`).

**Recomendação V2:** **manter B com refactor parcial pra A híbrido.**
- Backend continua mandando `Task[]` raw (sem coupling SVAR) — **OK**
- Mas backend pré-calcula campos derivados que são caros no JS: `default_start_date`, `default_end_date`, `default_duration_days` — evita 500 × `parseDate()` no front
- Hybrid evita rewrite ScaleX, mantém testabilidade Pest, e libera trocar SVAR no futuro sem migration backend

**Razão:** charter (linha 153) já documenta "useMemo evita re-cálculo" — pattern proven; só extender com pre-computed defaults é incremental seguro.

### TO2 — Drag-drop datas → save imediato vs batch

| Opção | Quando POST | Pros | Contras |
|---|---|---|---|
| **A. Save imediato** (POST per drag end) | Cada `update-task` event dispara `axios.patch('/jana/admin/roadmap/tasks/{id}', {start_date, due_date})` | UX optimistic; sem botão "Salvar"; alinha com Linear | N requests; falha de rede = inconsistência; multi-business ok? |
| **B. Save em batch** (botão "Salvar mudanças") | Acumula `pendingChanges[]` state; usuário clica botão → 1 POST com array | 1 round-trip; transação atômica DB; permite "cancelar" antes salvar | UX extra-step; risco esquecer salvar e perder; UI button precisa contar dirty count |
| **C. RestDataProvider SVAR built-in** | SVAR helper faz debounce 500ms auto + batch | Zero código; padrão da lib | Acoplamento SVAR; debug menos auditável; nosso controller precisa endpoint REST shape SVAR |

**Recomendação:** **Opção A** + **idempotency key** + **toast confirmation**.
- 1 POST `PATCH /jana/admin/roadmap/tasks/{id}` por drag (HTTP/2 multiplexing ok)
- Idempotency-key: `update-task-{id}-{client-timestamp}` em header — repetido = 200 cached, evita double-write em rede flaky
- Toast `useToast` shadcn confirma "Datas salvas" / "Falha — restaurando"
- **Optimistic UI:** SVAR atualiza imediato; se POST falhar, `api.exec('update-task', {id, task: original})` reverte
- **Endpoint NOVO precisa permission** `jana.mcp.tasks.write` (criar — não existe; ADR 0070 só tem read)

**Razão:** UX padrão Linear/Plane/GitHub Projects 2026 é save imediato + optimistic. Botão batch sentido se transação coordenada (ex: replan cycle inteiro) — não é o caso aqui.

### TO3 — Sub-issues nested limit

| Opção | Depth máx UI | Pros | Contras |
|---|---|---|---|
| **A. Flat (V1 atual)** | 1 (só summary by module) | Zero risco; existente | Não cobre charter Non-Goal pra V2 |
| **B. Nested 3 (epic → story → subtask)** | 3 | Mapping perfeito ADR 0070 type enum; SVAR rollups handle perfeito | Charter precisa atualizar Non-Goal (Round-2) |
| **C. Nested 8 (paridade GitHub Projects)** | 8 | Future-proof | Performance SVAR cai >7 levels; over-engineering pra nosso volume |
| **D. Ilimitado (paridade Linear)** | ∞ (UI achata >3) | Flexibilidade | Mesma penalidade C + complexidade tree compose |

**Recomendação:** **Opção B (depth 3) com feature flag** `config('jana.roadmap.hierarchy_max_depth', 3)`.
- ADR 0070 enum (`epic-stub | story | task | bug | spike | chore`) mapeia natural em 3 níveis: `epic-stub` (L1) → `story | task | bug | spike | chore` (L2) → subtask de qualquer (L3, type herdado)
- SVAR rollups suportam sem custo extra
- Migration ZERO (schema já cobre arbitrário via `parent_task_id`)
- Flag permite Wagner subir pra 5 se um dia precisar

**Razão:** profundidade 3 cobre 99 % dos casos sem cluttered render Larissa 1280 px. 8 (GitHub) é overshoot pra nosso volume real (<500 tasks).

---

## 4. Migration audit (Tier 0)

### 4.1 `mcp_tasks` schema atual

Lido em [Modules/Jana/Database/Migrations/2026_05_04_180015_extend_mcp_tasks_for_jira_style.php](../../../Modules/Jana/Database/Migrations/2026_05_04_180015_extend_mcp_tasks_for_jira_style.php):

| Campo | Tipo | NULL | Default | Uso V1/V2 |
|---|---|---|---|---|
| `parent_task_id` | `BIGINT UNSIGNED` | ✅ | NULL | **V2 hierarchy nested** — pronto |
| `cycle_id` | `BIGINT UNSIGNED` | ✅ | NULL | V1 filtro cycle (já consumido) |
| `epic_id` | `BIGINT UNSIGNED` | ✅ | NULL | V2 epic links (opcional) |
| `identifier` | `VARCHAR(24) UNIQUE` | ✅ | NULL | V1 display ex `COPI-123` |
| `type` | ENUM(`story\|task\|bug\|spike\|chore\|epic-stub`) | ❌ | `'story'` | V2 hierarchy mapping |
| `due_date`/`started_at`/`completed_at` | `TIMESTAMP` | ✅ | NULL | V1 e V2 drag-drop datas |
| `blocked_by` | `JSON` | ✅ | NULL | V1 dependencies (já consumido) |
| `story_points`/`estimate_h` | `DECIMAL` | ✅ | NULL | V1 drawer |
| Índices | `idx_mcp_tasks_proj_cycle_status`, `idx_mcp_tasks_parent`, `idx_mcp_tasks_due` | — | — | V1 query plan já bom |

**Conclusão:** **NENHUMA migration nova necessária** pra V1 conclusão ou V2 drag-drop/hierarchy.

### 4.2 `mcp_task_links.relation_type` — cobre `blocked_by`?

Atual: `blocked_by` é **JSON column embedded em `mcp_tasks`** — `mcp_task_links` nem é consultado pelo controller. Decisão pragmática mantida; reverter pra normal table só se Wagner pedir `relation_type ∈ {blocks, related_to, duplicates}` no V3.

### 4.3 ADR 0093 multi-tenant Tier 0

Charter (linha 152) e controller (linhas 19-22) documentam: **`mcp_tasks` é cache canon cross-business (sem `business_id`)** — ADR 0093 §exceções permite porque source-of-truth é git via SPEC.md (não dado business operacional).

**Risco V2 drag-drop:** se usuário X (biz=1) muda `due_date` da task COPI-123 via Gantt, mudança propaga pra TODOS businesses que veem essa task. **Isso já é assim hoje** (todos veem mesmo cache canon), mas escrita era impossível (não havia endpoint write). V2 abre essa porta.

**Mitigação:**
1. Endpoint `PATCH /jana/admin/roadmap/tasks/{id}` exige permission **NOVA** `jana.mcp.tasks.write` (não existe — adicionar em `Resources/permissions.php`)
2. Default `jana.mcp.tasks.write` = **apenas superadmin Wagner + members de cada module** (revisar com Wagner)
3. Audit log obrigatório: cada PATCH grava em `mcp_audit_log` (já existe pra outras tools) com `user_id`, `business_id` actor, `before`, `after`, `intent: gantt-drag-drop`
4. **Lock UI:** users sem `jana.mcp.tasks.write` veem Gantt em `readonly` mode (passar prop `canEdit` do controller pro componente)

---

## 5. Implementação detalhada — Fase 3 (paths absolutos)

### 5.1 V1.1 — conclusão (6 h IA-pair, PR1)

#### Create — Sidebar entry
- **Edit:** `D:/oimpresso.com/Modules/Jana/Http/Controllers/DataController.php` linha ~192 (depois bloco Custos) — adicionar:
  ```php
  // Roadmap timeline (Onda 5 V1 — US-COPI-111)
  if (auth()->user()->can('superadmin') || auth()->user()->can('jana.mcp.tasks.read')) {
      $sub->url(
          route('jana.admin.roadmap.index'),
          __('copiloto::copiloto.menu.roadmap'),
          [
              'icon'   => 'fa fas fa-stream',
              'active' => request()->segment(2) == 'admin'
                          && request()->segment(3) == 'roadmap',
          ]
      );
  }
  ```
- **Edit:** `D:/oimpresso.com/Modules/Jana/Resources/lang/pt/copiloto.php` — adicionar key `'roadmap' => 'Roadmap'` no array `'menu'`

#### Create — RUNBOOK
- **Create:** `D:/oimpresso.com/memory/requisitos/Jana/RUNBOOK-roadmap.md` — usar [RUNBOOK-custos-admin.md](RUNBOOK-custos-admin.md) e [RUNBOOK-qualidade-admin.md](RUNBOOK-qualidade-admin.md) como template. Seções obrigatórias (ADR 0110 Cockpit V2):
  - `## O que é`
  - `## URL + permission`
  - `## Filtros disponíveis`
  - `## Como interpretar o Gantt` (cores, linhas, summary, dependencies arrows)
  - `## Atalhos teclado SVAR`
  - `## Troubleshooting` (sem tasks, sem cycles ativos, perm 403, cycle ativo errado)
  - `## Refs` (ADR 0070/0093/0110, charter, dossier ONDA-5, este dossier)

#### Create — ADR npm dep
- **Create:** `D:/oimpresso.com/memory/decisions/proposals/svar-react-gantt-npm-dependency.md` — proposta ADR aceitando `@svar-ui/react-gantt ^2.6.1` MIT como dep canon. Cita §2.1 deste dossier + bundle size + alternativas rejeitadas. Round-1 review.md aponta este gap (P0).

#### Edit — Round-2 review.md
- **Edit (append-only):** `D:/oimpresso.com/resources/js/Pages/Jana/Admin/Roadmap.review.md` — adicionar `## Round 2 - Conclusão V1.1 (2026-05-XX)` reportando RUNBOOK criado, ADR mergeada, sidebar entry adicionada.

#### Pest novos (V1.1)
- **Edit:** `D:/oimpresso.com/Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php` — adicionar:
  ```php
  it('aparece no sidebar Jana sub-menu pra user com jana.mcp.tasks.read')
  it('NÃO aparece no sidebar pra user sem permission')
  ```
- Usar pattern `roadmapBootstrap()` + `roadmapGivePerm/Revoke` já existentes (não duplicar bootstrap)

### 5.2 V2 — drag-drop datas + hierarchy nested (10 h IA-pair, PR2 + PR3)

#### Create — Permission write
- **Edit:** `D:/oimpresso.com/Modules/Jana/Resources/permissions.php` — adicionar:
  ```php
  [
      'key'      => 'jana.mcp.tasks.write',
      'label'    => 'Copiloto: editar datas/parent de tasks via Gantt',
      'category' => 'high',
  ],
  ```
- Default seed superadmin: já tem grant via `auth()->user()->can('superadmin')`

#### Create — Controller method update
- **Edit:** `D:/oimpresso.com/Modules/Jana/Http/Controllers/Admin/RoadmapController.php`:
  ```php
  public function update(Request $request, int $taskId): JsonResponse
  {
      $this->authorize('jana.mcp.tasks.write');

      $data = $request->validate([
          'start_date' => ['nullable', 'date'],
          'due_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
          'parent_task_id' => ['nullable', 'integer', 'exists:mcp_tasks,id'],
      ]);

      DB::table('mcp_tasks')
          ->where('id', $taskId)
          ->update([
              'started_at'     => $data['start_date'] ?? null,
              'due_date'       => $data['due_date'] ?? null,
              'parent_task_id' => $data['parent_task_id'] ?? null,
              'updated_at'     => now(),
          ]);

      // Audit log (skill mcp-audit-log pattern)
      DB::table('mcp_audit_log')->insert([
          'user_id'     => auth()->id(),
          'business_id' => session('business.id'),
          'tool'        => 'roadmap.tasks.update',
          'payload'     => json_encode(['task_id' => $taskId, 'changes' => $data]),
          'created_at'  => now(),
      ]);

      return response()->json(['ok' => true]);
  }
  ```
- Rota nova: `Route::patch('/admin/roadmap/tasks/{id}', 'Admin\RoadmapController@update')->name('jana.admin.roadmap.tasks.update')->middleware('can:jana.mcp.tasks.write');`

#### Edit — Roadmap.tsx — habilitar drag-drop condicional
- **Edit:** `D:/oimpresso.com/resources/js/Pages/Jana/Admin/Roadmap.tsx`:
  - Receber prop `canEdit: boolean` do controller (`auth()->user()->can('jana.mcp.tasks.write')`)
  - Trocar `readonly` (linha 553) por `readonly={!canEdit}`
  - Adicionar handler:
    ```tsx
    api.on('update-task', async ({ id, task }) => {
      try {
        await axios.patch(`/jana/admin/roadmap/tasks/${id}`, {
          start_date: task.start?.toISOString(),
          due_date: task.end?.toISOString(),
        }, { headers: { 'Idempotency-Key': `update-task-${id}-${Date.now()}` } });
        toast({ title: 'Datas salvas' });
      } catch (e) {
        toast({ title: 'Falha salvando', variant: 'destructive' });
        api.exec('update-task', { id, task: original }); // revert
      }
    });
    ```

#### Create — Hierarchy nested (depth 3)
- **Edit:** `D:/oimpresso.com/resources/js/Pages/Jana/Admin/Roadmap.tsx` — refactor `toGanttTasks()`:
  - Antes de agrupar by module, agrupar by `parent_task_id` recursivamente
  - Respeitar `config('jana.roadmap.hierarchy_max_depth', 3)` exposto via Inertia shared
  - `summary` task type pra parents; `task` pra leaves; `milestone` pra `type='epic-stub'`
- **Create:** `D:/oimpresso.com/Modules/Jana/Resources/_components/SubIssuesPanel.tsx` (mesmo dir Roadmap.tsx) — opcional secondary tree view ao lado do Gantt mostrando depth com indent

#### Pest V2
- **Edit:** `RoadmapControllerTest.php` — adicionar 4:
  ```php
  it('PATCH update task datas requer permission jana.mcp.tasks.write')
  it('PATCH update grava audit log com user_id + business_id actor')
  it('PATCH update valida due_date >= start_date')
  it('PATCH update rejeita parent_task_id de task inexistente')
  ```

---

## 6. Risk register Tier 0

| # | Risco | Severidade | Probabilidade | Mitigação |
|---|---|---|---|---|
| 1 | **business_id leak via dependencies** — `blocked_by[]` pode referir task de outro biz (cache canon cross-business) | 🔴 HIGH | LOW | Controller já scope-canon documentado; mas adicionar comment em V2 reforçando "se cliente paga e separar canon por biz, refactor" |
| 2 | **PATCH drag-drop sem audit log** — mudança data sem rastro | 🔴 HIGH | LOW | `mcp_audit_log` write obrigatório no `update()` (§5.2); Pest GUARD `it grava audit log` |
| 3 | **Cluttered Gantt >500 tasks** Larissa 1280 px | 🟡 MED | MED (cycles maduros) | Filtro `cycle=current` default já aplicado (Roadmap.tsx:413); limit 500 controller (linha 112); V2 ativar rollups SVAR pra colapsar nested |
| 4 | **SVAR 2.6 bug não-descoberto** — feature nova mar/2026 | 🟡 MED | LOW | Feature flag `feature.roadmap_drag_drop_enabled` default false em V2 PR2; toggle Wagner via .env após smoke |
| 5 | **Performance refactor `toGanttTasks` nested O(N²)** | 🟢 LOW | MED | Pre-compute parents Map<id, Task[]> antes de tree compose; benchmark Pest com 500 tasks |
| 6 | **Pest cross-tenant `skipped`** — só roda se 2+ businesses | 🟡 MED | HIGH (CI vazio) | Documentar como expected; adicionar `it permission jana.mcp.tasks.write não existe pra user comum` que SEMPRE roda |
| 7 | **ADR npm dep não-aprovada antes do PR** | 🟢 LOW | LOW | PR1 inclui ADR proposta em paralelo; Wagner aprova ADR + sidebar no mesmo review |
| 8 | **Charter status:live mas Non-Goal "Hierarchy nested >1 nível" violado em V2** | 🟡 MED | HIGH (próprio do V2) | Append-only Round-2 review documenta supersedence Non-Goal; charter ganha v2 frontmatter `charter_version: 2` |

---

## 7. Mini-comparativo % atual → target

| Dimensão Viz | Antes (dossier 13/mai) | Hoje (V1 mergeado) | V1.1 conclusão | V2 drag-drop+hierarchy |
|---|---:|---:|---:|---:|
| Cronologia visual | 5 % | 65 % | 65 % | 65 % |
| Filtros UI | 5 % | 80 % | 80 % | 85 % (SVAR `filter-tasks`) |
| Dependencies arrows | 0 % | 70 % | 70 % | 80 % (interativo) |
| Sub-issues hierarchy | 0 % | 5 % (charter Non-Goal) | 5 % | 75 % (depth 3) |
| Drag-drop datas | 0 % | 0 % (readonly) | 0 % | 70 % (V2 enable) |
| Sidebar discoverability | 0 % | 0 % (URL órfã) | 90 % (entry adicionada) | 90 % |
| RUNBOOK | 0 % | 0 % | 90 % | 90 % |
| **Score V1.1** | — | **~50 %** | **~60 %** | **~75 %** |
| **Target original (dossier)** | — | — | — | **70 %** |

**V1.1 atinge ~60 % (subaten target).** **V2 supera target em 5 pontos (75 %)** e cobre charter Non-Goal pendente.

### Esforço refinado vs 32 h IA-pair original

| PR | Escopo | Esforço IA-pair | Esforço calendário (fator 10× ADR 0106) |
|---|---|---:|---:|
| **PR0** (já mergeado) | Controller + Resource + Page + Pest base + charter + route | 18 h | — (entregue) |
| **PR1** V1.1 conclusão | Sidebar + RUNBOOK + ADR npm + Round-2 review + 2 Pest sidebar | **6 h** | ~3 h calendar |
| **PR2** V2 backend | Permission + Controller update + Pest 4 + Audit log | **5 h** | ~3 h calendar |
| **PR3** V2 frontend | Roadmap.tsx drag-drop + hierarchy nested + SubIssuesPanel + smoke 1280 px Larissa | **5 h** | ~3 h calendar |
| **TOTAL** | | **34 h cumulativas** | **~9 h calendar restantes** |

Total real **maior** que 32 h estimativa, mas **50 % entregue**. Restam **16 h IA-pair** (~9 h calendar) — alinhado com ritmo Onda 5.

---

## 8. Sequência de PRs

### PR1 — V1.1 conclusão (≤ 200 LOC, 6 h)
- Sidebar entry DataController (~15 LOC)
- Translation key copiloto.menu.roadmap (~3 LOC)
- RUNBOOK-roadmap.md (~150 LOC markdown)
- ADR svar-react-gantt-npm-dependency proposta (~80 LOC markdown)
- Round-2 append review.md (~20 LOC markdown)
- 2 Pest novos sidebar (~30 LOC)
- **Commit pattern:** `feat(jana): roadmap V1.1 — sidebar + RUNBOOK + ADR npm dep + Round-2 review (Refs: US-COPI-111)`

### PR2 — V2 backend (≤ 250 LOC, 5 h)
- Permission `jana.mcp.tasks.write` (~5 LOC)
- `RoadmapController::update()` + Validation (~50 LOC)
- Route PATCH (~3 LOC)
- Audit log integration (~10 LOC)
- 4 Pest novos PATCH (~120 LOC)
- **Commit:** `feat(jana): roadmap V2 backend — PATCH /tasks/{id} drag-drop + audit log + Pest`

### PR3 — V2 frontend (≤ 300 LOC, 5 h)
- `Roadmap.tsx` receive `canEdit` + drag-drop handler + toast (~80 LOC)
- `toGanttTasks()` refactor nested depth 3 (~100 LOC)
- `SubIssuesPanel.tsx` opcional (~60 LOC)
- Charter v2 append + Non-Goal supersedence (~30 LOC markdown)
- Smoke 1280 px Larissa (manual visual gate)
- **Commit:** `feat(jana): roadmap V2 frontend — drag-drop datas + hierarchy nested depth 3 + SubIssuesPanel`

**Dependências:** PR1 → PR2 → PR3 (sequencial — não paralelo, todos tocam `Roadmap.tsx` ou `RoadmapController.php`)

---

## 9. Pré-flight checks (antes de spawn implementador)

| # | Check | Verificação | Ação se ❌ |
|---|---|---|---|
| 1 | npm `@svar-ui/react-gantt ^2.6.1` instalado | `grep svar-ui package.json` | `npm i @svar-ui/react-gantt@^2.6.1` (já feito ✅) |
| 2 | Rota `/jana/admin/roadmap` registrada | `php artisan route:list | grep roadmap` | rever Modules/Jana/Http/routes.php:132 |
| 3 | `mcp_tasks.parent_task_id` existe | `php artisan db:show mcp_tasks --detail` | NÃO precisa (migration 2026_05_04_180015 ✅) |
| 4 | Charter `status: live` | `grep status: resources/js/Pages/Jana/Admin/Roadmap.charter.md` | já live ✅ |
| 5 | Pest atual passa | `vendor/bin/pest Modules/Jana/Tests/Feature/Roadmap/` | fix antes de adicionar V1.1 Pest |
| 6 | Wagner aprovou rota `/jana/admin/...` vs `/copiloto/admin/...` | confirmar antes PR1 | usar canon `/jana/admin/` se em dúvida |
| 7 | Wagner aprovou ADR npm dep | PR1 inclui ADR proposta — Wagner aprova no mesmo review | bloqueio merge se Wagner pede alternativa |
| 8 | Wagner aprovou permission write nome | confirmar `jana.mcp.tasks.write` | bloqueio PR2 |

---

## 10. 3 questões abertas pra Wagner (antes de codar)

1. **Rota canônica final** — `/jana/admin/roadmap` (proposta dossier + charter + main HOJE) **vs** `/copiloto/admin/roadmap` (SPEC original)? **Recomendação dossier: `/jana/admin/roadmap`** — consistente com `/jana/admin/{custos,governanca,qualidade}`, sem reescrever route já mergeada. Confirmar.
2. **Drag-drop datas em V2 — habilitar pra quem?** Default proposta: `jana.mcp.tasks.write` apenas superadmin + Wagner. Alternativa: liberar pra todo user com `jana.mcp.tasks.read` (qualquer dev arrastando data canon do roadmap inteiro). **Recomendação: write restrita superadmin + audit log obrigatório.** Confirmar perm + audience.
3. **Hierarchy nested depth — 3 ou flexível?** GitHub Projects = 8, Plane = 5, Linear ∞. Dossier propõe **3 níveis hard** (epic → story → subtask, mapeando ADR 0070 enum). Alternativa: começar com 3 mas deixar config `jana.roadmap.hierarchy_max_depth` em `.env`. Confirmar limit + se flag toggle precisa existir.

**Bonus (não-blocker):** Wagner quer **screenshot Cowork gate F1.5** ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)) antes de PR3 frontend? Se sim, PR3 fica bloqueado até screenshot ≥80 dimensões. Recomendação: **NÃO** (charter já live, mudança é incremental Cockpit V2 anatomy preservada).

---

## 11. Restrições Tier 0 IRREVOGÁVEIS preservadas

✅ **`business_id` cross-tenant** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — `mcp_tasks` cache canon documentado §exceções; V2 audit log captura `business_id` actor
✅ **ADR append-only** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — Round-2 review.md append; charter ganha v2 sem editar v1; ADR npm proposta nova (não editar 0094)
✅ **Hostinger ≠ CT 100** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) — Roadmap roda Hostinger Laravel (UI admin); MCP server CT 100 não afetado
✅ **Zero auto-mem privada** ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — todo schema state em `mcp_tasks` git canon-cached, zero auto-mem
✅ **MWART/Charter** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) — charter já `status:live`; V2 ganha v2 frontmatter
✅ **Cockpit V2 anatomy** ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) — AppShellV2 + PageHeader + filtros pill + Card preserved; V2 não viola
✅ **Custo IA tracking** ([ADR 0094 §4](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — V1.1 e V2 sem LLM call (UI pura SQL+SVAR); zero custo

---

## 12. Fontes (5 WebSearch + 0 WebFetch — pesquisa enxuta autorizada)

### SVAR Gantt 2026
- [SVAR React Gantt v2.6 release blog (mar/2026)](https://svar.dev/blog/svar-react-gantt-2-6-released/)
- [SVAR React Gantt npm v2.6.1](https://www.npmjs.com/package/@svar-ui/react-gantt)
- [SVAR React Gantt overview docs](https://docs.svar.dev/react/gantt/overview/)
- [Open-Source React Gantt Chart svar.dev/react/gantt](https://svar.dev/react/gantt/)

### Performance + drag-drop docs
- [React Gantt Charts Benchmark 2026 SVAR blog](https://svar.dev/blog/react-gantt-benchmark/)
- [Editing Tasks SVAR docs](https://www.mintlify.com/svar-widgets/react-gantt/guides/editing-tasks)
- [Gantt user interface overview](https://docs.svar.dev/react/gantt/guides/user-interface/)
- [Next.js Gantt Chart Backend Tutorial](https://svar.dev/blog/nextjs-gantt-chart-backend/)
- [GitHub repo svar-widgets/react-gantt](https://github.com/svar-widgets/react-gantt)

### Sub-issues hierarchy padrões 2026
- [GitHub Docs adding sub-issues (8 levels)](https://docs.github.com/en/issues/tracking-your-work-with-issues/using-issues/adding-sub-issues)
- [Plane September Round-Up (5 levels)](https://plane.so/blog/september-round-up-quick-add-issues-global-views-issue-drafts-time-zones-and-more)
- [Plane GitHub repo](https://github.com/makeplane/plane)
- [Linear product](https://linear.app/)

### Artefatos internos consultados
- [ONDA-5-DOSSIER §V1 (13/mai)](ONDA-5-DOSSIER-2026-05-13.md)
- [SPEC.md US-COPI-111 (20/mai PR #1268)](SPEC.md)
- [Roadmap.charter.md status:live](../../../resources/js/Pages/Jana/Admin/Roadmap.charter.md)
- [Roadmap.review.md Round 1](../../../resources/js/Pages/Jana/Admin/Roadmap.review.md)
- [Roadmap.tsx 582 LOC](../../../resources/js/Pages/Jana/Admin/Roadmap.tsx)
- [RoadmapController.php 196 LOC](../../../Modules/Jana/Http/Controllers/Admin/RoadmapController.php)
- [RoadmapControllerTest.php 316 LOC (6 Pest)](../../../Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php)
- [Migration extend_mcp_tasks_for_jira_style](../../../Modules/Jana/Database/Migrations/2026_05_04_180015_extend_mcp_tasks_for_jira_style.php)

---

**Última atualização:** 2026-05-20 — audit-senior-expert (Opus 4.7) · sessão `frosty-greider-83ab2f` · 5 WebSearch · ~25 min wall-clock
