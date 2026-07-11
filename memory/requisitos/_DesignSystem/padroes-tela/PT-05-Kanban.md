---
pattern_id: PT-05
nome: Kanban
camada: 3-padroes-tela
status: draft
versao: 0.2
created: 2026-05-30
updated: 2026-07-11
parent_adr: UI-0013
golden_eleito: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
golden_score: ~8/10 regras binárias — board rolável + FSM + tokens OK; residual header/tap-target
persona: Técnico chão de fábrica (tablet, touch ≥44px, mobile_fit ALTO)
applied_in:
  - Pages/OficinaAuto/ServiceOrders/Board.tsx (golden)
  - Pages/Repair/ProducaoOficina/Index.tsx (consumidor — herda, ainda com drift)
  - Pages/team-mcp/Tasks/Index.tsx (consumidor — board de tasks)
---

# PT-05 · Kanban — board de colunas com cards arrastáveis

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md) + [Shell](../README.md) e nunca contradiz. Módulo só configura colunas/cards/transições, **não** muda a estrutura.
> **Status `draft`** — golden reeleito (`ServiceOrders/Board.tsx`, 2026-07-11) já resolve os drifts estruturais do golden anterior. Bump pra `live` aguarda **aprovação de screenshot do Wagner** (gate F1.5 · [ADR 0107](../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).

## Quando aplicar

Fluxo operacional com **estados sequenciais** onde o usuário move uma entidade de uma fase pra outra (OS de oficina, produção, pipeline FSM, etapas de serviço). Persona típica = operador de chão de fábrica em tablet (touch).

Não aplicar pra: lista paginável ([PT-01 Lista](PT-01-Lista.md)), form/cadastro ([PT-02 Form/Drawer](PT-02-Form-Drawer.md)), dashboard de gráficos ([PT-04 Dashboard](PT-04-Dashboard.md)).

## Golden eleito + por quê

**[`Pages/OficinaAuto/ServiceOrders/Board.tsx`](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)** (charter `live`, rota `/oficina-auto/ordens-servico`).

> **Reeleição 2026-07-11.** O golden anterior (`OficinaAuto/ProducaoOficina/Index.tsx`, score 68) **foi deletado/refatorado** — o board da oficina migrou pra `ServiceOrders/Board.tsx`, que herdou o `KanbanDndProvider` e evoluiu. O novo golden **resolve os 2 drifts estruturais** que travavam o bump (board não-rolável + tones inline) e é mais maduro (4 views Quadro·Lista·Grade·Fila + `ServiceOrderRichSheet`). Reusa o mesmo `_components/KanbanDndProvider` da oficina.

Por que `Board.tsx` é o arquétipo Kanban:

| Eixo | Board.tsx (eleito) | Repair/ProducaoOficina (consumidor c/ drift) |
|---|---|---|
| **Board rolável** | `repeat(n, minmax(228px,1fr))` em wrapper `overflow-auto` ([Board.tsx:670-673](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx), [:908-910](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)) — rola X/Y, não estoura viewport | `grid grid-cols-5` fixo ([Index.tsx:249](../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) — não rola em tablet retrato |
| **Drag touch** | `@dnd-kit/core` `PointerSensor` (mouse+touch) + `KeyboardSensor` a11y ([KanbanDndProvider.tsx:81-82](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx)) | HTML5 `draggable`/`onDragStart` — não dispara em touch |
| **Transição validada** | mapping FSM FROM→TO ([Board.tsx:271-302](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)) + `DragConfirmDialog` ([:946](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)) antes do POST | POST direto sem confirmação |
| **KPI/token** | `BoardKpiCard` com `kpiTone()` semântico + `rounded-lg` + `ring-primary` ([BoardKpiCard.tsx:41-55](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/BoardKpiCard.tsx)) | `bg-blue-*` cru |

## Anatomia · 5 slots fixos

```
┌─────────────────────────────────────────────────────────────┐
│ 1 · Header         h1 + ações de página (Nova OS · imprimir) │
├─────────────────────────────────────────────────────────────┤
│ 2 · KPI strip      BoardKpiCard por estado (container-query) │
├─────────────────────────────────────────────────────────────┤
│ 3 · Toolbar        filtros (box) · busca · toggle de views   │ ← sticky
├─────────────────────────────────────────────────────────────┤
│ 4 · Board          N colunas minmax roláveis · cards arrast. │
│                      header(dot+label+count) · overflow-auto  │
├─────────────────────────────────────────────────────────────┤
│ 5 · Drawer/Dialog  RichSheet do card + DragConfirmDialog FSM │
└─────────────────────────────────────────────────────────────┘
```

| Slot | Onde no golden | Faz | Não faz |
|---|---|---|---|
| **1 · Header** | [Board.tsx:685-700](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) | h1 + "Nova OS" + imprimir fila. **Drift:** hand-rolled `<header>`, migrar p/ `<PageHeader>` shared | filtros · busca |
| **2 · KPI strip** | [Board.tsx:707-720](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) (`BoardKpiCard`) | contagem por estado, grid container-query `@[700px]/@[1100px]`, KPI-ativo-como-filtro (`ring-primary`+`aria-pressed`) | navegação |
| **3 · Toolbar** | [Board.tsx:723](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) (filtro box `overflow-x-auto`) + barra de views | pills filtro + busca debounce 300ms + toggle Quadro·Lista·Grade·Fila. Sticky | mover card |
| **4 · Board** | [Board.tsx:908-931](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) + `ServiceOrderKanbanColumn` | colunas minmax roláveis + drag dnd-kit + drop highlight | persistir sem dialog |
| **5 · Drawer/Dialog** | [Board.tsx:946](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) (`DragConfirmDialog`) + `ServiceOrderRichSheet` | Sheet do card + confirm antes do POST FSM | modal sobre modal |

## Regras binárias (sim/não)

| # | Regra | Evidência no golden |
|---|---|---|
| **R1** | Drag usa `@dnd-kit/core` (`PointerSensor`+`KeyboardSensor`), **NÃO** HTML5 `draggable`? | [KanbanDndProvider.tsx:81-82](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx) · [Board.tsx:909](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) |
| **R2** | `PointerSensor` tem `activationConstraint.distance` (≥8) pra clique-abre-drawer não virar drag? | [KanbanDndProvider.tsx:82](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx) (`distance: 8`) |
| **R3** | Transição de coluna passa por mapping FSM (FROM→TO→action) **antes** de persistir? | [Board.tsx:271-302](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) + `handleDragMove` [:398](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) |
| **R4** | Ação `isCritical` abre `DragConfirmDialog` antes do POST — drop nunca persiste direto? | isCritical [:230,234,246](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) · dialog [:946](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) · `handleConfirm` [:426](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) |
| **R5** | Persistência via endpoint FSM `/fsm/execute` + `router.reload({only})` — **não** muta `current_stage_id` direto ([ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))? | fetch [:431](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) · reload [:391](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) |
| **R6** | Header de coluna = dot de tom + label + count em pílula `tabular-nums`? | [ServiceOrderKanbanColumn.tsx:62-80](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/ServiceOrderKanbanColumn.tsx) |
| **R7** | Board rola (`overflow-auto`) com colunas `minmax`, **NÃO** `grid-cols-N` fixo? | [Board.tsx:670-673,908-910](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) |
| **R8** | Handlers descendentes em `useCallback`/`useMemo` (evita re-render loop — lição PR #717)? | [Board.tsx:330-426](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) (comment [:22](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)) |
| **R9** | Busca com debounce (≥300ms) via `router.get` `preserveState`+`preserveScroll`+`replace`, sem visit por keystroke? | `applyBoardFilter` [:361-379](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx) |
| **R10** | Cor de status/KPI semântica via `kpiTone()`/`tone.dot`/`tone.badge` + `primary` roxo, **NÃO** `blue-*` cru? | [BoardKpiCard.tsx:25,41-55](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/BoardKpiCard.tsx) · [ServiceOrderKanbanColumn.tsx:64,78](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/board/ServiceOrderKanbanColumn.tsx) |

**Placar:** 10/10 = canon. O golden marca **~8/10** — passa R1-R7,R9-R10; residual é **tap-target ≥44px não explícito** (persona touch) e **header hand-rolled** (não `PageHeader`). Ver §Drift.

## Nunca

- ❌ **Drag HTML5 nativo** (`draggable`/`onDragStart`/`onDrop`) — não funciona em touch. Sempre `@dnd-kit`.
- ❌ **Drop que persiste direto** sem `DragConfirmDialog` em ação `isCritical` — risco de transição irreversível por toque acidental.
- ❌ **Mutar `current_stage_id` direto** (Eloquent `save()` ou POST genérico) — passa por `ExecuteStageActionService`/endpoint FSM ([ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md), [proibições](../../../proibicoes.md)).
- ❌ **`grid-cols-N` fixo no board** — não rola em tablet retrato. Use `repeat(n, minmax(…,1fr))` + wrapper `overflow-auto` (como o golden), ou `flex overflow-x-auto`.
- ❌ **Cor crua** (`bg-blue-500`, `#hex`) — token CSS var / escala semântica via `tone`. (anti-padrão AP1 / [INDEX §3c](../INDEX-DESIGN-MEMORIAS.md))
- ❌ **Tap-target <44px** em card ou ação — persona é dedo em tablet (WCAG 2.5.5).
- ❌ **Emoji em UI produtiva** — ícone lucide.
- ❌ Modal sobre modal — Sheet do card + Dialog de confirmação são camadas distintas, não empilhar.

## Drift conhecido do golden (corrija ao copiar)

Resolvidos na reeleição (o golden anterior tinha; `Board.tsx` **não**): board rolável ✓ · KPI tones semânticos + `rounded-lg` ✓ · tokens `primary`/`tone` ✓. Residual honesto:

1. ⚠️ **Header hand-rolled `<header>`** ([Board.tsx:685](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx)), não `<PageHeader>` shared → migrar pro componente canônico (slot 1). Não bloqueia copiar, mas é o alvo.
2. ⚠️ **Tap-target ≥44px não explícito** no card (`ServiceOrderKanbanCard`) e nas ações — persona touch exige `min-h-[44px]`/`h-11`. Auditar e travar ao evoluir.
3. ℹ️ **Consumidor `Repair/ProducaoOficina/Index.tsx` ainda com drift** (`grid-cols-5` fixo, HTML5 drag) — migrar pro esqueleto do golden ao tocar a tela.

## Aplicado em (estado real)

| Página | Papel | Drag | FSM | Confirm | Board rolável | Token | Score |
|---|---|---|---|---|---|---|---|
| `OficinaAuto/ServiceOrders/Board.tsx` | **golden** | dnd-kit ✓ | ✓ | ✓ | ✓ | semântico ✓ | **~8/10** |
| `Repair/ProducaoOficina/Index.tsx` | consumidor | HTML5 ✗ | POST direto ✗ | ✗ | ✗ (`grid-cols-5`) | `blue-*` ✗ | drift |
| `team-mcp/Tasks/Index.tsx` | consumidor | — | — | — | — | — | — |

**Próximo passo:** aprovação de screenshot Wagner (F1.5) → bump `status: live`. Depois, migrar `Repair/ProducaoOficina` pro esqueleto do golden e auditar tap-target.

## Referências

- **ADR-mãe:** [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Gate visual:** [ADR 0107](../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) (Wagner aprova screenshot, não tabela)
- **FSM canon:** [ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (LIVE prod) · [ADR 0129](../../../decisions/0129-state-machine-canonica-fsm-rbac.md)
- **Domínio Martinho:** [ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) · [ADR 0265](../../../decisions/0265-oficina-reparo-erradica-locacao.md) (reparo, não locação)
- **Golden form/lista:** [GOLDEN-REFERENCE.md](../../../../prototipo-ui/GOLDEN-REFERENCE.md) · [PT-01 Lista](PT-01-Lista.md)
- **Índice de design:** [INDEX-DESIGN-MEMORIAS.md](../INDEX-DESIGN-MEMORIAS.md) (regra de ouro + negativo)

## Versão

**v0.2** · 2026-07-11 · `draft`. **Golden reeleito** `OficinaAuto/ServiceOrders/Board.tsx` (o anterior `ProducaoOficina/Index.tsx` foi deletado). Drifts estruturais (board rolável, tones) resolvidos pela reeleição; residual = header + tap-target. Re-âncora completa dos slots + R1-R10.
**v0.1** · 2026-05-30 · golden estrutural anterior (OficinaAuto/ProducaoOficina, score 68), aposentado.
**Bump v1.0/`live`** após aprovação de screenshot Wagner (F1.5).
