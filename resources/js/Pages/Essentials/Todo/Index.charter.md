---
page: /essentials/todo
component: resources/js/Pages/Essentials/Todo/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_us: [US-ESSE-001]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/todo (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/ToDoController@index` (resource `todo`). Listagem paginada de tarefas com filtros, troca rápida de status e remoção.

---

## Mission
Dar à equipe a visão de trabalho: tabela paginada de tarefas com filtros (status, prioridade, atribuído, intervalo de datas), atalhos para criar/editar/ver, troca rápida de status por modal e remoção com confirmação. Não-admin vê só as próprias ou atribuídas a ele.

---

## Goals — Features (faz)
- Tabela paginada (código, tarefa, status, prioridade, datas, atribuídos, ações) com badges tonais por status/prioridade.
- Filtros por status/prioridade/usuário/intervalo de datas via partial reload (`only: ['todos','filtros']`, D-14) e botão Limpar.
- Troca rápida de status via modal (`PUT /essentials/todo/{id}` com `only_status`).
- Ações por linha: Ver (`/{id}`), Editar (`/{id}/edit`, se `can.edit`), Remover (se `can.delete`) com confirmação.
- Paginação server-side com links partial-reload.
- Gating por permissões (`can.add/edit/delete/assign`).

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO lista tarefas de outro business — `ToDo::where('business_id', ...)` + filtro não-admin (só próprias/atribuídas) (multi-tenant Tier 0).
- ❌ NÃO é um Kanban com drag-and-drop — é tabela com troca de status por modal.
- ❌ NÃO edita campos inline na linha (exceto status via modal).
- ❌ NÃO exporta a lista (CSV/Excel) — inferência pendente de Wagner.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- `todos` (paginação transformada) e `assignableUsers` carregados via `Inertia::defer`; filtro dispara re-fetch só da lista (partial reload).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO faz polling/refresh automático da lista.
- ❌ NÃO muta dados em GET (filtros/paginação são leitura).
- ❌ NÃO aplica filtro sem ação do usuário.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se export/visão Kanban é desejado
