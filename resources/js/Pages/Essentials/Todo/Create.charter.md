---
page: /essentials/todo/create
component: resources/js/Pages/Essentials/Todo/Create.tsx
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

# Page Charter — /essentials/todo/create (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/ToDoController@create` + `@store` (resource `todo`). Formulário de criação de tarefa (To-Do) com atribuição a usuários da equipe.

---

## Mission
Criar uma nova tarefa da equipe: título, atribuídos, prioridade, status inicial, datas de início/término, horas estimadas e descrição. Se o usuário tem permissão de atribuir, escolhe os responsáveis; senão a tarefa fica com ele mesmo. Comentários e anexos são adicionados depois, na tela de detalhe.

---

## Goals — Features (faz)
- Formulário com título obrigatório, prioridade/status (selects vindos do backend), datas (`date`/`end_date`), horas estimadas e descrição.
- Atribuição multi-usuário via chips, apenas quando `can.assign` e há usuários — nota clara de que sem seleção a tarefa fica só com o autor.
- Submete via `POST /essentials/todo` (`useForm`), com toasts e erros de campo inline.
- `task_id` gerado no backend com prefixo configurável (`essentials_todos_prefix`).
- Botões Voltar/Cancelar retornam ao índice de tarefas.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO cria tarefa em outro business — `authorizeAdd($businessId)` e `business_id` no create (multi-tenant Tier 0).
- ❌ NÃO anexa arquivos nem comenta nesta tela (feito no `show`).
- ❌ NÃO permite atribuir se faltar permissão `essentials.assign_todos` (cai no autor).
- ❌ NÃO cria subtarefas / dependências entre tarefas.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Backend gera `task_id` sequencial com prefixo e registra `activityLog('added')`.
- Envia `NewTaskNotification` aos atribuídos (exceto o autor) no store.
- Redireciona para `todo.show` após criar.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO salva rascunho automático (sem autosave).
- ❌ NÃO notifica o próprio autor.
- ❌ NÃO dispara notificação fora dos atribuídos.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar defaults de prioridade/status com Larissa (ROTA LIVRE)
