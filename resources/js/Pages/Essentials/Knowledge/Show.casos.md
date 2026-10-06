---
id: resources-js-pages-essentials-knowledge-show-casos
casos: Essentials · Leitor da base de conhecimento · /essentials/knowledge-base/{id}
irmaos: Show.charter.md (lei) · Show.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /essentials/knowledge-base/{id} · Leitor da base de conhecimento

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Show.charter.md`](Show.charter.md) + `KnowledgeBaseController@show` — **nunca** do `.tsx` (§5 2026-06-05). Teste: [`KnowledgeFormsContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php). Tenant 98 × 2 (ADR 0358), nunca biz=4.

> ⚖️ **Onde roda.** `PHP / Pest (Essentials · MySQL)` (`.github/workflows/essentials-pest.yml`), MySQL-only — pula no SQLite. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-EKBS-01 · Abrir um artigo mostra o livro dele, com o conteúdo limpo `[must]`
Status: 🧪 sem veredito
- **Persona:** operador — chega num artigo por link e vê onde ele está no livro.
- **Aceite:** Dado livro → seção → artigo com `<script>` no conteúdo · Quando abro o artigo · Então chegam `book.id` do livro, `sectionId` da seção, `articleId` do artigo, e `item.content` traz o texto sem `<script`.
- **Teste:** `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` — `UC-EKBS-01 · …`
- **Regressão que defende:** charter Automation hook *"Resolve o livro de topo a partir de qualquer nó"* + Anti-hook *"NÃO renderiza HTML não-sanitizado"*.

## UC-EKBS-02 · Nó de outro business não abre `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** operador — link de outra empresa não abre aqui.
- **Aceite:** Dado um livro meu e um do outro tenant · Quando abro cada um · Então o meu responde 200 (controle) e o alheio 404.
- **Teste:** `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` — `UC-EKBS-02 · …`
- **Regressão que defende:** charter Non-Goal *"NÃO exibe nó de outro business"* (ADR 0093).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] O `show` não aplica a ACL do índice: um livro `private` de colega abre por id. O charter não promete isso nem o contrário — pergunta para [W].
