# RUNBOOK — FSM Pipeline OficinaAuto (cacamba_locacao + cacamba_manutencao)

> **Atualizado:** 2026-05-20 (Wave 7-C/D/E — tríade MVP Martinho LIVE em prod biz=1)
> **Status:** PIPELINE LIVE — [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
> **Substitui leitura de:** ADR 0129 + ADR 0143 + 4 PRs (#1195, #1197, #1203, #1205) quando trabalhar em FSM OS

## TL;DR

OficinaAuto reusa o **FSM Orchestrator global** (`app/Domain/Fsm/*`) para gerenciar 2 processos canônicos por business:

- **`cacamba_locacao`** — 4 stages (Disponível → Locada → Recolhida; lateral Em manutenção)
- **`cacamba_manutencao`** — 4 stages (Aberta → Em serviço → Concluída; lateral Cancelada)

UI canon no drawer `ServiceOrderSheet`: **Timeline** (gap #1) + **StagePipeline** mini-grafo (gap #2) + **ações FSM** (Wave 7-A). Index OS tem **StageChips** com contador (gap #3).

## Arquitetura — onde mora cada peça

| Peça | Path | Função |
|---|---|---|
| FSM Orchestrator (genérico) | [`app/Domain/Fsm/`](../../../app/Domain/Fsm/) | Models, Services, Policies, SideEffects compartilhados (Sells também usa) |
| ServiceOrder Entity | [`Modules/OficinaAuto/Entities/ServiceOrder.php`](../../../Modules/OficinaAuto/Entities/ServiceOrder.php) | Campo `current_stage_id` (FK lógica → `sale_process_stages.id`) |
| FSM Seeder | [`Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php`](../../../Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php) | Cria processos+stages+actions+roles per-business idempotente |
| Action Controller | [`app/Http/Controllers/ServiceOrderFsmActionController.php`](../../../app/Http/Controllers/ServiceOrderFsmActionController.php) | Endpoints `/fsm/actions`, `/fsm/execute`, `/fsm/start-pipeline`, `/history` |
| Drawer Sheet | [`resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSheet.tsx`](../../../resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSheet.tsx) | Container drawer lateral pattern Cockpit V2 |
| UI canon — Timeline | `_components/ServiceOrderTimeline.tsx` | Histórico transições (gap #1 — PR #1195) |
| UI canon — StagePipeline | `_components/ServiceOrderStagePipeline.tsx` | Mini-grafo "você está aqui" (gap #2 — PR #1205) |
| UI canon — StageChips | `_components/ServiceOrderFsmActionPanel.tsx` + Index.tsx | Chips Linear-style com count (gap #3 — PR #1203) |
| Side-effects | [`app/Domain/Fsm/SideEffects/*Cacamba.php`](../../../app/Domain/Fsm/SideEffects/) | `IniciarLocacaoCacamba`, `RecolherCacamba`, `EnviarCacambaManutencao`, etc |

## Os 2 processos cacamba — fluxo + actions

### 1. `cacamba_locacao` (caso Martinho — locação avulsa)

```
[Disponível ●]  ──iniciar_locacao──▶  [Locada]  ──recolher──▶  [Recolhida ✓ terminal]
      ▲                                                                 │
      │                                                                 │
      │  voltar_disponivel  (de recolhida)                              │
      ╞══════════════════════════════════════════════════════════════════
      │
      │  enviar_manutencao  (de disponivel OU recolhida)
      ▼
[Em manutenção]  ──voltar_disponivel──▶  [Disponível]
```

**Roles canon (Spatie suffix `#{biz}`):** `mecanico`, `gerente`
- `iniciar_locacao` is_critical (gerente OU mecanico)
- `recolher` (gerente OU mecanico)
- `enviar_manutencao` is_critical (gerente only)
- `voltar_disponivel` (gerente OU mecanico)

### 2. `cacamba_manutencao` (oficina simples)

```
[Aberta ●]  ──iniciar_servico──▶  [Em serviço]  ──concluir──▶  [Concluída ✓ terminal]
    │              │
    └──cancelar────┴──cancelar──▶  [Cancelada ✓ terminal lateral]
```

**Roles canon:** `mecanico`, `gerente`
- `iniciar_servico` (mecanico)
- `concluir` is_critical (gerente OU mecanico)
- `cancelar` is_critical (gerente only)

## Pegadinha CRÍTICA — `transaction_id` polimórfico em `sale_stage_history`

> **História append-only de todas transições FSM grava em `sale_stage_history` — table compartilhada com Sells (transactions).** ServiceOrder reusa esse table armazenando `$order->id` no campo `transaction_id` (subject_id polimórfico). NÃO há campo `entity_type` físico.

**Discriminação obrigatória** ao consultar history de OS:

1. **Via `action.stage.process.key`** quando `action_id` é não-nulo — filtrar `IN ('cacamba_locacao', 'cacamba_manutencao')`
2. **Via `payload_snapshot.pipeline_started=true`** quando `action_id IS NULL` (entries do `startPipeline()`)

Sem essa discriminação, há risco real de colisão: `transactions.id=42` (sale Sells) e `service_orders.id=42` (OS OficinaAuto) gerariam history misturado se filtrarmos só por `transaction_id`.

Ver implementação canon: [`ServiceOrderFsmActionController::history()` linha ~290](../../../app/Http/Controllers/ServiceOrderFsmActionController.php).

## Endpoints REST canon

| Verb | Path | Função | Auth permission |
|---|---|---|---|
| `GET` | `/oficina-auto/service-orders/{order}/fsm/actions` | Lista actions disponíveis + current_stage + **stages_pipeline** | `oficinaauto.service_order.view` |
| `POST` | `/oficina-auto/service-orders/{order}/fsm/execute` | Executa transição (action_key + payload com motivo) | `oficinaauto.service_order.update` |
| `POST` | `/oficina-auto/service-orders/{order}/fsm/start-pipeline` | Inicializa OS legada (sem current_stage_id) no processo correto via `order_type` | `oficinaauto.service_order.update` |
| `GET` | `/oficina-auto/service-orders/{order}/history` | Timeline auditável (filtrada por process_key cacamba_*) | `oficinaauto.service_order.view` |
| `GET` | `/oficina-auto/ordens-servico?stage=X` | Index OS filtrado por stage.key (chips Linear) | `oficinaauto.service_order.view` |

**Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) em TODOS endpoints — `ServiceOrder` global scope + `SaleProcess.business_id` filter explicit no controller.

## Como iniciar pipeline FSM numa OS legada

OS criadas antes de Wave 5/6/7 podem ter `current_stage_id IS NULL`. O `ServiceOrderFsmActionPanel` mostra empty state com botão "Iniciar pipeline FSM" que chama:

```http
POST /oficina-auto/service-orders/{order}/fsm/start-pipeline
Body: {}  (ou { "process_key": "cacamba_locacao" } pra override superadmin)
```

O backend resolve `process_key` via `$order->order_type`:
- `order_type=locacao` → `cacamba_locacao` (stage inicial: `disponivel`)
- `order_type=manutencao` → `cacamba_manutencao` (stage inicial: `aberta`)

Audit entry registrada em `sale_stage_history` com `action_id=NULL` + `payload.pipeline_started=true` + `payload.subject_type='Modules\\OficinaAuto\\Entities\\ServiceOrder'`.

## UI canon — 3 componentes que ficam no drawer

```
┌─────────────────────────────────────────┐  ServiceOrderSheet (drawer lateral pattern Cockpit)
│ [OS-00001] [Locação] [Atrasada]         │  ← Header
│ Locação #001                            │
│ Cliente: Martinho Caçambas              │
├─────────────────────────────────────────┤
│ 3 KPIs (Diárias / Diária / A receber)   │
├─────────────────────────────────────────┤
│ Detalhes (veículo + cliente + datas)    │
├─────────────────────────────────────────┤
│ § Pipeline                              │  ← Gap #2 (Wave 7-E)
│   ● ─── ○ ─── ○   ServiceOrderStage    │
│   Disp  Locada Recolh  Pipeline.tsx    │
│   Variantes: ○ Em manutenção            │
├─────────────────────────────────────────┤
│ § Ações disponíveis                     │  ← Wave 7-A backend canon
│   [Iniciar locação]  [Enviar manuten.]  │  FsmActionPanel.tsx
├─────────────────────────────────────────┤
│ § Histórico                             │  ← Gap #1 (Wave 7-C)
│   ○ 2026-05-12 14:23  Wagner WR23      │  ServiceOrderTimeline.tsx
│     Pipeline iniciado → Disponível      │
└─────────────────────────────────────────┘
```

## CI/Deploy — pegadinhas catalogadas desta sessão

- **`quick-sync.yml` NÃO regenera composer autoload** — método novo em `ServiceOrderFsmActionController` (Wave 7-C) deu 500 em todo módulo até `Deploy to Hostinger` (workflow_dispatch) rodar `composer install`. Detalhe em [memory/reference/deploy-recovery-patterns.md §2.2](../../reference/deploy-recovery-patterns.md#22-quick-sync-vs-deploy-quando-precisa-composer-install-em-prod).
- **`Inertia::defer` no payload `kpis`/`stages` exige frontend tipar opcional + default** — sem isso `kpis.locacoes_ativas` no primeiro render crasha tela branca. Hotfix PR #1197. Detalhe em [skill inertia-defer-default §Antipattern](../../../.claude/skills/inertia-defer-default/SKILL.md).
- **`$this->authorize()` exige `extends App\Http\Controllers\Controller`** — `ServiceOrderController` + `VehicleController` extendiam `Illuminate\Routing\Controller` (base raw, sem trait `AuthorizesRequests`). Drawer JSON Wave 7-A começou a hitar `show()` → `Method authorize does not exist` → HTTP 500 em todas 5 OS. Hotfix PR #1211 (depois de diagnose PR #1209 com try-catch trace JSON). Detalhe em [memory/reference/deploy-recovery-patterns.md §6](../../reference/deploy-recovery-patterns.md#6-bug-latente-controller-authorize--illuminateroutingcontroller-vs-apphttpcontrollerscontroller).
- **Bug latente revelado por feature nova** — `show()` JSON nunca foi chamado em prod até drawer Wave 7-A. Sintomas: Inertia HTML page funcionava normal (outro fluxo de middleware mascarava), mas fetch JSON quebrou. **Smoke browser MCP pós-deploy** (skill `smoke-prod-evidence`) descobre essa categoria — sem ele, o 500 ficaria silencioso. Auditar Controllers via grep antes de adicionar novos endpoints JSON sobre rotas existentes:
  ```bash
  grep -rln 'use Illuminate\\Routing\\Controller' Modules/<X>/Http/Controllers/ | while read f; do
    grep -l 'this->authorize\|this->validate' "$f" >/dev/null && echo "BUG LATENTE: $f"
  done
  ```

## Pest tests canon

| Spec | Path | Cobre |
|---|---|---|
| FsmTransitionTest | `Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php` | Transições válidas/inválidas + accessors |
| ServiceOrderHistoryControllerTest | `Modules/OficinaAuto/Tests/Feature/ServiceOrderHistoryControllerTest.php` | 4 specs Timeline (cacamba_*, startPipeline, discriminação process_key, Tier 0) |
| ServiceOrderIndexStageFilterTest | `Modules/OficinaAuto/Tests/Feature/ServiceOrderIndexStageFilterTest.php` | 4 specs chips filter + counts + Tier 0 |
| ServiceOrderStagePipelineTest | `Modules/OficinaAuto/Tests/Feature/ServiceOrderStagePipelineTest.php` | 4 specs stages_pipeline payload + Tier 0 |

Todos skipam em SQLite ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) — CI roda contra MySQL.

## Refs canon

- [memory/sessions/2026-05-20-arte-tela-fsm-workflow.md](../../sessions/2026-05-20-arte-tela-fsm-workflow.md) — auditoria estado-da-arte FSM screen (origem dos 3 gaps)
- [memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE
- [memory/decisions/0129-state-machine-canonica-fsm-rbac.md](../../decisions/0129-state-machine-canonica-fsm-rbac.md) — FSM pattern canônico
- [memory/decisions/0137-modules-oficinaauto-qualificada.md](../../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto vertical
- [memory/decisions/0093-multi-tenant-isolation-tier-0.md](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 IRREVOGÁVEL
- PRs canon: [#1195](https://github.com/wagnerra23/oimpresso.com/pull/1195) (gap #1 Timeline) · [#1203](https://github.com/wagnerra23/oimpresso.com/pull/1203) (gap #3 chips) · [#1205](https://github.com/wagnerra23/oimpresso.com/pull/1205) (gap #2 pipeline) · [#1197](https://github.com/wagnerra23/oimpresso.com/pull/1197) (hotfix kpis defer)
