---
page: /repair/producao-oficina
component: resources/js/Pages/Repair/ProducaoOficina/Index.tsx
owner: wagner
status: rascunho
last_validated: 2026-05-09
parent_module: Repair
related_adrs: [0094, 0101, 0114]
related_prototype: prototipo-ui/prototipos/producao-oficina/F1.html
tier: A
---

# Page Charter — /repair/producao-oficina

> **Status:** F3 implementação inicial baseada em [F1 aprovado por Wagner em 2026-05-09](../../../../prototipo-ui/prototipos/producao-oficina/F1.html). Greenfield — sem tela Blade legacy.
> Query real `JobSheet` (US-REPAIR-PROD-2) com fallback gracioso pra mock data se biz não tem `repair_statuses` configurado.

---

## Mission (1 frase)

Visão de produção da oficina em **kanban de 5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto) pra Larissa enxergar o fluxo do dia em monitor 1280px **sem precisar abrir cada OS**.

---

## Goals — Features (faz)

- Kanban 5 colunas com cards de OS (placa Mercosul + KM + mecânico + box/elevador)
- Drawer lateral com detalhes da OS clicada (sintoma + fotos + peças + linha do tempo + banner aprovação cliente)
- Filtros funcionais por **Box** (B1-B4) e **Elevador** (E1-E2) — chips clicáveis no header, atualiza grid client-side via `useMemo` (US-REPAIR-PROD-3)
- Contador "X de Y OS" quando filtro ativo + botão "Limpar filtros"
- Destaque visual nas OS aguardando aprovação do cliente (banner laranja)
- **Drag-and-drop entre colunas** (US-REPAIR-PROD-4) — HTML5 nativo + optimistic update + POST `/move` que persiste no backend via mapping reverso (coluna → primeiro `repair_status_id` do bucket alvo)
- Cabe em monitor 1280px sem scroll horizontal (Larissa quirk crítico)
- AppShellV2 + topnav Repair (`KanbanSquare` icon)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test futuro (Non-Goal violado = CI quebra).

- ❌ Drag-and-drop entre colunas (mover status = ir pra `/repair/job-sheet/{id}/status`)
- ❌ CRUD de OS (vai pra `/repair/job-sheet`)
- ❌ Notificações push de mudança de status
- ❌ Atribuição de mecânico via UI (vai pra JobSheet)
- ❌ Aprovação cliente *via* esta tela (botão "Reenviar" no drawer só dispara WhatsApp template — sem UI de aprovar/recusar aqui)
- ❌ Edição inline em qualquer card

---

## UX Targets

- p95 first-paint < 600ms (mock data inline; props vêm prontas do Controller)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal — kanban grid `grid-cols-5` calculado pra caber
- Drawer overlay direita 480px com `transition` suave
- AppShellV2 layout (sidebar 260 + main 1fr; sem LinkedApps painel)
- Empty state em cada coluna sem cards ("Nenhuma OS")
- Density: cards compactos (3-4 visíveis por coluna sem scroll)
- Placa em fonte mono pra identificação rápida

---

## UX Anti-patterns

- ❌ Modal de qualquer tipo (drawer é o único container)
- ❌ Loading skeleton (props inline, sem async)
- ❌ Toast/snackbar
- ❌ Cores berrantes (DNA Cockpit V2 conservador — slate neutro + accent laranja só pra aprovação pendente)
- ❌ Animações decorativas (transitions só em hover/drawer)

---

## Automation Hooks

- Endpoint `/repair/producao-oficina` chama `ProducaoOficinaController::index`
- Mock data inline (até US-REPAIR-PROD-2) — query real virá com filtros backend e paginação por coluna
- Multi-tenant: queries scopadas por `business_id` global scope quando US-REPAIR-PROD-2 entrar
- Sem cache (mock é estático)

---

## Backlog (US futuras)

- ✅ **US-REPAIR-PROD-2** — query real `JobSheet` com fallback gracioso pra mock se biz não tem `repair_statuses` ou `job_sheets` configurado. Heurística sort_order quartil pra mapear status arbitrários do business pras 5 colunas fixas. Prop `data_source: 'live' | 'mock'` indica origem.
- ✅ **US-REPAIR-PROD-3** — filtros funcionais Box/Elevador (entregue PR ~~#TBD~~)
- ✅ **US-REPAIR-PROD-4** — drag-and-drop entre colunas via HTML5 nativo + optimistic update + POST `/producao-oficina/{id}/move` que invoca mapping reverso (coluna → primeiro `repair_status_id` do bucket alvo, espelha heurística `mapStatusesToColumns`). Mock data → drag local-only (sem persist, refresh volta ao estado original).
- ✅ **US-REPAIR-PROD-5** — Pest GUARD entregue (`Modules/Repair/Tests/Feature/ProducaoOficinaTest.php`): 5 tests cobrindo invariantes do charter — 5 colunas exatas/ordem, ≥1 OS aguardando aprovação, Non-Goal CRUD/mutações
