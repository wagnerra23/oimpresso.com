---
id: resources-js-pages-essentials-knowledge-edit-casos
casos: Essentials · Editar livro, seção ou artigo · /essentials/knowledge-base/{id}/edit
irmaos: Edit.charter.md (lei) · Edit.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /essentials/knowledge-base/{id}/edit · Editar livro, seção ou artigo

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Edit.charter.md`](Edit.charter.md) + `KnowledgeBaseController@edit`/`@update` — **nunca** do `.tsx` (§5 2026-06-05). Teste: [`KnowledgeFormsContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php). Tenant 98 × 2 (ADR 0358), nunca biz=4.

> ⚖️ **Onde roda.** `PHP / Pest (Essentials · MySQL)` (`.github/workflows/essentials-pest.yml`), MySQL-only — pula no SQLite. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-EKBE-01 · A edição abre com os usuários de acesso já marcados `[must]`
Status: 🧪 sem veredito
- **Persona:** dono do manual — ajusta quem lê sem refazer a lista.
- **Aceite:** Dado um livro meu com um usuário em `essentials_kb_users` · Quando abro a edição · Então chega `kb.id` do livro e `kb.assigned_user_ids` com exatamente esse usuário.
- **Teste:** `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` — `UC-EKBE-01 · …`
- **Regressão que defende:** charter §Goals *"chips de usuários com acesso, iniciados a partir de `assigned_user_ids`"*.

## UC-EKBE-02 · Atualizar nó de outro business não acontece `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** operador — nunca altera manual de outra empresa, mesmo sabendo o id.
- **Aceite:** Dado um livro no **outro** tenant · Quando envio `PUT` com título novo · Então a resposta é 404 e o título no banco não muda.
- **Teste:** `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` — `UC-EKBE-02 · …`
- **Regressão que defende:** charter Non-Goal *"NÃO edita nó de outro business"* (ADR 0093).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Mudar o pai do nó (reparent) — pendência do charter, decisão [W].
