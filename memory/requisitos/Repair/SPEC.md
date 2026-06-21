---
module: Repair
owner: wagner
version: "1.0"
last_updated: "2026-06-13"
---

# Especificação funcional

## 3. User stories

> Convenção do ID: `US-REPA-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-REPA-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _pendente_ — US-REPA-001 não escrita (placeholder TODO)

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-REPA-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Repair
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-002 · Autorização Spatie `repair.create`

```gherkin
Dado que um usuário **não** tem a permissão `repair.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.create')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-003 · Autorização Spatie `repair.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.update')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-004 · Autorização Spatie `repair.view`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-005 · Autorização Spatie `repair.view_own`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view_own`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view_own')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-006 · Autorização Spatie `repair.delete`

```gherkin
Dado que um usuário **não** tem a permissão `repair.delete`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.delete')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-007 · Autorização Spatie `repair_status.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair_status.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair_status.update')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-008 · Throttle endpoint público `/repair-status` (Wave 3 D8.a)

```gherkin
Dado que `/repair-status` é endpoint PÚBLICO (sem auth) que recebe número OS + telefone últimos dígitos
Quando um IP faz mais de N requests/minuto
Então recebe 429 Too Many Requests + log estruturado `repair.public_status.checked`
```

**Implementação proposta (backlog Wave 4):** middleware `throttle:30,1` no grupo top-level que envolve `Route::get('/repair-status', ...)` e `Route::post('/post-repair-status', ...)` em [Modules/Repair/Routes/web.php](../../../Modules/Repair/Routes/web.php) linhas 3-4. Hoje SEM throttle explícito — apenas throttle global Laravel via `RouteServiceProvider` (60 req/min padrão).
**Risco:** scraping massivo de OS expõe pattern de numeração + telefone redact incompleto.
**Ver:** [PII-LGPD.md §"Pontos críticos"](PII-LGPD.md), [OBSERVABILITY.md §"repair_public_status_abuse"](OBSERVABILITY.md).
**Testado em:** _(pendente — Pest test 31 requests retorna 429)_

---

## 5. Notas técnicas Wave 3 v3 booster (2026-05-16)

### D6.a · Inertia::defer já adotado em JobSheetController

Auditoria confirma `JobSheetController` já usa `Inertia::defer()` em props pesadas — refactor adicional desnecessário:

| Action | Linha | Defer aplicado em |
|---|---|---|
| `create()` | 376 | `options` (statuses + devices + brands + technicians + customers + groups) |
| `show()` | 550-552 | `parts`, `activities`, `anexos` |
| `edit()` | 706 | `options` (via `buildJobSheetEditOptions`) |

Pattern alinhado com skill `inertia-defer-default` (Tier B) e [RUNBOOK-inertia-defer-pattern.md](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md). Confirmado por Wave L L2 + auditoria Wave 3.

### D7.a · PiiRedactor herdado (sem código próprio)

Repair NÃO duplica `PiiRedactor` — herda do core (`App\Services\PiiRedactor`). Detalhe em [PII-LGPD.md §2](PII-LGPD.md).

### D7.b · LogsActivity trait em JobSheet

[Entities/JobSheet.php](../../../Modules/Repair/Entities/JobSheet.php) recebeu trait `LogsActivity` Spatie + método `getActivitylogOptions()` listando 8 campos críticos (`status_id`, `service_staff`, `device_id`, `brand_id`, `device_model_id`, `defects`, `completed_on`, `current_stage_id`), com `logOnlyDirty()` + `dontSubmitEmptyLogs()` + `useLogName('repair_job_sheet')`.

Complementa, NÃO substitui, `sale_stage_history` FSM ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).

### D9.a · OTel herdado

Repair NÃO emite traces/metrics próprios. Herda OTel auto-instrumentação + logs estruturados + health checks do core. Detalhe completo em [OBSERVABILITY.md §"Herança Core"](OBSERVABILITY.md).
