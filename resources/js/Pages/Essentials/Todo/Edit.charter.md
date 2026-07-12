---
page: /essentials/todo/{id}/edit
component: resources/js/Pages/Essentials/Todo/Edit.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_us: [US-ESSE-001]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/todo/{id}/edit (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/ToDoController@edit` + `@update` (resource `todo`). Formulário de edição de tarefa existente, pré-preenchido com os dados atuais.

---

## Mission
Editar uma tarefa existente: título, atribuídos, prioridade, status, datas, horas estimadas e descrição. Espelha o Create com valores pré-carregados e o código da tarefa (`task_id`) exibido no cabeçalho.

---

## Goals — Features (faz)
- Formulário pré-preenchido (`useForm` a partir de `todo`), datas normalizadas para `YYYY-MM-DD`.
- Atribuição multi-usuário via chips quando `can.assign`.
- Selects de prioridade/status vindos do backend.
- Submete via `PUT /essentials/todo/{id}`, com toasts e erros de campo inline.
- Botões Voltar/Cancelar retornam ao `show` da tarefa.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO edita tarefa de outro business — `authorizeEdit($businessId)` + `scopedQueryForUser($businessId)` (multi-tenant Tier 0).
- ❌ NÃO gerencia comentários/anexos aqui (ficam no `show`).
- ❌ NÃO permite editar se faltar permissão `essentials.edit_todos`.
- ❌ NÃO troca status "rápido" isolado (isso é o modal do Index via `only_status`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Backend registra atividade na tarefa ao atualizar (activity log do módulo).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO salva rascunho automático (sem autosave).
- ❌ NÃO renotifica atribuídos a cada edição (comportamento a confirmar com Wagner).
- ❌ NÃO recalcula `task_id` ao editar.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se mudança de atribuídos na edição deve notificar
