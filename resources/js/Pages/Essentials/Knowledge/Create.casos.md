---
id: resources-js-pages-essentials-knowledge-create-casos
casos: Essentials · Novo livro, seção ou artigo · /essentials/knowledge-base/create
irmaos: Create.charter.md (lei) · Create.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /essentials/knowledge-base/create · Novo livro, seção ou artigo

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Create.charter.md`](Create.charter.md) + `KnowledgeBaseController@create`/`@store` + `StoreKnowledgeBaseRequest` — **nunca** do `.tsx` (§5 2026-06-05). Teste: [`KnowledgeFormsContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php). Tenant 98 × 2 (ADR 0358), nunca biz=4.

> ⚖️ **Onde roda.** `PHP / Pest (Essentials · MySQL)` (`.github/workflows/essentials-pest.yml`), MySQL-only — pula no SQLite. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-EKBC-01 · O form sabe onde o nó vai entrar `[must]`
Status: 🧪 sem veredito
- **Persona:** operador — clica "Adicionar seção" dentro de um livro e o form já sabe o pai.
- **Aceite:** Dado um livro e uma seção minha no tenant · Quando abro o form com `?parent=<seção>` · Então chegam `parent.id` e `parent.kb_type = section`; e sem `parent`, chega `parent = null` com a lista de usuários do dropdown do **meu** business (e só ela).
- **Teste:** `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` — `UC-EKBC-01 · …`
- **Regressão que defende:** charter §Goals *"Deriva automaticamente o `kb_type` a partir do pai"* e *"chips multi-seleção de usuários do business"*.

## UC-EKBC-02 · Salvar um livro restrito grava no meu business com a lista de acesso `[must]`
Status: 🧪 sem veredito
- **Persona:** dono do manual — publica um livro só pra quem escolheu.
- **Aceite:** Dado o form de livro com `share_with = only_with` e um usuário marcado · Quando salvo · Então nasce um nó com `business_id` do meu tenant e `essentials_kb_users` contém exatamente o usuário marcado.
- **Teste:** `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` — `UC-EKBC-02 · …`
- **Regressão que defende:** charter §Goals *"Para tipo `knowledge_base`: seletor Compartilhar com"* + Non-Goal *"NÃO cria nó em outro business"*.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] **Achado, sem teste ainda:** o `store` valida `parent_id` com `exists:essentials_kb,id`, **sem** `business_id`. O `create` (GET) resolve o pai no tenant, mas um POST direto aceita pai de outro business e grava o nó no meu tenant pendurado nele. Não vaza leitura (o `show` do nó dá 404 e o `HasBusinessScope` esconde o filho do outro tenant), mas o charter afirma *"`parent` é resolvido com `where business_id`"* e isso só vale no GET. O caso entra junto com o conserto do `store`.
- [BACKLOG] Seção e artigo herdarem a visibilidade do livro — pendência do próprio charter.
