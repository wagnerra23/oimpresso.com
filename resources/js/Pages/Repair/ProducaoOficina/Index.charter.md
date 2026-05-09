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
> Mock data inline no Controller até US-REPAIR-PROD-2 entregar query real.

---

## Mission (1 frase)

Visão de produção da oficina em **kanban de 5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto) pra Larissa enxergar o fluxo do dia em monitor 1280px **sem precisar abrir cada OS**.

---

## Goals — Features (faz)

- Kanban 5 colunas com cards de OS (placa Mercosul + KM + mecânico + box/elevador)
- Drawer lateral com detalhes da OS clicada (sintoma + fotos + peças + linha do tempo + banner aprovação cliente)
- Filtros declarativos por **Box** (B1-B4) e **Elevador** (E1-E2) — chips clicáveis no header
- Destaque visual nas OS aguardando aprovação do cliente (banner laranja)
- Cabe em monitor 1280px sem scroll horizontal (Larissa quirk crítico)
- AppShellV2 + topnav Repair (`KanbanSquare` icon)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test futuro (Non-Goal violado = CI quebra).

- ❌ Drag-and-drop entre colunas (mover status = ir pra `/repair/job-sheet/{id}/status`)
- ❌ CRUD de OS (vai pra `/repair/job-sheet`)
- ❌ Filtros funcionais Box/Elevador no F3 inicial — chips renderizam mas só estilam, sem alterar grid (US-REPAIR-PROD-3)
- ❌ Query real backend (Controller usa mock data até US-REPAIR-PROD-2)
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

- **US-REPAIR-PROD-2** — query real `JobSheet` por status/business com mock substituído
- **US-REPAIR-PROD-3** — filtros funcionais Box/Elevador (atualiza grid client-side ou via Inertia partial reload)
- **US-REPAIR-PROD-4** — drag-and-drop entre colunas (chama `/repair/job-sheet/{id}/status` com optimistic update)
- **US-REPAIR-PROD-5** — Pest GUARD: kanban tem exatamente 5 colunas + isolamento `business_id` + mock-vs-real switch
