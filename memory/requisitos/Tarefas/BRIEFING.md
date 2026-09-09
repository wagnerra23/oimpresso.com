---
id: requisitos-tarefas-briefing
module: Tarefas
status: deprecated
updated_at: "2026-09-09"
lifecycle: arquivado
---

# BRIEFING — Tarefas · ⚰️ LÁPIDE (planejado, não existe)

> ⚠️ **MATAR — `Pages/Tarefas/Index.tsx` é stub sem controller/backend. Tarefas do TIME = MCP/TaskRegistry (ADR 0070); de CLIENTE = `ProjectMgmt`/`Essentials`.**
> - Decisão E1 (frente KL · 2026-06-15): MATAR com lápide.
> - **EXECUTADA em 2026-09-09** — removidos `Index.tsx`, `Index.charter.md` e a rota `tarefas.index` (`routes/web.php`). Recibo: `casos-gate` 78→77 violações · `screen-coverage` 223→222 telas, charter 100% · `deadlink-gate` zero novo. Confirmação [W] no chip do charter de stub.

**Tipo:** lápide (KL-E2). Não há módulo "Tarefas".

## Onde as tarefas vivem (ponteiro de 2026-09-09)

| Tarefas de… | Tela | Backend |
|---|---|---|
| **cliente** | `resources/js/Pages/Essentials/Todo/{Index,Create,Edit,Show}.tsx` | `Modules\Essentials\Http\Controllers\ToDoController` · `Route::resource('todo')` |
| **time (MCP)** | `/team-mcp/tasks` | `Modules\Forja\Http\Controllers\TasksAdminController` |

> A decisão de 2026-06-15 acima cita `ProjectMgmt` — nome correto naquela data; o módulo foi renomeado para **`Forja`** depois. O texto datado fica como estava; este ponteiro é o de hoje.
>
> A arquitetura `TaskProvider`/`TaskRegistry` cross-módulo ([ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md) Fase 4) **nunca foi construída** — medido 2026-09-09: `viewerComponent()` zero ocorrências, `resources/js/Components/Viewers/` zero arquivos. O `TaskRegistry` que existe (`Modules/Jana/Services/TaskRegistry/`) é o do sistema de tasks do MCP ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)), outro propósito. `DESIGN.md` §11, que mandava registrar um `TaskProvider` inexistente, foi corrigido no mesmo PR.
