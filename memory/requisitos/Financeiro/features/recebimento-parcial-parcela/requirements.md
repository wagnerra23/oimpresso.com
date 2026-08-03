---
id: requisitos-financeiro-features-recebimento-parcial-parcela-requirements
feature: recebimento-parcial-parcela
module: Financeiro
us: ["US-FIN-003"]
parent_plan: financeiro-recebimento-parcial-parcela
created: "2026-08-03"
---

# Requirements — recebimento parcial de parcela de cliente

> **US-mãe:** [US-FIN-003](../../SPEC.md) · **Sinal (ADR 0105):** drift arquitetural
> detectado entre a baixa manual da Visão Unificada, que grava `TituloBaixa` diretamente,
> e o recebimento por `TransactionPayment`, que grava `TituloBaixa` e `CaixaMovimento`.
> O pedido [W] de 2026-08-03 foi tornar esse fluxo documentado e controlável pela máquina.

## User story

**Como** Larissa-financeiro
**Quero** receber somente uma parte de uma parcela em aberto de um cliente
**Para** registrar exatamente o dinheiro que entrou hoje e continuar cobrando o saldo restante sem dupla digitação

## Clarifications (fase Clarify — Spec Kit 2026)

- **2026-08-03** — P: “Parcela” é um objeto separado? → R: no modelo atual, cada parcela é um
  `fin_titulos`; o recebimento parcial atua sobre um único título do tipo `receber`.
- **2026-08-03** — P: o título deve receber `status=parcial`? → R: não. O contrato vigente
  `CU-FIN-02` exige SPLIT: filho quitado no valor recebido e pai reduzido, ainda aberto.
- **2026-08-03** — P: esta geração altera valores em produção? → R: não. Este trio documenta
  requisitos, arquitetura e tarefas. Qualquer implementação que escreva dinheiro continua bloqueada
  pela REGRA MESTRE: impacto antes→depois, dois caminhos de confirmação e aprovação [W].
- **2026-08-03** — P: juros, multa, desconto e crédito por pagamento excedente entram agora? → R:
  não. A primeira entrega consolida principal recebido, split, caixa e idempotência; composição financeira
  adicional precisa de contrato próprio.

## Exemplo dourado

Dado o título/parcela `R-00042` de **R$ 300,00** totalmente aberto, o cliente paga **R$ 120,00**:

| Registro | Antes | Depois esperado |
|---|---:|---:|
| Título pai `R-00042` | total R$ 300,00 · aberto R$ 300,00 · `aberto` | total R$ 180,00 · aberto R$ 180,00 · `aberto` |
| Título filho | inexistente | total R$ 120,00 · aberto R$ 0,00 · `quitado` · `titulo_pai_id=R-00042` |
| Baixa append-only | inexistente | R$ 120,00 vinculados ao filho |
| Movimento de caixa | inexistente | entrada de R$ 120,00 na conta escolhida |

Confirmação de valor por dois caminhos independentes:

1. `filho.valor_total + pai.valor_aberto = 120,00 + 180,00 = 300,00`;
2. `SUM(fin_titulo_baixas.valor_baixa) = SUM(fin_caixa_movimentos.valor) = 120,00`.

## Acceptance criteria (EARS — ADR 0306)

- **AC-1** — QUANDO um título a receber aberto de valor `V` receber `B`, com `0,01 ≤ B < V`,
  O SISTEMA DEVE criar um filho quitado de `B`, reduzir o pai para `V-B` e mantê-lo aberto, sem usar
  `status=parcial`. _Prova: Pest MySQL com R$ 100,00 → R$ 37,45 e asserção do split._
- **AC-2** — QUANDO o recebimento for confirmado, O SISTEMA DEVE gravar na mesma transação uma
  `fin_titulo_baixas` append-only e uma entrada correspondente em `fin_caixa_movimentos`.
  _Prova: Pest compara contagem, valor, conta, data e `origem_id` dos dois registros._
- **AC-3** — O SISTEMA DEVE conservar centavo a centavo o valor original e provar a operação por
  dois caminhos independentes: títulos pai+filhos e baixa+caixa. _Prova: Pest do exemplo dourado e
  de valores com centavos; a ausência de qualquer registro falha por pré-condição anti-vácuo._
- **AC-4** — SE a mesma confirmação HTTP for repetida com a mesma `idempotency_key`, ENTÃO O SISTEMA
  DEVE devolver o mesmo resultado sem criar outro filho, outra baixa ou outro movimento de caixa.
  _Prova: duas requisições idênticas e contagens estáveis em todas as tabelas afetadas._
- **AC-5** — SE o título estiver quitado/cancelado ou a conta bancária pertencer a outro `business_id`,
  ENTÃO O SISTEMA DEVE recusar a operação sem escrita parcial. _Prova: Pest cross-tenant biz=1 versus
  biz=2 e comparação antes→depois igual em títulos, baixas e caixa._
- **AC-6** — QUANDO `B` exceder o valor aberto, O SISTEMA DEVE quitar exatamente o aberto, nunca gerar
  saldo negativo nem crédito implícito. _Prova: Pest envia R$ 999.999,99 para título de R$ 80,00 e
  confirma baixa+caixa de R$ 80,00._
- **AC-7** — QUANDO a operação concluir, O SISTEMA DEVE informar o valor recebido e o saldo restante;
  em falha, deve preservar os dados digitados e apresentar erro acionável. _Prova: caso de tela da Visão
  Unificada e teste do contrato do `FinBaixaSheet`._

## Fora de escopo

- Alterar o contrato vigente de SPLIT para `status=parcial`.
- Criar crédito automático quando o cliente paga acima do aberto.
- Calcular juros, multa, desconto, tarifa de adquirente ou diferença cambial.
- Implementar estorno, conciliação bancária ou recebimento em lote nesta feature.
- Executar backfill ou qualquer escrita em dados de produção durante a fase de documentação.

## Referências

- [SPEC.md](../../SPEC.md) — US-FIN-003.
- [SDD da tela Financeiro](../../SDD-tela-financeiro-v1.0.md) — CU-FIN-02..05 e CU-FIN-08.
- [Casos da Visão Unificada](../../../../../resources/js/Pages/Financeiro/Unificado/Index.casos.md) — UC-FUNI-01..04.
- [ADR 0093](../../../../decisions/0093-multi-tenant-isolation-tier-0.md) — isolamento multi-tenant.
- [Proibições](../../../../proibicoes.md) — REGRA MESTRE de valor e append-only contábil.
