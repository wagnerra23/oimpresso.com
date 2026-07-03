# Handoff — Programa de Ondas: cálculo de valor Tier-0 blindado (arco completo)

**Data:** 2026-07-03 · **Sessão:** design-sync → programa de ondas (mega-sessão paralela)
**Convenção:** [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) (append-only, MCP-first)

## O arco (de onde veio → onde chegou)

Começou em *"como sincronizar com o design?"*. O fluxo canônico `aplicar-prototipo` provou que
o bundle Cowork já estava absorvido (nada a aplicar). Ao auditar a tela-teste `/perfil`,
apareceu o padrão: **telas migradas e módulos de nota alta escondiam cálculo de valor
indefeso** — a mesma classe do incidente `num_uf` (R$ inflado ×100k, 2026-06-05).

Três auditorias adversariais + comparação com estado-da-arte 2026 deram a nota de garantia
**≈28/100** ponderada por Tier-0, com a dimensão pior (cálculo de valor, D1) em **15/100**. O
insight: a máquina de governança do projeto é de classe mundial (**D8=70**) mas guardava a
porta errada — protegia segurança (multi-tenant/PII/secrets), cega no cálculo de dinheiro.

Isso virou o **Programa de Ondas** (PLANO-MESTRE em `memory/requisitos/_Governanca/programa-ondas/`):
adversário por módulo (`capterra-senior`) → gaps/backlog (`/comparativo`) → régua por tela com
comportamento plugado → catraca. Reusa o que existe, pluga a dimensão que falta, encaixa nos
roadmaps (T6). Formalizado na **ADR 0320** (aceita por Wagner).

## Resultado — o portfólio Tier-0 inteiro tem dente de cálculo

9 testes de cálculo de valor, um por módulo de dinheiro (`tests/Feature/Calculo/`):

| Módulo | Dente | Cobre |
|---|---|---|
| Sells | `CalculoValorSellsTest` | `num_uf` / `calculateInvoiceTotal` |
| Compras | `CalculoValorComprasTest` | valor+estoque no `POST /purchases` |
| Financeiro | `CalculoValorFinanceiroTest` | `calculatePaymentStatus` + imposto |
| Cliente | `CalculoValorClienteTest` | saldo/ledger |
| Produto | `CalculoValorProdutoTest` | margem/preço/combo |
| Fiscal+NfeBrasil+NFSe | `CalculoTributarioTest` | motor tributário (1 dente, motor compartilhado) |
| PaymentGateway | `CalculoPaymentGatewayTest` | refund/CNAB |
| RecurringBilling | `CalculoRecurringBillingTest` | pró-rata/invoice |
| OficinaAuto | `CalculoValorOficinaAutoTest` | OS peças+serviço (test-only, canary intacto) |

\+ 5 fichas de capacidade novas (Produto, Cliente, Fiscal, PaymentGateway, NFSe) + régua por
tela + catraca por módulo. ~80 PRs na sessão (o núcleo do programa: #3694–#3777).

## Descobertas emergentes (não pedidas, alto valor)

- **Reforma tributária IBS/CBS** ([ADR 0321](../decisions/proposals/0321-pin-sped-nfe-dev-master-ibs-cbs.md)): a onda fiscal descobriu que o motor
  tributário precisava de IBS/CBS e entregou cálculo fim-a-fim atrás de feature flag (default OFF,
  byte-idêntico pro legado). Complementa a ARQ-0004 (schema flexível CBS/IBS).
- **`safe-merge.sh`** (#3768): guard anti-desync de merge criado após near-miss — a disciplina
  se auto-reforçou.

## Lições (o que fez a sessão dar certo)

- **Conferir antes de agir** evitou 2 erros caros: (a) abrir 16 chips quando 6 bastavam (fichas
  já existentes / colisão do motor tributário / ADR 0105 sinal); (b) "mover a correção pra
  onda-3-financeiro" — que teria violado a ADR 0320 que o próprio Wagner aprovara. A intuição
  do Wagner ("roadmap antigo é problema") forçou a leitura da ADR e confirmou o encaixe correto.
- **Governança respeitada em cada passo:** T6 (encaixar, não paralelo), ADR 0105 (sinal de
  cliente por onda), REGRA MESTRE (dente é test-only, unificação de cálculo = US separada),
  CT100-only pra testes, R10 (merge só com OK [W] — o classificador barrou um auto-merge e
  estava certo).
- **US no SPEC = task MCP:** "criar tasks dos gaps" era no-op — as sessões já as materializaram.

## Resíduo (nada urgente)

- **Repair** (sinal a confirmar) e **Manufacturing** (sem sinal — ADR 0105) conscientemente
  segurados. Únicos Tier-0 fora do ciclo.
- Tracking do PLANO-MESTRE: Ondas 0-6 registradas com detalhe pelas sessões; Ondas 7 (NFSe),
  8 (PaymentGateway), 9 (RecurringBilling), 10 (OficinaAuto) landaram — completar as linhas do
  tracking numa próxima passada de higiene (os handoffs individuais R12 já cobrem a narrativa).
- 7 tasks de gap atribuídas a wagner (US-COM-007/008/011, US-SELL-054..057); demais no backlog `todo`.

## Estado MCP no fechamento

- **ADRs da sessão:** 0320 (programa-ondas, aceita) · 0321 (IBS/CBS sped-nfe pin, proposta) +
  ADRs de módulo (NfeBrasil ARQ-0004/UI-0003, RecurringBilling ARQ-0004).
- **Handoffs irmãos** (sessões filhas, R12): `2026-07-03-1730-onda-cliente-capterra-completa.md`,
  `2026-07-03-2100-fiscal-onda6-ibscbs-us021.md`, + os de OficinaAuto/PaymentGateway/dente-fiscal.
- **Cycle:** off-cycle (programa transversal).
