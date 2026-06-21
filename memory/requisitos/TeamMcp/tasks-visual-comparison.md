---
slug: teammcp-tasks-visual-comparison
title: "TeamMcp — Comparativo visual da tela Tasks (Backlog + Quadro)"
type: visual-comparison
module: TeamMcp
status: approved
approved_by: wagner
approved_at: 2026-06-16
date: 2026-06-16
canon_reference: forja-cowork (forja-page.jsx + forja-data.jsx + forja-page.css)
blade_source: "N/A — tela já é Inertia (re-skin DS v6, não Blade→Inertia)"
inertia_target: resources/js/Pages/team-mcp/Tasks/Index.tsx
pr_branch: feat/forja-pr1-tasks-reskin
---

# TeamMcp — Comparativo visual · tela **Tasks** (Backlog + Quadro)

> **F1.5 do MWART V4** ([skill `mwart-comparative`](../../../../.claude/skills/mwart-comparative/SKILL.md)). PR-1 da onda **Forja** — *re-skin DS v6* de uma tela que **já é Inertia** (não é migração Blade→React). Gate: Wagner aprova **screenshot** (o protótipo Forja Cowork) **antes** de qualquer Edit em [`Index.tsx`](../../../../resources/js/Pages/team-mcp/Tasks/Index.tsx).

## Contexto

`/team-mcp/tasks` (`TasksAdminController` → `team-mcp/Tasks/Index`) hoje é uma tela Inertia funcional: Kanban 4-colunas (drag→`PATCH /tasks/{id}/status`, otimista) + tabela Backlog + filtros module/owner/sprint + polling 10s. O objetivo do PR-1 é **conformar à gramática Forja** (lista densa agrupável + aba Quadro + drawer de issue + teclado + ⌘K) **preservando 100% das features atuais**, sob DS v6 (roxo canon, tokens `--fs-*`/`--sh-*`, status Stripe-dot, sem cor crua, sem `rounded-xl+`, sem `<select>` nativo).

**Referência visual aprovada:** protótipo **Forja Cowork** (`forja-page.jsx` + `forja-data.jsx` + `forja-page.css`), desenhado por Wagner no Cowork (ADR 0114 loop). URLs públicas expiram ~1h — capturadas nesta sessão; pedir regenerar se necessário re-render.

## Princípio de override (UI-0013) — onde Forja conflita com o canon, **canon vence**

| Aspecto | Forja (módulo, camada 4) | Canon oimpresso | Decisão |
|---|---|---|---|
| Tokens de cor | `--accent`/`--bg`/`--surface` próprios | Fundações + semânticos (`--fs-*`, `--sh-*`, shadcn `card/muted/primary/border`) | **Canon** — nada de `--accent` por módulo |
| Header | eyebrow + `<h1>` custom (`.os-page-h`) | `<PageHeader>` shared (Shell, camada 2) | **PageHeader** — eyebrow vira overline/subtitle |
| Status badge | chip com bg colorido | PT-01: dot + texto colorido, **sem bg-fill** (Stripe) | **PT-01** dots |
| Raio | 7–12px | tokens de raio (sem `rounded-xl+`) | radius tokens |
| Drawer | 520px | ADR 0185 = 760px (entidades) | ⚠️ **decisão aberta** (issue ≠ cadastro — ver §Decisões) |
| Sidebar/Shell | n/a | AppShellV2 intocado (UI-0009 light) | **não tocar** |

## Matriz de preservação de features (PR-1 não pode perder nenhuma)

| Feature atual | Origem | PR-1 |
|---|---|---|
| Kanban todo/doing/review/done | `buildKanbanPayload` | ✅ vira aba **Quadro** (mantém colunas + drag) |
| Drag-drop → `PATCH /tasks/{id}/status` otimista | `KanbanView` / `updateStatus` | ✅ idêntico (status válidos todo/doing/review/done/blocked/cancelled) |
| Backlog 200, ordenação status×prio | `buildBacklogPayload` | ✅ vira **lista densa agrupável** |
| Filtros module/owner/sprint | dropdowns distinct | ✅ Toolbar (Components/ui/select, não nativo) |
| KPIs (total/p0/doing/blocked/done/cancelled/total_h) | `buildKpisPayload` | ✅ PageHeader subtitle + `.fj-totalbar` rodapé |
| `Inertia::defer` em kanban/backlog/kpis/modulos/owners/sprints | controller | ✅ + `<Deferred>` skeletons |
| Polling 10s + on-focus reload | `useEffect` | ✅ preservado |
| blocked_by[] | payload | ✅ vínculo "bloqueado por" no drawer + ícone na linha |

## Restrição de dados — **sem dado fantasma** (transversal §3)

Backend expõe hoje por task: `task_id, title, module, owner, sprint, priority(p0–p3), estimate_h, blocked_by[], status`. O drawer Forja sugere mais (atividade, descrição, subtarefas, vínculos ADR/PR, parent/children) que **não existem no payload**.

| Seção do drawer Forja | Dado real disponível? | Decisão PR-1 |
|---|---|---|
| Fases / situação | `status` (6 estados) | ✅ render como barra de situação (mapeia status, **não** inventa F0..F4) |
| Bloqueado por (vínculos) | `blocked_by[]` | ✅ |
| Meta (módulo/owner/sprint/prio/est_h) | sim | ✅ `<dl>` |
| Atividade | `mcp_task_events` existe mas **não exposto** à Page | ⚠️ **decisão**: endpoint read-only (ver §Decisões) ou omitir até PR-1b |
| Descrição / subtarefas / ADR-PR links / parent-children | **não no payload** | ❌ **não renderizar** (omitir seção; nunca placeholder fake) |

## 15 dimensões (Atual Inertia · Forja referência · Decisão DS v6)

| # | Dimensão | Atual (hoje) | Forja (ref aprovada) | Decisão DS v6 (PR-1) |
|---|---|---|---|---|
| 1 | **Layout** | PageHeader + KpiGrid + Card-filtros + tabs inline | `.os-page-h` + toolbar group-by + filterbar + lista/kanban + drawer | **PT-01 6 slots**: PageHeader › ModuleTopNav(Backlog/Quadro) › Toolbar(group-by+filtros+busca+⌘K) › BulkBar › lista densa agrupável / Quadro › Drawer |
| 2 | **Hierarquia visual** | 2 tabs + Filtrar | 1 título, view-tabs, grupos colapsáveis | h1 via PageHeader; **sem ação primária nova** (tela read+drag; criação é via `mcp:tasks:sync`/MCP) |
| 3 | **Densidade** | tabela `py-1.5`, cards `p-3`, `rounded-xl` ❌ | row-h 34px, `padding:0 32px`, gap 10px, 13px | `--row-h` 34px (compact toggle), corpo `--fs-3` 12.5px, IDs `--fs-2` 11.5px mono; **remover `rounded-xl`** → radius token |
| 4 | **Iconografia** | lucide (PageHeader/KpiCard) | SVG inline (chevron/check/star/robot/human) | **lucide only** (UI-0003): `ChevronRight` grupos, `Star` favorito, `Bot`/`User` selo de ator |
| 5 | **Estados** | empty "Nenhuma task"/"vazio" | empty/loading/selected/hover/drawer | **PT-01 6 estados** + skeleton nos `Deferred` |
| 6 | **Atalhos** | nenhum | ⌘K, ?, j/k, ↵/e, x, /, Esc | **PT-01 canon**: J/K · ↵ abre drawer · / busca · ⌘K palette global · ? cheat · Esc · `x` multi-select · drag no Quadro |
| 7 | **Persistência** | filtros na URL | saved views | URL p/ filtros (compartilhável) + `localStorage oimpresso.teammcp.tasks.{groupBy,density,tab,collapsed}`; **nada em sessionStorage** |
| 8 | **Componentes shared** | PageHeader, KpiGrid/Card, Card, Badge, Button, Select, ScrollArea | custom | Reusar PageHeader, ModuleTopNav, BulkActionBar, EmptyState, Sheet(drawer), KpiCard. **Lista agrupável** = variante (ver §Decisões: estende DataTable ou GroupedList module-local) |
| 9 | **Tipografia numérica** | KpiCard default | mono | KPI value `--fs-7` 22px `tabular-nums`; label `--fs-1` uppercase +0.08em; contagens de grupo mono |
| 10 | **Espaçamento numérico** | `gap-3/4` | escala 2–24px | gap por Fundações; group-head pad 6–8px; drawer body `18px 22px` |
| 11 | **Cores semânticas** | `bg-red-100`, `border-blue-500` ❌ cru | oklch hue por prio/fase | **status = dot + texto** (sem bg); prio P0→`destructive`(~25°) · P1→warn(~68°) · P2→**roxo 295** · P3→muted(~250°) via tokens, **zero palette cru** |
| 12 | **Microinterações** | `hover:shadow-md`, `ring-primary` drag | `fjslide .22s`, focus `--focus`, sh-1/sh-2 | `--ease`+`--t-1/--t-2`; drawer slide-in; cards `--sh-1`, drawer `--sh-2`; focus-visible ring; drag-over highlight accent-soft |
| 13 | **Referência aprovada** | — | Forja Cowork (Wagner desenhou) | ✅ protótipo é o screenshot aprovado |
| 14 | **Benchmarks externos** | — | (emula Linear) | **Linear** (issue tracker) + Height/GitHub Issues — densidade, group-by, ⌘K, drawer lateral |
| 15 | **Persona** | — | power-user | **Interno** (Wagner + time MCP, desktop) ≠ Larissa. Top 3: (1) teclado-first + ⌘K, (2) selo proveniência **agente vs humano**, (3) densidade > decoração |

## Tokens — mapa raw → DS v6

- Remover de `Index.tsx`: `border-slate-400/blue-500/amber-500/emerald-500`, `bg-red-100 text-red-700`, `bg-blue-100`, `rounded-xl`, `min-h-[300px]` mágicos.
- Usar: shadcn semânticos (`bg-card`, `text-muted-foreground`, `border`, `text-primary`, `text-destructive`) + Fundações (`--fs-*`, `--sh-1/2`, `--ease`).
- **Selo de proveniência (teal):** Forja usa `--dev: oklch(0.52 0.10 195)`. Transversal §3 pede **_PROPOSTA** de token `--origin-DEV` em Fundações (não criar `--accent` por módulo). → ver §Decisões (não crio token nesta PR sem OK).

## Decisões abertas pra Wagner (resolver antes do F3; não bloqueiam aprovar o screenshot agora)

1. **Largura do drawer** — Forja 520px (estilo Linear/issue) vs canon ADR 0185 760px (entidades cadastrais). Recomendo **exceção issue ~560px** (task ≠ cadastro), registrada como nota; ou seguir 760.
2. **Atividade no drawer** — adicionar `GET /team-mcp/tasks/{id}` read-only lendo `mcp_task_events` (dado real, sem fantasma) **nesta PR**, ou omitir atividade até PR-1b? Recomendo o endpoint read-only.
3. **Lista agrupável** — formalizar como variante PT-01 agora (doc) ou manter module-local? Recomendo **module-local** (formaliza quando 2º módulo pedir).
4. **`--origin-DEV` teal** — abrir _PROPOSTA de token de Fundação (`oklch(0.52 0.10 195)`) pro selo de proveniência? (sem isso, selo usa lucide `Bot`/`User` neutro).
5. **Breadcrumb/título** — hoje diz "Copiloto / Tasks"; atualizar pra "Equipe / Tasks" (hub Equipe)?

## Decisões [W] 2026-06-16 (via AskUserQuestion)

1. **Drawer 560px** — exceção issue ≠ cadastro (ADR 0185 = 760px). ✅
2. **Atividade real** — endpoint read-only `GET /team-mcp/tasks/{id}/detail` lendo `mcp_task_events`. ✅
3. **Lista agrupável** — module-local (não formaliza variante PT-01 ainda). ✅ _(default)_
4. **Selo de proveniência** — lucide `Bot`/`User` neutro (sem token novo; `--origin-DEV` teal fica como _PROPOSTA futura). ✅
5. **Breadcrumb** — "Copiloto" → "Equipe". ✅ _(default)_

## Gates antes do F3 (implementação)
- [x] Wagner aprova **screenshot** (protótipo Forja) — AskUserQuestion 2026-06-16.
- [x] Ler `prototipo-ui/PROCESSO_MEMORIA_CC.md` §5 + NÚCLEO 13 invariantes + `memory/LICOES_CC.md` (CLAUDE.md passo 4b).
- [x] Criar `Index.charter.md` ao lado (PT-01 exige charter).
- [ ] CI verde: `typecheck` + `eslint`/`ui:lint` + `conformance-gate` + `foundation-guard` + UI-Judge (advisory → 2 verdes → required).

---
**Status:** `approved` — implementado no PR `feat/forja-pr1-tasks-reskin` (aguardando CI + merge [W2]).
