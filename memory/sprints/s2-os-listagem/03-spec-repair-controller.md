# Spec — RepairController@index (Inertia)

> Sprint 2 · MWART · Modules/Repair

## Localização

`Modules/Repair/Http/Controllers/RepairController.php` — método `index()` existente, refatorado para dual-mode.

## Assinatura

```php
public function index(Request $req): \Inertia\Response|\Illuminate\Contracts\View\View
```

## Request — Query params aceitos

Mantêm-se os mesmos do Blade legacy (não inventar params novos):

| param | tipo | default | descrição |
|---|---|---|---|
| `q` | string | `null` | busca em `invoice_no`, `contact.name`, `repair_serial_no` |
| `repair_status_id[]` | int[] | `[]` | FK para `repair_statuses` (multi-tenant via `business_id`) |
| `contact_id` | int | `null` | id do customer (`contacts.type='customer'`) |
| `location_id` | int | `null` | `business_locations.id` |
| `service_staff_id` | int | `null` | `users.id` (mapeia para `transactions.res_waiter_id`) |
| `start_date` | date | `null` | YYYY-MM-DD, filtro em `transaction_date` |
| `end_date` | date | `null` | YYYY-MM-DD |
| `due_start` | date | `null` | filtro em `repair_due_date` |
| `due_end` | date | `null` | filtro em `repair_due_date` |
| `view_own` | bool | derivado | true se user só tem `repair.view_own` (não `repair.view`) |
| `sort` | string | `repair_due_date` | `invoice_no\|repair_due_date\|transaction_date\|final_total\|contact_name\|repair_status` |
| `dir` | string | `asc` | `asc\|desc` |
| `page` | int | `1` | paginação |
| `per_page` | int | `25` | `25\|50\|100` |

## Validação

```php
$validated = $req->validate([
    'q' => 'nullable|string|max:200',
    'repair_status_id' => 'array',
    'repair_status_id.*' => 'integer|exists:repair_statuses,id',
    'contact_id' => 'nullable|integer|exists:contacts,id',
    'location_id' => 'nullable|integer|exists:business_locations,id',
    'service_staff_id' => 'nullable|integer|exists:users,id',
    'start_date' => 'nullable|date',
    'end_date' => 'nullable|date|after_or_equal:start_date',
    'due_start' => 'nullable|date',
    'due_end' => 'nullable|date|after_or_equal:due_start',
    'sort' => 'nullable|in:invoice_no,repair_due_date,transaction_date,final_total,contact_name,repair_status',
    'dir' => 'nullable|in:asc,desc',
    'per_page' => 'nullable|in:25,50,100',
]);
```

## Query base (multi-tenant first)

```php
$business_id = $req->session()->get('user.business_id');
$user = $req->user();
$canViewAll = $user->can('repair.view') || $user->can('superadmin');
$canViewOwn = $user->can('repair.view_own');

if (! $canViewAll && ! $canViewOwn) {
    abort(403);
}

$query = Transaction::query()
    ->where('transactions.business_id', $business_id)   // SEMPRE primeiro
    ->where('transactions.type', 'sell')
    ->where('transactions.sub_type', 'repair')
    ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
    ->leftJoin('business_locations as bl', 'transactions.location_id', '=', 'bl.id')
    ->leftJoin('repair_statuses as rs', 'transactions.repair_status_id', '=', 'rs.id')
    ->leftJoin('users as ss', 'ss.id', '=', 'transactions.res_waiter_id')
    ->leftJoin('warranties as rw', 'rw.id', '=', 'transactions.repair_warranty_id')
    ->leftJoin('repair_device_models as rdm', 'rdm.id', '=', 'transactions.repair_model_id')
    ->select([
        'transactions.id',
        'transactions.invoice_no',
        'transactions.transaction_date',
        'transactions.repair_due_date',
        'transactions.repair_status_id',
        'transactions.repair_serial_no',
        'transactions.repair_defects',
        'transactions.final_total',
        'transactions.payment_status',
        'transactions.contact_id',
        'transactions.res_waiter_id',
        'transactions.location_id',
        'transactions.created_by',
        DB::raw("CONCAT(COALESCE(contacts.name, ''), CASE WHEN contacts.supplier_business_name IS NOT NULL THEN CONCAT(' (', contacts.supplier_business_name, ')') ELSE '' END) as contact_name"),
        'rs.name as repair_status_name',
        'rs.color as repair_status_color',
        'bl.name as location_name',
        'ss.first_name as service_staff_first',
        'ss.last_name as service_staff_last',
        'rw.name as warranty_name',
        'rdm.name as device_model_name',
    ]);

// view_own: só vê o que CRIOU
if (! $canViewAll && $canViewOwn) {
    $query->where('transactions.created_by', $user->id);
}

// Filtros
$query->when($validated['q'] ?? null, function ($q, $term) {
    $q->where(function ($w) use ($term) {
        $w->where('transactions.invoice_no', 'like', "%{$term}%")
          ->orWhere('contacts.name', 'like', "%{$term}%")
          ->orWhere('transactions.repair_serial_no', 'like', "%{$term}%");
    });
});

$query->when($validated['repair_status_id'] ?? [], fn ($q, $ids) =>
    $q->whereIn('transactions.repair_status_id', $ids));

$query->when($validated['contact_id'] ?? null, fn ($q, $id) =>
    $q->where('transactions.contact_id', $id));

$query->when($validated['location_id'] ?? null, fn ($q, $id) =>
    $q->where('transactions.location_id', $id));

$query->when($validated['service_staff_id'] ?? null, fn ($q, $id) =>
    $q->where('transactions.res_waiter_id', $id));

$query->when($validated['start_date'] ?? null, fn ($q, $d) =>
    $q->whereDate('transactions.transaction_date', '>=', $d));
$query->when($validated['end_date'] ?? null, fn ($q, $d) =>
    $q->whereDate('transactions.transaction_date', '<=', $d));

$query->when($validated['due_start'] ?? null, fn ($q, $d) =>
    $q->whereDate('transactions.repair_due_date', '>=', $d));
$query->when($validated['due_end'] ?? null, fn ($q, $d) =>
    $q->whereDate('transactions.repair_due_date', '<=', $d));

// Ordenação (whitelist)
$sort = $validated['sort'] ?? 'repair_due_date';
$dir = $validated['dir'] ?? 'asc';
$sortMap = [
    'invoice_no' => 'transactions.invoice_no',
    'repair_due_date' => 'transactions.repair_due_date',
    'transaction_date' => 'transactions.transaction_date',
    'final_total' => 'transactions.final_total',
    'contact_name' => 'contacts.name',
    'repair_status' => 'rs.name',
];
$query->orderBy($sortMap[$sort], $dir);

// Paginação preservando query string
$paginated = $query->paginate($validated['per_page'] ?? 25)
                   ->withQueryString();
```

## Resposta — Props Inertia

```php
$data = [
    'repairs' => RepairListResource::collection($paginated)->response()->getData(true),
    'filters' => $validated,
    'meta' => [
        'totals_by_status' => $this->totalsByStatus($business_id, $validated, $user),
        'repair_statuses' => RepairStatus::forDropdown($business_id),  // já existe
        'locations' => $this->businessUtil->getBusinessLocations($business_id),
        'service_staff' => User::forDropdown($business_id, true, false, false, false),  // já existe
        'business_currency' => $req->session()->get('currency'),
        'business_timezone' => $req->session()->get('business_timezone'),
    ],
    'permissions' => [
        'create' => $user->can('repair.create'),
        'update' => $user->can('repair.update'),
        'delete' => $user->can('repair.delete'),
        'status_update' => $user->can('repair_status.update'),
        'view_all' => $canViewAll,
    ],
];

if ($this->mwartEnabled('repair_index', $business_id)) {
    return Inertia::render('Repair/Index', $data);
}

// Caminho Blade legacy preservado — NÃO mudar.
return $this->renderBladeIndex($req, $data);
```

## RepairListResource

`Modules/Repair/Http/Resources/RepairListResource.php`:

```php
class RepairListResource extends JsonResource
{
    public function toArray($req): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'transaction_date' => $this->transaction_date?->toIso8601String(),
            'repair_due_date' => $this->repair_due_date?->toIso8601String(),
            'repair_due_human' => $this->repair_due_date?->diffForHumans(),
            'is_overdue' => $this->repair_due_date && $this->repair_due_date->isPast()
                && ! in_array(optional($this->repairStatus)->status_type, ['completed', 'cancelled']),
            'serial_no' => $this->repair_serial_no,
            'defects' => $this->repair_defects,
            'final_total' => (float) $this->final_total,
            'final_total_formatted' => $this->formatCurrency($this->final_total),
            'payment_status' => $this->payment_status,
            'contact' => [
                'id' => $this->contact_id,
                'name' => $this->contact_name,  // do select raw
            ],
            'service_staff' => $this->res_waiter_id ? [
                'id' => $this->res_waiter_id,
                'name' => trim($this->service_staff_first . ' ' . $this->service_staff_last),
            ] : null,
            'location' => [
                'id' => $this->location_id,
                'name' => $this->location_name,
            ],
            'status' => [
                'id' => $this->repair_status_id,
                'name' => $this->repair_status_name,
                'color' => $this->repair_status_color,
            ],
            'warranty_name' => $this->warranty_name,
            'device_model_name' => $this->device_model_name,
        ];
    }
}
```

## Bulk actions — endpoints já existentes

Manter as bulk actions atuais (Blade já implementa via `RepairController`):

- `POST /repair/update-repair-status` — `updateRepairStatus()` (já existe)
- Demais bulks existentes no Blade

A spec da Sprint 2 **não introduz endpoints novos** — port 1:1. Se faltar bulk no Blade hoje, é escopo Sprint 2.5.

## Performance

- Eager joins via `leftJoin()` (não `with()`) — espelha o Blade que usa DataTables com joins
- `select()` explícito (whitelist de colunas, sem `SELECT *`)
- Índices da Sprint 2 (vê `02-schema-repair-indices.sql`) cobrem 90% dos planos
- `paginate(25)` é o default — Blade usa DataTables server-side com mesmo limite
- Cache de `totals_by_status`: NÃO cachear na Sprint 2 (volume baixo, complica invalidação multi-user). Reavaliar Sprint 3.

## Telemetria

- Log canal `mwart` (criar em PR3): `Log::channel('mwart')->info('repair.index', [...])` com `business_id`, `user_id`, count de filtros, duration_ms, total
- Telescope captura naturalmente (já está em prod)
- Sentry: breadcrumb antes do `Inertia::render()`

## Permissions Spatie envolvidas

```
repair.view              → vê todas as OS do business
repair.view_own          → vê só as OS criadas pelo user
repair.create            → CTA "Nova OS" visível
repair.update            → menu "Editar" no kebab
repair.delete            → menu "Deletar" no kebab
repair_status.update     → bulk "Mudar status" disponível
superadmin               → bypass total
```

Nada de `User::canMwart()` — gate é por `business_id` em `config/mwart.php`.

## Testes — `Modules/Repair/Tests/Feature/RepairIndexTest.php`

> ⚠️ Antes de criar o primeiro teste do módulo, conferir se `Modules/Repair/Tests/` está registrado em `phpunit.xml` (CLAUDE.md §4 — runbook em `memory/requisitos/Infra/RUNBOOK-pest-suite.md`). Se não estiver, registrar no mesmo PR.

Cobertura mínima:

- [ ] lista respeita `business_id` (cria 2 businesses, valida que não vaza)
- [ ] `view_own` filtra por `created_by`
- [ ] `view` (sem own) lista todas
- [ ] filtros combinados (status + contact + due_date) retornam contagem correta
- [ ] paginação preserva query string
- [ ] sort por whitelist funciona; sort fora da whitelist é rejeitado
- [ ] flag MWART off → retorna view Blade (`assertViewIs('repair::repair.index')`)
- [ ] flag MWART on para business beta → retorna Inertia component (`assertInertia(fn ($a) => $a->component('Repair/Index'))`)
- [ ] flag MWART on para business NÃO beta → ainda Blade
- [ ] user sem `repair.view*` → 403
