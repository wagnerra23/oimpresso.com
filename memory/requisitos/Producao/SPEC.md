---
id: requisitos-pcp-spec
module: Producao
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: rascunho
status_nota: ativo desde 2026-10-15 (ADR 0431 tira o PCP do feature-wish da 0152); módulo Modules/Producao, só esqueleto
type: spec
scope: cross-vertical (OficinaAuto + ComunicacaoVisual + Repair + Vestuario)
piloto_previsto: Vargas (recapagem multi-mecânico) OU Extreme (ComVis multi-plotter) — sinal qualificado pendente
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0129-state-machine-canonica-fsm-rbac
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0137-modules-oficinaauto-qualificada
  - 0152-modules-pcp-feature-wish
  - 0431-pcp-sai-do-desejo-modules-producao
related_specs: [ComunicacaoVisual/SPEC.md (US-COMVIS-003 PCP), OficinaAuto/SPEC.md (US-OFICINA-004), Repair/SPEC.md, Manufacturing/SPEC.md]
related_research: [research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md]
last_review: 2026-05-15
---

<!-- schema-allowlist: US sob "## §7 — User Stories (US-PCP-001..020 — 20 propostas)"; SPEC escrito como blueprint enquanto dormente (ADR 0152), ativo pela ADR 0431 com numeração de seções §0..§9 do blueprint original — heading US mantido pra não quebrar links internos. -->

# SPEC — PCP / Apontamento de Produção (cross-vertical)

> ## ✅ ATIVO desde 2026-10-15 — [ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)
>
> O PCP saiu do feature-wish da [ADR 0152](../../decisions/0152-modules-pcp-feature-wish.md) por decisão de [W] em 2026-10-15. D1 = **`Modules/Producao`** (rota `/producao`, permissões `producao.*`); `IProduction` já tinha sido removido no PR #842. Esta pasta era `memory/requisitos/Pcp/` até 2026-10-15; os IDs `US-PCP-*` ficam.
>
> O texto abaixo dizia `Modules/Producao` até 2026-10-15; as citações foram trocadas para o nome decidido (rename curado no `ghost-rename-map`). O módulo existe só como esqueleto (sem tabela, sem tela); o fundo e as telas entram pelas threads do playbook de Produção, na ordem do [ROADMAP](ROADMAP.md).

> Convenção do ID: `US-PCP-NNN`.
> ⚠️ **NÃO É MÓDULO NOVO POR DEFAULT.** §0 abaixo prova que 60–70% do que PCP precisa já existe espalhado (`Modules/Repair` Kanban shared infra + FSM canon stages produção + IProduction placeholder + Modules/Manufacturing recipes/BoM). PCP é **camada fina de apontamento + capacidade + agenda** que **costura** o que já tem.

## §0 — Comparação com o que JÁ EXISTE (CRUCIAL — discovery 2026-05-12)

Wagner: *"não duplicar"*. Esta seção é a contra-prova.

| Feature | Modules/Repair atual | FSM canon (Sells/Repair) | Outros módulos oimpresso | Mercado | oimpresso precisa **construir**? |
|---|---|---|---|---|---|
| **Kanban OS visual** (drag-drop, colunas, cards) | ✅ `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` (shared infra refactor 2026-05-10: vocabulário genérico + slot config via `business.repair_settings`) + Page Inertia `resources/js/Pages/Repair/ProducaoOficina/Index.tsx` | ❌ | ❌ | Mubisys/Calcgraf/SAP S/4HANA têm | ❌ **REUSAR Repair Kanban** com agrupador configurável (item 4 do mapping Delphi: 8 agrupadores) |
| **Lookups Status/Estágio/Situação dinâmicos** | ✅ `repair_statuses` table + `RepairStatus` model com `HasBusinessScope` | ✅ `sale_process_stages` per-business (ADR 0143) | — | TOTVS SIGAPCP cadastra Centro Trabalho + Operações | ❌ reusar — FSM stages já suportam |
| **State machine stages produção** | ✅ legacy via `RepairStatus.sort_order` (heurística quartil) | ✅ **canônico ADR 0143**: Sells stage `in_production`; Repair stages `em_execucao`, `pausado`, `concluido_aguardando_retirada` | — | SAP/TOTVS/Odoo têm | ❌ usar FSM canon — adicionar **actions intermediárias** dentro de stages produção (não criar novo processo) |
| **OS / Ordem produção entity** | ✅ `repair_job_sheets` (job_sheet_no + serial_no + checklist + status_id + service_staff + estimated_cost) | ✅ `transactions` com `current_stage_id` | `service_orders` (OficinaAuto US-OFICINA-001 PR #556) | TOTVS PRODUCAO, SAP shop_floor_order | ❌ reusar `repair_job_sheets` + `transactions` + `service_orders` |
| **Apontamento mecânico/operador** (quem fez o quê quando) | ❌ existe `service_staff` (1 técnico fixo) — NÃO há registro temporal append-only | ⚠️ existe `sale_stage_history` (transições stage, append-only) mas é STAGE-level, não OPERATION-level | ❌ | TOTVS MATA680/681, Odoo "Operator Time Tracking" tab, SAP work center confirmation | ✅ **CONSTRUIR** `pcp_appointments` (append-only) — granularidade OPERATION-by-USER-by-TIME |
| **Cronômetro per-operação (start/pause/resume/stop)** | ❌ | ❌ | ❌ | Odoo Shop Floor stopwatch barcode-driven, SAP confirmation, TOTVS APP Minha Produção | ✅ **CONSTRUIR** — endpoint mobile com idempotency_key |
| **Postos de trabalho / Centro Trabalho** | ⚠️ "Box B1..B4 + Elevador E1..E2" via `business.repair_settings.slots` (genérico hoje) | ❌ | ❌ | TOTVS Centro_Trabalho, SAP work center, Mubisys terminal apontamento, Delphi `CENTRO_TRABALHO` table | ✅ **CONSTRUIR** `pcp_workstations` formal (catálogo + capacidade) — extrai/substitui slots JSON |
| **Catálogo Operações (tempo padrão)** | ❌ | ❌ | ❌ | TOTVS Operações + Roteiro, SAP routing, Delphi `PRODUCAO_ROTEIRO` | ✅ **CONSTRUIR** `pcp_operations` |
| **Capacidade máquina/posto (h/dia × unidade)** | ❌ | ❌ | ❌ | Mubisys ✅, SAP capacity load, TOTVS Carga Máquina | ✅ **CONSTRUIR** — coluna em `pcp_workstations` + view agregada |
| **Agendamento (slot × OS futura)** | ❌ | ❌ | ❌ | SAP Capacity Scheduling Board, TOTVS Plano Mestre | ✅ **CONSTRUIR** `pcp_schedules` — drag-drop tipo calendário |
| **Gargalo detection / alerta** | ❌ | ❌ | ❌ | Sankhya/SAP "real-time issue detection" + automatic root cause | ✅ **CONSTRUIR** — query agregada + alerta Jana (regra simples primeiro) |
| **QR code OS scan (mobile)** | ⚠️ existe `print_label.blade.php` que imprime label OS mas SEM QR scan | ❌ | ❌ | Mubisys, Odoo barcode + lot scanning | ✅ **CONSTRUIR** — PWA mobile + endpoint POST `/api/pcp/scan` |
| **Receita / BoM (recipe)** | ❌ | ❌ | ✅ **`Modules/Manufacturing/Entities/MfgRecipe.php`** + `MfgRecipeIngredient` (waste %, ingredient groups) | TOTVS Estrutura, SAP BoM, Odoo BoM | ❌ **REUSAR `MfgRecipe`** quando aplicável (ex: Vestuario peça com matéria-prima) |
| **Histórico timeline OS** | ⚠️ existe `repair/partials/activities.blade.php` (texto livre) | ✅ `sale_stage_history` append-only + UI timeline drawer SaleSheet (PR #623) | — | TOTVS PRODUCAO_HISTORICO, SAP order history | ❌ reusar `sale_stage_history` pro nível STAGE; criar `pcp_appointments` pro nível OPERATION |
| **Anexos OS** | ⚠️ existe `job_sheet/upload_doc.blade.php` | — | — | — | ❌ reusar |
| **Notificações cliente status** | ✅ `RepairStatusChanged` event + `NotifyRepairCustomer` listener + email/SMS templates em `repair_statuses` | ✅ FSM action side-effects | LGPD consent (ADR 0143) | — | ❌ reusar |
| **Dashboard PCP (carga semana, top performer)** | ⚠️ `Modules/Repair/dashboard` Blade básico | ❌ | ❌ | SAP Capacity Scheduling Board, TOTVS relatórios PCP | ✅ **CONSTRUIR** dashboard Inertia novo |
| **Multi-tenant Tier 0** | ✅ `HasBusinessScope` em RepairStatus, JobSheet | ✅ ADR 0143 — todas FSM tables têm `business_id` indexado | — | — | ✅ **OBRIGATÓRIO** em todas tabelas novas PCP (Tier 0 IRREVOGÁVEL — ADR 0093) |
| **IProduction placeholder** | — | — | ⚠️ existe como stub vazio (apenas DataController + InstallController + SCOPE L3) | — | ❓ **DECISÃO D1**: PCP vive como `Modules/Producao` novo OU **assume `IProduction`** (renomeando)? |
| **Modules/Manufacturing legacy** | — | — | ✅ existe: MfgRecipe + ProductionController + ingredient groups + waste % | TOTVS estrutura BoM | ❌ NÃO duplicar — PCP **integra** ao Manufacturing pra consumir BoM/receita quando aplicável |

### Veredito §0

- **~60% do que PCP precisa já existe** no oimpresso (Kanban shared Repair + FSM canon + BoM Manufacturing + history append-only + multi-tenant scaffolding).
- **Gap real (40%)** = apontamento OPERATION-level + capacidade postos + cronômetro mobile + QR scan + gargalo + agendamento + dashboard.
- **NÃO criar `Modules/Producao` novo às cegas.** Opções em D1.

## §1 — Visão

PCP/Apontamento cross-vertical: camada fina que **costura** Kanban Repair + FSM canon + BoM Manufacturing + adiciona granularidade OPERATION-level (quem-fez-o-quê-quando-em-qual-posto) pra `Modules/OficinaAuto` (mecânico) + `Modules/ComunicacaoVisual` (plotter+acabamento+instalação) + `Modules/Repair` (técnico) + `Modules/Vestuario` (costura/corte futuro).

**Não-objetivos:**

- ❌ Não substitui Kanban Repair (reusa)
- ❌ Não cria processo FSM novo (usa `venda_com_producao` + `os_reparo_padrao` — adiciona actions intermediárias)
- ❌ Não substitui Manufacturing (integra consumo BoM)
- ❌ Não vira MRP completo (planejamento materiais avançado) — fase 5+
- ❌ Não cobre Pilar 5 oimpresso Insights (DaaS externo descartado — ADR 0094)

## §2 — Cenários peculiares (Given/When/Then)

### Cenário A — Mecânico Vargas inicia trabalho via QR code

**Dado** que mecânico João abre PWA mobile autenticado (token JWT issued ao login web) e está no posto `mecanico_pit1`
**Quando** escaneia QR code OS-123 com câmera celular
**Então**:
- POST `/api/pcp/scan` com `{qr_token, workstation_id, operator_user_id, action: "start"}`
- Service cria `pcp_appointments(id, business_id=4, transaction_id=null, repair_job_sheet_id=123, operation_id="trocar_pneu", workstation_id="mecanico_pit1", user_id=João, started_at=now(), finished_at=null)`
- Side-effect: dispara FSM action `apontar_inicio` na OS (stage permanece `em_execucao`)
- Jana grava timestamp no `sale_stage_history` (append-only) — agora granularidade de operação
- UI Kanban refletida em ~5s via Centrifugo broadcast (CT 100)

### Cenário B — Plotter ComVis ocupa fila

**Dado** que operador Larissa seleciona OS-456 (banner 3×1,5m) na fila do posto `plotter_uv_1`
**Quando** clica "Iniciar impressão"
**Então**:
- `pcp_appointments` cria registro de início + `pcp_workstations.is_busy=true`
- Fila visível ("plotter_uv_1: 2h restantes") aparece pros próximos operadores via broadcast
- Quando finaliza (action `apontar_fim` com `qty_produced=3.0` m²), `is_busy=false` + libera fila

### Cenário C — Gargalo detectado

**Dado** que 5 OS estão na coluna `acabamento_corte` mas só há 1 posto ativo
**Quando** o cron `pcp:detect-bottleneck` (a cada 15min) detecta `count(waiting) >= 3 × capacity_per_hour`
**Então**:
- Cria `mcp_alerts(business_id, type=bottleneck, severity=high, ...)`
- Notifica gerente via UI badge vermelho no Kanban + WhatsApp (respeitando LGPD consent — ADR 0143)
- Jana responde quando perguntada: *"investir em 2ª plotter de corte ou subcontratar?"* (cita dados reais — última semana 8% acima capacidade)

### Cenário D — Capacidade do dia

**Dado** que ComVis tem `plotter_uv_1` com `capacity_per_hour=10` m², `hours_per_day=8` → 80 m²/dia
**Quando** Wagner abre dashboard PCP em 2026-05-13 09:00
**Então**:
- Card "Plotter UV — Hoje" mostra "60/80 m² agendados (75%)"
- Card "Amanhã" mostra "76/80 m² (95% — alerta)"
- Sugestão Jana: "considerar overtime ou recusar OS-789 (pedido emergencial)"

### Cenário E — Tempo padrão vs real (treinamento)

**Dado** que operação `trocar_pneu` tem `tempo_padrao_minutos=180` (3h)
**Quando** mecânico João leva 5h hoje em OS-100
**Então**:
- View `pcp_operator_performance` agrega `avg(finished_at - started_at) per user_id per operation_id`
- Alerta gerente "João — operação trocar_pneu 167% acima do padrão últimos 30d (n=12 amostras)" → treinamento?
- ⚠️ **LGPD** — performance per-user é PII trabalhista; só RH/owner com permissão `pcp.performance.view`

## §3 — Schema proposto (somente o que NÃO existe)

### Novas tabelas

```
pcp_workstations
  id, business_id (Tier 0), code, label, type (plotter|acabamento|pit|elevador|...),
  capacity_per_hour, capacity_unit (m2|peca|hora), hours_per_day,
  is_active, is_busy (denorm; recalculado por job), notes,
  timestamps

pcp_operations
  id, business_id, code, label, workstation_id (FK), tempo_padrao_minutos,
  unit (m2|peca|hora), category, is_active, timestamps

pcp_appointments  (CORE — append-only)
  id, business_id, transaction_id (FK nullable), repair_job_sheet_id (FK nullable),
  operation_id (FK), workstation_id (FK), user_id (operator, FK users),
  started_at, finished_at, qty_produced (decimal), qty_lost (decimal),
  notes, idempotency_key (uuid pra mobile retry-safe), timestamps,
  ⛔ append-only (trigger blocking UPDATE/DELETE)

pcp_schedules
  id, business_id, operation_id, workstation_id, transaction_id|repair_job_sheet_id,
  scheduled_start_at, scheduled_end_at, status (pending|started|done|cancelled),
  created_by, timestamps
```

### Reusa (NÃO recriar)

- **`repair_job_sheets`** — OS unificada (oficina/repair/comvis quando faz sentido); criar coluna `qr_token` (uuid + 12 chars curto pra label impressa)
- **`transactions`** — venda com produção (FSM canon ADR 0143)
- **`sale_stage_history`** — timeline STAGE-level (cria entrada quando appointment cruza fronteira de stage)
- **`Modules/Manufacturing/MfgRecipe`** — quando produção consome BoM
- **`business.repair_settings`** JSON — config slot legacy preservada; nova config `pcp_settings` chave separada
- **`Modules/Repair/ProducaoOficinaController`** — Kanban shared como **mesmo endpoint** mas com agrupador configurável (8 agrupadores do Delphi)

### Triggers MySQL (Tier 0 — append-only)

```sql
-- pcp_appointments: bloqueia UPDATE/DELETE (paridade Portaria 671/2021 pattern aplicada)
CREATE TRIGGER pcp_appointments_immutable_update BEFORE UPDATE ON pcp_appointments
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'pcp_appointments append-only';
-- exceção: setting finished_at se started_at sem fim ainda → coluna mutable enum?
-- DECISÃO D3 abaixo: cronômetro automático vs manual
```

## §4 — Integração FSM canon ADR 0143 (CRUCIAL — não duplicar)

PCP **NÃO cria processo FSM novo**. Usa os 2 existentes:

- `venda_com_producao` (stage `in_production`)
- `os_reparo_padrao` (stages `em_execucao`, `pausado`, `concluido_aguardando_retirada`)

Adiciona **actions intermediárias** dentro desses stages (mudam histórico mas não cruzam fronteira de stage até all operations done):

| Action FSM nova | Stage onde vive | Side-effect canônico |
|---|---|---|
| `apontar_inicio_operacao` | `in_production` / `em_execucao` | cria `pcp_appointments(finished_at=null)` + dispatch `OperationStartedEvent` |
| `apontar_pausa_operacao` | idem | UPDATE permitido somente em `finished_at` via service (não SQL direto) |
| `apontar_fim_operacao` | idem | seta `finished_at` + dispara `AllOperationsDoneCheck` → se 100% operações OS done, **automaticamente** dispara `concluir_producao` (FSM canon transição pra `ready_for_invoice` / `concluido_aguardando_retirada`) |
| `apontar_perda` | idem | grava `qty_lost`, não muda stage |
| `vincular_qr_token` | qualquer não-terminal | gera `qr_token` + label PDF |

Todas actions registradas em `sale_stage_actions` per-business com `is_critical=true` (role obrigatória — pattern ADR 0143 §"Actions `is_critical` fail-secure").

## §5 — QR code OS (mobile)

- **PWA mobile** (NÃO app nativo — DECISÃO D2 abaixo): React/Inertia já tem `vite-plugin-pwa` possível; service worker + manifest + ícones
- OS impressa com label contendo QR (base64-png) — extends `Modules/Repair/Resources/views/job_sheet/print_label.blade.php`
- Conteúdo QR: `{biz, type: 'jobsheet'|'transaction', id, token (12 chars HMAC)}`
- Endpoint API JWT auth: `POST /api/pcp/scan` body `{qr_payload, action: start|pause|resume|stop|loss, workstation_id, qty?, qty_lost?}`
- Idempotency-Key header obrigatório (UUID gerado client-side) — retry mobile safe
- ⛔ WhatsApp foto antes/depois: **descartado V1** (custo Whatsapp Cloud API + LGPD consent — só ativa quando cliente sinalizar; backlog P3)

## §6 — Dashboard PCP (Inertia + Centrifugo broadcast)

Página `/pcp/dashboard` Inertia v3 + React 19 + Tailwind 4 (stack canônica):

- **Header KPI cards** — Carga hoje %, OS em atraso, gargalo ativo, top performer mês
- **Kanban industrial** (reusa `ProducaoOficinaController` com query parameter `?grouping=workstation|status|stage|priority|customer|product|location|operator` — 8 agrupadores Delphi)
- **Carga semana** — heatmap workstation × dia (gráfico)
- **Performance operador** — table sortable (PII guard — só com `pcp.performance.view`)
- **Alertas Jana** — feed lateral

## §7 — User Stories (US-PCP-001..020 — 20 propostas)

**Implementado em:** _pendente_ — epic dormente (feature-wish ADR 0152); `Modules/Producao/` e `IProduction` não existem no disco; nenhuma das 20 US construída (estado agregado)

> Formato headers (parser MCP — ADR 0134).
> Status inicial **todo** até sinal qualificado (ADR 0105) — proposta = `proposed`.
> Estimates pós-recalibração 10x IA-pair (ADR 0106).

### US-PCP-001 · Schema base + migrations (4 tabelas + triggers append-only) — **P0**

**Implementado em:** _pendente_ — nenhuma migration `pcp_*` no disco; `Modules/Producao/` inexistente (SPEC dormente ADR 0152)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> Schema §3. Triggers MySQL append-only em `pcp_appointments`. `business_id` indexed + FK em tudo. Pest cross-tenant (biz=1 vs biz=99 — ADR 0101).

### US-PCP-002 · DECISÃO D1 — Pcp vs IProduction (renomeação ou módulo novo) — **P0 BLOCKER**

**Implementado em:** _pendente_ — decisão dormente por ADR 0152 (resolve no ADR de promoção quando trigger ativar); sem ADR/código

> owner: [W] · priority: p0 · estimate: 1h · status: todo · type: decision
> Ver ADR proposal §D1. Wagner decide ANTES de US-PCP-001 ir mainline.

### US-PCP-003 · Models + global scopes `HasBusinessScope` — **P0**

**Implementado em:** _pendente_ — sem models `Modules/Producao/Entities/*` (módulo não scaffoldado, ADR 0152)

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story

### US-PCP-004 · CRUD `pcp_workstations` Inertia (Index/Create/Edit) — **P0**

**Implementado em:** _pendente_ — sem Pages `Pages/Pcp/**` nem controller CRUD workstations (módulo não construído, ADR 0152)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> MWART process ADR 0104 (visual-comparison.md + Pest baseline + Charter).

### US-PCP-005 · CRUD `pcp_operations` Inertia — **P0**

**Implementado em:** _pendente_ — catálogo `pcp_operations` + telas não construídos (módulo dormente, ADR 0152)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story

### US-PCP-006 · FSM actions intermediárias (5 actions seed) — **P0**

**Implementado em:** _pendente_ — os seeders FSM existem (`database/seeders/FsmProcessoVendaComProducaoSeeder.php`, `FsmProcessoOsReparoPadraoSeeder.php`) mas NÃO seedam nenhuma action `apontar_*` de produção; deliverable não construído (ADR 0152)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> Seed em `FsmProcessoVendaComProducaoSeeder` e `FsmProcessoOsReparoPadraoSeeder` (já existem ADR 0143). Pest transition + side-effect.

### US-PCP-007 · Service `RegisterAppointment` (idempotent) — **P0**

**Implementado em:** _pendente_ — sem service de apontamento em `Modules/Producao/Services/*` (módulo não construído, ADR 0152)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> Idempotency-Key + race condition guard (mesmo usuário 2 starts).

### US-PCP-008 · Endpoint API JWT `POST /api/pcp/scan` — **P0**

**Implementado em:** _pendente_ — rota `/api/pcp/scan` inexistente (nenhum `Modules/Producao/Routes/api.php`; módulo dormente ADR 0152)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> Sanctum token user-level + rate limit + LGPD log audit.

### US-PCP-009 · PWA mobile shell + scanner QR (camera API) — **P0**

**Implementado em:** _pendente_ — sem PWA shell/scanner QR de apontamento (módulo não construído, ADR 0152)

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> @zxing-js/library ou jsqr. Offline-first com IndexedDB queue.

### US-PCP-010 · QR token na label OS + endpoint print extended — **P0**

**Implementado em:** _pendente_ — a label OS base existe (`Modules/Repair/Resources/views/job_sheet/print_label.blade.php`) mas SEM QR token/HMAC; extensão não construída (ADR 0152)

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story

### US-PCP-011 · Kanban shared infra adicionar agrupador configurável (8 agrupadores Delphi) — **P0**

**Implementado em:** _pendente_ — `ProducaoOficinaController` (Repair) existe como plug-point mas NÃO tem `?grouping`/8 agrupadores nem tabela `kanban_columns`; extensão não construída (ADR 0152; não ancorar em domínio Repair)

> owner: — · priority: p0 · estimate: 5h · status: todo · type: story
> Extends `ProducaoOficinaController` — query param `?grouping`. Espelha `WR_KANBAN` table → `kanban_columns(business_id, agrupador, value, order, is_collapsed)`.

### US-PCP-012 · Detect bottleneck (cron 15min) + alerta UI + Jana RAG context — **P1**

**Implementado em:** _pendente_ — sem comando cron de detecção de gargalo nem feed Jana PCP (módulo não construído, ADR 0152)

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story

### US-PCP-013 · Capacidade dia/semana view agregada + dashboard card — **P1**

**Implementado em:** _pendente_ — sem view de capacidade nem card dashboard PCP (módulo não construído, ADR 0152)

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story

### US-PCP-014 · Performance operador (view + dashboard table com permission guard) — **P1**

**Implementado em:** _pendente_ — sem view performance operador nem permission `pcp.performance.view` (módulo não construído, ADR 0152)

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story
> LGPD — `pcp.performance.view` permission. PII redactor logs.

### US-PCP-015 · `pcp_schedules` agendamento drag-drop calendário — **P2**

**Implementado em:** _pendente_ — sem tabela `pcp_schedules` nem calendário drag-drop (módulo não construído, ADR 0152)

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> React FullCalendar + endpoint PATCH. Validation overlap.

### US-PCP-016 · Integração BoM `MfgRecipe` (consumo automático ao concluir operação) — **P2**

**Implementado em:** _pendente_ — `MfgRecipe` (Manufacturing) existe pra reuso, mas o hook `apontar_fim_operacao → ConsumirEstoque` do PCP não existe (módulo não construído, ADR 0152)

> owner: — · priority: p2 · estimate: 5h · status: todo · type: story
> Hook em `apontar_fim_operacao` → `ConsumirEstoque` via Manufacturing.

### US-PCP-017 · Dashboard PCP Inertia (4 cards KPI + Kanban embed + heatmap) — **P1**

**Implementado em:** _pendente_ — sem página `Pages/Pcp/Dashboard` nem rota `/pcp/dashboard` (módulo não construído, ADR 0152)

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> Charter `.charter.md` obrigatório.

### US-PCP-018 · Broadcast Centrifugo (Kanban realtime + fila workstation) — **P2**

**Implementado em:** _pendente_ — sem broadcast/canal `pcp.{biz}.workstation.{id}` (módulo não construído, ADR 0152)

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story
> CT 100 only (ADR 0058 + 0062). Channel `pcp.{biz}.workstation.{id}`.

### US-PCP-019 · Smoke biz=4 ROTA LIVRE (canary 7d + cutover) — **P2**

**Implementado em:** _pendente_ — sem módulo PCP pra smoke/canary; cutover não iniciado (módulo não construído, ADR 0152)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> Processo MWART F4+F5 — aviso prévio Larissa + canary 7d (ADR 0104 §F5).

### US-PCP-020 · Documentação RUNBOOK + Charter páginas — **P0**

**Implementado em:** _pendente_ — sem `RUNBOOK-*.md` em `memory/requisitos/Producao/` nem charters (nenhuma tela PCP construída ainda; módulo em esqueleto, ADR 0431)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> 1 RUNBOOK por tela MWART + `.charter.md` ao lado de cada `.tsx`.

### US-PCP-021 · A OS baixa o material apontado em cada etapa, pelo custo do lote que saiu (UC-FLX-37) — **P1**

**Implementado em:** `Modules/Producao/Services/ConsumoMaterialService.php` · `Modules/Producao/Database/Migrations/2026_10_19_210000_create_pcp_consumos_table.php` · `Modules/Producao/Services/CustoRealService.php`

**Testado em:** `Modules/Producao/Tests/Feature/ProducaoMaterialContratoTest.php` (lane `verticais-pest.yml`).

> owner: wagner · priority: p1 · estimate: 4h · type: story

**Origem:** decisão [W] 2026-10-19 — *"a OS baixa do estoque o material apontado em cada etapa, pelo custo do lote que saiu"* — e o UC-FLX-37 da tela de fluxos. O que se negocia na venda (custo previsto, US-SELL-067) não é o que se emprega na produção: a OS precisa guardar o custo empregado.

**Problema:** a OS por item congelava só as horas apontadas (`pcp_ordens.custo_real`); o material consumido não baixava estoque nem entrava no custo.

**Aceite:**
- [x] O operador aponta material (variação + quantidade) na etapa dele; a baixa sai do local de estoque da venda que gerou a OS (UC-PCMAT-01).
- [x] Cada apontamento é uma transação `production_sell` de uma linha (ref `OS-<id>`; uma por baixa porque `uk_tsl_dup_prevent` não aceita a mesma variação duas vezes numa transação), amarrada ao lote pelo `mapPurchaseSell` (`production_purchase`) e tirada do saldo pelo `decreaseProductQuantity`.
- [x] O custo do material é Σ quantidade de cada lote × `purchase_lines.purchase_price_inc_tax`, guardado na baixa; compra posterior não muda (UC-PCMAT-02). Não usa o `dpp_inc_tax` do dia.
- [x] O custo real congelado ao concluir o item soma o material (UC-PCMAT-03).
- [x] Idempotente pela chave da fila offline (UC-PCMAT-04); tabela `pcp_consumos` com `business_id`, FK e índice.
- [x] Só o responsável da etapa iniciada ou feita, com a OS Em execução ou Pausada; material de outra empresa e sem controle de estoque recusam (UC-PCMAT-05).
- [x] Sem lote suficiente (empresa que não vende sem estoque) a baixa é recusada inteira (UC-PCMAT-06); empresa que vende sem estoque grava o que faltou em `quantidade_sem_lote`, sem custo.
- [x] Endpoints na PWA (`POST /producao/apontamento/material`) e no app (`POST /api/app/producao/apontamentos/material`), sem o custo na resposta (UC-PCMAT-07).
- [ ] Campo de material na tela do operador (Non-Goal da tela até a thread 08).
- [ ] Estorno de baixa apontada errada.

### US-PCP-022 · A regra da compra (congela | vivo) vale para a OS aberta (UC-FLX-36) — **P1**

**Implementado em:** `Modules/Producao/Services/PrevisaoCustoDaOrdem.php` · `Modules/Producao/Http/Controllers/OrdemCustoController.php`

**Testado em:** `Modules/Producao/Tests/Feature/ProducaoCustoOsAbertaContratoTest.php` (lane `verticais-pest.yml`).

> owner: wagner · priority: p1 · estimate: 3h · type: story
> blocked_by: US-PCP-021

**Origem:** UC-FLX-36 da tela de fluxos. A regra "Atualizar o custo dos insumos" das etapas da Compra (`RegrasCompra`, parâmetro `ops`) era só configuração gravada; nenhuma OS a lia.

**Aceite:**
- [x] Para a OS por item aberta, cada material apontado mostra o custo na data de abertura da OS e o de hoje (método da empresa, `App\Contracts\Compras\CustoDoInsumo`), o custo do lote que saiu e se mudou (UC-PCOSA-01).
- [x] `congela`: a previsão é a da abertura; `vivo`: a previsão é a de hoje; regra desligada ou nunca gravada vale `congela` (UC-PCOSA-01..03).
- [x] Só leitura: nenhum dos modos muda `pcp_ordens.custo_real` nem o custo das baixas; OS concluída mostra só o congelado (UC-PCOSA-04).
- [x] `GET /producao/ordens/{id}/custo` em JSON, só com `producao.desempenho.ver`; OS de outra empresa 404 (UC-PCOSA-05).
- [ ] Mostrar na tela da OS (a Ficha é da thread 08).
- [ ] Previsão do material ainda não apontado (empenho, D-EMPENHO da thread 08).

### US-PCP-023 · Romaneio de impressão peça a peça (UC-FLX-12) — **P2**

**Implementado em:** `Modules/Producao/Services/RomaneioDeImpressao.php` · `Modules/Producao/Http/Controllers/RomaneioController.php` · `Modules/Producao/Database/Migrations/2026_10_20_000001_create_pcp_romaneios_tables.php`

**Testado em:** `Modules/Producao/Tests/Feature/ProducaoRomaneioContratoTest.php` (lane `verticais-pest.yml`).

> owner: wagner · priority: p2 · estimate: 3h · type: story

**Origem:** UC-FLX-12 da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:65`, referência Mubisys, situação `sem`). Na passagem Pré-impressão → Impressão não existe lista do que foi enviado ao setor de impressão e corte: `rg -i "romaneio"` em `Modules/Producao` e `Modules/ComunicacaoVisual` volta vazio (medido 2026-10-20). As dimensões por peça já existem no item do orçamento da CV (`Modules/ComunicacaoVisual/Entities/OrcamentoItem.php:14-31`, largura × altura × quantidade).

**Aceite:**
- [x] O PCP gera, para uma OS de comunicação visual e a etapa de destino dela, o romaneio com uma linha por peça do orçamento: item, mídia, largura × altura, quantidade e área (UC-PCROM-01). `POST /producao/os/{id}/romaneio` e `GET /producao/romaneios/{id}`, em JSON.
- [ ] Gerar para um LOTE de OS de uma vez, e na própria passagem para a etapa de impressão (hoje é ação explícita por OS). Acabamento previsto: o item do orçamento não tem esse campo.
- [x] O romaneio fica gravado (número `ROM-<OS>-vN`, data, quem gerou, posto de destino) e não muda; gerar de novo cria a versão seguinte, e a peça que já saiu numa versão anterior é sinalizada (UC-PCROM-02).
- [ ] O operador de impressão/corte marca cada peça como impressa/cortada a partir do romaneio, e a marcação aparece na OS.
- [x] Gerar romaneio exige o papel `producao.liberar`; ler exige `producao.access`; sem isso, 403 (UC-PCROM-03). Marcar peça (responsável da etapa) segue com a marcação.
- [x] Multi-tenant: romaneio e linhas com `business_id`; romaneio, OS ou etapa de outra empresa → 404; Pest com os tenants 98 e 99 (UC-PCROM-04).
- [ ] Mostrar o romaneio na tela (impressão do romaneio em PDF).

### US-PCP-024 · Simulador de aproveitamento de mídia na bobina (UC-FLX-13) — **P2**

**Implementado em:** _pendente_

> owner: wagner · priority: p2 · estimate: 8h · type: story
> blocked_by: US-PCP-023

**Origem:** UC-FLX-13 da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:66`, referências Holdprint, printIQ e Calcgraf, porte média, situação `sem`). Na Pré-impressão os jobs vão para a máquina um a um; nada agrupa peças na largura da bobina. `rg -i "nesting|aproveitamento|largura_bobina"` em `Modules/` volta vazio (medido 2026-10-20): o substrato da CV guarda só preço e mínimo por m² (`Modules/ComunicacaoVisual/Entities/Substrato.php:27-30`), sem largura útil da bobina, e "bobina" no PCP aparece só como motivo de pausa (`Modules/Producao/Entities/Motivo.php:35`).

**Aceite:**
- [ ] O cadastro da mídia passa a ter largura útil da bobina e margem entre peças.
- [ ] Dado um conjunto de peças de OS na fila da Pré-impressão com a mesma mídia, o simulador propõe um arranjo na largura da bobina (girando a peça quando a mídia aceita) e mostra metros lineares, área útil e % de perda, comparado com imprimir uma a uma.
- [ ] O PCP aceita o arranjo e ele vira o romaneio de impressão (US-PCP-023), guardando o consumo previsto de mídia por OS (rateio pela área).
- [ ] Simular é só leitura; aceitar exige `producao.liberar`, sem ela 403.
- [ ] Multi-tenant: só entram peças/OS e mídias da empresa do usuário; OS de outra empresa no pedido → 404; Pest com dois tenants.

### US-PCP-025 · Produção só libera com o sinal pago (UC-FLX-28) — **P2**

**Implementado em:** _pendente_

> owner: wagner · priority: p2 · estimate: 6h · type: story
> blocked_by: parcelas de sinal e saldo na aprovação do orçamento (Vendas/Financeiro) · retorno do banco que baixa o título (PaymentGateway)

**Origem:** UC-FLX-28 da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:80`, situação `verificar`; medido **parcial** em 2026-10-20). Existe: o estado inicial `aguardando_liberacao` (`Modules/Producao/Services/ProcessoDaOrdem.php:27`) e a transição `liberar` → `na_fila` só com `producao.liberar` (`:45`); `SituacaoDaOrdem::liberar()` confere apenas os responsáveis das etapas (`Modules/Producao/Services/SituacaoDaOrdem.php:131-158`). Falta: aprovar o orçamento não gera parcelas de sinal e saldo (`app/Domain/Fsm/Modelos/CatalogoProcessos.php:74`); o webhook de boleto Inter/C6/Asaas só grava o evento e não libera produção (`CatalogoProcessos.php:105`); não há vínculo título do sinal ↔ OS, nem guarda no `liberar`, nem listener que libere sozinho. A US fica na Produção porque a liberação da OS é dela; gerar as parcelas é do Financeiro/Vendas e baixar pelo retorno do banco é do PaymentGateway.

**Aceite:**
- [ ] A empresa configura se a produção exige sinal; desligado, o `liberar` segue como hoje (só responsáveis).
- [ ] Com a regra ligada, a OS gerada da venda aprovada fica vinculada ao título do sinal, e `liberar` recusa com motivo enquanto o título não estiver quitado, inclusive para quem tem `producao.liberar`.
- [ ] Quitado o título do sinal (baixa manual ou retorno do banco), um listener executa `liberar` pela FSM com ator sistema, registrado no histórico da OS; OS com etapa sem responsável fica em `aguardando_liberacao` com a pendência visível.
- [ ] Idempotente: retorno do banco repetido não executa a transição duas vezes; OS cancelada não é liberada.
- [ ] Multi-tenant: o listener recebe o `business_id` no evento e só toca OS e título da mesma empresa; `liberar` em OS de outra empresa → 404; Pest com dois tenants.

### US-PCP-031 · Reserva o insumo na venda, baixa real na produção (UC-FLX-14) — **P1**

**Implementado em:** `Modules/Producao/Services/ReservaDeInsumoDaOs.php` · `Modules/Producao/Database/Migrations/2026_10_20_120000_add_pcp_ordem_id_to_stock_reservations.php` · `app/Contracts/Fabricacao/InsumosDaReceita.php` · `Modules/Manufacturing/Services/InsumosDaReceitaService.php`

**Testado em:** `Modules/Producao/Tests/Feature/ProducaoReservaInsumoContratoTest.php` (UC-PCRES-01..07, lane `verticais-pest.yml`).

> owner: wagner · priority: p1 · estimate: 4h · type: story
> blocked_by: US-PCP-021

**Origem:** decisão [W] 2026-10-20 — *"Reserva insumo na venda, baixa real na produção"* — para o item feito sob encomenda (banner, placa, adesivo). Fontes: o caso prático da Comunicação Visual (*"reserva 6m² lona em 'aprovado' → consome em 'produção concluída'"*), o UC-FLX-14 (*"Consome do estoque a quantidade real, não a orçada"*) e a decisão [W] 2026-10-15 de que a receita do Manufacturing é a fonte de material. O produto acabado sob encomenda não controla estoque (nunca entra nem sai); a revenda segue baixando na venda; a produção para estoque (ordem de produção do Manufacturing) segue dando entrada no acabado. Revê a US-PCP-016 (consumo automático pela receita ao concluir): a baixa é a apontada, a receita só reserva.

**Aceite:**
- [x] Venda aprovada: a OS do item sob encomenda (gera OS, produto `enable_stock = 0`, tem receita) reserva cada insumo que controla estoque — receita por unidade × quantidade da OS — em `stock_reservations`, ligada à OS (`pcp_ordem_id`), no local da venda, sem data de expiração e sem mexer no saldo; idempotente (UC-PCRES-01).
- [x] Produto que controla estoque (revenda) e empresa com a chave desligada não reservam (UC-PCRES-02).
- [x] O apontamento da etapa baixa a quantidade real (US-PCP-021) e abate da reserva da OS no máximo o que ela tem; consumo além do reservado baixa normalmente; reserva zerada vira `consumed` (UC-PCRES-03, UC-PCRES-04).
- [x] OS concluída ou cancelada libera o que sobrou; venda cancelada libera todas as reservas das OS dela (UC-PCRES-05, UC-PCRES-06).
- [x] Chave por empresa `business.common_settings['producao_reserva_insumo']`, desligada por padrão; sem backfill (só reserva OS criada com a chave ligada). Tier 0: receita, reserva e liberação só da empresa (UC-PCRES-07).
- [ ] Tela/ajuste para ligar a chave (hoje é dado de `common_settings`).
- [ ] Quantidade por área (m²) no item da CV: hoje a reserva usa a quantidade da linha na unidade do produto.
- [ ] Mostrar o reservado no saldo disponível para venda do insumo (hoje nenhuma tela lê `stock_reservations`).

**Total US: 27** · **Estimate agregado:** ~98h IA-pair (≈ ADR 0106 fator 10×) → ~3 sprints (cycles 2-semana).

### US-PCP-026 · Etapas da OS vêm do roteiro do produto (UC-FLX-07) — **P1**

**Implementado em:** _pendente_ — entrega 1 em `Modules/Producao/Services/CriarOsDaVenda.php` (OS por item copia as etapas do roteiro ligado ao produto) e `Modules/Producao/Http/Controllers/RoteirosController.php` (`PUT /producao/roteiros/{id}/produtos`); falta o seletor de produtos na tela de Roteiros.

**Testado em:** `Modules/Producao/Tests/Feature/ProducaoCriarOsDaVendaContratoTest.php` (lane `verticais-pest.yml`) · casos `Roteiros/Index.casos.md` UC-PCROT-01..04.

> owner: wagner · priority: p1 · estimate: 3h · status: doing · type: story

**Origem:** UC-FLX-07 da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:60`, referência shopVOX, situação `planejado`) e a regra *"Aprovado → Cria a OP com o roteiro do produto"* dos modelos CV (`app/Domain/Fsm/Modelos/CatalogoModelos.php:156`). Medido em 2026-10-20: a criação já existia (ADR 0436 — `CriarOsDaVenda` lê `pcp_produto_roteiro` e grava uma programação por etapa), mas nenhum código gravava o vínculo produto → roteiro (só os testes), e o vínculo não conferia se o roteiro era da mesma empresa.

**Aceite:**
- [x] Ao aprovar a venda, a OS por item do produto ligado a um roteiro nasce com uma programação por etapa, na ordem bloco/trilha, com o posto e o tempo previsto (horas) da etapa; a etapa opcional fica de fora (UC-PCROT-01, UC-FLX-07).
- [x] Produto sem roteiro não gera OS — o comportamento de antes, sem roteiro padrão da empresa (UC-PCROT-02).
- [x] As programações são cópia do roteiro no dia da aprovação: editar o roteiro depois não muda a OS já criada (UC-PCROT-03).
- [x] O gestor (`producao.access`) liga produtos ao roteiro por `PUT /producao/roteiros/{id}/produtos`; um produto fica em um roteiro só, e o vínculo vem no payload da tela (`produto_ids`).
- [x] Multi-tenant: roteiro de outra empresa → 404; produto de outra empresa é recusado; vínculo apontando roteiro de outra empresa conta como produto sem roteiro (UC-PCROT-04).
- [ ] Seletor de produtos na tela de Roteiros (`Roteiros/Index.tsx`, com charter e gate visual).
- [ ] Roteiro padrão da empresa para produto sem roteiro (decisão [W]: hoje produto sem roteiro não vira OS).

## §8 — Regras Gherkin (R-PCP-001..010) — resumo

- R-PCP-001 — `pcp_appointments` é append-only (trigger MySQL bloqueia UPDATE não-finished_at + DELETE total)
- R-PCP-002 — `business_id` global scope obrigatório em todos models (Tier 0 — ADR 0093)
- R-PCP-003 — Idempotency-Key obrigatório em `POST /api/pcp/scan` (rejeita 409 se duplicado em 24h)
- R-PCP-004 — Action `apontar_fim_operacao` com all-operations-done dispara automaticamente `concluir_producao` FSM
- R-PCP-005 — Performance per-user PII guard — `pcp.performance.view` permission obrigatória
- R-PCP-006 — Workstation `is_busy=true` bloqueia novo `apontar_inicio` (exceto operação `multi_concurrent` no catálogo)
- R-PCP-007 — QR token expira 90 dias (re-print label refresh token)
- R-PCP-008 — Cancelamento OS dispara `LiberarSchedule` (libera slots futuros agendados)
- R-PCP-009 — Mass UPDATE/DELETE em `pcp_appointments` detectado por `pcp:scan-drift` cron daily (paridade ADR 0143 `fsm:scan-drift`)
- R-PCP-010 — Cross-tenant test biz=1 vs biz=99 obrigatório (ADR 0101) em todos endpoints

## §9 — Refs

- ADR mãe FSM: [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- ADR multi-tenant: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR modular: [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- ADR Processo MWART: [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Mapping Delphi PCP: [research/_MAPPING/TELA-PRODUCAO-KANBAN.md](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md)
- ADR proposta PCP: [ADR-PROPOSAL.md](ADR-PROPOSAL.md) (D1-D5), aceita pela [ADR 0431](../../decisions/0431-pcp-sai-do-desejo-modules-producao.md)
- MATRIZ-ROI: [MATRIZ-ROI.md](MATRIZ-ROI.md)
- ROADMAP: [ROADMAP.md](ROADMAP.md)

---
**Versão inicial 2026-05-12** — discovery + proposta. Pendente aprovação Wagner D1 (Pcp vs IProduction) antes de qualquer scaffold.
