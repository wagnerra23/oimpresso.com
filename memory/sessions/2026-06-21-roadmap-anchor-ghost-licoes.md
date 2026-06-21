---
date: "2026-06-21"
topic: "Pós-auditoria: furo do pipeline task→roadmap, teste do SDD anchor spec↔código, CYCLE-SAUDE + p0 de segurança, runbook de rotação, e a saga do anti-ghost — com lições de churn/colisão do sweep paralelo"
authors: [W, C]
prs: [3164, 3165, 3166, 3174, 3175, 3176]
related_adrs:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0144-tasks-db-canonico-spec-template
  - 0218-multi-tenant-scope-checker-tier-0
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
---

# Pós-auditoria: roadmap, SDD anchor, ghost — e as lições

> Continuação de [2026-06-21-auditoria-saude-e-consertos-autonomos](2026-06-21-auditoria-saude-e-consertos-autonomos.md). Wagner pediu "deixar o residual no ciclo" → isso expôs furos no pipeline task→roadmap; depois pediu testar o SDD anchor (código↔fonte); por fim caçamos o vermelho do anti-ghost. Sessão longa, com vários tropeços meus catalogados abaixo.

## Parte A — "deixar no ciclo" expôs o pipeline task→roadmap furado
Registrei o residual da auditoria como US no backlog (#3164). Wagner desconfiou ("tem furo nesse processo, coloque adversário") — **correto**. Dois verificadores adversariais provaram a cadeia:

1. **`tasks-create` não persiste** — só gera o bloco + ID; persistir = commit do SPEC + webhook (#3164).
2. **US nasce invisível no roadmap:** o roadmap Jana filtra por `cycle_id` (cycle ativo) e o ProjectMgmt por `epic_id`; US `todo`/`unowned`/sem-cycle/sem-epic não aparece em lugar nenhum. Nada no fluxo atribui isso, nem há sentinela.
3. **`cycle:` no SPEC só resolve se o SPEC tem `project:` no frontmatter** — `TaskParserService.resolveCycleId` retorna NULL sem `project_id` (L571), e 55/57 SPECs não tinham `project:`. Minha 1ª tentativa de pôr no cycle (#3165) foi **no-op no DB**; o fix real foi `project: COPI` nos 3 SPECs (#3166).
4. **Não há tool MCP pra atribuir cycle/epic a US existente** (`tasks-update` não tem o campo).

**Ações:** criei o cycle **CYCLE-SAUDE** (id=12), atribuí as 4 p1 + 2 estruturais (#3165/#3166), e **bumpei 3 p0** via `tasks-update` (durável, [ADR 0144](../decisions/0144-tasks-db-canonico-spec-template.md)): US-INFRA-041 (backup/DR), US-INFRA-042 (rotação segredos), US-GOV-031 (multi-tenant checker). US estruturais: US-INFRA-043 (sentinela `tasks:unassigned`), US-INFRA-044 (wire `mcp:tasks:sync` no CI), US-INFRA-045 (resolução project/cycle no parser).
> ⚠️ **Ativar CYCLE-SAUDE NÃO é seguro com as tools atuais:** não há tool de ativar cycle, e o sistema assume 1 cycle ativo (`firstWhere('status','active')`) — 2 ativos esconderia o CYCLE-08 (Receita). Ficou em `planning`; acessível por `?cycle=12`.

## Parte B — Teste do SDD anchor (código↔fonte): FUNCIONA, adoção 6.9%
`anchor-lint.mjs` ([ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)) classifica `**Implementado em:**` por US: `anchored_ok` só se TODOS os paths existem; `anchored_dead` se path inexistente ("a spec mente", detectável). Estado real (`--check` em origin/main): **843 US · anchor_coverage 6.9% · 35 ok · 15 dead · 748 sem_campo**. Provei os 2 sentidos: KB resolve pra código real (`verificado@sha`); a US do próprio roadmap (US-COPI-111) estava com anchor MORTO (apontava `Pages/Admin/Roadmap/Index.tsx`; o real é `Pages/Jana/Admin/Roadmap.tsx`). **Veredito: o mecanismo funciona; o gap é adoção (89% das US sem o campo) + gate advisory.**

## Parte C — Runbook de rotação de segredos (#3174)
Risco #1 da auditoria (segredos vivos em repo público). Criei [`Infra/RUNBOOK-rotacao-segredos.md`](../requisitos/Infra/RUNBOOK-rotacao-segredos.md) (owner W) — inventário via gitleaks #3148, rotação MEILI_MASTER_KEY + DNS + 12 do incidente, repo privado, pós (Vaultwarden + `core.hooksPath`). **Execução é do Wagner** (credenciais).

## Parte D — A saga do anti-ghost (e por que demorou)
O gate `anti-ghost ratchet (advisory)` ficou vermelho. Sequência:
- #3175 pôs a isenção de `_Governanca/roadmap/` na função `allMd()`. Verde no meu local (baseline generoso 33).
- Em paralelo, **#3155 mergeou**: refatorou o ratchet pra usar **`allMdLive()`** (não `allMd`) E abaixou o piso de ghosts 33→14. Minha isenção ficou em função morta; o piso menor expôs 4 ghosts → vermelho **no próprio main**.
- **#3176** = o fix real: 1 linha de isenção no `allMdLive()` (ao lado do skip de `adr`). Verificado `0 NOVOS · OK` contra o main atual. Mergeado.

## 🎓 Lições (pro time + pro meu próximo eu)
1. **CHECAR sessões/PRs paralelos ANTES de editar arquivo de governança compartilhado.** Colidi 2× (fechei #3170/#3172 — duplicavam o sweep #3149/#3169) e perdi 1 corrida (a isenção foi pro `allMd` enquanto #3155 movia o ratchet pro `allMdLive`). A lição já estava na minha memória (`sessoes-paralelas-mesma-branch`) — falhei nela. `git ls-remote 'fix/*'` + `gh pr list` antes.
2. **Churn de governança é real:** o sweep mergeia vários PRs/hora refatorando os MESMOS arquivos (`knowledge-drift.mjs` mudou 2× numa hora). Fix corre contra refactor. Frentes grandes precisam consolidar antes de mais paralelismo.
3. **Cycle/epic:** US só entra no roadmap com `cycle_id`/`epic_id`; isso exige `project:` no frontmatter do SPEC + (idealmente) cycle ativo. Não há tool pra atribuir cycle a US existente — gap (US-INFRA-045).
4. **`allMd` ≠ `allMdLive`** no `knowledge-drift.mjs`: o ratchet (`--check`) usa `allMdLive`; o scan de "verdade" usa `allMd`. Editar a função errada = no-op silencioso.
5. **Hierarquia de confiança:** segurança (segredos/backup/isolamento) é a BASE; o SDD anchoring é a camada de cima. Não dá pra confiar no anchor 100% num sistema com segredos expostos.

## Residual (handoff — depende do Wagner)
- 🔴 **Rotação de segredos** (host) — runbook #3174 pronto · US-INFRA-042 p0.
- 🔴 **Backup/DR** (`mysqldump` + restore off-host) · US-INFRA-041 p0.
- 🟠 **Isolamento multi-tenant provado em CI** · US-GOV-031 p0 ([ADR 0218](../decisions/0218-multi-tenant-scope-checker-tier-0.md)).
- 🟡 Adoção do anchor (campanha US-GOV-043) + F2 (US-GOV-044) — área do sweep.
