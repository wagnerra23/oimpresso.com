---
id: resources-js-pages-governance-custos-casos
casos: Custos · Custos de IA do business · /governance/custos
irmaos: Custos.charter.md (lei) · memory/requisitos/Governance/RUNBOOK-custos.md (F1)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado no manifesto — trio nasce neste PR; Pest na jana-pest, vitest na governance-filtros-gate"
---

# Casos de uso — /governance/custos

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda o veredito chegar ao manifesto) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** A lei é o [`Custos.charter.md`](Custos.charter.md); o procedimento é o
> [`RUNBOOK-custos.md`](../../../../memory/requisitos/Governance/RUNBOOK-custos.md); o escopo é a
> **US-COPI-070** (`memory/requisitos/Jana/SPEC.md` — a US nasceu no Jana e o código é da Governança desde
> 2026-08-05, [ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B). Um
> UC só ganhou id quando o comportamento tem contrato em **duas** dessas fontes; o resto ficou como
> `[BACKLOG]` sem id. O `CustosController@index` e o `CustosService::painel` entraram só para confirmar
> comportamento.

> **Nenhum UC nasce ✅.** O G-7 deriva o `✅` do manifesto `scripts/casos-test-results.json`, que só carimba
> UC colhido de JUnit. O Pest `Modules/Jana/Tests/Feature/Governance/GovernanceCustosContratoTest.php` mora na árvore da Jana porque
> precisa de MySQL com `jana_conversas` e testa o `CustosService` da Jana: a lane `jana-pest` roda tudo de
> `Modules/Jana/Tests/Feature/**` (o filtro de paths dela inclui `CustosController` e `Custos.tsx`). O vitest
> `tests/js/governance-custos.test.tsx` roda na `governance-filtros-gate`. Até o veredito chegar ao manifesto, o teto
> honesto é 🧪 — "escrito e morde", nunca "passa".

> **Sem valor monetário neste contrato.** Os critérios falam de tokens, mensagens, dias, props e status
> HTTP. O cálculo de custo (tokens × preço × câmbio) é coberto por
> `Modules/Governance/Tests/Feature/CustosControllerTest.php`, fora deste trio.

## UC-GCUS-01 [T0][must] — Cada auditor só vê o consumo do próprio business
Status: 🧪 (Pest `GovernanceCustosContratoTest` — "UC-GCUS-01 [T0]: o consumo de outro business não entra nos números da tela")
**Persona:** auditor do business (Wagner). Non-Goal do charter: "Não expõe custo cross-business — cada
auditor vê só o `business_id` da própria sessão". O RUNBOOK §4.1 fixa a origem: o `business_id` vem da
**sessão**, nunca do request; sem `withoutGlobalScopes`, sem `?business_id=` na query ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
**Pronto quando:** dado consumo de IA no tenant 98 e no 99 no mesmo período, quando o usuário do 98 abre a
tela pedindo `?business_id=99`, então tokens e mensagens exibidos são exatamente os do 98.
**Regressão que defende:** vazamento cross-tenant do gasto de IA.

## UC-GCUS-02 [must] — Abrir a tela exige `jana.admin.custos.view`
Status: 🧪 (Pest — "UC-GCUS-02: sem jana.admin.custos.view a tela responde 403")
Charter (Anti-hooks): "Não renomeia a permissão no movimento de tela: o gate segue `jana.admin.custos.view`";
RUNBOOK §3 e §6 repetem, com o porquê — renomear revogaria acesso em silêncio e exige ADR + migration.
**Pronto quando:** usuário sem a permissão recebe 403; com ela, a rota renderiza o componente `governance/Custos`.
⚠️ **Ressalva D-GATE, estrutural:** o `Gate::before` do `AuthServiceProvider` devolve `true` para a role
`Admin#{business_id}`, então o `can:` barra o não-admin sem a permissão e **não** barra o admin de um
business. O teste mede o não-admin. Fechar o outro caminho é o passo 1 da ADR 0392, decisão [W] em aberto.

## UC-GCUS-03 [must] — KPIs, série diária e tabela por usuário chegam no primeiro render
Status: 🧪 (Pest — "UC-GCUS-03: KPIs, série diária e tabela por usuário chegam no 1º render, sem defer")
Charter (Anti-hooks): "Não reintroduz `Inertia::defer` em `kpis`/`por_usuario`/`serie_diaria` sem o wrap
`<Deferred>` no frontend"; RUNBOOK §4.2 registra o incidente (defer sem wrap → `undefined` no 1º render →
tela branca em prod). Os 4 KPIs do charter (custo, mensagens, tokens, usuários ativos) vêm juntos.
**Pronto quando:** o 1º render traz as três props com os 4 KPIs, e nenhuma delas aparece em `deferredProps`.
Se alguém quiser deferir, o teste cai e obriga a trazer o `<Deferred>` no mesmo PR.

## UC-GCUS-04 [should] — Preset fora da lista volta ao mês atual
Status: 🧪 (Pest — "UC-GCUS-04: preset fora da lista cai no mês atual")
Charter (Goals): os presets são `mes_atual`, `mes_anterior`, `90d` e `custom`. RUNBOOK §4.1: o preset é
validado contra essa lista. Um bookmark com valor estranho não pode quebrar a tela nem inventar período.
**Pronto quando:** `?preset=qualquer_coisa` devolve o mesmo período e o mesmo filtro ativo que `?preset=mes_atual`.

## UC-GCUS-05 [should] — Trocar o filtro é recarga parcial e não retrafega o pricing
Status: 🧪 (vitest `governance-custos.test.tsx` — "aplicar o range custom é partial reload que NÃO pede o pricing de volta"; Pest — "UC-GCUS-05: a troca de filtro é partial reload")
Charter (Goals + Automation hooks): "Partial reload (`router.get` com `only: [...]`) ao trocar filtro — não
retrafega `pricing` (config estática)", e o reload é server-driven sobre `kpis`, `por_usuario`,
`serie_diaria`, `periodo`, `filters`. RUNBOOK §4.4 diz o mesmo.
**Pronto quando:** aplicar o range De/Até chama a rota da Governança com exatamente essas 5 props no `only`,
sem `pricing`; e o servidor, recebendo esse pedido parcial, não devolve `pricing`.
O vitest foi mordido por mutação em 2026-10-14: acrescentar `pricing` ao `only` derruba o caso (1 failed |
3 passed), e o restauro foi conferido por hash.

## UC-GCUS-06 [must] — O range custom De/Até define o período, dia a dia
Status: 🧪 (Pest — "UC-GCUS-06: o range custom De/Até define o período")
Charter (Goals): no modo `custom`, range De/Até via form, e "Gráfico de área SVG inline do gasto diário no
período". US-COPI-070 DoD: "gráfico diário". RUNBOOK §2: `custom` revela o form De/Até.
**Pronto quando:** com consumo dentro e fora de jan/2001, pedir `custom` de 2001-01-01 a 2001-01-31 conta só o
de dentro, e a série diária tem um ponto por dia do range (31).

## UC-GCUS-07 [must] — A soma da tabela por usuário bate com os KPIs
Status: 🧪 (Pest — "UC-GCUS-07: a soma da tabela por usuário bate com os KPIs do período")
Charter (Goals): tabela "Por usuário" com conversas, mensagens, tokens, mais linha de total. RUNBOOK §2:
"Total bate com os KPIs". US-COPI-070 DoD: tabela por usuário + card de totais.
**Pronto quando:** com dois usuários consumindo no período, mensagens e tokens somados das linhas são iguais
aos KPIs, e "usuários ativos" conta os dois.
⚠️ A linha de total do `<tfoot>` usa os próprios KPIs para mensagens e tokens; quem garante a coerência é o
servidor, e é lá que o caso mede.

## UC-GCUS-08 [should] — A URL antiga redireciona 301 com o filtro
Status: 🧪 (Pest — "UC-GCUS-08: a URL antiga /ia/admin/custos redireciona 301 preservando o filtro")
Charter (Pendências): "Redirect 301 da rota antiga `/ia/admin/custos` ativo". SPEC US-COPI-070 `Implementado
em:` registra o redirect "preservando a query".
**Pronto quando:** `GET /ia/admin/custos?preset=90d` responde 301 para `/governance/custos?preset=90d`.

## UC-GCUS-09 [must] — Abrir e filtrar não grava nada
Status: 🧪 (Pest — "UC-GCUS-09: abrir e filtrar a tela não grava nada")
Charter (Anti-hooks): "Não muta dados em GET — a rota é read-only (dashboard)". Non-Goal: "Não edita/ajusta
preços de modelo nem câmbio pela tela". RUNBOOK §2 trata a tela como painel de leitura.
**Pronto quando:** abrir a tela e trocar o preset não altera a contagem de `jana_conversas` nem de `jana_mensagens`.

## UC-GCUS-10 [should] — A tela não depende de código do Jana no front
Status: 🧪 (vitest — "UC-GCUS-10 · a tela não importa nada de @/Pages/Jana")
Charter (Anti-hooks): "Não importa nada de `@/Pages/Jana/**`". RUNBOOK §4.3: "Zero import de `@/Pages/Jana/**`
na tela nova" (a tela usa a `GovernancaSubNav`, não o header do Jana).
**Pronto quando:** nenhum import do `Custos.tsx` aponta para `@/Pages/Jana`.

## Backlog (sem id — uma fonte só, ou decisão pendente de [W])

- `[BACKLOG]` Sem polling nem auto-refresh — só re-busca quando o usuário troca filtro (anti-hook do charter; sem segunda fonte e sem teste barato).
- `[BACKLOG]` Não exporta CSV/PDF, não projeta custo futuro, não alerta estouro desta tela — Non-Goals herdados **pendentes de ratificação [W]** (charter §Non-Goals e §Pendências).
- `[BACKLOG]` Estados vazios: "Nenhum consumo de IA no período." na tabela e "Sem dados no período." no gráfico (só no RUNBOOK §5).
- `[BACKLOG]` Contexto de pricing (modelo base e câmbio) lido de `config('copiloto.ai.*')` e exibido no topo (charter Goals; a US fala em preço lido de config, mas não em exibi-lo).
