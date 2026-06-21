---
casos: Forja · cockpit do cowork loop · /forja
irmaos: Cockpit.charter.md (lei) · Cockpit.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-17"
---

# Casos de uso — /forja (cockpit Forja · shell)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Onda Forja: cockpit completo — **as 6 abas reais** (Triagem · Backlog · Quadro F0→F3.5 · Changelog · MCP · Saúde), projetando `mcp_tasks` project=FORJA + git/ADR/sessão + gates (sem dado fantasma; MCP é MOCKADO por design). Persona: Wagner [W] (superadmin). A Triagem só muta sob confirmação [W]. Referência: [forja-cockpit-visual-comparison.md](../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

## UC-FORJA-01 — As 6 rotas /forja respondem (shell no ar)
Status: ⬜ (smoke pós-merge — abrir cada rota em prod)
Antes desta PR não havia rota `/forja`. `ForjaController` serve `/forja` (Triagem), `/forja/backlog`, `/forja/quadro`, `/forja/changelog`, `/forja/mcp`, `/forja/saude`.
**Pronto quando:** cada uma das 6 rotas renderiza o shell (sem 500 / tela branca).

## UC-FORJA-02 — Topnav de 6 abas aparece e navega
Status: ⬜ (manual/visual)
O topnav vem de `config/core_topnavs.php['Forja']` via `useAutoModuleNav` (casa raiz `/forja`). 6 abas: Triagem · Backlog · Quadro · Changelog · MCP · Saúde.
**Pronto quando:** as 6 abas aparecem no header, navegam entre as rotas e destacam a ativa por URL.

## UC-FORJA-03 — Entry "Forja" na sidebar
Status: ⬜ (manual/visual)
`DataController@modifyAdminMenu` injeta o dropdown "Forja" (ícone martelo, atalho `G F`, ghosts = as 6 abas), separado do hub Equipe.
**Pronto quando:** "Forja" aparece na sidebar e leva ao cockpit; ghosts listam as 6 abas.

## UC-FORJA-04 — Sem colisão de topnav com /team-mcp
Status: 🧪 (cobertura: raiz `/forja` ≠ `/team-mcp` no `useAutoModuleNav`)
`useAutoModuleNav` casa pelo 1º segmento da URL. Forja usa raiz própria `/forja` pra não herdar o topnav do hub Equipe (`/team-mcp`).
**Pronto quando:** em `/forja/*` o topnav mostrado é o da Forja (6 abas), não o da Equipe.

## UC-FORJA-05 — Read-only (o shell não muta nada)
Status: ⬜ (manual)
Nenhuma rota desta onda escreve estado; todas são GET de render.
**Pronto quando:** não há ação na tela que escreva no banco.

## UC-FORJA-06 — DS v6 (sem cor crua · PageHeader canon)
Status: 🧪 (cobertura: eslint `ds/*` = 0 + conformance + layout + pageheader ratchets)
PageHeader canon (`@/Components/PageHeader`), layout via `inline-flex`, tokens semânticos, zero paleta crua / `rounded-xl+`.
**Pronto quando:** `conformance-gate`, `layout-primitives`, `pageheader-gate` e `eslint` verdes pro Cockpit.tsx.

## UC-FORJA-07 — Acesso (auth + permissão)
Status: ⬜ (manual — rota sob `auth` + `copiloto.mcp.usage.all`)
`ForjaController` exige login + `copiloto.mcp.usage.all` (mesma do Scorecard/Team). Repo-wide cross-business intencional (ADR 0093) pro superadmin.
**Pronto quando:** usuário sem `copiloto.mcp.usage.all` recebe 403.

## UC-FORJA-08 — Triagem lista as propostas FORJA fiéis ao protótipo
Status: ⬜ (smoke pós-merge — depende do seeder rodar; sem DB no worktree)
`/forja` projeta `mcp_tasks` project=FORJA em estado de triagem (`McpTask::triage()`) via `Inertia::defer` (`tickets`). Após `db:seed --class=…ForjaDemoTicketsSeeder`: FORJA-152 (Tela·KB·[CC]), FORJA-151 (Bug·Financeiro·[CC]), FORJA-150 (Refino·Atendimento·[CC]).
**Pronto quando:** as 3 linhas aparecem com ID mono · badge de tipo colorido (Tela=roxo·Bug=âmbar·Refino=azul) · título · tag de módulo · selo `[CC]` · botão roxo Analisar; aba mostra badge 3.

## UC-FORJA-09 — Analisar abre o dossiê lateral (Aprovar/Rejeitar/Fundir)
Status: 🧪 (cobertura: endpoints `/forja/{id}/{dossier,aprovar,rejeitar,fundir}` espelham TriageController PR-5a; aguarda Pest verde)
Clicar **Analisar** (ou `Enter` na linha em foco) abre `ForjaDossier` → `GET /forja/{id}/dossier` (valor×esforço sugerido, risco Tier-0 heurístico, duplicatas, docs/sessões). Aprovar→backlog (status→todo, exige dono+prio), Rejeitar (→cancelled), Fundir (duplicata + evento) — cada um sob dialog de confirmação [W].
**Pronto quando:** dossiê carrega dados reais e as 3 ações respondem (Pest cobrindo `aprovar`/`rejeitar`/`fundir` como no TriageController).

## UC-FORJA-10 — Triagem só muta sob confirmação [W]
Status: ⬜ (manual)
Listar e abrir o dossiê é read-only. Nenhuma escrita acontece sem o `AlertDialog` de confirmação (Aprovar/Rejeitar/Fundir). valor×esforço e risco Tier-0 são **sugestão derivada rotulada**, não dado medido.
**Pronto quando:** não há mutação sem confirmação humana; nada inventado é apresentado como medido.

## UC-FORJA-11 — Badge "3" da aba é estático (limitação documentada)
Status: ⬜ (nota de fidelidade)
O badge "3" da aba Triagem vem de `config/core_topnavs.php` (estático = nº de propostas-semente). O contador vivo da fila chega via prop deferida `triagemCount` (usada no badge do sino). O topnav não suporta badge por-request hoje (`LegacyMenuAdapter::buildTopNavs` lê config estática).
**Pronto quando:** o "3" aparece na aba (fiel ao protótipo) e o sino reflete o contador vivo; quando o shell ganhar badge dinâmico, migrar o da aba.

## UC-FORJA-12 — Aba MCP lista os handoffs reais de `cowork_handoffs` (Fase 1 · ADR 0283)
Status: 🧪 (cobertura: `ForjaMcpServiceTest` prova a projeção — exclui superseded, maior-version-por-slug, stale derivado, gate verde/vermelho/rodando/na, serialização, heartbeat; aguarda Pest verde + smoke visual pós-merge)
A aba MCP deixou de ser 100% mock: `ForjaController@mcp` projeta `cowork_handoffs` (+ heartbeat do ingest) via `Inertia::defer` (`handoffs`/`heartbeat`) — `ForjaMcpService`. Status REAIS `pending/applied/rejected/stale/superseded`; `stale` derivado na leitura (>3d); gate derivado do `gate_status` com a MESMA regra verde do `handoff-ack` (`conformance && critique_score>=80 && a11y`). A seção fica no topo (`data-testid="forja-mcp-handoffs"`); contrato/tokens/auditoria seguem MOCKADO embaixo (sem regressão de 1º paint — `Deferred` só na seção nova).
**Pronto quando:** `/forja/mcp` lista os handoffs reais (status correto + gate do `gate_status` + ⚿ sig + `N arq` + PR drill), filtros por status com contagem funcionam, empty-state mostra o heartbeat ("transporte sem sinal" vira alerta), e o contrato lista `handoff-pending`/`handoff-ack`. Levers (re-disparar/devolver/supersede) ficam `disabled`+TODO (Fase 2); **SEM merge** (1-clique do [W]).

## UC-FORJA-13 — Badge `conflito` quando o ack mente sobre o gate (Gap 2 · ADR 0283)
Status: 🧪 (cobertura: `ForjaMcpServiceTest` — conflito em check vermelho/pendente, mantém verde com checks verdes, só cruza ack verde, degrada sem token/API/branch-protection; GitHub API mockada via `Http::fake`, sqlite lane `ci-sqlite-pest.list`)
O `gate_status` é AUTO-REPORTADO pelo [CC] e pode divergir dos required checks REAIS do PR no GitHub. `ForjaMcpService::deriveGate` cruza o ack VERDE com o estado real do PR (`PrChecksResolver` → GitHub API: PR → branch protection → check-runs). Se a realidade não está verde (vermelho/pendente) → badge `conflito` (dot destructive pulsando, drill pro PR, hint no hover). Best-effort: sem token/rede/branch-protection legível → segue o `gate_status` (comportamento da Fase 1, sem conflito falso por check advisory).
**Pronto quando:** um handoff `applied` com `gate_status` verde + `pr_url` cujo required check está vermelho/pendente mostra `conflito ack×checks`; com checks verdes mostra `gate ok`; e a leitura nunca quebra quando o GitHub está indisponível.
