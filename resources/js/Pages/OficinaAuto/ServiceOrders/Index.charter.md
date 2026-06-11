---
page: /oficina-auto/service-orders
component: resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-11"
parent_module: OficinaAuto
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0110-tipografia-canon-h1-subtitle
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0265-oficina-reparo-erradica-locacao
tier: A
charter_version: 5
---

# Page Charter — /oficina-auto/service-orders

> **Status:** live (V0). Listagem-detalhe canon Cockpit Pattern V2 das Ordens de Serviço.
>
> **Sub-vertical 4 ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** OS em prod biz=164 Martinho são sub-vertical 4 mecânica pesada (reparo de caminhão). Auto-faturar OS→Venda extensão ADR 0192 LIVE 2026-05-25 (Card "Esta OS gerou venda #V-NNNN" no drawer).
>
> **Erradicação de locação ([ADR 0265](../../../../../memory/decisions/0265-oficina-reparo-erradica-locacao.md) — 2026-06-09):** KPI "Locações ativas" **aposentado** (o backend removeu o KPI no W25/0265). Os 4 KPIs do topo passam a ser de reparo (Em diagnóstico / Aguardando aprovação / Em execução / Atrasadas). Colunas e badges de locação ("Caçamba"/"Diárias"/"Endereço") removidas — `order_type ∈ {manutencao, mecanica}`. `formatBRL(null)` → "—" (matou o vazamento de string literal na tabela).

## Mission

Dashboard operacional pra atendente/gerente da oficina decidir próxima ação em cada OS — visão consolidada de OS abertas, em serviço, aguardando aprovação, atrasadas e concluídas pendente entrega.

## Goals — Features (faz)

- AppShellV2 + topnav padrão Cockpit V2 (ADR 0110)
- `<PageHeader>` shared (h1 "Ordens de Serviço" + subtítulo descritivo, sem círculo decorativo de ícone — v4)
- KPIs topo no canon do Board (`BoardKpiCard` — label uppercase + número tabular-nums + sublinha) e **clicáveis como filtro D-05** (clica filtra `?status=`, clica de novo limpa, chip "limpar filtro" na toolbar) — substituem as abas Todas/Em andamento/Concluídas mês/Atrasadas (v4)
- Toolbar única canon `.ofc-view-toolbar`: busca com limpar (×) + contador "N OS", tipo (Mecânica/Manutenção), chips de estágio FSM, toggle **Quadro · Lista · Fila** (Quadro navega pro `/board` — simetria com o toggle do Board) (v4)
- Coluna VALOR com dado real `items_total` (withSum dos itens da OS — `valor_receber` é accessor sempre-0 pós-ADR 0265); indicador de atraso único (pill "Atrasada", sem dot duplicado) (v4)
- Listagem com filtros: status, order_type (manutencao/mecanica — **sem** locacao), vehicle.plate, contact, intervalo de datas
- Badge status semântico (rose=atrasada, amber=orcamento aguardando, blue=em_servico, emerald=concluida)
- Drawer detail (ServiceOrderSheet) ao clicar linha — FSM action panel + timeline append-only
- Multi-tenant Tier 0 (ADR 0093) — dados scopados business_id
- Inertia::defer obrigatório em KPIs agregados + paginação pesada (RUNBOOK-inertia-defer-pattern)

## Non-Goals — Features (NÃO faz)

- Edição inline na listagem (vai pra drawer ou Edit page)
- Trigger manual de WhatsApp (futuro US-OFICINA-006)
- Histórico full > 90 dias (paginar; arquivo via export)
- Importer Firebird inline (artisan command US-OFICINA-002)

## UX Targets

- p95 first-paint < 800ms (KpiGrid + 50 OS)
- 0 erros JS console
- Drawer abre < 200ms
- Cores semânticas Cockpit V2 (rose/amber/emerald/blue/info)

## UX Anti-patterns

- Cor crua `bg-red-100/bg-blue-100` (canon = rose/emerald semântico)
- KPI inline com `<Card>` custom (canon = `<KpiCard>` shared)
- `sessionStorage` (canon = querystring + Inertia)
- Eager-load de transactions paginate sem `Inertia::defer`

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php)
- [Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php)
- tests/Feature/Design/CockpitPatternConformanceTest.php (sistêmico)

## Refs

- [SPEC.md US-OFICINA-001](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [RUNBOOK-index.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-index.md)
- [ADR 0137](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0143 FSM canon](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)

## Trilha do tempo

> Append-only (L-22 — não reescrever histórico).

- **2026-05-26** (v2) — Cockpit Pattern V2; KPI "locações ativas" + colunas locação mantidos.
- **2026-06-09** (v3) — sweep ADR 0265 no front ([sessão](../../../../../memory/sessions/2026-06-09-sweep-os-front-adr0265.md) · [avaliação CC](../../../../../prototipo-ui/AVALIACAO_OS_GIT_2026-06-09.md)): KPI "Locações ativas" **morto**; colunas/pills/badges de locação → reparo (`OrderType = {manutencao, mecanica}`); `formatBRL(null)` → "—". `mecanica` deixou de cair no ramo locação.
- **2026-06-11** (v4) — polish canon Board (pedido [W], mesmo canon Cowork oficina-page/oficina-fila): header sem círculo decorativo; KPIs no `BoardKpiCard` (extraído do Board) clicáveis D-05 substituindo as abas de status; toolbar única (busca+limpar+contador · tipo · estágio FSM · toggle Quadro/Lista/Fila); VALOR real (`items_total` via withSum); dot vermelho de atraso removido (pill única). FILA: caixa cinza → meta-grid canon (label 11px/valor 13px tabular-nums, defeito full-row), `ServiceOrderTimeline` no canon `.ofc-timeline` (fio+dot+quem·quando, sem pills com seta), stepper com labels truncadas (flex-1 min-w-0). DRAWER (`ServiceOrderSheet`): header eyebrow "OS #N · etapa" + badge tipo fora do título + h2 17px veículo + p 12.5px cliente, MiniKpi → meta compacta (Entrada/Valor BRL à direita), seções border-top fino (mesmo Section do RichSheet), Cancelar OS vira outline destructive.
- **2026-06-11** (v5) — **paridade TOTAL com o protótipo Cowork** (pedido [W] "resultado esperado" = nível do Board, após o v4 ficar aquém). LISTA reconstruída: **6 KPIs** (Recepção/Em diagnóstico/Aguardando peças/Em execução/Urgentes/**Valor em curso**) clicáveis (etapa via `?stage=`, urgentes via `?status=atrasada`, valor só-leitura); **abas de box/elevador** (`?box=` com contador); **tabela rica** (OS · PLACA Mercosul · VEÍCULO+km · CLIENTE · ETAPA dot+nome · BOX · MECÂNICO · PRAZO · VALOR). Header vira "Oficina Auto" + subtítulo "Recepção, diagnóstico, peças, execução e entrega de veículos". Backend `index()` enriquecido — `buildStageMap`/`shapeListRow`/`buildListKpis`/`buildListBoxOptions` (`buildServiceOrderKpisPayload` 3-KPI removido); etapa/box/mecânico/km com lastro real (`assignedUser`, `mileage_at_entry`, `current_stage_id`), "—" quando ausente (no-mock). FILA com detalhe rico inline = PR seguinte.
