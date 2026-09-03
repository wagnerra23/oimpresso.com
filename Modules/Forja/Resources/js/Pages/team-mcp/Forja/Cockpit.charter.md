---
id: resources-js-pages-team-mcp-forja-cockpit-charter
page: /forja
component: Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx
related_prototype: prototipo-ui/cowork/forja-page.jsx
owner: wagner
status: draft
last_validated: "2026-09-03"
parent_module: TeamMcp
related_adrs:
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0081-identity-mesh-mcp-actors"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
related_ficha: memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
tier: A
charter_version: 2
---

# Page Charter — `/forja` cockpit (DRAFT · Onda Forja)

> Cockpit do cowork loop (humano ↔ agente) — abas: Triagem (proposta + dossiê), Backlog (agrupável Onda/Fase/Papel/Prioridade/Módulo), Quadro (board F0→F3.5), Changelog (PRs/ADRs/sessões), MCP (contrato/tokens/auditoria — **MOCKADO por design**), Saúde (KPIs + WIP por fase + automação). Cada aba projeta `mcp_tasks` project=FORJA + git/ADR/sessão + gates (`ScorecardBuilderService`) — **sem dado fantasma**. **Absorção em TeamMcp** (não é módulo novo). Backend: `ForjaController` + `Modules/TeamMcp/Services/Forja/*Service`. Persona: Wagner [W] (superadmin, `jana.mcp.usage.all`). Ref: [forja-cockpit-visual-comparison.md](../../../../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

> ⚠️ **Errata 2026-07-27.** Este charter afirmava "6 abas reais" incluindo `/forja/saude`, e a permissão `copiloto.mcp.usage.all`. Ambas ficaram stale. Medido em 2026-07-27: **5** rotas GET de aba sob `/forja` (`route:list --path=forja` no CT 100 — sem `forja.saude`; Saúde foi fundida no `/team-mcp/scorecard`) e **9** itens de topnav em `config/core_topnavs.php['Forja']` desde a fusão com o hub TeamMcp (2026-06-16). A permissão virou `jana.mcp.usage.all` no [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853) — `git grep -l "copiloto.mcp.usage.all" -- '*.php'` = **0**. Detalhe e recibos no [Cockpit.casos.md](Cockpit.casos.md).

> ⚠️ **Errata 2026-09-03 (Onda 7) — duas frases deste charter caducaram quando `/forja/saude` passou a existir.** A errata de 2026-07-27 acima **segue verdadeira na data dela** (naquele dia a Saúde estava fundida no `/team-mcp/scorecard` e a rota era item fantasma); o que mudou não é o fato, é o mundo. A [#6572](https://github.com/wagnerra23/oimpresso.com/pull/6572) construiu a view `saude` do protótipo como tela própria (2026-09-02), e o `casos.md` foi atualizado no mesmo PR — **este charter não**. As duas frases stale: a **Mission**, que listava "Saúde (TeamMcp absorvido)" entre as abas emprestadas, e a **métrica de sucesso**, que afirmava "5 rotas" e "Saúde não tem rota própria". Corrigidas abaixo, com o oráculo ao lado.

> ⚠️ **Errata 2026-09-03 (Onda 11 · Triagem — fechada declarando, [W]).** Duas afirmações deste
> charter ficaram stale e uma decisão nova entra aqui:
>
> - **A lista de abas acima é fato de 2026-06-16.** Medido em 2026-09-03, o topnav vivo é
>   `Modules/Forja/Resources/menus/topnav.php` com **6** destinos (Aprovações · Trabalho · Saúde ·
>   MCP · Changelog · Integrador) — a Triagem **saiu do topnav** na Onda 2 (2026-09-02) e
>   `Backlog`/`Quadro`/`Tarefas` colapsaram em segmentos de `Trabalho`. As rotas seguem vivas; saiu
>   o item do topo, não a tela. (`DataController::modifyAdminMenu()` ainda lista `Triagem`, mas
>   está atrás de um `return` incondicional na L116 — é código morto, não a nav servida.)
> - **A Triagem NÃO foi replicada nem absorvida, e isso é o desfecho, não pendência.** O motivo
>   medido está no [Cockpit.casos.md](Cockpit.casos.md), no bloco do `UC-FORJA-08`: a fonte não
>   monta `FjTriagemView`; `ForjaAprovacoesService::fila()` (`status='pending_approval'`) não
>   contém `McpTask::scopeTriage()`; e o slot `Proposta` do protótipo já é, aqui, o estado
>   *posterior* à triagem. Triagem é o **F0**, Aprovações é o **gate** — etapas distintas do mesmo
>   funil. Reabrir exige onda de **construção** + decisão [W], não re-skin.

## Mission

Cockpit **read-only** de observabilidade/governança do próprio loop de desenvolvimento. **Projeta** estado que já existe (`mcp_tasks` + git/PR/ADR/sessão + gates/memory-health) — **sem dado fantasma**. Header fixo (Forja + subtítulo do loop) + as abas do hub: Triagem · Backlog · Quadro (F0→F4) · Changelog · MCP (próprias) + Tarefas · Equipe · CC Sessions (TeamMcp absorvido) — e **Saúde**, que desde a Onda 7 é aba PRÓPRIA (`/forja/saude`), não mais o desvio pro Scorecard.

## Goals — Features (faz)

- **Shell navegável** (PR-A): entry "Forja" na sidebar + topnav do hub + rotas `/forja`, `/forja/{backlog,quadro,changelog,mcp}` + landing (Triagem).
- Cada rota renderiza o mesmo shell `Cockpit.tsx` com a aba ativa via prop `tab` (topnav highlight por URL).
- **Triagem REAL** (esta PR): a aba Triagem (`/forja`) projeta `mcp_tasks` project=FORJA em estado de triagem (`McpTask::triage()`: sem owner OU sem priority OU backlog) via `Inertia::defer` (`tickets`/`triagemCount`). Linha = ID mono · badge de tipo (Tela=roxo `bg-primary/10`, Bug=âmbar, Refino=azul) · título · tag de módulo · selo `[CC]` · botão roxo **Analisar** → **dossiê lateral** (`ForjaDossier`, reusa o padrão Analista de ProjectMgmt) com **Aprovar→backlog / Rejeitar / Fundir** (`/forja/{taskId}/{dossier,aprovar,rejeitar,fundir}`). Navegação `J/K` + `Enter` (abre dossiê). Header: sino com badge, busca **⌘K** (trigger do command palette do AppShellV2), primária roxa **Novo issue**. Eyebrow: `DESENVOLVIMENTO · MCP · PROJEÇÃO DO GIT`.
- Abas reais restantes entregues incrementalmente (B Saúde · C Changelog · D Backlog · E Quadro · G MCP).

### Notas de fidelidade (vs protótipo aprovado)

- **Badge da aba Triagem = "3" (ESTÁTICO).** O contador da aba vem de `config/core_topnavs.php['Forja']` (`'badge' => 3`), config carregada no boot — não tem dado por-request, então o "3" é fixo (= nº de propostas-semente FORJA-150/151/152). O contador **vivo** da fila chega na própria aba via prop deferida `triagemCount` (usado no badge do sino). Quando o shell suportar badge dinâmico no topnav (`shell.topnavs` por-request), trocar o estático pelo contador vivo. Hoje o `ModuleTopNav` suporta `item.badge`, mas a fonte (`core_topnavs.php` via `LegacyMenuAdapter::buildTopNavs`) é estática.
- **Botão "Novo issue"** aponta pra `/forja` (sem fluxo de criação dedicado nesta PR — criação de issue é onda futura). Visualmente fiel (primária roxa), funcionalmente um no-op de navegação até o fluxo de criação existir.
  > **Errata 2026-09-03.** A frase acima ("no-op de navegação") era verdade quando escrita e
  > caducou: desde a Triagem REAL, `/forja` é a landing que projeta `McpTask::triage()`, então o
  > botão navega para a lista de propostas — não é no-op. O que **permanece ausente** é o fluxo de
  > **criação**: no protótipo, `Novo issue` abre o compositor `forja-novo-issue.jsx` (overlay), e
  > esse overlay não tem receptor aqui. Divergência declarada, não bug.
- **Dados de demo:** sem o seeder `ForjaDemoTicketsSeeder`, a fila nasce vazia ("Nada pra triar"). Rodar no deploy pra fidelidade screenshot (ver Métricas).

## Non-Goals — Features (NÃO faz)

- ❌ Tabela/entidade nova — issues = projeção sobre `mcp_tasks` (Tier 0: sem schema sem ADR mãe).
- ❌ Enforce de permissão de tool — a aba MCP é **design/MOCKADO**; o enforce real é do servidor TeamMcp.
- ❌ Merge ou `constituicao.edit` pela UI — soberania: merge só `[W2]`, ADR/PROTOCOL/BRIEFING só `[W]`.
- ❌ Filtro business_id — cockpit é repo-wide intencional (ADR 0093).

## UX targets

> **Bundle (2026-09-02 · PARIDADE §11 Onda 1).** O CSS do protótipo (`forja-page.css`) vive em
> `resources/css/cowork-forja-bundle.css`, cópia integral, importado pelo `Cockpit.tsx`. Decisão [W]
> 2026-09-02: o protótipo é a implementação que sobrevive; as views migram pro vocabulário
> `fj-`/`ap-`/`tf-` onda a onda, e cada onda fecha com `design-diff --compare --check` = 0 bug.

- DS v6: roxo canon na aba ativa / primárias, status Stripe-dot, `tabular-nums`, ramp `--fs`, **sem cor crua**.
- Topnav auto via `config/core_topnavs.php['Forja']` + `useAutoModuleNav` (raiz `/forja`, segmento próprio pra não colidir com `/team-mcp`).
- Layout via `inline-flex`/primitivos; PageHeader **canon** (`@/Components/PageHeader`). Locators `data-testid`.

## Anti-hooks (NÃO faz automaticamente)

- ❌ A Triagem **só muta sob confirmação humana [W]** (Aprovar/Rejeitar/Fundir via dialog) — o agente PROPÕE, [W] aprova. Listar/abrir dossiê é read-only. ❌ NÃO inventa métrica/issue (valor×esforço e risco Tier-0 são **sugestão derivada rotulada**, não dado medido). ❌ NÃO persiste/loga token raw (Tier 0 ADR 0081). ❌ As outras 5 abas seguem read-only/placeholder.

## Restrições Tier 0

- Permissão `jana.mcp.usage.all` no construtor do `ForjaController`.
- Repo-wide cross-business INTENCIONAL (ADR 0093) — governança da plataforma.
- `mcp_*` sem `business_id` por design.

## Métricas de sucesso (validação Wagner)

- ✅ As **8** rotas de aba `/forja/*` respondem (sem 500 / tela branca) — Triagem · Backlog · Quadro · Changelog · MCP · Handoffs · Integrador · **Saúde**. Contagem NÃO se lê aqui: sai do dataset que o teste percorre — `sed -n '/^function forjaRotasAbas/,/^}/p' Modules/Forja/Tests/Feature/ForjaRoutesSmokeTest.php | grep -cE "=> ..forja."` (medido **8** em 2026-09-03).
- ✅ Entry "Forja" aparece na sidebar e o topnav do hub (9 itens: 5 próprios + 4 do TeamMcp absorvido) navega + destaca o ativo.
- ✅ Sem cor crua / PageHeader canon (conformance/foundation/layout/pageheader verdes).
- ✅ Acesso negado (403) sem `jana.mcp.usage.all`.
- ✅ **Triagem fiel ao protótipo:** após `php artisan db:seed --class="Modules\Forja\Database\Seeders\ForjaDemoTicketsSeeder"`, `/forja` lista FORJA-152 (Tela·KB), FORJA-151 (Bug·Financeiro), FORJA-150 (Refino·Atendimento), cada um com badge de tipo colorido + tag de módulo + selo `[CC]` + botão roxo Analisar; aba mostra badge 3.
- ✅ **Analisar** abre o dossiê lateral (valor×esforço, risco Tier-0, duplicatas, Aprovar→backlog / Rejeitar / Fundir) — `GET /forja/{id}/dossier` + `POST /forja/{id}/{aprovar,rejeitar,fundir}`.
