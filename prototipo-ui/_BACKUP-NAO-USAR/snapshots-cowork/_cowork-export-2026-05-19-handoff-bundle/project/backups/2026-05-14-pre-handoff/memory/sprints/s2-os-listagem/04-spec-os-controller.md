# Spec — OsController@index (Inertia)

> Sprint 2 · MWART · Officeimpresso

## Localização

`Modules/Officeimpresso/Http/Controllers/OsController.php`

## Assinatura

```php
public function index(Request $req): Response|InertiaResponse
```

## Request — Query params aceitos

| param | tipo | default | descrição |
|---|---|---|---|
| `q` | string | `null` | busca texto livre (numero, descricao, observacoes via FULLTEXT) |
| `status[]` | array | `[]` | filtro multi: `briefing\|arte\|aprovacao\|producao\|acabamento\|expedicao\|entregue\|arquivada` |
| `cliente_id` | int | `null` | id do cliente |
| `responsavel_id` | int | `null` | id do user responsável |
| `prazo_de` | date | `null` | YYYY-MM-DD |
| `prazo_ate` | date | `null` | YYYY-MM-DD |
| `prioridade[]` | array | `[]` | `baixa\|media\|alta\|urgente` |
| `apenas_minhas` | bool | `false` | filtra `responsavel_id = auth()->id()` |
| `incluir_arquivadas` | bool | `false` | inclui `arquivada_em IS NOT NULL` |
| `sort` | string | `prazo_entrega` | coluna de ordenação |
| `dir` | string | `asc` | `asc\|desc` |
| `page` | int | `1` | paginação |
| `per_page` | int | `50` | 25, 50, 100 |

## Validação

```php
$validated = $req->validate([
    'q' => 'nullable|string|max:200',
    'status' => 'array',
    'status.*' => 'in:briefing,arte,aprovacao,producao,acabamento,expedicao,entregue,arquivada',
    'cliente_id' => 'nullable|integer|exists:clientes,id',
    'responsavel_id' => 'nullable|integer|exists:users,id',
    'prazo_de' => 'nullable|date',
    'prazo_ate' => 'nullable|date|after_or_equal:prazo_de',
    'prioridade' => 'array',
    'prioridade.*' => 'in:baixa,media,alta,urgente',
    'apenas_minhas' => 'boolean',
    'incluir_arquivadas' => 'boolean',
    'sort' => 'in:numero,prazo_entrega,created_at,cliente_nome,valor,status',
    'dir' => 'in:asc,desc',
    'per_page' => 'in:25,50,100',
]);
```

## Query base

```php
$empresaId = $req->user()->empresa_id;

$query = Os::query()
    ->where('empresa_id', $empresaId)
    ->with(['cliente:id,nome', 'responsavel:id,name'])
    ->select([
        'id', 'numero', 'descricao', 'status', 'prioridade',
        'cliente_id', 'responsavel_id', 'prazo_entrega',
        'valor', 'created_at', 'arquivada_em',
    ]);

// Filtros
$query->when($validated['q'] ?? null, fn ($q, $term) =>
    $q->where(function ($w) use ($term) {
        $w->where('numero', 'like', "%{$term}%")
          ->orWhereRaw('MATCH(descricao, observacoes) AGAINST(? IN BOOLEAN MODE)', [$term]);
    })
);

$query->when($validated['status'] ?? [], fn ($q, $st) => $q->whereIn('status', $st));
$query->when($validated['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id));
$query->when($validated['responsavel_id'] ?? null, fn ($q, $id) => $q->where('responsavel_id', $id));
$query->when($validated['prazo_de'] ?? null, fn ($q, $d) => $q->where('prazo_entrega', '>=', $d));
$query->when($validated['prazo_ate'] ?? null, fn ($q, $d) => $q->where('prazo_entrega', '<=', $d));
$query->when($validated['prioridade'] ?? [], fn ($q, $p) => $q->whereIn('prioridade', $p));
$query->when($validated['apenas_minhas'] ?? false, fn ($q) => $q->where('responsavel_id', $req->user()->id));
$query->unless($validated['incluir_arquivadas'] ?? false, fn ($q) => $q->whereNull('arquivada_em'));

// Ordenação
$sort = $validated['sort'] ?? 'prazo_entrega';
$dir = $validated['dir'] ?? 'asc';
if ($sort === 'cliente_nome') {
    $query->join('clientes', 'clientes.id', '=', 'ordens_servico.cliente_id')
          ->orderBy('clientes.nome', $dir)
          ->select('ordens_servico.*');
} else {
    $query->orderBy($sort, $dir);
}

// Paginação preservando query string
$paginated = $query->paginate($validated['per_page'] ?? 50)
                   ->withQueryString();
```

## Resposta — Props

```php
$data = [
    'os' => OsListResource::collection($paginated)->response()->getData(true),
    'filtros' => $validated,
    'meta' => [
        'totais_por_status' => $this->totaisPorStatus($empresaId, $validated),
        'clientes_options' => Cliente::where('empresa_id', $empresaId)
            ->orderBy('nome')->limit(500)->get(['id', 'nome']),
        'responsaveis_options' => User::where('empresa_id', $empresaId)
            ->where('ativo', true)->orderBy('name')->get(['id', 'name']),
    ],
    'permissions' => [
        'create' => $req->user()->can('os.create'),
        'bulk_update' => $req->user()->can('os.bulk_update'),
        'archive' => $req->user()->can('os.archive'),
    ],
];

if (config('mwart.os_index_enabled') && $req->user()->canMwart()) {
    return Inertia::render('Os/Index', $data);
}
return view('officeimpresso::os.index', $data);
```

## OsListResource

```php
class OsListResource extends JsonResource
{
    public function toArray($req): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'descricao' => $this->descricao,
            'status' => $this->status,
            'status_label' => OsStatus::label($this->status),
            'status_color' => OsStatus::color($this->status),
            'prioridade' => $this->prioridade,
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente->id,
                'nome' => $this->cliente->nome,
            ]),
            'responsavel' => $this->whenLoaded('responsavel', fn () => [
                'id' => $this->responsavel->id,
                'name' => $this->responsavel->name,
            ]),
            'prazo_entrega' => $this->prazo_entrega?->toIso8601String(),
            'prazo_humano' => $this->prazo_entrega?->diffForHumans(),
            'atrasada' => $this->prazo_entrega?->isPast() && !in_array($this->status, ['entregue','arquivada']),
            'valor' => (float) $this->valor,
            'valor_formatado' => 'R$ ' . number_format($this->valor, 2, ',', '.'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

## Bulk actions — endpoint separado

`POST officeimpresso.os.bulk` — handled em `OsBulkController`:

```php
public function update(Request $req)
{
    $req->validate([
        'ids' => 'required|array|min:1|max:200',
        'ids.*' => 'integer|exists:ordens_servico,id',
        'action' => 'required|in:mudar_status,mudar_responsavel,arquivar',
        'value' => 'required_unless:action,arquivar',
    ]);

    DB::transaction(function () use ($req) {
        $os = Os::whereIn('id', $req->ids)
            ->where('empresa_id', $req->user()->empresa_id)
            ->lockForUpdate()
            ->get();

        $this->authorize('bulkUpdate', [$os->first(), $os]);

        match ($req->action) {
            'mudar_status' => $os->each->update(['status' => $req->value]),
            'mudar_responsavel' => $os->each->update(['responsavel_id' => $req->value]),
            'arquivar' => $os->each->update(['arquivada_em' => now()]),
        };

        OsHistorico::registrar($os, $req->user(), $req->action, $req->value);
    });

    return back()->with('success', count($req->ids) . ' OS atualizadas.');
}
```

## Performance

- Eager load apenas `cliente:id,nome` e `responsavel:id,name` (sem N+1)
- `select()` explícito (não usar `SELECT *`)
- `paginate()` usa `LIMIT/OFFSET`; pra >10k registros considerar cursor pagination (Sprint 3+)
- Cache de `totais_por_status` por 60s (Redis) — invalidar em qualquer write de OS

## Telemetria

- Log estruturado: `Log::channel('mwart')->info('os.index', ['user_id' => ..., 'filters' => ..., 'duration_ms' => ..., 'count' => ...])`
- Métrica Telescope: query count, total ms
- Sentry breadcrumb antes do `return Inertia::render()`

## Testes

- `tests/Feature/Officeimpresso/OsIndexTest.php` — cobertura mínima:
  - lista respeita empresa_id do user
  - filtros combinados retornam contagem correta
  - paginação preserva query string
  - bulk update muda status atomicamente
  - bulk update falha se OS não pertence à empresa do user
  - flag MWART off → retorna view Blade
  - flag MWART on → retorna Inertia response
