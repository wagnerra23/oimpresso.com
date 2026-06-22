---
page: /forja
component: resources/js/Pages/team-mcp/Forja/Cockpit.tsx
owner: wagner
status: draft
last_validated: "2026-06-16"
parent_module: TeamMcp
related_adrs:
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0081-identity-mesh-mcp-actors"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
related_ficha: memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
tier: A
charter_version: 1
---

# Page Charter — `/forja` cockpit (DRAFT · Onda Forja · 6 abas reais)

> Cockpit do cowork loop (humano ↔ agente) — **as 6 abas são reais**: Triagem (proposta + dossiê), Backlog (agrupável Onda/Fase/Papel/Prioridade/Módulo), Quadro (board F0→F3.5), Changelog (PRs/ADRs/sessões), MCP (contrato/tokens/auditoria — **MOCKADO por design**), Saúde (KPIs + WIP por fase + automação). Cada aba projeta `mcp_tasks` project=FORJA + git/ADR/sessão + gates (`ScorecardBuilderService`) — **sem dado fantasma**. **Absorção em TeamMcp** (não é módulo novo). Backend: `ForjaController` + `Modules/TeamMcp/Services/Forja/*Service`. Persona: Wagner [W] (superadmin, `copiloto.mcp.usage.all`). Ref: [forja-cockpit-visual-comparison.md](../../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

## Mission

Cockpit **read-only** de observabilidade/governança do próprio loop de desenvolvimento. **Projeta** estado que já existe (`mcp_tasks` + git/PR/ADR/sessão + gates/memory-health) — **sem dado fantasma**. Header fixo (Forja + subtítulo do loop) + 6 abas: Triagem · Backlog · Quadro (F0→F4) · Changelog · MCP · Saúde.

## Goals — Features (faz)

- **Shell navegável** (PR-A): entry "Forja" na sidebar + topnav de 6 abas + rotas `/forja`, `/forja/{backlog,quadro,changelog,mcp,saude}` + landing (Triagem).
- Cada rota renderiza o mesmo shell `Cockpit.tsx` com a aba ativa via prop `tab` (topnav highlight por URL).
- **Triagem REAL** (esta PR): a aba Triagem (`/forja`) projeta `mcp_tasks` project=FORJA em estado de triagem (`McpTask::triage()`: sem owner OU sem priority OU backlog) via `Inertia::defer` (`tickets`/`triagemCount`). Linha = ID mono · badge de tipo (Tela=roxo `bg-primary/10`, Bug=âmbar, Refino=azul) · título · tag de módulo · selo `[CC]` · botão roxo **Analisar** → **dossiê lateral** (`ForjaDossier`, reusa o padrão Analista de ProjectMgmt) com **Aprovar→backlog / Rejeitar / Fundir** (`/forja/{taskId}/{dossier,aprovar,rejeitar,fundir}`). Navegação `J/K` + `Enter` (abre dossiê). Header: sino com badge, busca **⌘K** (trigger do command palette do AppShellV2), primária roxa **Novo issue**. Eyebrow: `DESENVOLVIMENTO · MCP · PROJEÇÃO DO GIT`.
- Abas reais restantes entregues incrementalmente (B Saúde · C Changelog · D Backlog · E Quadro · G MCP).

### Notas de fidelidade (vs protótipo aprovado)

- **Badge da aba Triagem = "3" (ESTÁTICO).** O contador da aba vem de `config/core_topnavs.php['Forja']` (`'badge' => 3`), config carregada no boot — não tem dado por-request, então o "3" é fixo (= nº de propostas-semente FORJA-150/151/152). O contador **vivo** da fila chega na própria aba via prop deferida `triagemCount` (usado no badge do sino). Quando o shell suportar badge dinâmico no topnav (`shell.topnavs` por-request), trocar o estático pelo contador vivo. Hoje o `ModuleTopNav` suporta `item.badge`, mas a fonte (`core_topnavs.php` via `LegacyMenuAdapter::buildTopNavs`) é estática.
- **Botão "Novo issue"** aponta pra `/forja` (sem fluxo de criação dedicado nesta PR — criação de issue é onda futura). Visualmente fiel (primária roxa), funcionalmente um no-op de navegação até o fluxo de criação existir.
- **Dados de demo:** sem o seeder `ForjaDemoTicketsSeeder`, a fila nasce vazia ("Nada pra triar"). Rodar no deploy pra fidelidade screenshot (ver Métricas).

## Non-Goals — Features (NÃO faz)

- ❌ Tabela/entidade nova — issues = projeção sobre `mcp_tasks` (Tier 0: sem schema sem ADR mãe).
- ❌ Enforce de permissão de tool — a aba MCP é **design/MOCKADO**; o enforce real é do servidor TeamMcp.
- ❌ Merge ou `constituicao.edit` pela UI — soberania: merge só `[W2]`, ADR/PROTOCOL/BRIEFING só `[W]`.
- ❌ Filtro business_id — cockpit é repo-wide intencional (ADR 0093).

## UX targets

- DS v6: roxo canon na aba ativa / primárias, status Stripe-dot, `tabular-nums`, ramp `--fs`, **sem cor crua**.
- Topnav auto via `config/core_topnavs.php['Forja']` + `useAutoModuleNav` (raiz `/forja`, segmento próprio pra não colidir com `/team-mcp`).
- Layout via `inline-flex`/primitivos; PageHeader **canon** (`@/Components/PageHeader`). Locators `data-testid`.

## Anti-hooks (NÃO faz automaticamente)

- ❌ A Triagem **só muta sob confirmação humana [W]** (Aprovar/Rejeitar/Fundir via dialog) — o agente PROPÕE, [W] aprova. Listar/abrir dossiê é read-only. ❌ NÃO inventa métrica/issue (valor×esforço e risco Tier-0 são **sugestão derivada rotulada**, não dado medido). ❌ NÃO persiste/loga token raw (Tier 0 ADR 0081). ❌ As outras 5 abas seguem read-only/placeholder.

## Restrições Tier 0

- Permissão `copiloto.mcp.usage.all` no construtor do `ForjaController`.
- Repo-wide cross-business INTENCIONAL (ADR 0093) — governança da plataforma.
- `mcp_*` sem `business_id` por design.

## Métricas de sucesso (validação Wagner)

- ✅ As 6 rotas `/forja/*` respondem (sem 500 / tela branca).
- ✅ Entry "Forja" aparece na sidebar e o topnav de 6 abas navega + destaca a ativa.
- ✅ Sem cor crua / PageHeader canon (conformance/foundation/layout/pageheader verdes).
- ✅ Acesso negado (403) sem `copiloto.mcp.usage.all`.
- ✅ **Triagem fiel ao protótipo:** após `php artisan db:seed --class="Modules\TeamMcp\Database\Seeders\ForjaDemoTicketsSeeder"`, `/forja` lista FORJA-152 (Tela·KB), FORJA-151 (Bug·Financeiro), FORJA-150 (Refino·Atendimento), cada um com badge de tipo colorido + tag de módulo + selo `[CC]` + botão roxo Analisar; aba mostra badge 3.
- ✅ **Analisar** abre o dossiê lateral (valor×esforço, risco Tier-0, duplicatas, Aprovar→backlog / Rejeitar / Fundir) — `GET /forja/{id}/dossier` + `POST /forja/{id}/{aprovar,rejeitar,fundir}`.
