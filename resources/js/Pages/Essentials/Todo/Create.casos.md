---
id: resources-js-pages-essentials-todo-create-casos
casos: Essentials · Nova tarefa · /essentials/todo/create
irmaos: Create.charter.md (lei) · Create.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /essentials/todo/create · Nova tarefa

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Create.charter.md`](Create.charter.md) + `ToDoController@create`/`@store` + US-ESS-002 do SPEC — **nunca** do `.tsx` (§5 2026-06-05). Teste: [`TodoFormsContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php). Tenant 98 × 2 (ADR 0358), nunca biz=4.

> ⚖️ **Onde roda.** `PHP / Pest (Essentials · MySQL)` (`.github/workflows/essentials-pest.yml`), MySQL-only — pula no SQLite. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-ETDC-01 · Criar sem escolher atribuídos deixa a tarefa com o autor `[must]`
Status: 🧪 sem veredito
- **Persona:** colaborador — anota uma tarefa pra si sem pensar em atribuição.
- **Aceite:** Dado o form com título, data e prioridade, sem atribuídos · Quando salvo · Então a tarefa nasce no meu business, com `status = new`, `task_id` preenchido e `essentials_todos_users` contendo só eu.
- **Teste:** `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` — `UC-ETDC-01 · …`
- **Regressão que defende:** charter Mission *"senão a tarefa fica com ele mesmo"* + US-ESS-002 (*"`task_id` único auto-gerado"*).

## UC-ETDC-02 · O form recebe status e prioridades do servidor `[must]`
Status: 🧪 sem veredito
- **Persona:** colaborador — escolhe entre as opções que o sistema aceita.
- **Aceite:** Dado o form de criação · Quando ele abre · Então `statuses` traz `new, in_progress, on_hold, completed` e `priorities` traz `low, medium, high, urgent`, na ordem.
- **Teste:** `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` — `UC-ETDC-02 · …`
- **Regressão que defende:** charter §Goals *"prioridade/status (selects vindos do backend)"* — o mesmo conjunto que o `ToDoStoreRequest` aceita.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Notificação `NewTaskNotification` vai aos atribuídos e não ao autor — charter Anti-hook; hoje sem teste que cite.
