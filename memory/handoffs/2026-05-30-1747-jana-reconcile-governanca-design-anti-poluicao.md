---
date: '2026-05-30'
time: '17:47 BRT'
slug: jana-reconcile-governanca-design-anti-poluicao
tldr: "Sessão-maratona partindo de 'o index está poluindo com memórias ruins?' (Wagner). Arco completo diagnóstico→prevenção→conserto-de-raiz: reavaliação grade ~65/100 + 5 gates de enforcement de design (#1996-2000) + jana:reconcile loop único (ADR 0237, #2002) revisado por 8 agentes adversariais que pegaram um risco Tier-0 real e 3 fix-agents que corrigiram. ~13 PRs, 2 ADRs (0236+0237), ~30 agentes em 5 ondas. Todo em main."
topic: "Reavaliação governança de design + enforcement (5 gates) + jana:reconcile loop único anti-poluição de memória (ADR 0237)"
duration: ~8h
prs: [1995, 1996, 1997, 1998, 1999, 2000, 2001, 2002]
adrs: [0236, 0237]
decided_by: [W]
authors: [W, C]
---

# Handoff — jana:reconcile (ADR 0237) + governança de design anti-poluição

## Estado MCP no momento
Cycle **CYCLE-07** (Fundações pós-4.8) · 12d restantes. Esta sessão foi **governança transversal** (não um goal direto do cycle — adjacente). `decisions-search`: ADR 0236 já no MCP; 0237 recém-mergeado (webhook propaga ~2min). MCP conectado (brief-fetch/cycles-active/decisions-search ativos a sessão toda).

## O que aconteceu (arco)
Wagner começou com `/continuar`. Reavaliação do **sistema de governança de design** (#1991-1994 da sessão anterior) **com MCP ativo** + método grade → ~65/100 (conceito Leader ~85, execução Developing ~40; gap = **enforcement**). Achou a **colisão ADR 0235** (DS-v4-roxo + staging compartilham número). Tentativa de renumerar (#1995) **bloqueada pela Constituição append-only** (gate funcionou) → Wagner decidiu **documentar, não mutar** → #1997 registrou as colisões. **Correção arquitetural-chave do Wagner:** *"no design não tem MCP, tem que ser arquivos bem organizados"* → o Design Request Ledger virou **file-based** (#1996) + feedback canon.

Wagner pediz **"testes que garantem que ninguém bagunça"** → construí **5 gates de enforcement** (3 testes Pest + hook reprocess + freshness checker, #1997-2000). O teste de colisão, ao rodar, **surfaçou 6 colisões históricas nunca registradas** (0126/0141/0170×3/0178/0180/0216). Medi a poluição: `0039` (superseded) citada em **31 docs**, `total_adrs` declara 119 mas disco tem **238**.

Wagner: *"o index está poluindo com memórias ruins?"* → **sim, é drift de orquestração**. Escolheu o **conserto de raiz** (reconcile loop). Aceitei **ADR 0237** (`jana:reconcile`) + construí o loop completo em **ondas paralelas**: contrato `Reconciler` + orquestrador + **5 reconcilers** (Index/Settings/Content/Deploy/Eval) por 6 agentes paralelos. **Onda 3 = revisão adversarial 8 agentes** pegou um **risco Tier-0 real** (ContentReconciler `--heal` ia disparar soft-delete GLOBAL cross-tenant sem business_id → mataria docs da biz=4) + phantom-drift (1000+ falsos) + gate-cego-por-typo. **3 fix-agents** corrigiram tudo. #2002 mergeado (Pest verde, RAGAS 0.835 pass, Module Grades Jana +1).

## Artefatos gerados (canon, em main)
- **ADR 0237** `memory/decisions/0237-jana-reconcile-loop-unico.md` (aceito) + **ADR 0236** (índice tocado)
- **Reconcile loop:** `Modules/Jana/Contracts/Reconciler.php` + `Services/Reconcile/{ReconcileResult,ReconcileDrift,Reconcilers/{Index,Settings,Content,Deploy,Eval}Reconciler}.php` + `Console/Commands/ReconcileCommand.php` + 6 testes Pest (~65 testes)
- **5 gates de design:** `tests/Feature/Memory/AdrNumberCollisionTest.php` · `tests/Feature/Design/{DesignIndexSingleSource,DesignLedgerIntegrity}Test.php` · `.claude/hooks/design-handoff-reprocess.mjs` · `Modules/Governance/Services/Checkers/DesignDocsFreshnessChecker.php`
- **Ledger file-based:** `memory/governance/design-requests/{LEDGER,_TEMPLATE-REQ}.md`
- **Feedback canon:** `memory/reference/feedback-claude-design-so-arquivos.md`
- **Registro completo de 11 colisões** em `memory/decisions/_INDEX-LIFECYCLE.md` (Bloco 11)

## Persistência
- **Git:** 8 PRs mergeados em main (#1996-2002; #1995 fechado). Branch deste handoff: `docs/handoff-2026-05-30-reconcile`.
- **MCP:** webhook git→MCP propaga (ADRs 0236/0237 + feedback + reconcilers indexados em ~2min).

## Próximos passos pra retomar (com o reconcile loop JÁ em main — falta operar)
1. **`php artisan jana:reconcile --check`** no CT 100 → inventário real da poluição (vigente vs histórico).
2. **`jana:reconcile --only=index --heal`** supervisionado → cura os índices de verdade (o conserto que o Wagner pediu).
3. Wirar **cron diário `--heal`** + **gate CI `--check`** (snippet no docblock de ReconcileCommand + ADR 0237 §rollout).
4. **ContentReconciler safe-heal (follow-up Tier-0):** path-lister canônico compartilhado com `IndexarMemoryGitParaDb` + escopo `business_id` no soft-delete → só então o `content` volta a `healable=true`.

## Lições catalogadas
- **Revisão adversarial pré-merge é OURO:** os 8 agentes pegaram um **vazamento Tier-0 destrutivo** que nenhum dos 6 builders viu (cada um validou só sua peça). Sem a Onda 3, ia pra prod.
- **Constituição append-only funcionou:** o gate barrou a renumeração de ADR 0235 — o fix certo é documentar (precedente 0195), não mutar.
- **PHPStan ratchet cego pra arquivo novo:** o ratchet do main já falha por débito pré-existente → não distingue violação nova. Solução: agente de varredura level-5 manual + cada builder roda phpstan no próprio arquivo.
- **Claude Design = só arquivos (nunca MCP)** — `feedback-claude-design-so-arquivos.md`. Reescreveu a proposta do Ledger.
- **"Doc-em-prosa não basta":** `0180` é uma ADR *sobre* a colisão do 0178, mas o registro nunca foi atualizado → precisa registro estruturado + teste (o que o AdrNumberCollisionTest agora garante).

## Pointers detalhados
ADR 0237 (design + rollout incremental). Dossiê-base: `memory/sessions/2026-05-29-arte-reconcile-loop-kb-self-healing.md`. Revisão adversarial: ver os findings nos PRs #2002. Reavaliação grade: inline na sessão (não persistida como doc — candidata a session log se quiser).
