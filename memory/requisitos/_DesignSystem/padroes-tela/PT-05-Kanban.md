---
pattern_id: PT-05
nome: Kanban
camada: 3-padroes-tela
status: draft
versao: 0.1
created: 2026-05-30
parent_adr: UI-0013
golden_eleito: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
golden_score: 68/100 (Developing) — estrutural com drift a corrigir
persona: Técnico chão de fábrica (tablet, touch ≥44px, mobile_fit ALTO)
applied_in:
  - Pages/OficinaAuto/ProducaoOficina/Index.tsx
  - Pages/Repair/ProducaoOficina/Index.tsx
---

# PT-05 · Kanban — board de colunas com cards arrastáveis

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md) + [Shell](../README.md) e nunca contradiz. Módulo só configura colunas/cards/transições, **não** muda a estrutura.
> **Status `draft`** — golden eleito é estrutural mas ainda tem drift sério (score 68 no piloto). Use como esqueleto, **corrija os drifts da §Drift conhecido ao copiar.**

## Quando aplicar

Fluxo operacional com **estados sequenciais** onde o usuário move uma entidade de uma fase pra outra (OS de oficina, produção, pipeline FSM, etapas de serviço). Persona típica = operador de chão de fábrica em tablet (touch).

Não aplicar pra: lista paginável ([PT-01 Lista](PT-01-Lista.md)), form/cadastro (PT-02 quando documentado), dashboard de gráficos (PT-04 quando documentado).

## Golden eleito + por quê

**[`Pages/OficinaAuto/ProducaoOficina/Index.tsx`](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)** — vence a candidata `Repair/ProducaoOficina` em 4 eixos que importam pra persona touch:

| Eixo | OficinaAuto (eleito) | Repair (rejeitado) |
|---|---|---|
| **Drag touch** | `@dnd-kit/core` — `PointerSensor` cobre mouse+touch + `KeyboardSensor` a11y ([KanbanDndProvider.tsx:86-91](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx)) | HTML5 nativo `draggable`/`onDragStart` ([Index.tsx:419-425](../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) — **não dispara em touch**, gap fatal no piloto |
| **Transição validada** | `resolveDragMapping` FSM FROM→TO + `DragConfirmDialog` ([Index.tsx:124-236](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)) | `handleDrop` POST direto sem confirmação ([Index.tsx:136-165](../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) |
| **Token v4** | `border-primary` no preview ([KanbanDndProvider.tsx:54](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx)) | `bg-blue-400`/`bg-blue-50` crus ([Index.tsx:89-102](../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) |
| **Componentes DS** | `@/Components/ui` Input/Button ([Index.tsx:34-35](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)) | zero `@/ui` — tudo `<button>` nativo |

> **Honestidade (regra GOLDEN-REFERENCE §4):** o eleito é **golden estrutural com drift a corrigir**, score **68/100 Developing** no [piloto](../../../governance/screen-grades-pilot.md). Tem o esqueleto certo (dnd-kit + FSM + dialog + ui), mas o piloto achou: grid fixo `grid-cols-5` ([Index.tsx:603](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)) que não rola; tones `bg-slate/amber/rose/emerald` direto no `KpiCard` ([Index.tsx:649-675](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)) em vez de `<Badge>`; e card tap-target não auditado pra ≥44px.

### O golden IDEAL (alvo do bump v1.0)

1. **Colunas flex roláveis** — `flex gap-4 overflow-x-auto` com `min-w` por coluna + scroll-snap, NÃO `grid-cols-5` fixo (quebra em tablet retrato).
2. **Drag com fallback** — dnd-kit cobre touch; **somar** botão/menu "Mover para…" em cada card pra quem não arrasta (acessibilidade + dedo grosso).
3. **Tap ≥44px** — card inteiro clicável com altura mínima `min-h-[44px]`, ações com `h-11 w-11` (alvo WCAG 2.5.5 / persona touch).
4. **Tokens v4** — `primary` roxo + status na escala warm semântica (`emerald/amber/rose/sky`), zero `blue-*`/`slate-*` cru de marca ([INDEX §0 regra 2](../INDEX-DESIGN-MEMORIAS.md)).

## Anatomia · 5 slots fixos

```
┌─────────────────────────────────────────────────────────────┐
│ 1 · PageHeader     h1 + sub · toggle Kanban|Lista · ações    │
├─────────────────────────────────────────────────────────────┤
│ 2 · KPI strip      cards de contagem por estado (opcional)   │
├─────────────────────────────────────────────────────────────┤
│ 3 · Toolbar        filtros (pills) · busca · KPI inline      │ ← sticky
├─────────────────────────────────────────────────────────────┤
│ 4 · Board          N colunas flex roláveis · cards arrastáv. │
│                      header(dot+label+count) · scroll interno │
├─────────────────────────────────────────────────────────────┤
│ 5 · Drawer/Dialog  Sheet do card + DragConfirmDialog FSM     │
└─────────────────────────────────────────────────────────────┘
```

| Slot | Onde no golden | Faz | Não faz |
|---|---|---|---|
| **1 · Header** | [Index.tsx:478-521](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) | h1 + sub + toggle Kanban\|Lista + Novo. Migrar p/ `<PageHeader>` shared | filtros · busca |
| **2 · KPI strip** | [Index.tsx:523-530](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) | contagem por estado, `tabular-nums` | navegação |
| **3 · Toolbar** | [Index.tsx:533-598](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) | pills filtro + busca debounce 300ms + KPI inline. Sticky `z-10` | mover card |
| **4 · Board** | [Index.tsx:601-615](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) + `CacambaKanbanColumn` | colunas + drag dnd-kit + drop highlight `useDroppable.isOver` | persistir sem dialog |
| **5 · Drawer/Dialog** | [Index.tsx:618-633](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) | `Sheet` do card + `DragConfirmDialog` antes do POST FSM | modal sobre modal |

## Regras binárias (sim/não)

| # | Regra | Evidência no golden |
|---|---|---|
| **R1** | Drag usa `@dnd-kit/core` (`PointerSensor`+`KeyboardSensor`), **NÃO** HTML5 `draggable` nativo? | [KanbanDndProvider.tsx:86-91](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx) |
| **R2** | `PointerSensor` tem `activationConstraint.distance` (≥8) pra clique-abre-drawer não virar drag acidental? | [KanbanDndProvider.tsx:87-89](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx) |
| **R3** | Transição de coluna passa por mapping FSM (FROM→TO→action) **antes** de persistir, bloqueando inválida com toast? | [Index.tsx:124-236](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) |
| **R4** | Ação crítica (`isCritical`) abre `DragConfirmDialog` antes do POST — drop nunca persiste direto? | [Index.tsx:316-333](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) + [:627-633](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) |
| **R5** | Persistência via endpoint FSM `service-orders/{id}/fsm/execute` com CSRF + `router.reload({only:['kanban','kpis']})` — **não** muta `current_stage_id` direto ([ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))? | [Index.tsx:356-386](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) |
| **R6** | Header de coluna = dot de tom + label + count em pílula `tabular-nums`? | `CacambaKanbanColumn` (header pattern espelha [Repair Index.tsx:359-367](../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) |
| **R7** | Drop-target destaca coluna no hover (`useDroppable.isOver`), e o card arrastado tem `DragOverlay` preview leve (não duplica o card)? | [KanbanDndProvider.tsx:51-77,143-145](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/_components/KanbanDndProvider.tsx) |
| **R8** | Handlers descendentes em `useCallback`/`useMemo` (evita re-render loop — lição PR #717)? | [Index.tsx:263-279,286,335,400-407](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) |
| **R9** | Busca tem debounce (≥300ms) via `router.get` `preserveState`+`preserveScroll`+`replace`, sem visit por keystroke? | [Index.tsx:244-258](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) |
| **R10** | Cor de status/KPI na escala warm semântica (`amber/rose/emerald`) + `primary` roxo, **NÃO** `blue-*` cru? | [Index.tsx:657-674](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx) (warm ✓) · drift `border-primary` ✓ · ver §Drift |

**Placar:** 10/10 = canon. <8 = volta pro Claude Design. Golden eleito hoje: ~7/10 (falha R-touch-target e colunas-flex que viram regras do IDEAL acima).

## Nunca

- ❌ **Drag HTML5 nativo** (`draggable`/`onDragStart`/`onDrop`) — não funciona em touch, persona não consegue mover (gap do piloto Repair). Sempre `@dnd-kit`.
- ❌ **Drop que persiste direto** sem `DragConfirmDialog` em ação `isCritical` — risco de transição irreversível por toque acidental.
- ❌ **Mutar `current_stage_id` direto** (Eloquent `save()` ou POST genérico) — passa por `ExecuteStageActionService`/endpoint FSM ([ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md), [proibições](../../../proibicoes.md)).
- ❌ **`grid-cols-N` fixo no board** — não rola em tablet retrato. Use `flex overflow-x-auto`.
- ❌ **Cor crua** (`bg-blue-500`, `#hex`) — token CSS var / escala warm. (anti-padrão AP1 / [INDEX §3c](../INDEX-DESIGN-MEMORIAS.md))
- ❌ **Tap-target <44px** em card ou ação — persona é dedo em tablet (WCAG 2.5.5).
- ❌ **Drawer mock** com dados hardcoded (sintoma/fotos/preço fixos) declarado pronto — o `JobDrawer` da Repair ([Index.tsx:532-565](../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) tem texto fixo "barulho na suspensão" e fotos 📷; golden real usa `ServiceOrderRichSheet` com dados do servidor.
- ❌ **Emoji em UI produtiva** (📷/✓ no card Repair) — ícone lucide.
- ❌ Modal sobre modal — Sheet do card + Dialog de confirmação são camadas distintas, não empilhar.

## Drift conhecido do golden (corrija ao copiar)

1. ⚠️ **Board `grid grid-cols-5` fixo** ([Index.tsx:603](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)) → trocar por `flex gap-4 overflow-x-auto` + `min-w-[280px]` por coluna (board IDEAL §2).
2. ⚠️ **`KpiCard` com tones inline** `bg-amber-50/bg-rose-50/...` ([Index.tsx:649-675](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)) → migrar pra `<Badge variant>` shared quando existir (tipo-2 da MATRIZ — não bloqueia copiar, mas é o alvo).
3. ⚠️ **Tap-target não auditado** — card e botões precisam `min-h-[44px]`/`h-11` explícito pra persona touch (não verificado no código atual).
4. ⚠️ **`bg-slate-50`/`bg-slate-900` de chrome** espalhados — manter como neutro estrutural OK, mas accent/marca = `primary` roxo nunca `blue-*` ([INDEX §0](../INDEX-DESIGN-MEMORIAS.md)).
5. ⚠️ **Header é `<header>` hand-rolled** ([Index.tsx:478](../../../../resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx)), não `<PageHeader>` shared → migrar pro componente canônico (slot 1).

## Aplicado em (estado real)

| Página | Drag | FSM | Confirm dialog | Token v4 | Touch ok | Score |
|---|---|---|---|---|---|---|
| `OficinaAuto/ProducaoOficina/Index.tsx` | dnd-kit ✓ | ✓ | ✓ | parcial (`primary` no preview, warm KPI) | parcial | **68** (golden) |
| `Repair/ProducaoOficina/Index.tsx` | HTML5 ✗ | ✗ (POST direto) | ✗ | ✗ (`blue-*` cru) | ✗ | — |

**Próximo passo:** quando OficinaAuto fechar os 5 drifts (board flex + tap≥44 + Badge + PageHeader) e marcar ≥8/10, bump PT-05 pra `status: live` e migrar a Repair pro mesmo esqueleto.

## Referências

- **ADR-mãe:** [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **FSM canon:** [ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (LIVE prod) · [ADR 0129](../../../decisions/0129-state-machine-canonica-fsm-rbac.md)
- **Domínio Martinho:** [ADR 0194](../../../decisions/0194-correcao-dominio-martinho-mecanica-pesada.md) · [ADR 0137](../../../decisions/0137-oficinaauto-qualificada.md)
- **Golden form/lista:** [GOLDEN-REFERENCE.md](../../../../prototipo-ui/GOLDEN-REFERENCE.md) · [PT-01 Lista](PT-01-Lista.md)
- **Índice de design:** [INDEX-DESIGN-MEMORIAS.md](../INDEX-DESIGN-MEMORIAS.md) (regra de ouro + negativo)
- **Piloto que gradeou:** [screen-grades-pilot.md](../../../governance/screen-grades-pilot.md) (Repair/ProducaoOficina kanban = 68 Developing)
- **Protótipo de origem:** [`prototipo-ui/prototipos/producao-oficina/visual-source.html`](../../../../prototipo-ui/prototipos/producao-oficina/visual-source.html)

## Versão

**v0.1** · 2026-05-30 · `draft`. Golden estrutural eleito (OficinaAuto), drifts catalogados, golden IDEAL definido.
**Bump v1.0** quando golden fechar os 5 drifts + marcar ≥8/10 nas regras binárias.
