---
id: resources-js-pages-essentials-todo-show-casos
casos: Essentials · Detalhe da tarefa · /essentials/todo/{id}
irmaos: Show.charter.md (lei) · Show.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /essentials/todo/{id} · Detalhe da tarefa

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Show.charter.md`](Show.charter.md) + `ToDoController@show` — **nunca** do `.tsx` (§5 2026-06-05). Teste: [`TodoFormsContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php). Tenant 98 × 2 (ADR 0358), nunca biz=4.

> ⚖️ **Onde roda.** `PHP / Pest (Essentials · MySQL)` (`.github/workflows/essentials-pest.yml`), MySQL-only — pula no SQLite. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-ETDS-01 · O detalhe traz os comentários e só o meu pode ser removido `[must]`
Status: 🧪 sem veredito
- **Persona:** colaborador — limpa um comentário que escreveu errado, sem poder apagar o do colega.
- **Aceite:** Dada uma tarefa com um comentário meu e um de colega · Quando abro o detalhe · Então chegam os dois, o meu com `can_delete = true` e o do colega com `can_delete = false`.
- **Teste:** `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` — `UC-ETDS-01 · …`
- **Regressão que defende:** charter Non-Goal *"NÃO remove comentário/anexo de terceiro (só quando `can_delete`)"*.

## UC-ETDS-02 · Tarefa de outro business não abre `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** colaborador — link de outra empresa não abre aqui.
- **Aceite:** Dada uma tarefa minha e uma do outro tenant · Quando abro cada uma · Então a minha responde 200 (controle) e a alheia 404.
- **Teste:** `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` — `UC-ETDS-02 · …`
- **Regressão que defende:** charter Non-Goal *"NÃO exibe tarefa fora do escopo do usuário/business"* (ADR 0093).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Não-admin só vê tarefa própria ou atribuída (`scopedQueryForUser`) — vira UC com teste de usuário sem papel Admin.
- [BACKLOG] Deletes de comentário/anexo por GET — pendência de hardening do charter.
