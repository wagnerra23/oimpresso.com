---
feature: gateway-ativacao
module: RecurringBilling
us: ["US-RB-052"]
parent_plan: recurring-billing-gateway-ativacao
created: "2026-07-09"
---

# Requirements — Ativar gateway nas assinaturas dormentes (gateway=NULL)

> **US-mãe:** [US-RB-052](../../SPEC.md) · **Sinal (ADR 0105):** receita parada — 109 assinaturas
> ativas com cobrança dormente (faturas nascem `gateway=NULL`, viram só "registro contábil",
> handoff 2026-06-07): 36 C6 + 51 Inter + 22 Cora. Maior ROI do batch-34.

## User story

**Como** Eliana (financeiro) operando o RecurringBilling
**Quero** atribuir a conta bancária/gateway correta a cada assinatura ativa hoje dormente e reativar a emissão de cobrança
**Para** que as faturas dessas 109 assinaturas voltem a gerar boleto/PIX de verdade (receita recorrente destravada).

## Acceptance criteria (EARS — ADR 0306)

- **AC-1** — QUANDO o operador executar `php artisan rb:gateway-backfill` (modo padrão = dry-run), O SISTEMA DEVE imprimir a tabela **antes→depois** (assinatura, business, contato `[REDACTED]`, provider proposto, conta bancária alvo) SEM escrever nada no banco. _Prova: count de `rb_subscriptions.conta_bancaria_id NOT NULL` idêntico antes/depois do comando._
- **AC-2** — QUANDO executado com `--apply` (após aprovação Wagner do dry-run — REGRA MESTRE), O SISTEMA DEVE gravar `conta_bancaria_id` em cada assinatura mapeada, com 1 linha de audit por escrita (quem/quando/de→pra). _Prova: audit trail consultável + tabela pós-apply bate com o dry-run aprovado._
- **AC-3** — SE o comando for re-executado (`--apply` 2×), ENTÃO O SISTEMA DEVE ser no-op na 2ª rodada (idempotência: 0 mudanças reportadas). _Prova: Pest de re-run._
- **AC-4** — ENQUANTO houver assinatura sem provider resolvível (business sem conta bancária ativa do banco esperado), O SISTEMA DEVE pulá-la e listá-la no relatório final — NUNCA atribuir gateway por palpite. _Prova: Pest com business sem conta → assinatura intocada + presente no relatório._
- **AC-5** — O SISTEMA DEVE escopar `business_id` em toda query (Tier 0, ADR 0093); assinatura de um business jamais recebe conta bancária de outro. _Prova: Pest cross-tenant biz=1 vs biz=99._
- **AC-6** — QUANDO a assinatura destravada gerar a próxima fatura, a fatura DEVE nascer com `gateway`/`conta_bancaria_id` preenchidos e a emissão (boleto/PIX) disparada. _Prova: smoke real de 1 ciclo em 1 assinatura canário (R1 — evidência literal, não narração)._

## Fora de escopo

- Criar adapter novo de gateway (C6/Cora emissão é dependência — se não existir driver, a assinatura fica no relatório AC-4, não bloqueia as demais; integração é US própria).
- Recalibração de pricing (US-RB-055) e unificação de "próximo vencimento" (US-RB-056).
- Cobrança retroativa das faturas históricas já geradas com `gateway=NULL` (decisão de negócio separada — Wagner/Eliana).
- UI de gestão de gateway por assinatura (hoje: comando artisan; tela é evolução futura).

## Referências

- SPEC: [SPEC.md](../../SPEC.md) (US-RB-052) · Handoff: [2026-06-07 retroatividade](../../../../handoffs/2026-06-07-1855-recurring-billing-retroatividade-completa.md) ("Ativar gateway real — invoices geradas têm gateway=NULL")
- Fonte do batch: [_processo/BATCH-BACKLOG-34-2026-06-20.md](../../../_processo/BATCH-BACKLOG-34-2026-06-20.md) (§Aprovação [W])
- REGRA MESTRE valor/estoque: [memory/proibicoes.md](../../../../proibicoes.md) — mexe em COBRANÇA → dry-run + antes→depois + dupla confirmação
