# Changelog — Modules/OficinaAuto

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-20 — Wave 7-C/D/E tríade MVP Martinho LIVE (FSM screen 65→~80)

Fecha os 3 gaps urgentes da auditoria estado-da-arte FSM screen ([memory/sessions/2026-05-20-arte-tela-fsm-workflow.md](../../sessions/2026-05-20-arte-tela-fsm-workflow.md)) — pré-ativação Martinho. Nota agregada na grade 15 dimensões: **65/100 → ~80/100**.

### Added

- **Wave 7-C — Timeline FSM auditável** ([PR #1195](https://github.com/wagnerra23/oimpresso.com/pull/1195) `84a585951`):
  - `ServiceOrderFsmActionController::history()` (+90 linhas) — método novo no controller existente
  - Rota `GET /oficina-auto/service-orders/{order}/history` (named `oficinaauto.service_orders.history`)
  - `ServiceOrderTimeline.tsx` — port de `SaleTimeline.tsx` (US-SELL-035 LIVE)
  - Wire no `ServiceOrderSheet.tsx` substituindo placeholder "Em breve..."
  - 4 Pest specs (`ServiceOrderHistoryControllerTest`) — happy path, startPipeline action_id=NULL, discriminação process_key cacamba_*, multi-tenant Tier 0
- **Wave 7-D — Chips por stage Linear-style com contador** ([PR #1203](https://github.com/wagnerra23/oimpresso.com/pull/1203) `627c008e2`):
  - `ServiceOrderController::index()` aceita `?stage=X` (filtra `current_stage_id` via stage.key)
  - `buildStagesPayload` (Inertia::defer) — 1 query bulk `GROUP BY` counts por stage
  - `STAGE_CHIP_COLOR_MAP` Tailwind (12 cores) + UI chips no Index.tsx
  - 4 Pest specs (`ServiceOrderIndexStageFilterTest`) — filter key, multi-tenant, counts bulk, sem filtro
- **Wave 7-E — Mini-grafo horizontal stages no drawer** ([PR #1205](https://github.com/wagnerra23/oimpresso.com/pull/1205) `5f924b6ff`):
  - `actions()` endpoint ganha campo `stages_pipeline` (lista ordenada por `sort_order`)
  - `ServiceOrderStagePipeline.tsx` (+227 linhas) — componente novo: bullets conectados + check passados + ring atual + variantes laterais
  - Heurística separa pipeline principal vs laterais (manutencao/cancelada ficam abaixo)
  - 4 Pest specs (`ServiceOrderStagePipelineTest`) — ordem sort_order, is_current único, multi-tenant Tier 0, OS sem stage
- **[RUNBOOK-fsm-pipeline.md](RUNBOOK-fsm-pipeline.md)** — doc canon novo: arquitetura, 2 processos cacamba_*, polimorfismo `sale_stage_history`, endpoints REST, UI canon 3 componentes, pegadinhas CI/deploy

### Fixed

- **Hotfix tela branca Index.tsx** ([PR #1197](https://github.com/wagnerra23/oimpresso.com/pull/1197) `ac84ac8d1`): `kpis: Kpis` (não-opcional) crashava com `Inertia::defer`. Fix: `kpis?: Kpis` + `EMPTY_KPIS` default no destructuring. Pattern catalogado em [skill inertia-defer-default §Antipattern](../../../.claude/skills/inertia-defer-default/SKILL.md).
- **Hotfix authorize() trait missing** ([PR #1211](https://github.com/wagnerra23/oimpresso.com/pull/1211) `21676447d`): `ServiceOrderController` + `VehicleController` extendiam `Illuminate\Routing\Controller` (base raw sem trait `AuthorizesRequests`). Drawer JSON Wave 7-A começou a hitar `show()` → `Method authorize does not exist` → HTTP 500 em todas 5 OS biz=1. Diagnose via [PR #1209](https://github.com/wagnerra23/oimpresso.com/pull/1209) try-catch trace JSON. Fix: `extends App\Http\Controllers\Controller` (projeto canon com traits). Bug latente catalogado em [memory/reference/deploy-recovery-patterns.md §6](../../reference/deploy-recovery-patterns.md#6-bug-latente-controller-authorize--illuminateroutingcontroller-vs-apphttpcontrollerscontroller).

### Operational lessons (canon — todos módulos)

- **`quick-sync.yml` NÃO regenera composer autoload** — PR #1195 (método novo) deu 500 em todo módulo até `Deploy to Hostinger` rodar `composer install`. Matriz de decisão quick-sync vs deploy em [memory/reference/deploy-recovery-patterns.md §2.2](../../reference/deploy-recovery-patterns.md#22-quick-sync-vs-deploy-quando-precisa-composer-install-em-prod).
- **Hostname canon** ([PR #1196](https://github.com/wagnerra23/oimpresso.com/pull/1196) `50d9695ab`): `oimpresso.com` (não `oi.wr2.com.br` legado). Doc canon em [memory/reference/sandbox-hostnames.md](../../reference/sandbox-hostnames.md).

### Preserved (Tier 0 IRREVOGÁVEL)

- Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — todos endpoints history/actions/stages_pipeline filtram `business_id` via global scope + filter explicit em SaleProcess
- FSM Orchestrator global ([app/Domain/Fsm/](../../../app/Domain/Fsm/)) — ServiceOrder reusa `sale_stage_history` polimorficamente via `transaction_id` carrying `$order->id`
- Pest skip SQLite ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) — todos novos tests rodam só MySQL CI

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Mecânico, Auto Manager, Lokoz, Bling Oficina, GP Soft Auto), top 5 gaps P0 (US-OFICINA-006/008/009/010/011), score V1-V6 W22→W23 (63→≥85).
- **Wave23OficinaAutoSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 11 assertions cobrindo Vehicle + ServiceOrder + FormRequests Store/Update, LGPD PII fields tracked (plate/chassis/renavam), MATRIZ-ROI presença, governance.bucket=vertical_client_facing + FSM canon `service_order`.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave: 23`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 63/100 → ≥85/100 estimado.
- V1 Pest E2E: +6 (complementa WhatsAppAprovacaoPinTest + E2EJourneyMartinhoBiz1Test DB-based existentes).
- V5 Docs canon: +10 (CAPTERRA-FICHA + CHANGELOG W23 — BRIEFING/ROADMAP/SPEC já existiam, MATRIZ-ROI asserted).
- V6 Capterra ROI Top 5: +3 (FICHA fechando gap W22).

### Preserved (Tier 0 IRREVOGÁVEL)

- FSM canon ADR 0143 `service_order` pipeline complexa (orçamento→aprovação→produção→entrega).
- Vargas + Martinho biz reais NUNCA em test (ADR 0101 — biz=99 sempre).
- PII plate/chassis/renavam protegidos via PiiRedactor.
- ServiceOrderController Inertia::render eager (rollback PR #963 Wave L/W7 preservado — defer quebrava initial render Pages).
- Modules/OficinaAuto lifecycle `V0 em construção` mantido (ADR 0137 — qualificada por sinal).
