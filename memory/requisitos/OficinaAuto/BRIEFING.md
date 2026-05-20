---
module: OficinaAuto
status: em-construcao
cnae_principal: "4520-0/01"
piloto: Martinho Caçambas (locação) + Vargas Recapagem (manutenção complexa)
ultima_atualizacao: 2026-05-20
nota_capterra: 63 (Bom)
nota_fsm_screen: 80 (estado-da-arte gaps #1+#2+#3 LIVE 2026-05-20)
related_adrs: [0137, 0121, 0129, 0143, 0093, 0094, 0101]
owner: [W]
---

# BRIEFING — Modules/OficinaAuto

> Estado consolidado da capacidade. 1 página executiva. Atualizado por PR mergeado (skill `brief-update` Tier B).

## Missão

Vertical especializado pra **oficinas mecânicas + locação de equipamentos automotivos** (caçambas, retroescavadeiras, etc) — CNAE 4520-0/01 principal, com cobertura 2212-9/00 (recapagem) e 4581-4/00 (acessórios). Cliente piloto qualificado em ADR 0137 (2 de 4 candidatos OfficeImpresso saudáveis são deste setor).

## Capacidades atuais (V0 — done)

- **FSM screen tríade MVP Martinho LIVE 2026-05-20** ([RUNBOOK-fsm-pipeline.md](RUNBOOK-fsm-pipeline.md)):
  - **Timeline auditável** drawer ServiceOrderSheet (gap #1 — PR #1195) — quem/quando/motivo/side-effects via `GET /history`
  - **Mini-grafo horizontal stages** (gap #2 — PR #1205) — bullets conectados estilo Linear, current ring + passados check + variantes laterais (manutencao/cancelada)
  - **Chips por stage Index** (gap #3 — PR #1203) — filtro `?stage=X` com contador bulk `GROUP BY`
  - 12 Pest specs novos (history, stages, pipeline) com cobertura multi-tenant Tier 0
  - Grade 15 dimensões FSM screen evoluiu **65→~80/100** (auditoria estado-da-arte [memory/sessions/2026-05-20-arte-tela-fsm-workflow.md](../../sessions/2026-05-20-arte-tela-fsm-workflow.md))
- **8 peças nWidart canônicas** (module.json, ServiceProvider, RouteServiceProvider, InstallController, DataController, Routes, Config, composer)
- **Wave 18 saturação D4/D9** — 2 Services novos stateless (`VehicleQueryService`, `ServiceOrderSummaryService`) com OtelHelper canon (9 spans `oficinaauto.*` mensurados em prod) + spans D9.a em `AprovacaoOsService` (gerar_token/validar_token/validar_pin); 2 Pest novos (ServicesObservabilityTest 8 cenários + AprovacaoOsTokenTest 8 cenários edge); README.md público; CHANGELOG.md canônico; module.json com bloco `governance.fsm_canonico=true` apontando ADR 0143.
- **Schema multi-tenant Tier 0** (ADR 0093) — `vehicles` (multi-placa nullable, legacy_id pra mapping Firebird) + `service_orders` (vehicle_id FK + transaction_id nullable UltimatePOS + order_type locacao/manutencao)
- **2 fluxos OS coexistentes:**
  - **Simples (Martinho):** `aberta → em_servico → concluida` — caçamba avulsa
  - **Complexa (Vargas, V1):** `aberta → orcamento → aprovada → em_producao → concluida → entregue` — recapagem multi-item
- **Locação extension** (migration 2026_05_12_220002): `daily_rate`, `expected_return_date`, `delivery_address`, accessor `valor_receber` (daily_rate × dias_locacao), accessor `is_overdue`
- **8 Pages Inertia** (Vehicles + ServiceOrders × Index/Create/Show/Edit) + **Kanban ProducaoOficina** (drag-drop @dnd-kit, placa Mercosul visual)
- **3 Pest tests Feature** (ServiceOrderCrudTest, VehicleCrudTest, VehicleMultiTenantTest) — biz=1 vs biz=99 (ADR 0101)
- **9 permissions registradas** + sidebar via DataController

## Gaps conhecidos (P0-P1 ativos)

| US | Prio | Status | Descrição |
|---|---|---|---|
| US-OFICINA-002 | P0 | todo | Importer Firebird `EQUIPAMENTO_VEICULO` → `vehicles` (91 veículos Martinho piloto) |
| US-OFICINA-003 | P0 | todo | FSM canônica via `ExecuteStageActionService` (ADR 0143) — 2 processos seed Simples/Complexa |
| US-OFICINA-004 | P1 | todo | UI Kanban OS multi-item (Vargas) — aproveitar pré-arte Delphi WR_KANBAN |
| US-OFICINA-005 | P0 | todo | Cleanup tools cliente legacy migrado (tela "Revisão pendências", conciliação VENDA↔FINANCEIRO, write-off candidate) |
| US-OFICINA-006 | P1 | todo | WhatsApp aprovação OS via link público + PIN (paridade Repair) |

## Diferenciais vs mercado (Capterra)

- **Núcleo oimpresso compartilhado:** multi-tenant Tier 0, Jana IA, NFe-de-boleto-pago automática, FSM auditável (ADR 0143 LIVE prod biz=1)
- **Locação first-class:** `order_type=locacao` nativo (não gambiarra) — único entre Mecânico/Auto Manager/Lokoz
- **Importer Firebird:** zero-friction migração OfficeImpresso (Delphi WR Comercial 26 anos) — concorrentes pedem reimportação manual

## Arquitetura referência

- **Modules/Repair** (shared infrastructure — Kanban OS, JobSheet pattern)
- **Modules/Vestuario** (produção — ✅ piloto ROTA LIVRE)
- **FSM canon** ADR 0143 (`app/Domain/Fsm/ExecuteStageActionService`)

## Próximos PRs (ordem sugerida)

1. US-OFICINA-002 importer Firebird (desbloqueia migração Martinho)
2. US-OFICINA-003 FSM seed Simples + Complexa via `php artisan fsm:bulk-start-pipeline` adapted
3. US-OFICINA-005 cleanup tools (ROI alto pra cliente legacy)
4. US-OFICINA-006 WhatsApp PIN aprovação

## Refs

- [SPEC.md](SPEC.md) — US completas
- [ROADMAP.md](ROADMAP.md) — Fases V0/V1/V2
- [ADR 0137](../../decisions/0137-modules-oficinaauto-qualificada.md) — qualificação módulo
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canon LIVE prod
- Cliente legacy: [research/clientes-legacy-officeimpresso/05-martinho-cacambas/](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/)
