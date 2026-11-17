---
id: requisitos-oficina-auto-runbook-service-orders-show
title: "RUNBOOK — Detalhe da OS (OficinaAuto/ServiceOrders/Show)"
module: OficinaAuto
tela: OficinaAuto/ServiceOrders/Show
owner: W
status: ativo
last_validated: "2026-11-05"
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
preconditions:
  - "Ler Show.charter.md e Show.casos.md ao lado do .tsx"
steps:
  - "Itens, DVI e fiscal mudam por endpoint próprio; a página só recarrega o payload"
  - "Entrega com foto e assinatura NÃO acontece nesta página: é no drawer do quadro"
---

# RUNBOOK — Detalhe da OS (`/oficina-auto/ordens-servico/{order}`)

> Descreve o código de `origin/main` em 2026-11-05 (`7d997591d7`). Substitui, para esta tela, a
> seção `ServiceOrders/Show.tsx` do [`RUNBOOK-show.md`](RUNBOOK-show.md) (V0 de 2026-05, que
> cobria as duas telas Show e não tinha DVI, itens nem etapa).

## Peças

| Peça | Path |
|---|---|
| Página | `resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx` |
| Controller | `ServiceOrderController@show` (`Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php`) |
| Etapa FSM (só leitura) | `_components/ServiceOrderStagePipeline.tsx` |
| Gate de aprovação do cliente | `_components/ApprovalGateCard.tsx` |
| Vistoria → orçamento | `_components/DviBudgetSection.tsx` |
| Itens (peça, mão de obra, serviço) | `_components/ServiceOrderItemRow.tsx`, `ServiceOrderItemFormSheet.tsx` |
| Split fiscal (presentacional) | `_components/FiscalSplitCard.tsx` |
| Impressão A4 | `resources/js/Lib/printServiceOrder.ts` → `GET ordens-servico/{order}/print` |

## Contrato

- **Payload:** `order` (OS + `items` + `dvi_items`) montado em `ServiceOrderController@show`.
  A venda derivada só aparece se for da mesma empresa da OS (#10146, UC-OSH-11).
- **Ações na página:** editar (vai para `Edit`), imprimir A4, adicionar/editar/remover item,
  levar item reprovado da vistoria para o orçamento.
- **Etapa:** o pipeline é só indicador. Mudar etapa é no quadro (`Board`), pelo
  `ServiceOrderFsmActionPanel` do drawer `ProducaoOficina/_components/ServiceOrderRichSheet.tsx`.

## Entrega (foto + assinatura) — onde fica

Os casos UC-OSH-12..22 estão no `Show.casos.md`, mas a interface da entrega (checklist
`ServiceOrderStageGate.tsx` e quadro `ServiceOrderSignaturePad.tsx`) é montada no drawer do quadro,
não nesta página. A regra é do servidor (`StageGateEvaluator`, ação `entregar`) e vale para web e
app. Detalhe em [`RUNBOOK-fsm-pipeline.md`](RUNBOOK-fsm-pipeline.md).

## Testes

`ServiceOrderItemHttpIntegrationTest`, `ServiceOrderPrintTest`, `ServiceOrderEntregaFotoTest`,
`ServiceOrderEntregaAssinaturaTest`, `ServiceOrderStagePipelineTest` em
`Modules/OficinaAuto/Tests/Feature/`. Rodam no CT 100 ou na lane `oficinaauto-pest`.

## Pegadinhas

- Itens mexem em VALOR (total da OS e da venda derivada): regra mestre de cálculo em
  `memory/proibicoes.md`.
- O select de status fica no `Edit`, não aqui; ele é o que dispara a venda derivada
  (`status = concluida`). Decisão D1 do [W] aberta.
