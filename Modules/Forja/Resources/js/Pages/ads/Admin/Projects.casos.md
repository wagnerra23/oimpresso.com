---
id: resources-js-pages-ads-admin-projects-casos
casos: Portfólio de Projects (admin MCP) · /ads/admin/projects
irmaos: Projects.charter.md (lei) · ProjectShow.charter.md (o detalhe, para onde a criação leva)
tecnica: Caso de uso = narrativa de quem usa + critério de aceite verificável (Dado/Quando/Então)
por_que: "de quem é o project" e "o que criar dispara" não mudam num refactor de layout
owner: wagner
last_run: "2026-10-07"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Forja · MySQL)"
---

# Casos de Uso & Aceite — Portfólio de Projects (Forja, URL `/ads`)

> Os UC derivam do [`Projects.charter.md`](Projects.charter.md) (Goals, Non-Goals, Anti-hooks) e
> da **US-ADS-003** ([`memory/requisitos/ADS/SPEC.md`](../../../../../../../memory/requisitos/ADS/SPEC.md):
> *"lista os Projects … KPIs agregados … cria Project validando"*). Conferidos no
> `Admin\ProjectsController@index|store` + `ProjectService` — **nunca** derivados do `.tsx`
> (§5 2026-06-05). A URL fica sob `/ads` por ADR 0087 (drift resolution sem mover URL); o
> controller é da Forja desde 2026-07-31.

> ⚖️ **Onde roda.** Teste: [`Modules/Forja/Tests/Feature/AdsAdminProjectsContratoTest.php`](../../../../../Tests/Feature/AdsAdminProjectsContratoTest.php),
> MySQL-only (pula no SQLite), na allowlist da lane `PHP / Pest (Forja · MySQL)`
> (`.github/workflows/forja-pest.yml`) — **advisory**: reprova visível, não bloqueia merge
> (não consta em `governance/required-checks-baseline.json`). Tenant 98 × adversário 99
> (ADR 0358), nunca biz=4. O wiring das 4 rotas já é travado por
> `tests/Feature/Ads/AdsProjectsRoutesContratoTest.php`; os UC daqui são de comportamento.

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

---

## UC-ADPJ-01 · A lista traz os projects do meu business e não traz os de outro `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** admin do time abrindo o portfólio.
- **Aceite:** Dado um project no meu tenant e outro no tenant adversário · Quando abre `/ads/admin/projects` · Então o meu aparece e o do outro não.
- **Teste:** `Modules/Forja/Tests/Feature/AdsAdminProjectsContratoTest.php` — `UC-ADPJ-01 · a lista traz os projects do meu business …`
- **Regressão que defende:** vazamento cross-tenant (ADR 0093). O charter: *"Não mostra projects de outro business — scopado por `businessId` da sessão"*. O project próprio é o controle positivo: sem ele, lista vazia faria o negativo passar.

## UC-ADPJ-02 · Criar com nome e objetivo grava um project `draft` meu, leva pro detalhe e não decompõe `[must]`
Status: 🧪 sem veredito
- **Persona:** admin iniciando uma iniciativa estratégica.
- **Aceite:** Dado nome + objetivo macro · Quando envia o form "Novo Project" · Então um project é gravado com `status=draft` **no meu business**, a resposta leva para `/ads/admin/projects/{id}`, e o project nasce com **zero parts** (a decomposição por IA não roda).
- **Teste:** `AdsAdminProjectsContratoTest.php` — `UC-ADPJ-02 · criar com nome e objetivo grava um project draft …`
- **Regressão que defende:** o Anti-hook do charter — *"Criar project NÃO dispara a decomposição por IA — é ação separada e cara (Sonnet), no detalhe, com confirm"* — e a US-ADS-004, que põe o decompose no detalhe.

## UC-ADPJ-03 · Sem nome ou sem objetivo, nada é gravado `[must]`
Status: 🧪 sem veredito
- **Persona:** admin que enviou o form incompleto.
- **Aceite:** Dado um envio sem `nome` (ou sem `objetivo_macro`) · Quando o POST chega · Então volta erro de validação no campo que faltou e a contagem de projects do meu business não muda.
- **Teste:** `AdsAdminProjectsContratoTest.php` — `UC-ADPJ-03 · sem nome ou sem objetivo …`
- **Regressão que defende:** project sem objetivo entraria na esteira de decomposição sem o insumo que o agente usa.

## UC-ADPJ-04 · Os KPIs contam só os projects do meu business, por status `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** admin lendo a banda de KPIs (total, ativos, draft, concluídos).
- **Aceite:** Dados projects `draft`/`active`/`completed` no meu tenant e o mesmo trio no adversário · Quando abre a lista · Então cada KPI é igual à contagem do **meu** business naquele status (comparado com o banco, não com número fixo).
- **Teste:** `AdsAdminProjectsContratoTest.php` — `UC-ADPJ-04 · os KPIs contam só os projects do meu business …`
- **Regressão que defende:** KPI calculado sobre a tabela inteira (vazamento por agregado, que a lista sozinha não flagra).

## UC-ADPJ-05 · Logado sem a permissão da Forja, a lista e a criação dão 403 `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** usuário logado da empresa que não recebeu a permissão do módulo Forja.
- **Aceite:** Dado um usuário com a permissão `jana.mcp.usage.all` · Quando abre `/ads/admin/projects` · Então 200 (controle positivo). Dado um usuário **sem** ela · Quando abre a lista ou envia o form "Novo Project" · Então **403** nos dois, e nenhum project é gravado.
- **Teste:** `Modules/Forja/Tests/Feature/ForjaProjectsAcessoTest.php` — `UC-ADPJ-05 · logado sem a permissão da Forja …`
- **Regressão que defende:** decisão [W] D10 (2026-10-07, thread 13 do playbook Forja): *"exigir permissão do módulo Forja"*. Até então a rota só exigia login.

---

## Backlog de casos (sem id — entram quando tiverem teste e ≥2 fontes)

- **[BACKLOG] Lista vazia mostra EmptyState** — só o charter; asserção de cliente.
- **[BACKLOG] Criar registra audit LGPD (`EVENT_PROJECT_CREATED`, objetivo redacted)** — só o charter (Automation hooks).
- ~~**[BACKLOG] A tela não tem gate de permissão além do login**~~ — respondido pelo [W] em 2026-10-07 (D10): exige a permissão do módulo Forja. Virou **UC-ADPJ-05**.
- **[BACKLOG] Código do project gerado por contagem global** — `ProjectService::generateCodigo` usa `count(mcp_projects)+1` (todos os businesses) sobre uma coluna `UNIQUE`; **hipótese de leitura, não medida**: se algum project for apagado no mês, o próximo código pode colidir e a criação falhar. Sem fonte canônica que defina o formato do código — não vira UC.
