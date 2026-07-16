---
page: /project-mgmt/board
component: resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx
related_prototype: n/a (sheet slide-in de detalhe da task, com abas state-driven, bespoke — nao segue um dos 5 Padroes de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_adrs: [114, 101, 93, 100]
tier: B
charter_version: 1
---

# Page Charter — /project-mgmt/board (Detail Sheet) (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/BoardController@show` — `GET /project-mgmt/board/{taskId}/detail` (rota `project-mgmt.board.show`, permissão `copiloto.mcp.usage.all`). **Componente**, não página com rota própria: é o drawer slide-in aberto ao clicar num card do Board (`/project-mgmt/board`). História PMG-004 (ADR 0100) — não é ID no formato `US-...`, por isso `related_us` foi omitido. **Silencioso:** sheet de detalhe bespoke — sem `FsmActionPanel`/`Timeline`/`<dl>`/`StatCard`, sem tabela/form/kanban/kpi; nenhum dos 5 Padrões de Tela se aplica.

---

## Mission
Ser o painel de detalhe da task no estilo Jira: um `Sheet` que desliza pela direita ao clicar num card do Kanban, com abas state-driven (sem lib de Tabs) — Descrição, Comentários, Atividade, Subtasks, Watchers. Concentra a foundation da Fase 2 do PM (comentários com @mentions, watchers, subtasks) num só lugar, sem tirar o operador do Board.

---

## Goals — Features (faz)
- Header com prioridade (bolinha + badge), status, `display_id`, título, módulo, owner, prazo (destaque de atraso), estimativa e projeto.
- Fetch on-open de `GET /board/{taskId}/detail` com tratamento de 403/404 e AbortController (cancela ao trocar de task).
- Aba Descrição: texto + lista de dependências (tipo + target + status).
- Aba Comentários: lista + `MentionInput` com @mentions e submit (`POST /board/{taskId}/comment`), append otimista.
- Aba Atividade: eventos da task (`mcp_task_events`) com ícone/label e de→para.
- Aba Subtasks: lista com checkbox clicável (toggle status via `PATCH /board/{taskId}/status`) + criar inline (`POST /board/{taskId}/subtask`).
- Aba Watchers: seguir/parar de seguir (`POST`/`DELETE /board/{taskId}/watch`) + lista de seguidores, com refetch pra reconciliar.
- Footer com link "ver no board" e dica de edição via `tasks-update` (MCP).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não é multi-tenant por `business_id` — opera sobre `mcp_*` (PM interno do time), gated por `copiloto.mcp.usage.all`. (inferência pendente de Wagner)
- ❌ Não edita campos "duros" da task (título/owner/prazo/estimativa) — isso é `tasks-update` via MCP (o próprio footer diz). (inferência pendente de Wagner)
- ❌ Não é uma rota/página própria — é um componente do Board; `page` aponta pra `/project-mgmt/board`. (inferência pendente de Wagner)
- ❌ Não segue um dos 5 Padrões de Tela: sheet de detalhe bespoke, deliberadamente silencioso quanto a PT. (inferência pendente de Wagner)

---

## UX targets
- p95 < 1500ms (admin) ; sheet `sm:max-w-2xl` cabe em 1280px (ROTA LIVRE) ; abre sobre o Board (AppShellV2 do Board).

---

## Automation hooks (faz)
- Comentário com @mention grava notificação no fluxo `mcp_inbox_notifications` (PMG-005/006 — aparece na Inbox de My Work).
- Toggle de watch reflete em `mcp_task_watchers`; watchers recebem notif quando a task muda.
- Updates otimistas (comment/subtask/status/watch) com reconciliação por refetch.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Toda mutação via POST/PATCH/DELETE com CSRF — nada muta em GET.
- ❌ Não segue o autor da task automaticamente ao comentar (watch é ação explícita).
- ❌ Falha de toggle status/watch é silenciosa e reverte na próxima abertura — não retenta sozinho.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Wagner confirma o silêncio de PT (sheet de detalhe bespoke, não força um dos 5 Padrões)
- [ ] Smoke visual/interativo 1280/1440 (screenshot) — abrir cada aba a partir de um card do Board
