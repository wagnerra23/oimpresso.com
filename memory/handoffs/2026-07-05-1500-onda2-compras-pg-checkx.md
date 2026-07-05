---
date: "2026-07-05"
time: "15:00 BRT"
slug: onda2-compras-pg-checkx
tldr: "Onda 2 do plano de aprofundamento executada: 3 PRs abertos (aguardam merge Wagner). Compras 58→74 medido no CT100 (2 falso-negativos do grader corrigidos + testes canônicos + retention + na_justified D6.c honesto); AUDITORIA PaymentGateway zera o Check X (contrafactual provado); ADR 0170 later respeitado (PG = só diagnóstico). Fichas/inventários NÃO recriados — já existiam da Onda 2.1 (T6)."
prs: [3832, 3833, 3834]
related_adrs:
  - "0155-module-grade-rubrica-v3"
  - "0170-paymentgateway-extracao-camada-cobranca"
  - "0101-tests-business-id-1-nunca-cliente"
next_steps:
  - "Wagner mergeia (R10) na ordem #3833 (grader) → #3834 (Compras) → #3832 (AUDIT PG). O 74 do Compras exige os dois primeiros juntos; module-grades-baseline reconcilia no snapshot (subidas não bloqueiam gate)"
  - "Wagner aprova (ou corta) o batch de tasks: evolve_tasks do module:grade (5 Compras + 5 PG, destaque D9.a OTel nos 23 services PG = observabilidade de dinheiro) + inventários 2026-07-03. Nenhuma task foi auto-criada"
  - "Onda 2 DoD restante: nada — ficha+inventário+batch existiam (Onda 2.1), diagnóstico+audit+fix entregues; Check X zera quando #3832 mergear. Próxima onda do plano: Onda 3 (Ops/DR CT100+Hostinger) ou Onda 1 (telas stale, Check B 217) — plano #3820 ainda aberto, Wagner decide ordem"
  - "PaymentGateway fase 2 (quando Wagner ativar ADR 0170): fila sugerida na AUDITORIA §3 — OTel services → US-PG-003 webhook hardening → D8/FormRequests → testes canônicos por nome"
---

# Onda 2 — Compras + PaymentGateway (Tier-0 mais fracos): diagnóstico + fix medido + Check X

## O que esta sessão fechou

Contrato da [Onda 2 do PLANO-APROFUNDAMENTO-AVALIACOES](../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) (lido da branch do PR #3820, não mergeado): sair de nota-fria pra diagnóstico + backlog nos 2 piores módulos do baseline, ambos tocando dinheiro/estoque (REGRA MESTRE).

**Estado real encontrado ≠ plano:** fichas CAPTERRA + inventários + batch dos 2 módulos JÁ existiam (Onda 2.1 do programa-ondas, 2026-07-03 — Compras #3708-#3715 nota 30/100 capacidade; PaymentGateway #3736 nota 67/100). Pelo T6 nada foi recriado. O que faltava e foi entregue:

| Entrega | PR | Evidência |
|---|---|---|
| Diagnóstico `module:grade --detail --evolve` CT100 (Compras 60, PG 64) | corpo dos PRs | output literal nos comentários |
| Fix 2 falso-negativos do grader (D8.a throttle-em-array; D4.d OtelHelper/Activitylog) + cenários 18-20 | [#3833](https://github.com/wagnerra23/oimpresso.com/pull/3833) | 3 passed/7 asserts CT100; amostra 5 módulos sem regressão |
| Compras ≥70: Smoke+Scaffold canônicos + retention 1825d + na_justified_v3 D6.c | [#3834](https://github.com/wagnerra23/oimpresso.com/pull/3834) | 9 passed/19 asserts CT100 · **re-grade 74/100** |
| AUDITORIA PaymentGateway (diagnóstico-only, ADR 0170 later) — **zera Check X** | [#3832](https://github.com/wagnerra23/oimpresso.com/pull/3832) | contrafactual: sem doc flag=1, com doc flag=0 |

**REGRA MESTRE:** nenhum cálculo de valor/estoque foi tocado em nenhum PR (testes/manifesto/docs/rubrica) — dupla-confirmação não aplicável; registrado nos bodies.

## Decisões técnicas que o próximo precisa saber

- **Compras 58→74 sem "melhorar features"**: ~10 pts eram detector míope (throttle real desde o audit sênior Gap #3 + OTel/Activity reais no Service). Consertar o grader > consertar o módulo. Detecção só ADICIONA caminhos 0→pontos — monotônico, ninguém regride.
- **Receita worktree de teste CT100** (pegadinha nova catalogada na session): symlink de vendor faz autoload resolver classes do checkout ERRADO; `/tmp` é outro device (hardlink falha silencioso). Usar `/var/www/html/.claude/worktrees/<x>` + `cp -al vendor` + `.env` + `mkdir storage/framework/{cache,sessions,views}`. Limpo no fim (worktree removido, vendor main intacto 27023 files).
- 4 falhas pré-existentes em `--filter=ModuleGradeService` no staging (AssetManagement bucket/D2.c — expectativas stale de ambiente); ocorrem idênticas no código antigo. Não são dos PRs.

## Estado MCP no momento do fechamento

Sessão desktop **sem tools MCP oimpresso conectadas** (nenhuma `mcp__Oimpresso_*` disponível no ambiente) — checklist MCP-first (cycles-active/my-work/sessions-recent/decisions-search) **não executável nesta sessão**; snapshot do brief veio do hook SessionStart: Brief #310, cycle "—" (off-cycle), 2 HITL pendentes Wagner (runbook on-prem pós-Gold · FIN-004 cobrança ROTA LIVRE), PaymentGateway "linkage cobranca_id no webhook" na lista EM VOO. Handoff irmão mais recente: [2026-07-05-0130 RAG investigação profunda](2026-07-05-0130-rag-investigacao-profunda-sync-fix.md). Task de rastreio desta onda: criar/atualizar via `tasks-*` na próxima sessão com MCP (registrado aqui como pendência honesta, ADR 0070).

## CI no fechamento

Os 3 PRs com CI em andamento no momento do handoff; monitor ativo na sessão reportou estado final — conferir `gh pr checks 3832 3833 3834` antes do merge (regra: verde antes de propor merge).
