---
page: /essentials/todo/{id}
component: resources/js/Pages/Essentials/Todo/Show.tsx
related_prototype: n/a (tela de detalhe bespoke — dados da tarefa + tabs comentários/anexos/atividades; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_us: [US-ESSE-001]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /essentials/todo/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/ToDoController@show` (resource `todo`) + endpoints add-comment / upload-document / delete-comment / delete-document / view-share-docs. Detalhe da tarefa com dados, comentários, anexos, atividades e documentos compartilhados.

---

## Mission
Mostrar tudo sobre uma tarefa: cabeçalho com código/status/prioridade, card de dados (datas, horas, criador, atribuídos, descrição) e três tabs — Comentários, Anexos e Atividades. Permite comentar, anexar arquivos, remover comentário/anexo próprios e abrir documentos compartilhados via diálogo.

---

## Goals — Features (faz)
- Cabeçalho com `task_id`, título, badges de status/prioridade e data de criação.
- Card "Dados" com início/término, horas estimadas, criador, atribuídos (badges) e descrição (renderizada como TEXTO — `<br>` legado vira quebra).
- Tab Comentários: adicionar (`POST /essentials/todo/add-comment`, throttle 60/min) e remover próprio (`GET /essentials/todo/delete-comment/{id}`).
- Tab Anexos: upload múltiplo (`POST /essentials/todo/upload-document`, throttle 30/min, com barra de progresso), download e remoção do próprio (`GET /essentials/todo/delete-document/{id}`).
- Tab Atividades: histórico de activity log (causer + descrição + data).
- Diálogo "Docs compartilhados" via `GET /essentials/view-todo-{id}-share-docs` (JSON).
- Gating por `can.edit`; botões Editar / Voltar.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO exibe tarefa fora do escopo do usuário/business — `scopedQueryForUser($businessId)` (multi-tenant Tier 0 + regra não-admin só próprias/atribuídas).
- ❌ NÃO edita campos da tarefa aqui (edição é na tela `edit`).
- ❌ NÃO renderiza descrição/comentário como HTML (React escapa — evita stored-XSS).
- ❌ NÃO remove comentário/anexo de terceiro (só quando `can_delete`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Backend envia `NewTaskCommentNotification` e `NewTaskDocumentNotification` ao comentar/anexar; registra activity log.
- Documentos compartilhados resolvidos sob demanda ao abrir o diálogo.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO faz polling/refresh dos comentários/anexos.
- ❌ NÃO renderiza HTML de usuário via `dangerouslySetInnerHTML` (texto escapado).
- ❌ Endpoints de remoção via GET herdados do legado — não são disparados sem ação explícita do usuário (confirmar hardening com Wagner).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Avaliar migrar deletes de comentário/anexo de GET para DELETE (CSRF/idempotência)
