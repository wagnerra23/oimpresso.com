---
date: "2026-07-17"
time: "22:30 BRT"
slug: triagem-floor-mt-vazamento-scope-viaparent
tldr: "Triagem dos 4 testes MT restantes do floor CT100 (cont. #4454) destapou um vazamento cross-tenant Tier 0 REAL no ScopeByBusinessViaParent (fail-open desde Wave 7, mascarado por FK-1452). Escalei (R10), [W] aprovou, corrigi por reflection, provei no CT100 (LEAKED YES→NO), mergeei. 3 PRs em main: #4474 (fix), #4475 (Ponto+lane), #4476 (Financeiro)."
prs: [4474, 4475, 4476]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
decided_by: [W]
next_steps:
  - "Auditoria per-site do gate de tenant: 14 consumers Essentials (folha/RH) + PeriodosController — gate explícito além do backstop restaurado"
  - "Registrar no §5 proibicoes.md: nunca ler property protected via $model->x dentro de um Scope (Eloquent __get → NULL)"
---

# Handoff — triagem floor MT → fix Tier 0 ScopeByBusinessViaParent

## Estado MCP no momento do fechamento
MCP live-tools **indisponível** (brief-fetch fallback exit 28; snapshot via brief #373 + git).
- Cycle: — (nenhum ativo) · HITL [W] pending: 2 (FIN-004, runbook on-prem).
- ADRs 24h (via brief): 0340, 0341, 0342, 0343. Incidentes: 0.
- Handoffs irmãos (main): 2026-07-18 00:24 (DTCG), 2026-07-17 21:46 (visreg item7), 20:53 (custo-por-PR).

## O que aconteceu
Continuei a triagem de #4454. Rodei os 4 testes MT do floor **no CT100** (§5). Compras
e Financeiro passam (Financeiro: o vermelho era assert OTel stale, não-MT). Ponto era
artefato (config-override que nada lê + FK biz=99). O do **Jana**, ao armar biz=99,
fez a asserção rodar e **VAZAR**: `ScopeByBusinessViaParent` nunca aplicava o
`whereHas` — lia `$model->businessParentRelation` (`protected`) via `__get` → NULL →
early-return sem filtro (fail-open desde Wave 7, mascarado pela FK-1452). Parei,
provei o mecanismo + raio (17 entities; consumidor explorável `PeriodosController`),
escalei a [W] (R10). Aprovado → fix por **reflection** + regression test + âncora.

## Artefatos gerados (mergeados — merge = [W])
- **#4474** `fix(jana)` scope + regression Jana + jana-pest allowlist → main `4f9fe79598`
- **#4475** `test(ponto)` Wave27 biz=99 + reframe D2.A1 + **novo ponto-pest.yml** → `bbe5f72f8b`
- **#4476** `test(financeiro)` D9 needle `.projetar`→`.render` → `f6377c85a9`
- Session log: [2026-07-17-triagem-floor-mt-vazamento-scope-viaparent.md](../sessions/2026-07-17-triagem-floor-mt-vazamento-scope-viaparent.md)

## Persistência
- **git**: 3 PRs merged em main + este handoff + session log (PR à parte).
- **MCP**: indisponível no fechamento (webhook propaga quando voltar).
- **BRIEFING**: sem update (bugfix de correção, não muda capacidade de módulo).

## Próximos passos pra retomar
`/continuar` — os 2 follow-ups estão no frontmatter `next_steps` (auditoria consumers
Essentials + lição §5 proibicoes). Nenhum é bloqueio; o vazamento já está fechado em main.

## Lições catalogadas
1. **`protected` prop via `$model->x` em Scope = NULL** (Eloquent `__get`) — vetor deste vazamento.
2. **Teste estrutural ≠ prova de runtime** — só a query que roda com dado cross-tenant pega fail-open.
3. **FK-1452 mascara asserção** — "vermelho de infra" escondia bug de produto Tier 0.
4. **Force-push bloqueado (hook, correto)** → un-stale via `gh pr update-branch` (merge, não rewrite).

## Pointers detalhados (on-demand)
- Mecanismo + prova CT100 + fix: session log acima · [ScopeByBusinessViaParent.php](../../Modules/Jana/Scopes/ScopeByBusinessViaParent.php)
- Precedente: #4454 (Fiscal cockpit MT).
