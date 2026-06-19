---
date: "2026-06-12"
time: "13:52 BRT"
slug: sdd-fases-0-1-2a-adr0276-decisao-pelo-fluxo
tldr: "Reestruturação SDD executada em 1 dia: auditoria 59/100 → plano 4 ondas → Semana 0 (13 PRs) + Fase 1 (12 PRs) mergeadas + Fase 2a parcial. ADRs 0273-0276 aceitas. Nightly MySQL no CT 100 rodando. ADR 0276: Wagner sai do caminho crítico (pares adversariais decidem o rito). Limite de tokens pausou Fase 2; retomada 100% especificada na US-GOV-017."
topic: "Reestruturação SDD — Semana 0 + Fase 1 + Fase 2a parcial + ADR 0276 decisão-pelo-fluxo"
duration: "~1 dia (sessão longa multi-workflow)"
authors: [W, C]
decided_by: [W]
prs: [2586, 2587, 2588, 2589, 2590, 2591, 2592, 2593, 2594, 2595, 2596, 2597, 2598, 2599, 2600, 2601, 2602, 2603, 2604, 2605, 2606, 2607, 2608, 2609, 2610, 2612, 2613]
---

# Handoff — Reestruturação SDD (fases 0/1/2a) + ADR 0276

> **TL;DR:** Auditoria independente deu **59/100** pro sistema SDD (spec mente: 0 traceability ativa; suite mente: subset hardcoded, mutation/RAGAS teatro). Plano de 4 ondas com crítica adversarial virou execução no MESMO dia: **27 PRs mergeados**, ADRs **0273** (anchor spec↔código) / **0274** (slug+alias 13 colisões) / **0275** (scorecard 10 métricas+calendário) / **0276** (decisão-pelo-fluxo) aceitas, **full-suite MySQL rodando no CT 100** (cron nightly 02:00). Limite de tokens (91% semanal) pausou a Fase 2 no meio — estado de retomada completo na **timeline da US-GOV-017** (fonte canônica de retomada, 5 comments).

## Estado MCP no momento

- **US-GOV-016** (Semana 0): **done**. **US-GOV-017** (Fases 1+2): doing — timeline tem o checkpoint de retomada (5 comments, último = P1 executado).
- Cycle ativo CYCLE-08 (Receita) segue com drift — decisão de cycle novo "Fundação SDD" ficou em aberto (sugerida, não executada).

## O que aconteceu (macro)

1. **Auditoria com pesquisa real** (2 agents, 13 buscas): nota composta 59/100; reclassificação independente das prioridades — fundação de verificação ANTES de knowledge lifecycle ([audit](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md)).
2. **Plano 4 ondas** corrigido por 2 críticos adversariais (7 lacunas + 14 erros de DAG) ([plano](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md)).
3. **Semana 0** (workflow `sdd-semana-0`, 13 frentes paralelas em worktrees): 13 PRs draft → auditor adversarial → todos mergeados. Renumeração de ADRs colididas resolvida em cadeia.
4. **Fase 1** (workflow `sdd-fase-1`, 8 frentes): 12 PRs mergeados. Auditor **REPROVOU** o codemod por reescrever 11 ADRs históricos (corrupção real no MemCofre/adr/0008) → fix cirúrgico + hard-skip `**/adr/**` permanente. CT 100: infra full-suite + 1º run + cron.
5. **Validação pós-merge**: gate-selftest pegou acoplamento selftest↔scorecard (caso ruim passava) → fix de coerência PENDENTE (morreu no limite).
6. **Fase 2a**: Felipe abriu #2611/#2612 da máquina dele. #2612 auditado+mergeado. **#2611 segurado** (sufixo viola gramática ADR 0273 — checklist na US-GOV-017). G7/G8/FV-B4/refutadores morreram no limite de tokens (ambas as contas).
7. **ADR 0276 aceita**: "NÃO QUERO SER A TRAVA" — D0 gate decide · D1 par adversarial+ledger · D2 lista curta humana. Secret RAGAS eliminado (key já existe no CT 100). Tabela identidade + golden set → refutadores.

## Vivo agora (zero token)

- **CT 100**: full-suite MySQL rodando (run 20260612-101615) + **cron nightly 02:00 BRT** → `summary.json` em `/opt/oimpresso-fullsuite/runs/latest/` alimenta a Fase 2b.
- **Em main**: anchor-lint+anchor-drift · catraca anti-ghost · foundation-ratchet · sdd-scorecard+baseline+meta-catraca · protection-drift+watchdog · gate-selftest · protocolo refutador+ledger · hook red-first WARN · RAGAS destravável · recall-eval+golden set · decay flag-OFF.
- Scorecard v1: anchor_coverage ~1.8% · ghost_count 27→23 efetivos · front_door 63.9% · 7 métricas aguardando 1ª medição.

## Próximos passos (ordem, qualquer máquina — ANTES de iniciar: `git ls-remote origin "sdd/*"` + `gh pr list` pra não duplicar)

1. Fix **#2611** (checklist no último comment da US-GOV-017) → merge.
2. **Fix coerência**: scorecard `--baseline` flag (selftest 8/8) · knowledge-drift excluir `/adr/` da contagem ghost · scorecard consumir anchor-lint --json.
3. **G7** (snapshot DB+health-check) + **G8** (linha SDD no brief) + **FV-B4** (trait WithSeededTenant + 4 loader-blockers) — specs nos workflows `sdd-fase-2a.js` e na sessão Felipe.
4. **Refutadores D1** (ADR 0276): tabela `_TRIAGEM-IDENTIDADE-2026-06.md` + `tests/eval/recall-golden.yaml`.
5. **Fase 2b** (SÓ máquina Wagner — CT 100 inacessível do tailnet Felipe; share Tailscale ficou ADIADO por Wagner): coletar summary → triage Q2 (Haiku) → quarentena Q3 → burn-down B1/B2/B3 → C3-C5 → RAGAS real no CT 100.

## Lições catalogadas

- **Par adversarial > humano no rito** (provado 2× no dia: corrupção de ADRs históricos + catraca sem dente) → virou ADR 0276.
- **ADR histórico não é ghost**: referência a nome antigo em ADR de rename é FATO — codemod com hard-skip `**/adr/**`.
- **Partição por área funcionou entre SESSÕES** (Wagner + Felipe trabalharam o mesmo plano sem colisão), mas o orçamento de tokens é POR CONTA — distribuir máquinas não multiplica budget.
- **Estado de retomada vive no MCP** (timeline da US), não no contexto do chat — 5 chats travados puderam ser fechados sem perda.

## Pointers

- Retomada: `tasks-detail task_id:US-GOV-017` (timeline completa) · workflows versionados em `.claude/workflows/sdd-*.js`.
- Auditoria + plano: `memory/sessions/2026-06-12-*.md` · ADRs: 0273-0276.
