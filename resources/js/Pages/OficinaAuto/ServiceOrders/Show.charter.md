---
id: resources-js-pages-oficina-auto-service-orders-show-charter
page: /oficina-auto/service-orders/{id}
component: resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx
related_runbook: memory/requisitos/OficinaAuto/RUNBOOK-service-orders-show.md
related_visual_comparison: memory/requisitos/OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md
visual_source: oficina-os-page.jsx
owner: wagner
status: live
last_validated: "2026-11-05"
parent_module: OficinaAuto
related_us: [US-OFICINA-006, US-OFICINA-035, US-OFICINA-038, US-OFICINA-014, US-OFICINA-017, US-OFICINA-018, US-OFICINA-027, US-OFICINA-037, US-OFICINA-040, US-OFICINA-041, US-OFICINA-042]
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0110-tipografia-canon-h1-subtitle
  - 0093-multi-tenant-isolation-tier-0
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0179-cliente-drawer-760px-substitui-show-fullpage
  - 0190-primary-button-roxo-universal-295
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
charter_version: 4
---

# Page Charter — /oficina-auto/service-orders/{id}

> **Status:** live (V0). Detalhe completo de uma OS com FSM action panel + timeline auditável.
>
> **Sub-vertical 4 ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** OS Martinho biz=164 é sub-vertical 4 mecânica pesada. Card "Valor a receber" `daily_rate × dias_locacao` aplica primariamente pra schema sub-vertical 3 hipotético preservado nullable — V0 OS mecânica retorna R$ [redacted Tier 0] hoje (Wagner edita manual). Recalc canon V1 = `peça×qty + hora-trabalho×horas` via US-OFICINA-027 P0 8h ([levantamento 2026-05-26](../../../../../memory/sessions/2026-05-26-levantamento-martinho-ready.md) §B2). Card "Esta OS gerou venda #V-NNNN" LIVE (ADR 0192 ext + Onda 7 PR #1534 componente shared `@/Components/shared/VendaDerivadaCard.tsx`).

> **Conferido contra o código em 2026-11-05 (v4, lote 5.5 da auditoria):** Goals, Tests e Refs
> reescritos para o que `Show.tsx` renderiza hoje. O parágrafo acima é de 2026-05 e cita locação,
> erradicada pela ADR 0265. Non-Goals e UX Anti-patterns **não foram alterados** — só o [W]
> preenche; as divergências medidas estão anotadas logo antes de cada seção.

## Mission

Tela única-fonte-da-verdade sobre 1 OS — mecânico/atendente acompanha estado FSM, próximas ações disponíveis, histórico append-only de transições, valor acumulado (locação ativa).

> Nota 2026-11-05: "locação ativa" é texto de 2026-05 (ADR 0265 erradicou locação). Reescrever a Mission é do [W].

## Goals — Features (faz)

Conferido em `Show.tsx` e `ServiceOrderController@show` (2026-11-05):

- `PageHeader` com o número da OS e ações Voltar, Editar e Imprimir (A4 via `printServiceOrder`, US-OFICINA-037).
- Resumo da OS: veículo, cliente, tipo, datas e check-in de entrada (combustível, avarias).
- **Etapa FSM só leitura**: `ServiceOrderStagePipeline`. O painel de ações FSM e a linha do tempo
  ficam no drawer do quadro (`ServiceOrderRichSheet`), não nesta página.
- **Gate de aprovação do cliente**: `ApprovalGateCard` (US-OFICINA-041).
- **Vistoria → orçamento**: `DviBudgetSection` (US-OFICINA-035/040).
- **Itens da OS** inline (peça, mão de obra, serviço) com editar/excluir por linha e
  `ServiceOrderItemFormSheet` para adicionar (US-OFICINA-027).
- **Split fiscal presentacional**: `FiscalSplitCard` (US-OFICINA-042; não emite documento).
- Multi-tenant Tier 0: OS de outra empresa → 404; a venda derivada só vem se for da mesma
  empresa (#10146, UC-OSH-11).
- **Entrega (foto + assinatura)**: a regra é do servidor (`StageGateEvaluator`, ação `entregar`);
  os casos UC-OSH-12..22 estão no `Show.casos.md`, mas a interface (checklist + quadro de
  assinatura) é montada no drawer do quadro. Ver o RUNBOOK da tela.

## Non-Goals — Features (NÃO faz)

> Nota 2026-11-05 (sem alterar a lista — só o [W] edita): "Edição de items inline (vai pra Edit
> page)" diverge do código: a página edita e exclui itens inline desde a Wave 5 (US-OFICINA-027).


- UPDATE direto em `current_stage_id` (PROIBIDO — usar `ExecuteStageActionService`)
- Comentários livres (vai pra `notes` field ou audit log via action)
- Edição de items inline (vai pra Edit page)
- Geração NFe inline (núcleo Modules/NfeBrasil via action canônica)

## UX Targets

- p95 first-paint < 600ms (1 OS + 20 history entries)
- FSM action button disabled state com tooltip explicativo ("Role X não autoriza")
- Timeline scroll virtual quando > 50 entries
- Cores semânticas Cockpit V2 consistentes com Index

## UX Anti-patterns

> Nota 2026-11-05 (sem alterar a lista — só o [W] edita): "Edição direta de status via select
> (PROIBIDO)" — o select de status existe no `Edit.tsx` e é ele que dispara a venda derivada
> (`status = concluida`). É a decisão D1 do [W], descrita em `RUNBOOK-fsm-pipeline.md`.


- Botão FSM sem checagem RBAC (canon = `sale_stage_action_roles`)
- Submit FSM sem confirm dialog em ações críticas (`is_critical=true`)
- Edição direta de status via select (PROIBIDO — usar action panel)
- Eager-load completo de transaction.lines sem defer

## Tests anti-regressão

`Modules/OficinaAuto/Tests/Feature/`: `ServiceOrderCrudTest`, `ServiceOrderItemHttpIntegrationTest`,
`ServiceOrderPrintTest`, `ServiceOrderStagePipelineTest`, `ServiceOrderEntregaFotoTest`,
`ServiceOrderEntregaAssinaturaTest`, `FsmTransitionTest`. Casos: [`Show.casos.md`](Show.casos.md).

## Refs

- [SPEC.md US-OFICINA-001 + US-OFICINA-003](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [RUNBOOK-service-orders-show.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-service-orders-show.md) (substitui, para esta tela, o V0 [RUNBOOK-show.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-show.md))
- [RUNBOOK-fsm-pipeline.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-fsm-pipeline.md) — os dois donos do estado da OS (D1)
- [ADR 0143 FSM pipeline LIVE prod](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0129 FSM canônica tabular](../../../../../memory/decisions/0129-state-machine-canonica-fsm-rbac.md)

## UCs cobertos (PRECISA TER · rastreável · §10.4 [CC])

> Casos de Uso ("A tela precisa:") amarrados a GUARD Pest `uc-<id>` via [`scripts/design/audit/uc-registry.json`](../../../../../scripts/design/audit/uc-registry.json).
> ✅ presente+travado (some o elemento = build vermelho) · 🟡 gap (acende no `protocol_freshness`, advisory).

- ✅ **UC-03** (`uc-03`) — vistoria digital (DVI) por item com foto + mapeamento achado→item de orçamento (`DviBudgetSection`).
- ✅ **UC-05** (`uc-05`) — gate de aprovação: aprovação item a item, recusados, parcial, estado "Aprovada" (`ApprovalGateCard`).
- ✅ **UC-09** (`uc-09`) — split fiscal: soma peças = NF-e, soma serviços = NFS-e (a tela prepara; emissão é listener) (`FiscalSplitCard`).
- 🟡 **UC-04** — estado explícito "Orçamento enviado" + agrupar itens por seção + envio com registro. _(sem cobertura)_
- 🟡 **UC-06** — ciclo de peça (necessária→cotada→pedida→recebida), reserva e baixa de estoque vinculada à OS. _(sem cobertura)_
- 🟡 **UC-07** — apontamento de tempo, checklist de roteiro, pausa com motivo. _(sem cobertura)_
- 🟡 **UC-08** — etapa/checklist de qualidade entre execução e pronto. _(sem cobertura)_
- 🟡 **UC-10** — histórico do veículo (passagens anteriores), retorno de garantia, gatilho de lembrete. _(sem cobertura)_
