---
slug: 0434-fsm-pedido-retomar-pagamento-fora-das-etapas
number: 434
title: "FSM do pedido — retomar produção, pagamento fora das etapas, conclusão pelo PCP e passos do app vindos do processo"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-16"
module: core
tags: [fsm, vendas, producao, pcp, app-mobile, financeiro]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0129-state-machine-canonica-fsm-rbac
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0431-pcp-sai-do-desejo-modules-producao
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0434 — FSM do pedido: retomar, pagamento fora das etapas, conclusão pelo PCP

## Contexto

O processo `venda_com_producao` ([ADR 0129](0129-state-machine-canonica-fsm-rbac.md),
`database/seeders/FsmProcessoVendaComProducaoSeeder.php`) foi desenhado em 2026-05 como uma linha
única: Orçamento → Aprovado → Em produção → Pronto pra faturar → Faturada → **Paga** → Entregue →
Concluída, com `on_hold` (Em espera) como lateral.

A análise "FSM do pedido: ERP x app mobile" do Felipe, revisada por [W] em 2026-10-16, mostrou três
problemas medidos no seed:

1. `on_hold` só tinha **saída por cancelamento**. Pedido pausado não voltava à produção.
2. `entregar` só existia a partir de `paid`. O pedido não podia ser entregue antes de pago, e quem
   entrega fiado ou faturado a prazo ficava travado na etapa Faturada.
3. O app das lojas e a OS de Comunicação Visual tinham passos escritos à mão, que não seguiam o
   processo cadastrado.

Concorrentes conferidos na mesma análise: shopVOX, Odoo e ShopXpert têm "retomar" com motivo e
recalculam prazo; o Odoo separa faturamento de entrega (pagamento é situação da fatura, não etapa
do pedido).

## Decisão (5 itens, [W] 2026-10-16)

1. **Retomar produção.** Nova ação `retomar_producao`: `on_hold` → `in_production`, papel
   `producao.iniciar`, não crítica, sem side effect. Pausar e retomar aceitam `motivo` opcional,
   gravado em `sale_stage_history.payload_snapshot`. O app oferece o campo (`pede_motivo`).
2. **Pagamento sai da sequência de etapas.** O caminho padrão passa a ser Faturada → Entregue →
   Concluída. "Pago" vira **situação do título no Financeiro** (`sem_titulo | pendente | parcial |
   pago`), derivada dos títulos ligados à venda — não etapa do pedido. A etapa `paid` fica por
   compatibilidade: pedidos que já estão nela continuam podendo entregar.
3. **Conclusão pelo PCP.** Quando a última etapa do PCP termina, o ERP executa `concluir_producao`
   (com a baixa de estoque do side effect `ConsumirEstoque`), pelo mesmo `ExecuteStageActionService`.
4. **Passos do app vêm do processo.** A lista de passos do pedido no app é lida do processo
   cadastrado, não de uma lista fixa no código.
5. **Inclui a OS de Comunicação Visual.** O item 4 vale também para o processo da OS ComVis.

**Mantido por decisão [W]:** `pausar_producao` continua liberado no app. A proposta de tirá-lo do
app foi recusada.

## Consequências

- O seed não roda no deploy (`firstOrCreate`): cada mudança de ação chega às empresas que já têm o
  processo por **migration de dados**, copiando os papéis da ação equivalente do mesmo processo.
- `retomar_producao` entra na lista segura do app (`AcoesDoPedido::SEGURAS`). Empresa que gravou
  o ajuste `app_acoes_fsm_liberadas` só a vê se incluí-la no ajuste (o ajuste só estreita a lista).
- A regra mestre de valor/estoque ([proibicoes.md](../proibicoes.md)) vale para o item 3: a baixa de
  estoque automática exige dupla prova e antes→depois antes de ligar em produção.
- Itens 3, 4 e 5 são implementados em PRs próprios (`Modules/Producao` e a API do app), não neste.

## Alternativas descartadas

- **Manter `paid` como etapa obrigatória:** trava entrega a prazo e duplica o estado do Financeiro,
  que é quem sabe se o título foi pago.
- **Apagar a etapa `paid`:** quebraria pedidos que estão nela hoje e o histórico append-only que a
  referencia.
- **Tirar `pausar_producao` do app:** recusado por [W].
