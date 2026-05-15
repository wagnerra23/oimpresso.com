---
name: inertia-defer-default
description: Use SEMPRE antes de Edit em qualquer Controller que chama `Inertia::render(...)` no oimpresso (qualquer `Modules/<X>/Http/Controllers/**/*Controller.php` ou `app/Http/Controllers/**/*Controller.php` que retorna `Inertia::render`). Garante que props com queries SQL pesadas (paginate/count/with-eager/aggregated/Service-DB/HTTP-externo) usem `Inertia::defer(fn () => $this->buildXxxPayload(...))` em vez de eager — pula execução quando partial reload `only:[]` não pede. Frontend wrap em `<Deferred data="..." fallback={skeleton}>`. SPA-feel real (300ms → 50ms validado). Tier B auto-trigger por description.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: ""
tier: B
parent_adr: 0095
---

# Inertia::defer default no oimpresso ERP

## Quando ativa

Toda vez que o trabalho **edita um Controller Laravel que chama `Inertia::render(...)`** — qualquer:

- `Modules/<X>/Http/Controllers/**/*.php` retornando Inertia
- `app/Http/Controllers/**/*.php` retornando Inertia
- Criação de novo Controller/método Inertia
- Refactor de Controller existente onde se adiciona ou modifica props

## Regra de ouro

> **Toda prop com query SQL pesada DEVE ser `Inertia::defer(fn () => ...)`. Default = defer. Exceções catalogadas e justificadas.**

Inertia partial reload (`router.get(..., { only: [...] })`) **NÃO pula execução do Controller** — só filtra resposta JSON. Sem defer, o backend faz todas queries mesmo quando o frontend não precisa delas → percepção UX de "carregando página inteira".

## Como aplicar (5 passos)

### 1. Identifique props caras vs eager

**SEMPRE defer:**
- `paginate(N)` / `get()` Eloquent
- `count()` (especialmente múltiplos)
- Aggregated (`groupBy`, `selectRaw('SUM(...)')`)
- `with(...)` eager-load com várias relações
- Service calls DB (ContextoNegocio, MetricsAggregator)
- `ensureXxx()` side-effects (seed defaults)
- Subqueries scalar (`addSelect`)
- HTTP externo (Meta Cloud, Asaas, SEFAZ)

**SEMPRE eager:**
- Request inputs (`$request->input(...)`)
- IDs primitivos (`$businessId`, `$selectedXxxId`)
- `config('xxx')` static
- Tokens curtos HMAC (~1ms)
- Props target de partial reload (ex: `thread`/`messages` no Inbox que `selectThread` chama explicit)

### 2. Pattern Controller — extrair pra protected method

```php
public function index(Request $request): Response
{
    $businessId = (int) session('user.business_id');
    $tab = $request->input('tab', 'all');

    return Inertia::render('Foo/Index', [
        // Defer (closures que pulam quando only não pede)
        'items' => Inertia::defer(fn () => $this->buildItemsPayload($businessId, $tab)),
        'stats' => Inertia::defer(fn () => $this->buildStatsPayload($businessId)),

        // Eager (custo zero)
        'tab' => $tab,
        'businessId' => $businessId,
    ]);
}

protected function buildItemsPayload(int $businessId, string $tab): array
{
    $paginated = Item::query()
        ->where('business_id', $businessId)
        ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
        ->with('category')
        ->paginate(50);

    return [
        'data' => $paginated->getCollection()->map(fn ($i) => $this->toArray($i))->all(),
        'current_page' => $paginated->currentPage(),
        'last_page' => $paginated->lastPage(),
        'total' => $paginated->total(),
    ];
}
```

### 3. Pattern frontend — `<Deferred>` wrappers

```tsx
import { Deferred } from '@inertiajs/react';

interface Props {
  items?: Paginated<Item>;  // ?: opcional pois defer
  stats?: { open: number };
  tab: string;
  businessId: number;
}

export default function FooIndex({ items, stats, tab, businessId }: Props) {
  return (
    <div className="cockpit">
      <FilterBar tab={tab} />  {/* eager render imediato */}

      <Deferred
        data={['items', 'stats']}
        fallback={<SkeletonList rows={8} />}
      >
        <ItemList items={items as Paginated<Item>} stats={stats!} />
      </Deferred>
    </div>
  );
}
```

### 4. Skeleton fallback canon

Use tokens canon (`bg-muted/40`, `animate-pulse`). Por tipo de UI:

- **Lista paginada**: 8 row skeletons + spinner "Carregando…"
- **KPI cards (4-6)**: 4 cards skeleton h-20
- **Tabela**: header skeleton + 10 row skeletons
- **Dropdown**: span inline 24×5 animate-pulse

### 5. Pest test adaptation

Se Pest test do Controller faz `$response->props['items']['data']` direto, vai quebrar porque virou `DeferProp`. Adicione helper:

```php
function resolveDeferProp(mixed $value): mixed
{
    if ($value instanceof \Inertia\DeferProp || $value instanceof \Inertia\OptionalProp) {
        return $value();
    }
    return $value;
}
```

E use `resolveDeferProp($props['items'])['data']` em vez de `$props['items']['data']`.

## Exceções legítimas (NÃO aplicar defer)

- **Initial paint requirements críticos** — login screen, error 500, splash
- **Props que vêm via `Inertia::share()` middleware** — global shared, comportamento diferente
- **Modais/Drawers client-side** — sem partial reload, defer não ajuda
- **Telas que user passa <2s** — confirmation pages pós-submit
- **Props target do partial reload principal** — `thread`/`messages` no Inbox quando `selectThread` pede explícito

## Como saber se está aplicado

- Run `Grep` em `Inertia::render` no Controller — toda prop com query pesada deve ter `Inertia::defer(fn () => ...)` ao lado
- Frontend `Props` interface — props deferred têm `?:` (opcional)
- Frontend tem `import { Deferred } from '@inertiajs/react'`
- `<Deferred>` wraps presentes

## Refs

- [RUNBOOK canônico completo](../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md) — 11 seções, exemplos, checklist 9 pontos
- [proibicoes.md §Sempre fazer](../../memory/proibicoes.md) — regra Tier 0 fonte
- Inertia v3 deferred props: https://inertiajs.com/deferred-props
- Origem: D-14 incident 2026-05-15 PR #873 — Wagner reportou Inbox "carregando página" em switch conversa, fix `defer` deu 300ms → 50ms (-83%)
