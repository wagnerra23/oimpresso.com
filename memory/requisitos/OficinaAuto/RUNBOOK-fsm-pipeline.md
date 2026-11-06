---
id: requisitos-oficina-auto-runbook-fsm-pipeline
title: "RUNBOOK — Fluxo de etapas (FSM) da OS e a coluna status (OficinaAuto)"
module: OficinaAuto
owner: W
status: ativo
last_validated: "2026-11-05"
related_adrs:
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0129-state-machine-canonica-fsm-rbac"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
  - "0265-oficina-reparo-erradica-locacao"
  - "0093-multi-tenant-isolation-tier-0"
preconditions:
  - "OficinaAutoFsmSeeder rodado para a empresa (cria os 3 processos)"
steps:
  - "Ler a seção Dois donos do estado antes de mexer em faturamento, estoque ou entrega"
  - "Transição de etapa sempre por ExecuteStageActionService (nunca UPDATE em current_stage_id)"
---

# RUNBOOK — Fluxo de etapas (FSM) da OS e a coluna `status`

> Descreve o código de `origin/main` em 2026-11-05 (`7d997591d7`). A versão anterior deste arquivo
> (2026-05-20) documentava só os processos de locação de caçamba, erradicados pela ADR 0265; o
> processo vivo `oficina_mecanica_os` não aparecia. Histórico no `git log` deste arquivo.

## Dois donos do estado da OS — decisão D1 do [W], aberta

Hoje uma OS tem **dois campos de estado que não se conversam**. Este RUNBOOK descreve o fato e
**não escolhe** qual vale: é a decisão D1 do [W], aberta desde a auditoria de 2026-11-05.

| | Coluna `service_orders.status` | Etapa FSM `service_orders.current_stage_id` |
|---|---|---|
| Valores | `aberta`, `orcamento`, `aprovada`, `em_servico`, `em_producao`, `concluida`, `entregue`, `cancelada` (`ServiceOrderController::statuses()`, rotulado "Status livre V0") | etapas do processo da OS (tabelas abaixo) |
| Quem grava | select "Status *" do `ServiceOrders/Edit.tsx`; o Create envia `aberta` fixo; `Store/UpdateServiceOrderRequest` exige o campo | só `ExecuteStageActionService` e `ServiceOrderPipelineStarter` (o trait `GuardsFsmTransitions` barra o resto, `ServiceOrder.php:62`) |
| O que dispara | `ServiceOrderObserver` (`ServiceOrderObserver.php:72-75`): quando `status` vira `concluida`, cria a venda derivada (`Transaction`) e baixa o estoque dos itens | nada no faturamento; as ações do `oficina_mecanica_os` não têm side-effect |
| Quem lê | Observer, filtros do Board (`concluida`, `cancelada`, `entregue` em `ServiceOrderController.php:369`), side-effects legados | Board (colunas), drawer, gate de etapa |

**Consequência pelo código (não medida em produção):** levar a OS até `entregue` pelo quadro não
grava `status`, então não gera venda nem baixa estoque. Só o status `concluida` escolhido no Edit
gera. Nenhuma ação de nenhum dos 3 processos escreve `service_orders.status` (os side-effects
legados escrevem `vehicles.current_status`, não a OS). Os testes de venda derivada gravam `status`
direto (`FsmTransitionTest.php`, `DemoOsSmokeTest.php`).

Mexer em qualquer um dos dois lados toca VALOR e ESTOQUE: vale a regra mestre de cálculo
(`memory/proibicoes.md`), com dupla prova e antes→depois apresentado ao [W].

## Os 3 processos que o seeder cria por empresa

`ServiceOrderPipelineStarter` escolhe o processo pelo `order_type` da OS:

| `order_type` | Processo | Situação |
|---|---|---|
| `mecanica` | `oficina_mecanica_os` | fluxo vivo de reparo (confirmado [W] 2026-06-02) |
| `manutencao` | `cacamba_manutencao` | legado preservado para não orfanar OS antigas |
| — | `cacamba_locacao` | ainda seedado; não é alvo do starter (ver RUNBOOK de erradicação) |

### `oficina_mecanica_os` (vivo)

Etapas (`OficinaAutoFsmSeeder::MECANICA_STAGE_META`): `recepcao` (inicial) → `em_diagnostico` →
`aguardando_aprovacao` → `aguardando_pecas` → `em_execucao` → `pronto_retirada` → `entregue`
(terminal). Terminais laterais: `cancelado`, `garantia_acionada`.

| De | Ação | Para | Crítica | Papéis |
|---|---|---|---|---|
| recepcao | `iniciar_diagnostico` | em_diagnostico | não | mecanico, gerente |
| em_diagnostico | `enviar_orcamento` | aguardando_aprovacao | não | mecanico, gerente |
| aguardando_aprovacao | `aprovar_pedir_pecas` | aguardando_pecas | sim | gerente, mecanico |
| aguardando_aprovacao | `aprovar_executar` | em_execucao | sim | gerente, mecanico |
| aguardando_aprovacao | `recusar_orcamento` | cancelado | sim | gerente |
| aguardando_pecas | `pecas_chegaram` | em_execucao | não | mecanico, gerente |
| em_execucao | `concluir_servico` | pronto_retirada | sim | mecanico, gerente |
| pronto_retirada | `entregar` | entregue | não | mecanico, gerente |
| pronto_retirada | `acionar_garantia` | garantia_acionada | sim | gerente |
| recepcao, em_diagnostico, aguardando_pecas, em_execucao, pronto_retirada | `cancelar_os` | cancelado | sim | gerente |

Todas sem side-effect (`side_effect_class` nulo). A ação `entregar` tem gate
(`StageGateEvaluator`): foto do serviço feito, sempre; assinatura do cliente quando a empresa liga
a exigência (`php artisan oficina:entrega-assinatura {biz} --ligar`). O servidor recusa o
`fsm/execute` com 422 se o gate não passa.

### `cacamba_manutencao` (legado)

`aberta` → `em_servico` → `concluida` (terminal); `cancelada` lateral. Ações `iniciar_servico`,
`concluir`, `cancelar`, com side-effects `IniciarServicoCacamba`, `ConcluirServicoCacamba`,
`CancelarServicoCacamba` que só mexem em `vehicles.current_status`.

### `cacamba_locacao` (resíduo)

Chaves e side-effects intocados por Tier 0 (ADR 0143/0194); rótulos já em vocabulário de reparo
(migration `2026_06_10_000002`). Medição do resíduo em
[`RUNBOOK-erradicacao-locacao.md`](RUNBOOK-erradicacao-locacao.md).

## Onde mora cada peça

| Peça | Path |
|---|---|
| Orquestrador FSM (compartilhado com Vendas) | `app/Domain/Fsm/` |
| Entity + trava | `Modules/OficinaAuto/Entities/ServiceOrder.php` (`GuardsFsmTransitions`) |
| Seeder | `Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php` |
| Starter (order_type → processo) | `Modules/OficinaAuto/Services/ServiceOrderPipelineStarter.php` |
| Gate de etapa | `Modules/OficinaAuto/Services/StageGateEvaluator.php`, `AssinaturaEntrega.php` |
| Endpoints | `app/Http/Controllers/ServiceOrderFsmActionController.php` |
| Faturamento pela coluna `status` | `Modules/OficinaAuto/Observers/ServiceOrderObserver.php` |
| UI | `ServiceOrders/Board.tsx`; `_components/ServiceOrderFsmActionPanel.tsx`, `ServiceOrderStagePipeline.tsx`, `ServiceOrderStageGate.tsx`, `ServiceOrderTimeline.tsx` |

## Endpoints (`Modules/OficinaAuto/Routes/web.php:152-185`, prefixo `/oficina-auto`)

| Verbo | Path | Função |
|---|---|---|
| GET | `service-orders/{order}/fsm/actions` | ações disponíveis + etapa atual + pipeline |
| POST | `service-orders/{order}/fsm/execute` | executa a ação (throttle 60/min); 422 se o gate falha |
| POST | `service-orders/{order}/fsm/start-pipeline` | põe OS sem etapa no processo do seu `order_type` (throttle 30/min) |
| GET | `service-orders/{order}/history` | trilha de transições |
| GET | `service-orders/{order}/fsm/gate` | checklist da próxima transição |

## Pegadinha — histórico compartilhado com Vendas

As transições gravam em `sale_stage_history`, a mesma tabela de Vendas, com o id da OS em
`transaction_id`. Não há coluna de tipo: o `history()` discrimina pelo processo da ação e, para a
entrada de início de pipeline (sem ação), por `payload.pipeline_started`. Consulta direta por
`transaction_id` mistura OS e venda de mesmo id.

## Testes que cobrem

`Modules/OficinaAuto/Tests/Feature/`: `FsmTransitionTest`, `ServiceOrderGuardsFsmTest`,
`ServiceOrderHistoryControllerTest`, `ServiceOrderIndexStageFilterTest`, `ServiceOrderStageGateTest`,
`ServiceOrderStagePipelineTest`. Rodam no CT 100 ou na lane `oficinaauto-pest`, nunca local.
