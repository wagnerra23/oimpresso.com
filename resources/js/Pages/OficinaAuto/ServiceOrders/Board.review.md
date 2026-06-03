---
review_round: 1
review_type: static-analysis
reviewer: Claude Code (port Cowork oficina-kanban-carro)
review_at: 2026-06-02
page: OficinaAuto/ServiceOrders/Board
file: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
charter_present: true
charter_file: Board.charter.md
runbook_present: true
runbook_notes: memory/requisitos/OficinaAuto/RUNBOOK-serviceorders-board.md
append_only: true
---

# Review estática — `OficinaAuto/ServiceOrders/Board.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos
- AppShellV2 ✓ · Head title ✓
- Kanban data-driven (colunas vêm de `props.columns` = etapas reais FSM `oficina_mecanica_os`) ✓
- REUSA canon (não recria): `KanbanDndProvider` (generalizado backward-compat), `DragConfirmDialog`
  (+`subjectLabel`), `ServiceOrderRichSheet`, `MercosulPlate` ✓
- Drag → DragConfirmDialog → `POST /fsm/execute` → `ExecuteStageActionService` ✓ (canon GUARD)
- `useMemo`/`useCallback` nos handlers (lição PR #717) ✓
- @container/board pra densidade KPI @1280 (Tailwind v4 nativo) ✓ — NÃO @media (lição Financeiro)
- Mods [W]: foto real `thumb_url`/esconde · DVI x/y checklist · "N OS" · aguardando-peças × aprovação ✓

## Riscos Tier 0 / dívidas
1. **DRAG/GUARD — coberto por Pest** `ServiceOrderBoardTest` (spec 2/3/7): transição via service +
   history + invalid throw + smoke fluxo feliz. ServiceOrder não usa trait GuardsFsmTransitions
   (UPDATE direto não é bloqueado a nível de model) — board não faz UPDATE; única via é /fsm/execute.
   Follow-up opcional: adicionar trait GuardsFsmTransitions ao ServiceOrder (decisão [W], LIVE prod).
2. **STAGE_TRANSITIONS hardcoded no frontend** espelha o seeder `oficina_mecanica_os` (mesma estratégia
   do Kanban de caçamba aceita no review ProducaoOficina). Follow-up: derivar de `/fsm/actions`
   dinamicamente pra eliminar duplicação.
3. **order_type enum** estendido p/ 'mecanica' via migration reversível (idempotente, MySQL-guard).
   OS 'manutencao' legadas preservam `cacamba_manutencao` (sem orfanar) — decisão [W].
4. **CACHE/L3 — `columns` eager**: payload do quadro é o first-paint + alvo de partial reload
   (`only:['columns','kpis']`), então NÃO deferido (defer só p/ prop fora do reload — RUNBOOK defer).
5. **A11Y** — cards role=button + teclado; dnd-kit keyboard sensor herdado do provider canon.

## Verificações executadas (local)
- `tsc --noEmit`: arquivos novos (Board/card/column/boardTone) **sem erros** (baseline do repo à parte).
- `eslint` arquivos novos: **0 erros, 0 warnings**; `lint:baseline:check` **delta −60 (sem regressão)**.
- `php -l` seeder/migration/controller/request/test: **sem erros de sintaxe**.
- Pest `ServiceOrderBoardTest`: 7 specs (skip em SQLite — mesmo pattern dos testes FSM shipped;
  rodam em MySQL no CI). Smoke resiliente = fluxo feliz recepcao→pronto_retirada.

## Top recomendações próximos rounds
1. P2 — derivar STAGE_TRANSITIONS de `/fsm/actions` (remover duplicação seeder↔frontend).
2. P2 — avaliar trait GuardsFsmTransitions no ServiceOrder (enforcement defense-in-depth) — [W].
3. P3 — smoke browser (Claude in Chrome) com data-testid `so-card-*`/`board-column-*` pós-deploy.
4. P3 — Create: oferecer iniciar pipeline automático ao criar OS 'mecanica' (hoje via drawer).
