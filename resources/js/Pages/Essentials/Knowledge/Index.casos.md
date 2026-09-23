---
id: resources-js-pages-essentials-knowledge-index-casos
casos: Essentials · Base de conhecimento interna · /essentials/knowledge-base
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-09-23"
---

# Casos de uso — /essentials/knowledge-base · Base de conhecimento interna

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei: §Goals *"ACL via
> Controller: `whereHas users` + `orWhere created_by` + `orWhere share_with public`"* e §Métricas)
> + o `KnowledgeBaseController@index` real (`Inertia::render('Essentials/Knowledge/Index')`, prop
> `books` deferida) — **nunca** do `.tsx` nem do protótipo (§5 2026-06-05). **Não confundir com
> `Modules/KB`** (grafo RAG da Jana).

> ⚖️ **Onde roda.** Teste: [`tests/Feature/Essentials/KnowledgeIndexContratoTest.php`](../../../../../tests/Feature/Essentials/KnowledgeIndexContratoTest.php),
> MySQL-only (pula no SQLite). Medido 2026-09-23: o arquivo **não está** na allowlist de
> nenhuma lane de PR com MySQL — a lane natural é `PHP / Pest (Essentials · MySQL)`
> (`.github/workflows/essentials-pest.yml`), que **não** é required. Até ser listado lá, o UC
> não tem veredito de CI. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-EKB-01 · A grade mostra só os livros que a ACL me libera `[must]`
Status: 🧪 sem veredito
- **Persona:** operador novo — abre a base pra ler os manuais que a equipe publicou pra ele.
- **Aceite:** Dado, no meu tenant, um livro `public` de colega, um livro `private` **meu**, um livro `only_with` de colega que me inclui (`essentials_kb_users`), um `private` de colega e um `only_with` de colega que **não** me inclui · Quando a tela pede a prop deferida `books` · Então chegam os três primeiros e **não** chegam os dois últimos.
- **Teste:** `tests/Feature/Essentials/KnowledgeIndexContratoTest.php` — `UC-EKB-01 · a grade mostra …`
- **Regressão que defende:** charter §Métricas *"User sem ACL (não criador, não público, não na lista) NÃO vê book"*. Os três positivos são controle: sem eles, uma lista vazia faria os negativos passarem.

## UC-EKB-02 · Livro de outro business não aparece `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** operador — nunca vê manual de outra empresa, mesmo que o conteúdo seja público lá.
- **Aceite:** Dado um livro `public` de minha autoria no meu tenant e outro livro `public` de minha autoria em **outro** `business_id` · Quando a tela pede `books` · Então chega o do meu tenant e **não** chega o do outro.
- **Teste:** `tests/Feature/Essentials/KnowledgeIndexContratoTest.php` — `UC-EKB-02 · livro de outro business …`
- **Regressão que defende:** vazamento cross-tenant (ADR 0093). O livro alheio é público **e** meu de propósito — as duas pernas da ACL o deixariam passar, então só o filtro de `business_id` o segura.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Excluir um livro remove seções e artigos junto (cascade `parent_id` no DB) — charter §Métricas; hoje sem teste que cite.
- [BACKLOG] `content` chega sanitizado (sem `<script>`/handlers) — já defendido por `Modules/Essentials/Tests/Feature/KnowledgeXssSanitizationTest.php`, que não cita UC-id; vira UC quando aquele teste (fora deste prefixo) passar a citá-lo.
- [BACKLOG] Busca por título na grade — existe no `.tsx` (estado `busca`) e **não** está no charter; fica fora até [W] decidir se é contrato.
