---
page: /jana/admin/roadmap
component: resources/js/Pages/Jana/Admin/Roadmap.tsx
owner: wagner
status: live
last_validated: "2026-05-13"
approved_by: wagner
parent_module: Jana
related_adrs: [70, 93, 94, 110]
tier: B
charter_version: 1
---

# Page Charter — /jana/admin/roadmap

> **Status:** draft (Onda 5 V1, agent V1 implementador). Wagner aprovará Non-Goals + Anti-hooks pra promover pra `status: live`. Cobre o gap V1 do dossier `memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md` (Viz score 5% → 70%).

---

## Mission

Visualizar cronologicamente os **cycles ativos** + **tasks (US-*)** do MCP como Gantt interativo, agrupado por módulo, com filtros por cycle/owner/priority/module e dependency arrows via `blocked_by[]`. Substitui visão markdown de `tasks-list` quando o usuário (Wagner principal, eventualmente Felipe/Maiara) precisa de visão temporal — "o que vence essa semana, o que tá bloqueando o que".

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb (Cockpit V2 canon)
- Gantt SVAR React MIT v2.6.x renderiza tasks como barras + summary tasks por módulo
- Filtros (Select pills): Cycle ativo (default) / Owner / Priority P0-P3 / Módulo
- Botão "Limpar filtros" aparece só quando algum filtro está aplicado
- Click numa tarefa abre Sheet lateral (`/Components/ui/sheet`) com:
  - identifier + title + módulo + status + priority badges
  - description completa
  - estimativa (story_points OU estimate_h)
  - due_date / completed_at
  - lista de `blocked_by[]`
  - **link `mcp://tasks-detail?task_id=...`** + snippet `tasks-detail task_id:US-XXX-NNN` pra Wagner abrir no Claude Code/Cursor
- Scales semana + dia (default Larissa 1280px friendly)
- Summary task per-module com range cobrindo mínimo até máximo de tasks do grupo
- Progress visual: done=100%, doing/review=50%, demais=0%
- Dependency arrows e2s (end-to-start) renderizadas a partir de `blocked_by[]`
- Multi-tenant: query `mcp_tasks` é cache canon cross-business (ADR 0093 §exceções repo-wide). Permission `jana.mcp.tasks.read` controla acesso.
- Limite 500 tasks por render (anti-cluttered Larissa 1280px) — se exceder, filtros refinam.

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ **Editar task no Gantt** (drag-drop reschedule, rename inline) — leitura pura V1; mutação fica em [task-detail MCP](mcp://tasks-detail) ou skill task management
- ❌ **Criar task** no Gantt — usar `tasks-create` MCP
- ❌ **Hierarchy nested >1 nível** (sub-issues 8 níveis estilo GitHub Projects mar/2026) — V1 = só summary-by-module, V2 espera `mcp_tasks.parent_task_id` populado + tree view
- ❌ **Cycle múltiplo simultâneo** (overlay de 2 cycles lado-a-lado) — V1 = 1 cycle por vez
- ❌ **Export PDF/PNG do Gantt** — backlog Wagner
- ❌ **Compartilhar timeline via link público** — risco governança, sem caso de uso interno
- ❌ **Editar dependency arrow** (drag pra criar/remover blocked_by) — mutação fica em tools MCP
- ❌ **Notificação real-time** via Centrifugo de tasks updated — V1 = snapshot no render; usuário recarrega manual
- ❌ **Mostrar custo $ por task** — vai pra `/jana/admin/custos`
- ❌ **Time tracking ao vivo** (cronômetro Pomodoro) — fora do escopo MCP-task
- ❌ **Auto-suggestion de reordenação** baseada em prazos — IA roadmap re-plan vira US-COPI-XXX separada

---

## UX Targets

- p95 first-paint < 1500ms com 200 tasks (Gantt SVAR ~80KB gzip + payload Inertia)
- Filtro aplicado: Inertia partial reload < 600ms (preserve-state)
- Click numa task → drawer aparece < 100ms (cliente já tem o objeto)
- Cabe em monitor 1280px (Larissa) sem scroll horizontal no chrome (Gantt interno scroll horizontal aceito)
- 0 erros JS console
- Tipografia canon ADR 0110: h1 = page header, badge 12px, label 11-12px
- Cores semânticas: rose (P0), amber (P1), sky (P2), muted (P3); emerald (status done), amber (status doing/review)
- Dark mode respeita tokens canônicos (SVAR Gantt CSS pode precisar override custom V2)

---

## UX Anti-patterns

- ❌ Modal full-screen pra detalhe de task (canon = Sheet lateral inline)
- ❌ Color crua `bg-(red|green|blue)-500` em badges (canon = `tone="success|info|warning|danger"` com `*-500/15` + texto-foreground)
- ❌ Loading skeleton infinito (canon = mensagem "Sem tasks no filtro atual" se vazio)
- ❌ Gantt com cells de 1h (canon = day step mínimo; senão vira ilegível Larissa)
- ❌ Animação de barra Gantt durante render inicial (canon = render estático, transição só em filtro reaply)
- ❌ Drawer com tabs internas mais de 2 (canon = info linear, link MCP pra deep-dive)
- ❌ Auto-scroll horizontal no Gantt ao abrir (canon = aterrissa na semana atual ou cycle.start_date)

---

## Automation Hooks

- Endpoint `RoadmapController::index()` carrega cycles (20 mais recentes) + tasks com filtros aplicados + listas distintas de owners/modules pros dropdowns
- Inertia partial reload preserva state ao trocar filtro (URL com query params canônicos)
- Click `select-task` no SVAR Gantt API → atualiza `selectedTask` state local, abre Sheet
- `useMemo` em `toGanttTasks()` + `toGanttLinks()` evita re-cálculo a cada keystroke
- Permission gate `can:jana.mcp.tasks.read` no controller construtor
- Audit log: não-aplicável V1 (leitura pura, sem mutação)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não escreve no banco no render (leitura pura — `SELECT`-only)
- ❌ Não modifica `mcp_tasks` (canon = git source-of-truth via SPEC.md; UI só read)
- ❌ Não dispara jobs ao filtrar (Inertia partial, sem fila)
- ❌ Não acessa data de outro `business_id` quando feature for promovida a per-business (V1: cache canon cross-business)
- ❌ Não persiste filtro em backend (state vive na URL via query params)
- ❌ Não consulta MCP server externo no render (toda data vem do banco local)
- ❌ Não loga task_id em audit_log no click (drawer é cliente puro)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5/V2)

```php
// Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php (V1 cobre subset)

it('renders Inertia component Jana/Admin/Roadmap')
it('returns 403 for user without jana.mcp.tasks.read')
it('redirects to login when unauthenticated')
it('props include cycles, tasks, filters, owners, modules')
it('filters tasks by cycle_id query param')
it('filters tasks by owner query param')
it('filters tasks by priority enum (p0-p3)')
it('limits result set to 500 tasks (anti-cluttered)')
it('decodes blocked_by[] JSON column safely')
it('does not write to mcp_tasks on render (read-only)')      // GUARD anti-hook
it('does not call external MCP server on render')             // GUARD anti-hook
```

---

## Comparáveis canônicos (15 dimensões — `mwart-comparative` V4)

- **Linear Roadmap timeline** (referência principal — densidade + filtros pill)
- **Plane Open Source Gantt** (referência cycles/iterations)
- **GitHub Projects Hierarchy GA mar/2026** (sub-issues 8 levels — V2 target)
- **Excluir:** DHTMLX Gantt enterprise (UI sobrecarregada), MS Project (overkill desktop), Asana Timeline (foco em milestones, não tasks)

---

## Decisões técnicas-chave

- **Lib:** `@svar-ui/react-gantt` v2.6.1 MIT (decisão dossier §V1.escolha-tecnica)
- **Rota:** `/jana/admin/roadmap` (consistência com `/jana/admin/custos`, `/jana/admin/governanca`, etc — não `/copiloto/admin/roadmap` legacy)
- **Permission:** `jana.mcp.tasks.read` (existente, ADR 0070) — sem criar permission nova V1
- **Multi-tenant:** `mcp_tasks` é cache canon cross-business; sem filtro por `business_id` (ADR 0093 §exceções repo-wide — git canon)
- **Schema sub-issues:** `mcp_tasks.parent_task_id` já existe (migration 2026_05_04_180015) — pronto pra hierarchy V2
- **CSS:** `@svar-ui/react-gantt/style.css` importado direto. Override de dark mode fica em CSS custom em V2 se necessário.

---

## Refs

- [ONDA-5-DOSSIER §V1](../../../../../memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md)
- [ADR 0070 Jira-style tasks](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0093 Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição V2](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0110 Cockpit V2](../../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [SVAR Gantt 2.4 MIT release](https://medium.com/@SvarWidgets/svar-gantt-2-4-a-modern-gantt-chart-library-for-react-svelte-under-the-mit-license-ae62f36a5dde)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-13 | agent-V1 (audit-senior-expert spawn) | Charter draft criado simultâneo ao componente, ANTES de visual gate F1.5. Aguarda Wagner aprovar Non-Goals + Anti-hooks pra promover pra `status: live`. |
