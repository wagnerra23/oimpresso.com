---
id: modules-kb-pages-ads-admin-graph-casos
casos: Graph · Knowledge Graph (Cognitive Control Panel #3) · /ads/admin/graph
irmaos: Graph.charter.md (lei) · memory/requisitos/KB/SCOPE.md (dono do controller)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane kb-pest (PHP / Pest KB · MySQL)"
---

# Casos de uso — /ads/admin/graph

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda o veredito chegar ao manifesto) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** A lei é o [`Graph.charter.md`](Graph.charter.md) (status `draft`). O dono do
> controller é o KB: `memory/requisitos/KB/SCOPE.md` `contains` lista *"Admin/GraphController — knowledge
> graph ADS …; URL /ads/admin/graph mantida"*. A rota viva mora em `Modules/Forja/Http/routes.php`
> (`ads.admin.graph.index`, grupo `prefix: ads` com a stack canônica UltimatePOS), recebida na parte 6 da
> [ADR 0363](../../../../../../../memory/decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md)
> quando o `Modules/ADS` foi removido — o comentário ali registra que esta é *"tela viva de módulo
> sobrevivente"*. O `.tsx` não foi usado para derivar caso nenhum (lápide §5 2026-06-05).

> **Não confundir com `/kb/graph`** (`resources/js/Pages/kb/Graph.casos.md`, UC-KBG-*): aquela é fachada
> sem props (US-KB-006, SDD §9 D-3); esta monta grafo de 5 fontes reais (memória do KB, meta-skills e
> policy do Governance, tools da Forja, e o eixo "skills" — este último morto, ver abaixo).

> **Força do veredito.** O teste citado vive em `Modules/KB/Tests/Feature/KbAdsGraphContratoTest.php`,
> listado no step Pest de `.github/workflows/kb-pest.yml` (entrada adicionada no mesmo PR deste casos,
> junto com `Modules/Forja/Http/routes.php` nos filtros de paths, porque a rota mora lá). Se aquela lane bloqueia merge
> é do [`required-checks-baseline.json`](../../../../../../../governance/required-checks-baseline.json),
> não desta prosa. Até a lane colher o JUnit no manifesto, o teto honesto é 🧪.

## UC-KBGR-01 — A tela exige autenticação
Status: 🧪 (Pest `KbAdsGraphContratoTest` — GR1)
Charter (backend): *"middleware `auth` — V1 superadmin"*; a rota está no grupo com a stack canônica
(`web`, `SetSessionData`, `auth`, …), e o `GraphController` registra `auth` no construtor. O grafo expõe
a estrutura de governança (regras, tools, contagem de memória) — servir isso anônimo seria vazamento.
**Pronto quando:** `GET /ads/admin/graph` sem sessão não devolve 200 nem 500 (302/401/403).

## UC-KBGR-02 — A tela abre com 4 KPIs e o nó Memory no centro
Status: 🧪 (Pest `KbAdsGraphContratoTest` — GR2)
Charter §Goals: *"4 KPIs de topo: docs em Memory, skills, meta-skills, tools"* e §Mission: *"Memory
(centro) ↔ Skills ↔ Meta-skills ↔ Tools ↔ Policy"*. O contrato é a faixa ter 4 contagens e o grafo ter
um nó central de memória — não o nome de cada chave do payload.
**Pronto quando:** autenticado, a resposta serve o componente `ads/Admin/Graph`, com exatamente 4 KPIs
inteiros ≥ 0, exatamente 1 nó do tipo memória, e a rota nomeada `ads.admin.graph.index` registrada.

## UC-KBGR-03 [T0] [must] — Não cruza dados entre businesses
Status: 🧪 (Pest `KbAdsGraphContratoTest` — GR3)
Charter §Non-Goals: *"❌ NÃO cruza dados entre businesses — patterns e memory docs escopados por
business_id da sessão"*. Tier 0 ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
Teste em tenant fictício 98 com vizinho 99 ([ADR 0358](../../../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
**Pronto quando:** com 3 docs de memória no business 98 e 7 no 99, a sessão do 98 vê no nó Memory a
contagem do 98 — e nunca a soma dos dois.
**Regressão que defende:** remover o `where('business_id', …)` da contagem de memória.

## UC-KBGR-04 [must] — Abrir o grafo é leitura pura
Status: 🧪 (Pest `KbAdsGraphContratoTest` — GR4 + GR4b)
Charter §Non-Goals *"é read-only; edição é nas telas próprias"* e §Anti-hooks *"❌ Sem mutação em GET"*.
**Pronto quando:** `GET /ads/admin/graph` não altera a contagem de `mcp_memory_documents` nem de
`mcp_governance_rules` (com ≥1 doc semeado — anti-vácuo) e não enfileira Job.

## Fora do contrato — capacidade aposentada e itens de fonte única

- [BACKLOG] **Eixo "skills" (top 15 por uso) é promessa não cumprida.** O charter §Goals ainda lista o
  KPI "skills" e as arestas `derived_from`/`archives`, alimentados por `mcp_decision_patterns` — tabela
  do núcleo do ADS **dropada** em `database/migrations/2026_07_31_235000_drop_ads_dual_brain_core_tables.php`
  (ADR 0363). O controller guarda com `Schema::hasTable` e o eixo some (KPI sempre 0). **Não se escreve
  caso para capacidade aposentada** (lápide §5 2026-08-02, não ressuscitar o ADS). Podar o charter ou
  substituir a fonte é decisão [W] — registrado nos dois lados, não resolvido aqui.
- [BACKLOG] Arrastar nó não persiste posição (charter §Non-Goals + §Anti-hooks) — comportamento de front,
  fonte única (charter); sem teste vitest/e2e existente para a tela.
- [BACKLOG] Sem polling / auto-refresh do grafo (charter §Anti-hooks) — idem, fonte única.
- [BACKLOG] Cor por tipo via tokens DS, nunca hex cru (charter §Goals) — forma; a régua é o DS/ui-lint, não UC.

