---
id: resources-js-pages-essentials-todo-edit-casos
casos: Essentials · Editar tarefa · /essentials/todo/{id}/edit
irmaos: Edit.charter.md (lei) · Edit.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /essentials/todo/{id}/edit · Editar tarefa

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Edit.charter.md`](Edit.charter.md) + `ToDoController@edit`/`@update` + US-ESS-003 do SPEC — **nunca** do `.tsx` (§5 2026-06-05). Teste: [`TodoFormsContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php). Tenant 98 × 2 (ADR 0358), nunca biz=4.

> ⚖️ **Onde roda.** `PHP / Pest (Essentials · MySQL)` (`.github/workflows/essentials-pest.yml`), MySQL-only — pula no SQLite. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-ETDE-01 · Salvar a edição grava os campos e volta pro detalhe `[must]`
Status: 🧪 sem veredito
- **Persona:** colaborador — corrige prioridade e título de uma tarefa.
- **Aceite:** Dada uma tarefa minha · Quando abro a edição e envio título, prioridade e status novos · Então a edição abre com `todo.id` dela, o `PUT` redireciona para `/essentials/todo/{id}` e o banco guarda os três valores.
- **Teste:** `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` — `UC-ETDE-01 · …`
- **Regressão que defende:** charter §Goals *"Submete via `PUT /essentials/todo/{id}`"* e *"retornam ao `show`"*.

## UC-ETDE-02 · Editar tarefa de outro business não acontece `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** colaborador — nunca altera tarefa de outra empresa.
- **Aceite:** Dada uma tarefa no **outro** tenant · Quando abro a edição e envio o `PUT` · Então os dois respondem 404 e o título não muda.
- **Teste:** `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` — `UC-ETDE-02 · …`
- **Regressão que defende:** charter Non-Goal *"NÃO edita tarefa de outro business"* + US-ESS-003 (*"Tarefa de outro business = 404"*).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Renotificar atribuídos ao mudar a lista — pendência do charter, decisão [W].
