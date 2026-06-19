---
date: "2026-06-13"
slug: sdd-floor-frente-c-era-sqlite
tldr: "Floor do nightly full-suite NÃO caiu (1928) após A.1/A.2/Frente C — o lever real é isolamento dos ~30 testes era-sqlite que dropam tabela CORE compartilhada. A.2 (FK-off) revertido (piorava). Fix-spec era-sqlite pronto (31 arq). Decisão pendente Wagner: piloto vs thread-all."
hour: "17:30 BRT"
topic: "SDD full-suite floor — cadeia de 4 root-causes (sqlite→mysql-client/TLS→trigger-DEFINER→era-sqlite isolation). Floor 1870→1928 (NÃO caiu); lever real = isolamento dos testes era-sqlite. A.1 mergeado, A.2 revertido, Frente C não-é-o-lever, era-sqlite fix-spec pronto pra implementar."
duration: "~5h"
authors: [W, C]
---

# Handoff — SDD floor: a verdade é isolamento de teste, não harness

> **TL;DR:** "abre Frente A" → virou a investigação do floor do nightly full-suite. **4 iterações de root-cause, cada uma refutando a anterior** (par adversarial ADR 0276 + decomposições 18/32 threads). Resultado honesto: **o floor NÃO caiu (1928)** — nem A.1, nem A.2, nem Frente C são o lever. **O lever é isolamento dos ~30 testes era-sqlite que dropam tabela CORE compartilhada.** Fix-spec pronto (31 arquivos). Errei previsão 2× (Frente C ~970, era-sqlite) → **regra: MEDIR cada passo, nunca previsão-como-fato.**

## Estado MCP
- **US-GOV-018** (A.1 client/TLS + A.2 FK-off): A.1 mergeado (#2640); **A.2 REVERTIDO** (provado net-harmful) no #2657.
- **US-GOV-019**: re-triage eixo-FAILURE (91 quarentena + 11 bugs + 11 unclear). 4 quick-wins em PR; #2648 (ChannelUserAccess) já mergeado por outra sessão.
- **US-GOV-020** (Frente C — trigger DEFINER privilege): grants no #2657. Correto mas **NÃO é o lever** (0 migrate:fresh nos testes que corrompem).
- **US-GOV-021**: roadmap 4-fronts até o verde.
- **PRs abertos (minha fila p/ Wagner):** #2657 (Frente C grants + revert A.2) · #2646 (macro_variant) · #2647 (superadmin:health) · #2649 (ads:health) · #2652 (biz=4→1 fixtures).
- **Mergeados na sessão:** #2640 (Frente A A.1/A.2) · #2642 (números SPEC US-GOV-018) · #2653 (re-triage doc + US-GOV-019/020) · #2658 (re-baseline foundation-ratchet 71/75).

## O que aconteceu (cadeia de root-cause, medida)
1. **A.1** — imagem `oimpresso/mcp` sem CLI mysql + (refutado pelo adversário) TLS-verify. Fix: mariadb-client + `/etc/my.cnf.d` ssl-off. ✅ funcionou (mysql-not-found/TLS = 0).
2. **A.2 (FK-off)** — tentou silenciar "Cannot drop" (3730). **PIOROU**: deixou os testes era-sqlite dropar `business` com sucesso → cascata `Base table not found` (business 252×). **Revertido.**
3. **Frente C (US-GOV-020)** — trigger DEFINER prod (`u906587222_oimpresso`) bloqueia o `migrate:fresh` do `fullsuite` (ERROR 1419/1227). Fix provado isolado (188→377 tabelas) com `GRANT SET_USER_ID` + `log_bin_trust_function_creators=1`. **MAS o floor não caiu** — porque **0 eventos de migrate:fresh** nos testes que corrompem (eles fazem `Schema::drop/create` manual, não RefreshDatabase).
4. **era-sqlite (lever real)** — ~30 testes fazem `Schema::create/drop` de tabela CORE compartilhada (business/users/activity_log/permissions) em beforeEach/afterEach. No MySQL persistente isso corrompe o schema. Decomposição 18-thread: dos 1497 ERROR, ~765 são schema-coupled, 224 era-sqlite, 390 código (~15 root-causes incl KB-FK 116 de 1 bug), 160 env.

## Próximos passos pra retomar (DECISÃO PENDENTE Wagner: piloto vs thread-all)
**Front-2 era-sqlite** (workflow `wr87uw59a`, fix-spec por arquivo). **31 arquivos:**
- **REMOVER-DROP-CORE (23):** tirar `Schema::create/drop` de tabela CORE; usar schema seedado via `firstOrCreate`/`updateOrInsert` (business id=1/99). Baixo risco a maioria. Médios: `NfeBrasil/NfeInutilizacaoServiceTest` (business precisa state/tax_number/ambiente), `Repair/Wave25RepairFsmCanonExpandedTest` (sale_* migrations re-aplicadas → preferir CONVERTER), `Whatsapp/ContactObserverCacheInvalidationTest` (FK contacts→business, remoção ingênua = risco), `KB/Tests/Helpers.php` (afeta muitos KB).
- **CONVERTER (3):** RefreshDatabase→DatabaseTransactions: `ComunicacaoVisual/CustomerJourneyTest`, `RecurringBilling/Wave21NewSubscriptionTest`, `RecurringBilling/Wave6PlanCrudTest`.
- **INVESTIGAR-DEV (5) — falsos-positivos, NÃO tocar:** `tests/Feature/Infra/InstallControllerSecurityTest` (teste de SEGURANÇA que só LÊ o source via Reflection — derrubaria a catraca US-INFRA-008), `Cliente/ClienteIndexDrawer760CharterTest`, `TeamMcp/ActorPermissionMatrixTest` (não tocam schema).
- ⚠️ **Nuance crítica de pacing:** vários REMOVER são **skip-guarded no MySQL hoje** → fixá-los = COBERTURA, NÃO queda-de-floor. Os corrompedores REAIS = os que RODAM em MySQL E dropam core.
- **Recomendação (C):** PILOTO — fixar 5-8 corrompedores reais de baixo risco (KB Helpers, NfeInutilizacao, ContactObserver-com-cuidado…) + **1 re-run** + MEDIR a queda. Se cair proporcional → escalar o resto.
- **Comando de retomada:** `tasks-detail task_id:US-GOV-021` + re-derivar a lista via `git grep -lE "Schema::drop(IfExists)?\('(business|users|activity_log|permissions|roles|contacts)'\)" -- tests Modules`.

## Persistência
- git: PRs acima (5 abertos + 4 mergeados). MCP: US-GOV-018/019/020/021 (SPEC + DB).
- **CT 100 estado live (importante):** setei `SET GLOBAL log_bin_trust_function_creators=1` + `GRANT SET_USER_ID TO fullsuite` no mysql-workers pra provar a Frente C (persistem; GLOBAL reseta em restart — o #2657 re-seta no passo 3). Runs nightly: `20260613-100035` (floor 1870, A.1+A.2) e `20260613-115507` (1928, +grants Frente C).

## Lições catalogadas
- **Previsão-como-fato é veneno**: "Frente C → ~970" e "A.2 conserta 508" erraram. Cada hipótese de harness PARECE certa isolada mas o floor só responde à medição. Regra nova: re-run + medir ANTES de afirmar.
- **A.2 (FK-off) é anti-padrão**: silenciar "Cannot drop" deixou o drop destrutivo de tabela core suceder → cascata maior. Falhar-seguro (Cannot-drop) é melhor que mascarar.
- **O bedrock do floor é isolamento de teste** (testes era-sqlite mutando DB compartilhado), não tweak de harness. Sem isolamento, nenhum harness-fix leva ao verde.
- **Foundation-ratchet (FV-Q1) tem premissa furada pós-fix**: penaliza RefreshDatabase, mas RefreshDatabase é o padrão CORRETO (o problema era o reload do dump). Re-baseline #2658 + flag pra revisão da premissa.

## Pointers detalhados (on-demand, NÃO duplicar)
- Docs: `memory/sessions/2026-06-13-sdd-retriage-eixo-failure-32threads.md` (re-triage + floor + decomposição) · `2026-06-13-sdd-f2b-triage-q2.md` · `2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md`.
- RUNBOOK: `memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md` (troubleshooting C1/A.1/A.2-revertido/Frente-C).
- Workflows (outputs em temp, re-deriváveis): `wr87uw59a` (era-sqlite spec), `wghs2qmuh` (decomposição ERROR), `wnw19l15c` (re-triage 32t), `wqompm50q` (par adversarial A.1/A.2/A.3).
