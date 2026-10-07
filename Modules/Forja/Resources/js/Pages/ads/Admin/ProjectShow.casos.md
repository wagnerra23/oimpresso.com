---
id: resources-js-pages-ads-admin-project-show-casos
casos: Detalhe do Project (admin MCP) · /ads/admin/projects/{id}
irmaos: ProjectShow.charter.md (lei) · Projects.charter.md (a lista, de onde se chega aqui)
tecnica: Caso de uso = narrativa de quem usa + critério de aceite verificável (Dado/Quando/Então)
por_que: "de quem é o project" e "em que ordem o plano se lê" não mudam num refactor de layout
owner: wagner
last_run: "2026-10-07"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Forja · MySQL)"
---

# Casos de Uso & Aceite — Detalhe do Project (Forja, URL `/ads`)

> Os UC derivam do [`ProjectShow.charter.md`](ProjectShow.charter.md) (Goals, Non-Goals) e da
> **US-ADS-004** ([`memory/requisitos/ADS/SPEC.md`](../../../../../../../memory/requisitos/ADS/SPEC.md):
> *"devolve o detalhe via `findDetail()` e 404 quando o id não existe"*, rota `whereNumber`).
> Conferidos no `Admin\ProjectsController@show` + `ProjectService::findDetail` — **nunca**
> derivados do `.tsx` (§5 2026-06-05).

> ⚖️ **Onde roda.** Teste: [`Modules/Forja/Tests/Feature/AdsAdminProjectShowContratoTest.php`](../../../../../Tests/Feature/AdsAdminProjectShowContratoTest.php),
> MySQL-only (pula no SQLite), na allowlist da lane `PHP / Pest (Forja · MySQL)`
> (`.github/workflows/forja-pest.yml`) — **advisory**: reprova visível, não bloqueia merge
> (não consta em `governance/required-checks-baseline.json`). Tenant 98 × adversário 99
> (ADR 0358), nunca biz=4.

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

---

## UC-ADPS-01 · O detalhe do meu project abre com as parts na ordem da decomposição `[must]`
Status: 🧪 sem veredito
- **Persona:** admin lendo o plano executável de uma iniciativa.
- **Aceite:** Dado um project meu com 3 parts gravadas fora de ordem (`ordem` 3, 1, 2) · Quando abre `/ads/admin/projects/{id}` · Então a tela é o detalhe daquele project e as parts chegam como `P1, P2, P3`.
- **Teste:** `Modules/Forja/Tests/Feature/AdsAdminProjectShowContratoTest.php` — `UC-ADPS-01 · o detalhe do meu project abre …`
- **Regressão que defende:** o charter lista a decomposição em `<ol>` por *"código/ordem"*; parts na ordem de gravação embaralhariam as dependências do plano.

## UC-ADPS-02 · Project de outro business, inexistente ou com id não numérico → 404 `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** usuário de outro business tentando o id pela URL.
- **Aceite:** Dado um project meu e um do tenant adversário · Quando abro o meu, abre (controle positivo) · Quando abro o do adversário · Então **404**. Também 404 para id que não existe e para id não numérico (`/ads/admin/projects/abc`, barrado na rota).
- **Teste:** `AdsAdminProjectShowContratoTest.php` — `UC-ADPS-02 · project de outro business, inexistente …`
- **Regressão que defende:** vazamento cross-tenant (ADR 0093). O charter: *"Não mostra project de outro business"*; a US-ADS-004: *"404 quando o id não existe"* + `whereNumber`.

## UC-ADPS-03 · O decompose só age em project da minha empresa `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** usuário de outro business mandando `POST /ads/admin/projects/{id}/decompose` com o id de um project alheio.
- **Aceite:** Dado um project meu e um do tenant adversário, os dois já com uma part · Quando decompõo o meu, a resposta é "já decomposto" (controle positivo: a requisição chega ao serviço) · Quando decompõo o do adversário · Então a resposta é "project não encontrado", e as parts e o `updated_at` dele não mudam.
- **Teste:** `ProjectDecomposeTenantTest.php` — `UC-ADPS-03 · decompose no serviço …` e `UC-ADPS-03 · POST …/decompose de project de outra empresa …`
- **Regressão que defende:** vazamento cross-tenant (ADR 0093). Até 2026-10-07 o `ProjectDecomposerService::decompose` lia `mcp_projects` só por `id`: medido no CT 100, como empresa 98 ele lia o project da 99 e as parts dele (`already_decomposed` em vez de `project_not_found`). Sem part gravada, seguiria para o agente de IA e gravaria parts no project alheio.

---

## Backlog de casos (sem id — entram quando tiverem teste e ≥2 fontes)

- **[BACKLOG] Project já decomposto não é decomposto de novo (botão some; o POST devolve erro sem chamar a IA)** — só o charter (Non-Goals + Anti-hooks).
- **[BACKLOG] "Decompor com IA" só aparece com o project em `draft` e sem parts, com `confirm()` de custo** — só o charter; asserção de cliente.
- **[BACKLOG] Decisões linkadas ao project** — o charter promete *"decisões linkadas ao project (linka pra `/ads/admin/decisoes/{id}`)"*, mas o `ProjectService::findDetail` devolve `decisions: []` sempre desde a ADR 0363 (a tabela de decisões do ADS foi dropada). Promessa do charter sem cumprimento: **não escolho o vencedor** — podar o charter ou religar a fonte é decisão do [W].
