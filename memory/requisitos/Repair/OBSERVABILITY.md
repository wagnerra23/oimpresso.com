# OBSERVABILITY — Modules/Repair

> Declaração canônica de pontos de hook OTel (D9.a Observability v3 — 2026-05-16).
> Estratégia leve: documenta superfície de instrumentação SEM código novo. Quando SDK OTel full subir no CT 100 (`OTEL_FULL_SDK=true` — ver `config/otel.php`), services serão envolvidos via decorator/middleware sem mudar assinatura.

## Spans canônicos planejados

| Service / Método | Span name (OTel GenAI conv.) | Atributos obrigatórios | Trigger |
|---|---|---|---|
| `KanbanProductionService::mapStatusesToColumns()` | `repair.kanban.map_statuses` | `business_id`, `count.statuses`, `count.completed`, `count.active` | Cada render Kanban |
| `KanbanProductionService::findStatusForColumn()` | `repair.kanban.find_status` | `business_id`, `column_id`, `status_id_resolvido` | Cada drop card |
| (Futuro) `KanbanTransitionService::move()` | `repair.kanban.move_card` | `business_id`, `os_id`, `from_status`, `to_status`, `user_id` | DnD persistente |
| (Futuro) `OsCreationService::criar()` | `repair.os.criar` | `business_id`, `cliente_id`, `equipamento_tipo` | POST nova OS |

## Princípios Tier 0

- **Zero-cost quando driver=null** — wrapper checa `config('otel.enabled')` ANTES de criar span
- **business_id SEMPRE atributo** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Stateless preservado** — KanbanProductionService é puro mapping; spans não devem introduzir state
- **FSM Pipeline** ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — `ExecuteStageActionService` em `app/Domain/Fsm/` já emite `sale_stage_history` (audit append-only); spans OTel são complemento NÃO substituto

## Padrão de hook (quando ativar)

```php
use Modules\Jana\Services\Memoria\Telemetry\RetrievalSpan;

$span = new RetrievalSpan('repair.kanban.map_statuses', null, [
    'business_id' => $businessId,
    'count.statuses' => $statuses->count(),
]);
try {
    $map = /* lógica existente intocada */;
    $span->setAttribute('count.completed', $statuses->where('is_completed_status', true)->count());
    $span->setStatus('ok');
    return $map;
} finally {
    $span->end();
}
```

## Refs
- [config/otel.php](../../../config/otel.php)
- [Modules/Repair/Services/KanbanProductionService.php](../../../Modules/Repair/Services/KanbanProductionService.php)
- ADR canon: 0094 §5 SoC brutal, 0143 FSM Pipeline LIVE

---

## Herança Core oimpresso (Wave 3 v3 booster D9.a — 2026-05-16)

Além dos spans canônicos planejados acima, Repair herda três camadas de observabilidade do **core oimpresso** SEM código próprio:

### 1. OpenTelemetry auto-instrumentação

Spans automáticos via instrumentação Laravel global (`config/otel.php`):

| Span | Origem | Atributos relevantes p/ Repair |
|---|---|---|
| `http.server` | toda request `/repair/*`, `/repair-status`, `/job-sheet/*` | `http.method`, `http.route`, `http.status_code`, `user.business_id` |
| `db.query` | queries `JobSheet::with(...)`, `RepairStatus::*` | `db.statement` (redacted), `db.rows_affected` |
| `queue.job` | `ExecuteStageActionService` async | span filho de `http.server` parent |

**Atributos custom recomendados** ao criar novo Controller/Service Repair:

```php
\OpenTelemetry\API\Trace\Span::getCurrent()
    ->setAttribute('repair.job_sheet_id', $jobSheet->id)
    ->setAttribute('repair.fsm_action', $actionKey)
    ->setAttribute('repair.status_id', $jobSheet->status_id);
```

⛔ NÃO logar `defects`/`contact->mobile`/`tax_number` em span — viola [PII-LGPD.md](PII-LGPD.md). Usar `PiiRedactor` antes.

### 2. Logs estruturados

Padrão `Log::info('repair.<dominio>.<evento>', $context)` com prefixo `repair.` (facilita filtro Loki):

| Evento | log_name | Contexto recomendado |
|---|---|---|
| OS criada | `repair.job_sheet.created` | id, business_id, contact_id (redacted) |
| Status mudou | `repair.job_sheet.status_changed` | id, from, to, actor |
| FSM action | `repair.fsm.action_executed` | id, action_key, from_stage, to_stage |
| Upload | `repair.arquivo.uploaded` | id, sub_destination, size_bytes |
| Print customer copy | `repair.print.customer_copy` | id, actor (PII física exposta) |
| Status público | `repair.public_status.checked` | os_number, ip, success (rate-limit signal D8.a) |

### 3. Health checks

Comando `php artisan jana:health-check` (daily 06:00 BRT, `app/Console/Kernel.php`). 5 checks globais; Repair entra via:

- `multi_tenant_isolation` → `repair_job_sheets.business_id` NOT NULL
- `procedure_drift` → triggers/views `repair_*` versus migration canon

**Checks específicos Repair (backlog futuro):**

- `repair_orphan_job_sheets` — OS com `contact_id` apontando pra Contact deletado/anonimizado
- `repair_stale_diagnostic` — OS em `recebido_para_diagnostico` há >7 dias
- `repair_public_status_abuse` — taxa de `/repair-status` por IP / 24h (bot detection)

### 4. Activity log (Spatie) — campos JobSheet

Wave 3 v3 booster D7.b adicionou trait `LogsActivity` em `JobSheet` (8 campos críticos, `logOnlyDirty`, log_name `repair_job_sheet`). Complementa `sale_stage_history` FSM:

- `sale_stage_history` → transições de stage (audit append-only ADR 0143)
- `activity_log` (Spatie) → diffs de field-level (status_id, service_staff, defects, completed_on, etc)

Consulta: `Activity::where('log_name', 'repair_job_sheet')->where('subject_id', $jobSheet->id)`.

## Skills relacionadas

`jana-arch` (Tier B, OTel GenAI) · `multi-tenant-patterns` (Tier A)

