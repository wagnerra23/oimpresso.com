---
date: "2026-07-01"
time: "21:40"
slug: p10-wave2-anchoring-fechada
tldr: "P10 wave 2 fechada e 100% mergeada: 8 lotes (147 US), 5 reprovados na rodada 1 pelo refutador Fable e re-aprovados a 0%; coverage do dia 16,1%→59,8% (sem_campo 717→344); fila A6 atualizada com 6 advisories de produto novos"
prs: [3571, 3572, 3573, 3574, 3575, 3576, 3577, 3580]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Handoff — P10 wave 2 (anchoring batches, fechamento)

## O que aterrissou (TUDO mergeado — merges autorizados por Wagner "sim"/"Merge")

8 lotes: Pcp #3571 · PaymentGateway #3572 · Fiscal #3573 · Compras #3574 · Sells-completion #3575 · Crm #3576 · NfeBrasil #3577 · RecurringBilling #3580. Detalhe por lote no [session log](../sessions/2026-07-01-sdd-p10-wave2-anchoring-batches.md).

**Métricas em origin/main pós-merge:** anchor_coverage **59,8%** (dia: 16,1% → 42,6% wave 1 → 59,8% wave 2) · sem_campo **344** (era 717) · dead=0 · zombie=0 · ledger 26 entries (reprovados registrados) · baseline entry-gate 437 chaves (trailers auditáveis em todo crescimento).

## Qualidade (o dado que importa)

**7 de 12 lotes do dia reprovados na rodada 1** pelo refutador Fable (tier superior, sessão fresca, amostra 100%) e re-aprovados a 0% pós-correção. Modo de falha dominante do gerador Opus: **overclaim de done-ness** (nunca path inventado — dead=0 sempre). Pior caso: RecurringBilling 30,9% (stub vendido como entregue + UI removida citada como viva). A regra nova (refutador tier superior, encodada em #3530) pagou o próprio custo várias vezes.

## Decisões pendentes Wagner (fila A6 — `memory/requisitos/_ANCHOR-REVIEW-QUEUE.md`)

- §3/§3-bis: telas órfãs + 13 `related_us` deferidos (aguardam smoke/prod-flags).
- §4.1: entry retroativa do batch 1 Sells (#3483) — único item do §4 ainda aberto (o §4.2 foi FECHADO pelo #3575).
- **§4-bis (novo)**: 6 advisories de produto/higiene dos refutadores da wave 2 (deep-link Sells, RB-012, §0 do Crm stale, doneness legados PG, DoDs stale Fiscal).
- §5: triagem 3-baldes da dívida entry-gate (universo agora maior — fonte única = anchor-lint 📋/🚪).

## Próxima sessão (wave 3 — receita pronta)

Restam ~344 sem_campo: Infra (45) → Governance (35) → Marketplaces (26) → ComVis (18) → wish verticais (Autopecas/Comissao/NFSe ~45) → Mwart/Vestuario/Connector/Cms/Essentials/Ponto/Superadmin (~77) → cauda. GATED (não ancorar): TaskRegistry/Inventory/EvolutionAgent/LaravelAI/MemoriaAutonoma. Melhorias pro prompt do gerador: régua explícita "acceptance codável incompleto = _parcial_" com exemplos (o modo de falha dominante) + pegadinhas de parser (PLACEHOLDER_RE casa 'todo' em 'método'; backtick-com-/ em prosa vira path morto).

Trilho floor/nightly (outra sessão): armar `anchor_coverage` no scorecard (3 medições consecutivas do cron; a subida de hoje acelera o caso).

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI (igual à abertura).
- `my-work` (wagner): inalterado pela campanha (trabalho é docs/governança; nenhuma US de produto mudou de status no MCP — o status-truth foi nos SPECs).
- Sessões do dia: wave 1 (`2026-07-01-sdd-p10-wave1-anchoring-batches.md`) + wave 2 (`2026-07-01-sdd-p10-wave2-anchoring-batches.md`) + avaliação adversarial (gatilho da campanha).
