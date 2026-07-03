---
date: "2026-07-03"
time: "12:15 BRT"
slug: dente-calculo-recurringbilling
tldr: "Dente de cálculo do RecurringBilling (programa de ondas, Onda 1.4 aplicada ao RB). 1 PR MERGED #3737, TEST-ONLY. Novo CalculoRecurringBillingTest cobre 3 superfícies REAIS (fidelidade de valor + ciclo NoOverflow + divergência das 3 impls de próximo-vencimento). Achado: pró-rata/cupom/take-rate do briefing NÃO existem no módulo."
prs: [3737]
decided_by: [W]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente, 0062-separacao-runtime-hostinger-ct100]
next_steps: ["US separada sob REGRA MESTRE pra unificar as 3 implementações de próximo-vencimento (avancarCiclo NoOverflow/EN vs calcularProximoVencimento & recalcularProximaCobranca Overflow/PT)"]
---

# Dente de cálculo — RecurringBilling (Onda 1.4 aplicada ao motor de cobrança recorrente)

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ATIVO em COPI (off-cycle).
- **my-work (@wagner):** 30 tasks (8 review / 8 blocked / 14 todo). Vizinhas ao tema: `US-RECURRINGBILLING-002/003/004` (p0/p1, TODO — motor/boleto/NFSe). Esta sessão NÃO fechou task MCP: veio via `parent_plan=programa-ondas`, não US rastreada.
- **decisions-search:** nenhuma ADR nova criada nesta sessão.

## O que aconteceu
Pedido: dente de cálculo do RecurringBilling (programa de ondas, Onda 1.4). **TEST-ONLY.** O briefing citava pró-rata, cupom/desconto recorrente e take rate — **verificado 2026-07-03 (grep em todo `Modules/RecurringBilling/`): NENHUM existe.** Testar feature inexistente seria tautológico (proibicoes §"teste que deriva do código"). O "cálculo" real do RB reduz a 3 superfícies, e o teste trava as 3:

1. **Fidelidade de valor (end-to-end)** — fecha o gap "0 teste do VALOR final da fatura". `plan.valor → invoice.valor` sem inflar (property 7 valores + golden do milhar `1.234,56` sobrevive mesmo o `SubscriptionEvent` formatando pt-BR "R$ 1.234,56" — análogo do vetor `num_uf`).
2. **Avanço de ciclo NoOverflow** — edge dia-31 + os 4 ciclos (antes só havia teste dia-10/monthly). `avancarCiclo(2026-01-31, monthly)=2026-02-28`.
3. **Divergência das 3 implementações de "próximo vencimento"** — análogo exato do `getTotalPaid ≠ getTotalAmountPaid` da Onda 1.4:
   - A `InvoiceGeneratorService::avancarCiclo` — enum EN, **NoOverflow**, default=+1mês
   - B `AssinaturaService::calcularProximoVencimento` — enum PT, Overflow, default=**NO-OP**
   - C `AssinaturaCobrancaService::recalcularProximaCobranca` — enum PT, Overflow, default=**NO-OP**
   Storage com vocabulário **SPLIT** (`rb_plans.ciclo`=EN vs `metadata['ciclo']`=PT). Caracteriza: anchor-31 A fica em fev / B transborda pra mar; default cruzado A avança / B,C **congelam** (re-cobrança presa = bug latente); B≡C hoje (duplicata que o docblock diz "compartilhada"). Nomeia a fonte de verdade (A, o job que fatura). **NÃO unifica** — vira US separada sob REGRA MESTRE.

## Artefatos gerados
- `tests/Feature/Calculo/CalculoRecurringBillingTest.php` (387 linhas) — classe espelhando `CalculoValorSellsTest`, `DatabaseTransactions`, biz=1 (ADR 0101), reflection nos helpers privados A/C, discriminador RED in-suite.

## Prova RED/GREEN (CT100 `oimpresso-staging`, MySQL real, biz=1 — ADR 0062/0101)
- **GREEN:** 24 passed / 42 assertions.
- **RED:** anchor `2026-01-31` monthly → real NoOverflow `2026-02-28` (golden GREEN) vs versão Overflow `2026-03-03` (golden RED, pega a regressão). Provado por probe read-only no container (mutação do source de prod foi **bloqueada** pelo classifier — correto; nunca toquei prod). Discriminador reproduz o Overflow **inline** (TEST-ONLY, não muta prod).
- **CI PR #3737:** lane `PHP / Pest (Arquivos · MySQL)` **pass**; mergeado com todos os required verdes, 0 fail.

## Persistência
- **git:** PR #3737 squash-merged em `main` (`1bb37f6fd3`). Este handoff via branch `claude/handoff-dente-rb`.
- **MCP:** webhook GitHub→MCP propaga o handoff em ~2min após push.
- **BRIEFING:** não tocado (TEST-ONLY, sem mudança de capacidade do módulo).

## Próximos passos pra retomar
- Se for atacar a divergência: abrir US "unificar próximo-vencimento do RB" sob REGRA MESTRE (dupla confirmação + tabela antes→depois das datas de cobrança afetadas + OK [W]). Decisão de fonte de verdade já documentada no teste: A (`InvoiceGeneratorService`, NoOverflow) é quem fatura.

## Lições catalogadas
- **Briefing pode citar features que não existem** — o RB não tem pró-rata/cupom/take-rate. Grep-antes-de-testar evitou 3 testes tautológicos. Alinha com proibicoes §"teste que deriva do código".
- **Mutar source de prod no container staging compartilhado é bloqueado pelo auto-mode classifier** (corretamente). RED se prova com discriminador in-suite (convenção `CalculoValorSells`) + probe read-only — nunca mutando prod.

## Pointers detalhados (on-demand)
- Plano: `memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md`
- Irmão hoje: `memory/handoffs/2026-07-03-1044-fin-dente-calculo.md` (mesmo dente no Financeiro)
- Código-alvo: `Modules/RecurringBilling/Services/{InvoiceGeneratorService,AssinaturaService,AssinaturaCobrancaService}.php`
