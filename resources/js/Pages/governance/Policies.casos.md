---
id: resources-js-pages-governance-policies-casos
casos: Policies · Regras do ActionGate · /governance/policies
irmaos: Policies.charter.md (lei) · governance-policies-gap.md (GAP-SPEC canon)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-11-19"
---

# Casos de uso — /governance/policies

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda o veredito chegar ao manifesto) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** Ordem de fonte do `how-trabalhar.md` §"Quando [W] pede o contrato da tela": a lei é o [`Policies.charter.md`](Policies.charter.md); o escopo é a **US-GOV-002** (`memory/requisitos/Governance/SPEC.md`); a copy literal é o protótipo Cowork `prototipo-ui/cowork/Wagner/governance-page.jsx` (faixa `:296-372`), mapeado item a item pelo GAP-SPEC canon [`governance-policies-gap.md`](../../../../memory/requisitos/Governance/governance-policies-gap.md) de 2026-09-06. O `.tsx` entrou **só para confirmar comportamento**, nunca para derivar caso — teste tautológico é lápide §5 de 2026-06-05.

> **O contrato JSON que o playbook mandava usar não existe.** A thread 03a ancorava a copy em `governance-cockpit.contract.json`, que a errata do Code de 2026-09-08 (§6) já dava como natimorto: a thread 01 mudou o destino, e a pasta de estágio dela saiu na #7224. Medido hoje: `governance/design/contracts/` tem 38 contratos e **nenhum de governança**. A copy literal destes UCs sai do protótipo — que existe, é o dono da forma ([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)) e o GAP-SPEC já resolveu linha a linha.

> **Nenhum UC nasce ✅, e isso é honestidade, não pendência.** O G-7 deriva o `✅` do manifesto `scripts/casos-test-results.json`, que só carimba UC colhido de JUnit. A lane que roda os casos vitest desta tela — [`governance-filtros-gate.yml`](../../../../.github/workflows/governance-filtros-gate.yml) — **não emite `--reporter=junit` nem sobe artifact**, então o veredito dela não chega ao painel. É o mesmo buraco que a `sells-v3-dominio-gate` e a `jana-conversas-gate` já fecharam dentro do próprio arquivo delas; aqui fica **declarado** e fora deste PR (é a lane, não o contrato — outro assunto). Até lá o teto honesto é 🧪.

## UC-GPOL-01 — O catálogo mostra cada regra inteira, e a desligada continua na lista
Status: 🧪 (vitest `governance-filtros.test.tsx` — "o catálogo inteiro aparece sem busca" + "desligada continua na lista")
Cada linha expõe `rule_key` (mono), `name`, `description`, `version` e `triggered_count`, agrupada por
categoria. A ordenação vem do backend (`PolicyToggleService::listPolicies` — `enabled DESC`, `category`,
`rule_key`), não da tela. O charter proíbe esconder regra desligada em §UX Anti-patterns
("❌ Esconder rules disabled — Wagner precisa ver todas pra reativar"): é justamente a desligada que o
operador precisa achar.
**Pronto quando:** com uma regra `enabled=false` e nenhum termo de busca, a chave dela aparece na lista.

## UC-GPOL-02 — Os 4 KPIs do MVP abrem com a tela
Status: 🧪 (e2e `governance-policies.spec.ts` — "a tela de políticas abre com os 4 KPIs do MVP")
`Rules total` · `Ativas` · `Triggered total` · `Categorias`, vindos de `PolicyToggleService::kpisFor`
(`count`, `where enabled`, `sum(triggered_count)`, `count` dos grupos). São os mesmos 4 indicadores do
protótipo (`governance-page.jsx:324-329`, lá rotulados "Regras no total · Ativas · Disparos · Categorias") —
o rótulo difere, a capacidade não, e o GAP-SPEC registrou isso como paridade.
**Pronto quando:** `GET /governance/policies` renderiza os 4 rótulos e cada valor bate com o payload `kpis`.

## UC-GPOL-03 — Alternar é ação direta: nunca abre modal de confirmação
Status: ⬜ (e2e escrito, mas **inerte nesta lane**: `test.skip` explícito — nenhum seeder popula `mcp_governance_rules`, medido em 2026-09-08, então a tela cai no `EmptyState` e não há switch para clicar)
O charter lista modal de confirmação como anti-padrão explícito ("❌ Confirmação modal pra toggle —
toggle é reversível; modal só atrita; canon = ação direta + flash"). O `router.post` roda com
`preserveScroll` + `preserveState`, o estado é otimista e há rollback com toast no erro.
**Pronto quando:** com ≥1 regra em `mcp_governance_rules`, clicar no switch não abre `role=dialog` — nem no
clique, nem depois que o POST responde. Destrava sozinho no dia em que houver seed.

## UC-GPOL-04 — A tela avisa, na hora, que alternar não deixa rastro
Status: 🧪 (vitest — "avisa que alternar não deixa rastro")
`mcp_governance_rule_history` **não existe** — zero migration no repo; o que há é o TODO em
`PoliciesController.php:19` e `PolicyToggleService.php:17` ("Fase 5+1"). Enquanto for assim, ligar ou
desligar uma política muda o enforcement em runtime e a auditoria fica cega justamente para essa mudança.
O charter já registrava isso como anti-padrão; o aviso torna o fato visível a quem opera, com a copy do
protótipo (`governance-page.jsx:325-329`). O bloco sai da tela no dia em que a tabela existir.
⚠️ **Fato novo, 2026-11-19 (lote L0):** a trilha passou a existir — não na `mcp_governance_rule_history`, mas
no `activity_log` (UC-GPOL-11). O aviso da tela ficou **falso** desde então. Tirá-lo ou reescrevê-lo é mudança
de FORMA com fonte no protótipo (UI-0029): o pedido vai ao Cowork e o PR da tela vem depois; até lá, este UC
descreve uma copy que não corresponde mais ao comportamento.
**Pronto quando:** havendo ≥1 regra, o texto "Alternar não deixa rastro" está visível acima da lista.

## UC-GPOL-05 — Não existe alternar em lote
Status: 🧪 (Pest `Modules/Governance/Tests/Feature/PoliciesRotasContratoTest.php` — "UC-GPOL-05 · …": a única rota de escrita é o toggle, com um `{id}` só. O lado da tela, sem seleção múltipla, segue sem teste)
Non-Goal do charter: "❌ Bulk toggle — 1 rule por vez (força reflexão; bulk ops vão pra cron/script
artisan)". Cada alternância é um POST para um `{id}`, e a rota de toggle não aceita coleção. É invariante
de enforcement, não conveniência de UI: desligar N regras de uma vez é exatamente a operação que o charter
quer cara.
**Pronto quando:** não existe controle de seleção múltipla na tela, e `POST /governance/policies/{id}/toggle`
segue sendo a única porta de escrita (um id por requisição).

## UC-GPOL-06 — Ler exige a permissão da sidebar; alternar exige a de edição
Status: 🧪 (Pest `Modules/Governance/Tests/Feature/PoliciesRotasContratoTest.php` — "UC-GPOL-06 · …", lido do registry. Antes o id só estava em comentário do e2e e o UC ficava na fila ⛓. Caso negativo do toggle também em `tests/Feature/Governance/PoliciesContratoTest.php` — "UC-GPOL-06 · sem governance.policies.edit o toggle recusa com 403", na lane `Governance · Pest (MySQL)`, desde 2026-11-19)
Medido no `routes.php`: `GET /policies` → `can:governance.dashboard.view`; `POST /policies/{id}/toggle` →
`can:governance.policies.edit`.
⚠️ **Divergência com o SPEC, registrada e não consertada aqui:** a tabela §Permissões do `SPEC.md` atribui a
listagem a `governance.policies.view`, permission que a rota **não** usa. Pela precedência (teste verde >
casos > charter > SPEC) o teste e a rota ganham; alinhar o SPEC é decisão [W], porque trocar a permission
da rota revogaria acesso em silêncio.
⚠️ **Ressalva D-GATE, e ela é estrutural:** `AuthServiceProvider` registra um `Gate::before` que devolve
`true` para a role `Admin#{business_id}` em qualquer ability fora de backup/superadmin/manage_modules —
logo o `can:` barra o não-admin sem a permission, e **não** barra o admin de um business. _Correção
2026-11-19:_ o passo 1 da ADR 0392 foi fechado pelo #6962 e a ADR 0415 tirou do bypass só as permissões de
plataforma — `governance.*` segue liberada para `Admin#{empresa}`. Fechar isso é o passo 3 da 0392, decisão
[W] em aberto.
**Pronto quando:** o registry vivo (`gatherMiddleware()`) mostra `can:governance.dashboard.view` na rota de
índice e `can:governance.policies.edit` na de toggle — e o caso negativo real só depois do D-GATE.

## UC-GPOL-07 — O toggle é limitado a 10 por minuto
Status: 🧪 (Pest `Modules/Governance/Tests/Feature/PoliciesRotasContratoTest.php` — "UC-GPOL-07 · …": `throttle:10,1` no toggle e `60,1` na leitura, do registry)
`throttle:10,1` contra os `60,1` da leitura, porque alternar uma policy afeta o enforcement em runtime — o
próprio `routes.php` declara o motivo ("operação sensível"). É propriedade do registry de rotas; o lugar
barato de prová-la é o mesmo Pest que já lê `gatherMiddleware()`, não o browser.
**Pronto quando:** o middleware da rota `governance.policies.toggle` inclui `throttle:10,1`, lido do registry.

## UC-GPOL-08 — A busca filtra em memória e nunca esconde o catálogo por acidente
Status: 🧪 (vitest — "busca por chave filtra", "busca casa por CATEGORIA também", "busca sem resultado mostra o vazio e devolve o catálogo ao limpar")
Capacidade decidida em 2026-09-09 no GAP-SPEC (estava "**Decidir.** Construir ou rejeitar por escrito") e
construída como front puro: filtra `rule_key` / `name` / `description` / `category` sobre o catálogo já
carregado, sem re-consultar o backend. O anti-hook de esconder desligadas fica respeitado por construção —
sem termo digitado a lista volta inteira, e o hint da toolbar diz isso ("Desligadas continuam na lista —
são elas que você precisa achar para reativar", copy do protótipo `:342`). Sem resultado, o vazio é
`no-results` com "Limpar busca", e limpar devolve o catálogo.
**Pronto quando:** digitar um termo que casa só uma regra esconde as demais; digitar termo que não casa nada
mostra "Nenhuma política bate com essa busca"; clicar em "Limpar busca" traz o catálogo de volta.

## UC-GPOL-09 — Catálogo vazio não é o vazio de busca
Status: 🧪 (vitest — "catálogo vazio NÃO é o vazio de busca")
São dois estados distintos com mensagens distintas, e confundi-los mente sobre a causa. Sem nenhuma regra
cadastrada a tela mostra "Sem rules ainda" (pedagógico: quando o decision flow ADS criar rules, elas
aparecem aqui) e **não** renderiza a toolbar de busca — não há o que buscar. O vazio de busca (UC-GPOL-08)
só existe com catálogo povoado. O GAP-SPEC registra o vazio de catálogo como "vivo à frente" do protótipo,
que nunca o desenhou.
**Pronto quando:** com `rules_by_category: []` aparece "Sem rules ainda" e o campo de busca não existe.

## UC-GPOL-10 — Alternar regra inexistente responde 404
Status: 🧪 (Pest `tests/Feature/Governance/PoliciesContratoTest.php` — "UC-GPOL-10 · toggle de id inexistente responde 404 e não grava trilha")
Lote L0 da auditoria do Governance (aprovado pelo [W] em 2026-11-19). Até ali o `PolicyToggleService` fazia
UPDATE por id sem conferir existência, e a tela recebia "Policy #N ativada" para uma regra que não existe.
**Pronto quando:** `POST /governance/policies/{id}/toggle` com id sem linha em `mcp_governance_rules` responde
404 e nenhuma linha de trilha nasce.

## UC-GPOL-11 — Toda alternância que muda o estado deixa rastro: quem, quando, antes→depois
Status: 🧪 (Pest `tests/Feature/Governance/PoliciesContratoTest.php` — os 2 casos "UC-GPOL-11 …")
Charter, anti-padrão "Toggle sem registrar histórico — audit fica cego". A trilha usa o mecanismo que o repo já
tem: uma linha só-inserção em `activity_log` (log `governance`, evento `updated`, causer = quem alternou,
`old.enabled` → `attributes.enabled`, mais `policy_id` e `rule_key`). Sem `business_id`: `mcp_governance_rules`
é de plataforma e não tem a coluna, então a linha não aparece na /auditoria de nenhuma empresa. A
`mcp_governance_rule_history` prevista ("Fase 5+1") não foi criada — o `activity_log` cobre o que ela cobriria.
**Pronto quando:** alternar uma regra grava exatamente 1 linha com autor, data e os dois estados; pedir o estado
que a regra já tem não grava nada.
