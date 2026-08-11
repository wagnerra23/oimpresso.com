---
id: requisitos-forja-briefing
module: Forja
status: producao
status_nota: "Uso interno diário do time (Kanban/Backlog/MyWork) + host da infra MCP viva (brief-fetch, handoff loop, identity/token, CC ingest). NÃO cliente-facing."
updated_at: "2026-08-01"
owner: W
piloto: time interno oimpresso (uso próprio)
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
adr_mae: ADR 0070 (Jira-style tasks) · ADR 0088 (rename ProjectMgmt→Forja)
charter: charters ao lado das Pages (Board/Triage/MyWork/Inbox/Backlog/Roadmap/Activity/Burndown/DetailSheet)
spec: SPEC.md + SPEC-COMPLEMENTO.md
---

# BRIEFING — `Forja`

> **Função única:** resumo executivo e índice. Aponta para os donos; não recopia SCOPE, SUPERFICIE, SPEC ou contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

Cockpit de trabalho do **time interno oimpresso** (Wagner + Felipe + Maiara + Eliana + Luiz) estilo Jira — Kanban, Backlog, Roadmap, My Work, Inbox, Triage, Burndown, Activity — como **interface visual** sobre as tabelas `mcp_jira_*` governadas por tools MCP (ADR 0070). Renomeado `ProjectMgmt → Forja` em 2026-07-30 (module.json + PR-5 Pages; URL/alias/permission legacy por compat).

**Desde a última porta (2026-05-16) o módulo deixou de ser só a UI Jira:** absorveu, sem mudar URLs, a infraestrutura MCP do time (identity/token, endpoints `/api/mcp`, Daily Brief, loop de handoff, ingest de sessões Claude Code, hub Equipe, Admin do MCP e o núcleo de registro/decompose do ADS). Fronteira e proveniência de cada peça em [`SCOPE.md`](SCOPE.md).

## Estado atual

- **Superfície de código:** 180 arquivos em 14 papéis — 23 Controllers, 26 Services, 23 FormRequests, 9 telas React, 8 comandos artisan, 51 arquivos Pest. Fonte viva (regenerável): [`SUPERFICIE.md`](SUPERFICIE.md) via `node scripts/governance/module-surface.mjs Forja --check`.
- **Nota do módulo:** não fixar aqui (o `32/100` das portas antigas está stale). Rode `php artisan module:grade Forja --detail`.
- **Consolidação 2026-07-30/31 (ancorada em SCOPE + commits):**
  - MCP endpoints vindos da Jana — `Mcp/{Health,SyncMemoryWebhook}Controller`, `/api/mcp/*` inalteradas (PR #5101).
  - Daily Brief (ex-`Modules/Brief`, ADR 0091) — `BriefFetchController` + `BriefGeneratorService`/`BriefValidator` + `mcp:generate-brief` + `Mcp/Tools/BriefFetchTool` (PR #5098).
  - `Modules/TeamMcp` **deletado** (89 → 0 arquivos, PR #5122); suas capacidades **movidas** (não fundidas) pra cá: identidade MCP + emissão de token (PR #5111), loop de handoff zero-paste (PR #5114), CC ingest (PR #5116), Admin do MCP (PR #5117), hub Equipe (PR #5118), cockpit `/forja` 6 abas (PR #5120).
  - Núcleo de registro do ADS — `ToolRegistry` + `UserScopeService` + `ProjectDecomposerService`/Agent (PRs #5131/#5132/#5134); URLs `/ads/admin/*` e permissions `ads.*` mantidas (ADR 0087).
- **Tela nova desde a porta:** Triage (`TriageController` + `Triage/Index.tsx` + `TriageDossier.tsx`) — tasks órfãs com fluxo assign/aprovar/rejeitar/fundir; paridade da tool MCP `triage` (rotas em `Http/routes.php`).
- **Mesa de Aprovações (2026-08-08):** `/forja/aprovacoes` (`AprovacoesController` + `ForjaAprovacoesService` + `Forja/Aprovacoes/Index.tsx`) — fila de `mcp_tasks` em `pending_approval` por ordem de espera, com admitir/parquear/recusar. É a **superfície** que a [ADR 0368](../../decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) deixou pendente ao fechar a política ("o código vai em PR próprio"); estado, FSM e trava de recusa-sem-motivo já existiam (#5283/#5288). Escrita 100% via `TaskCrudService` — sem 2º caminho. US-FORJA-010.
- **⚠️ Sobreposição conhecida (não resolvida):** as abas do cockpit `/forja` (triagem/backlog/quadro/changelog) sobrepõem Triage/Backlog/Board/Activity deste módulo — MOVIDO, não fundido; fundir = deletar uma implementação = decisão [W] (SCOPE §cockpit).

## Portas canônicas

- **Herança geral (componentes/layouts compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Fronteira/ownership + proveniência das absorções:** [`SCOPE.md`](SCOPE.md)
- **Superfície derivada de código:** [`SUPERFICIE.md`](SUPERFICIE.md)
- **Requisitos:** [`SPEC.md`](SPEC.md) + [`SPEC-COMPLEMENTO.md`](SPEC-COMPLEMENTO.md)
- **Concorrência/mercado:** [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) + [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md)
- **Telas:** `resources/js/Pages/Forja/**` + charters/casos ao lado das Pages
- **Tabelas de dados (donas):** `mcp_jira_*` + 5 herdadas do ADS extinto (`mcp_projects`/`mcp_project_parts`/`mcp_decision_links`/`mcp_tool_executions`/`mcp_user_module_access`) — lista em SCOPE `db_tables_owned`.

## Decisões e riscos que exigem atenção

- ⛔ **Multi-tenant Tier 0 (ADR 0093):** nunca `withoutGlobalScopes` em models `mcp_*` sem `// SUPERADMIN: <razão>`. Exceções POR DESIGN cross-business: `mcp_actors`, `cowork_handoffs` (Identity Mesh ADR 0081 / handoff ADR 0283) — documentadas em SCOPE, não replicar sem base.
- ⛔ **Stack de middlewares completa** em `Http/routes.php` — sem `SetSessionData`, `session('user.business_id')` fica null → vazamento.
- ⚠️ **Schema não é dono da Forja pra `mcp_jira_*`** originalmente — mas as 5 tabelas do ADS extinto foram declaradas `db_tables_owned` aqui de propósito (senão a próxima varredura de deprecação as acha órfãs — errata C5 do plano do ADS).
- ⚠️ **Permission legacy** — `jana.mcp.usage.all` (herdada); não criar permission própria da Forja.
- ⚠️ **URLs legadas preservadas** por compat: `/ads/admin/*`, `/api/mcp/*`, `/team-mcp/*`, `/forja/*`, prefixo web legacy (ver SCOPE `url_prefixes`). Renomear é decisão [W] separada.

## Próxima ação verificável

- **Decisão [W] pendente:** resolver a sobreposição cockpit `/forja` × telas nativas (Triage/Backlog/Board/Activity) — fundir = deletar uma implementação. Evidência de conclusão: uma das duas implementações removida + SCOPE §cockpit atualizado.
  - ⚠️ **Medido 2026-08-08 — são TRÊS implementações, não duas.** Backlog existe em `Pages/Forja/Backlog/Index.tsx` (416 ln), `_components/ForjaBacklog.tsx` (207 ln) **e** `Pages/team-mcp/Tasks/Index.tsx` (647 ln); Quadro em `Board/Index.tsx` (529 ln, com casos) × `ForjaQuadro.tsx` (130 ln); Triagem em `Triage/Index.tsx` (471 ln, com casos) × `ForjaTriage.tsx` (210 ln). **As nativas são as ricas** — o cockpit é a versão enxuta, o que inverte a intuição de que fundir seria "levar tudo pro cockpit". Números via `wc -l`; re-conte em vez de confiar neste retrato.
- **Dívida vizinha (PR próprio):** a procedure do Daily Brief calcula `hitl_pending` como `status='blocked' AND owner='wagner'` — o proxy que a [ADR 0368 §3](../../decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) aposentou ao criar `pending_approval`. Logo o número do brief **não** é a fila da Mesa. Reconciliar exige migration de procedure + `ProcedureDriftSnapshotTest`.
- **Rename Project (Fase 3.9, ADR 0079):** bloqueado por Fase 3.8 (delete `Modules/Project` legado). Evidência: `Modules/Project` removido + `git mv` executado.

## Regra de manutenção

1. Mudou árvore de código: regenere `SUPERFICIE.md` (`module-surface.mjs Forja --write`); não edite a lista aqui.
2. Mudou requisito: altere `SPEC.md`/charter/casos.
3. Mudou fronteira/absorveu módulo: altere `SCOPE.md` (dono da proveniência), ajuste só a linha-resumo aqui.
4. Métrica (nota, contagem) sempre via comando reexecutável — nunca número solto que apodrece.
5. Componente/layout compartilhado não se copia: consulte `_Geral`, valide o contrato, aponte pro dono único.
