# RUNBOOK — Inertia::defer pattern (SPA-feel default cross-módulo)

> **Tipo:** RUNBOOK canon Tier 0 — pattern obrigatório em TODA `Inertia::render(...)` do oimpresso
> **Status:** ativo desde 2026-05-15 (D-14 incident perception SPA fix)
> **Refs:** [ADR 0110 Cockpit V2](../../decisions/0110-cockpit-pattern-v2-ativacao.md) · [proibicoes.md §Sempre fazer](../../proibicoes.md) · skill `inertia-defer-default` Tier B
> **Origem:** Wagner 2026-05-15 reportou "parece que está carregando a página inteira a cada troca de contato, o sistema inteiro deveria ser SPA?" — diagnóstico catalogado como D-14 + fix em PR #873

---

## 1. Por que esse RUNBOOK existe

Inertia partial reload (`router.get(..., { only: [...] })`) **NÃO pula execução do Controller** — só filtra a resposta JSON. Toda prop com query/count/aggregated EXECUTA mesmo quando partial reload não pede. Resultado típico: switch de tab/conversa/registro disparando 8-12 queries SQL pesadas a cada navegação = 300-800ms = **percepção UX de "carregando página inteira"** (apesar do DOM ser preservado).

**Fix arquitetural canônico:** `Inertia::defer(fn () => ...)` envolve props caras em closures que **só executam quando o partial reload `only: [...]` explicita o nome OU quando frontend faz auto-fetch async pós render inicial** (via componente `<Deferred data="..." fallback={...}>`).

## 2. Regra duráurea (Tier 0 — IRREVOGÁVEL)

> **Toda prop com query SQL pesada DEVE usar `Inertia::defer()`. Default = defer. Exceções (sempre eager) catalogadas abaixo.**

### 2.1 SEMPRE defer (closures `Inertia::defer(fn () => ...)`)

| Tipo de prop | Por quê |
|---|---|
| `paginate(N)` ou `get()` de qualquer Eloquent | Custo: scan + ORDER BY + 1-3 JOINs + (talvez) subqueries N+1 |
| `count()` (1 ou mais) | Custo: COUNT(*) scan; multiplicar por 5 stats vira ~50ms+ |
| Aggregated queries (`groupBy`, `selectRaw('SUM(...)')`) | Custo: aggregation scan |
| `with(...)` eager-load de N relações | Custo: N+1 queries |
| Service calls que tocam DB (`ContextoNegocio::compute()`, `MetricsAggregator::snapshot()`) | Custo variável, sempre > 50ms |
| `ensureXxx()` side-effects idempotentes (seed defaults) | Custo: SELECT count + INSERTs raros |
| Subqueries scalar (`addSelect(['last_xxx' => ...])`) | Custo: 1 EXTRA scan per row do outer query |
| HTTP externo (Meta Cloud API, Asaas, NFe SEFAZ) | Custo: latência rede 200-2000ms |

### 2.2 SEMPRE eager (NÃO defer)

| Tipo de prop | Por quê |
|---|---|
| Request inputs (`$request->input('tab')`, `$request->boolean('within_24h')`) | Custo zero — só leitura HTTP |
| IDs/booleanos derivados (`$businessId`, `$selectedChannelId`, `$activeTagIds`) | Custo zero — variáveis primitivas |
| `config('xxx')` static | Custo zero — array em memória |
| Tokens curtos (`CentrifugoTokenIssuer::issue()` HMAC HS256 ~1ms) | Custo trivial |
| Estados de UI (`session()->get('...')`) | Custo trivial |
| Props que o partial reload **target** (ex: `selectThread` pede `thread`+`messages` → essas NÃO podem defer) | Quebra a finalidade da navegação |
| Props pequenas pré-computadas (até ~5 inteiros/strings) | Overhead defer > custo de incluir |

## 3. Pattern canônico backend

### 3.1 Controller `index()` — antes (anti-pattern)

```php
public function index(Request $request): Response
{
    $businessId = (int) session('user.business_id');
    $tab = $request->input('tab', 'all');

    // ❌ EAGER — executa toda navegação, mesmo partial reload pra `only:['thread']`
    $conversations = Conversation::query()
        ->where('business_id', $businessId)
        ->with('channel')
        ->paginate(50)
        ->getCollection()
        ->map(fn ($c) => $this->convToArray($c));

    $stats = [
        'open' => Conversation::query()->where('business_id', $businessId)->where('status', 'open')->count(),
        'resolved' => Conversation::query()->where('business_id', $businessId)->where('status', 'resolved')->count(),
        // ... 3 outros counts
    ];

    return Inertia::render('Foo/Index', [
        'conversations' => $conversations,  // ❌ paginate executou
        'stats' => $stats,                   // ❌ 5 counts executaram
        'tab' => $tab,
    ]);
}
```

### 3.2 Controller `index()` — pattern canônico (`Inertia::defer`)

```php
public function index(Request $request): Response
{
    $businessId = (int) session('user.business_id');
    $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
    $tab = $request->input('tab', 'all');

    // ✅ Closures defer — só executam quando partial reload `only:[]` pede OU auto-fetch async
    return Inertia::render('Foo/Index', [
        'conversations' => Inertia::defer(fn () => $this->buildConversationsPayload($businessId, $tab)),
        'stats' => Inertia::defer(fn () => $this->buildStatsPayload($businessId)),

        // Eager (custo zero)
        'tab' => $tab,
        'businessId' => $businessId,
    ]);
}

/**
 * Pattern: extrair query pesada pra método protected facilita Pest test
 * + closures defer com `fn () => $this->buildXxxPayload(...)` ficam limpas.
 */
protected function buildConversationsPayload(int $businessId, string $tab): array
{
    $paginated = Conversation::query()
        ->where('business_id', $businessId)
        ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
        ->with('channel')
        ->paginate(50);

    return [
        'data' => $paginated->getCollection()->map(fn ($c) => $this->convToArray($c))->all(),
        'current_page' => $paginated->currentPage(),
        'last_page' => $paginated->lastPage(),
        'total' => $paginated->total(),
    ];
}

protected function buildStatsPayload(int $businessId): array
{
    $base = fn () => Conversation::query()->where('business_id', $businessId);
    return [
        'open' => $base()->where('status', 'open')->count(),
        'resolved' => $base()->where('status', 'resolved')->count(),
        // ...
    ];
}
```

## 4. Pattern canônico frontend (Inertia v3 React)

### 4.1 Import + types opcionais

```tsx
import { Deferred } from '@inertiajs/react';

interface Props {
  // Props deferred viram opcionais (?: undefined) — defer skipa execução inicial,
  // auto-fetch async preenche depois.
  conversations?: Paginated<Conversation>;
  stats?: { open: number; resolved: number };

  // Eager mantém non-optional
  tab: string;
  businessId: number;
}
```

### 4.2 `<Deferred>` wrappers com fallback skeleton

```tsx
export default function FooIndex({ conversations, stats, tab, businessId }: Props) {
  return (
    <div className="cockpit">
      {/* Eager state — render imediato */}
      <FilterBar tab={tab} />

      {/* Deferred props — fallback skeleton enquanto async fetch resolve */}
      <Deferred
        data={['conversations', 'stats']}
        fallback={(
          <Card className="h-full flex flex-col">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="flex gap-2 items-center p-2">
                <div className="h-8 w-8 rounded-full bg-muted/40 animate-pulse shrink-0" />
                <div className="flex-1 space-y-1.5">
                  <div className="h-3 w-3/4 bg-muted/40 rounded animate-pulse" />
                  <div className="h-2 w-1/2 bg-muted/30 rounded animate-pulse" />
                </div>
              </div>
            ))}
          </Card>
        )}
      >
        <ConversationList
          conversations={conversations as Paginated<Conversation>}
          stats={stats!}
        />
      </Deferred>
    </div>
  );
}
```

### 4.3 Skeletons canônicos por tipo de UI

- **Lista paginada (50 rows)**: 8 row skeletons + spinner "Carregando…"
- **KPI cards (4-6)**: 4 cards skeleton 60×80px
- **Tabela**: header skeleton + 10 row skeletons
- **Dashboard charts**: rect skeleton + texto "Carregando gráfico…"
- **Dropdown topbar (ChannelSelector etc)**: span 24×5 inline animate-pulse

## 5. Comportamento por path de partial reload

| Path | `only:[]` | Defer closures que executam | Defer closures que pulam | Eager |
|---|---|---|---|---|
| Initial visit | (vazio) | — | TODOS (post-paint async fetch) | Eager state |
| `selectThread` | `['thread', 'messages']` | `thread`, `messages` (já eager) | `conversations`, `stats`, channels, tags | tab, q, filters |
| `setTab` | `['conversations', 'tab', 'stats']` | `conversations`, `stats` | channels, tags | tab |
| Centrifugo msg incoming | `['messages', 'thread', 'conversations', 'stats']` | `conversations`, `stats` | channels, tags | — |
| Toggle filter | `['conversations', 'within24h']` | `conversations` | stats, channels, tags | within24h |
| Polling fallback 5s | depends — preserva pattern existente | — | — | — |

## 6. Anti-hooks (PROIBIDO)

- ❌ **NÃO** marcar `thread`/`messages` (ou equivalentes que são alvo principal de `selectXxx` partial reload) como defer — quebra a finalidade do partial reload
- ❌ **NÃO** wrap o WHOLE PAYLOAD em 1 único `Deferred` — perde granularidade
- ❌ **NÃO** usar `Inertia::optional()` em vez de `defer()` quando você quer UX progressiva (defer = async-loaded; optional = só on-request manual)
- ❌ **NÃO** esquecer de marcar props como opcional (`?:`) no interface TS quando deferidas — TypeScript reclama
- ❌ **NÃO** chamar a closure sem necessidade no test (ex: `$props['conversations']['data']` direto) — `DeferProp` é objeto, precisa `$props['conversations']()` pra invocar
- ❌ **NÃO** deferir props que o initial paint precisa CRÍTICO (ex: AppShellV2 sidebar items) — UX quebrada
- ❌ **NÃO** mexer no `preserveScroll` / `preserveState` / `replace` dos `router.get()` existentes — preservar pattern de partial reload já estabelecido

## 7. Pest test adaptation

Quando você converte uma prop pra `Inertia::defer`, ela vira `DeferProp` no `$response->props`. Tests que faziam `$props['conversations']['data']` direto quebram. Solução:

```php
function iqtConvData(array $props): array
{
    $convs = $props['conversations'] ?? null;
    // Invocar DeferProp closure se prop foi marcada defer
    if ($convs instanceof \Inertia\DeferProp || $convs instanceof \Inertia\OptionalProp) {
        $convs = $convs();
    }
    $data = $convs['data'] ?? [];
    // ...
    return $data;
}
```

## 8. Métricas esperadas pós-aplicação

Casos reais medidos no oimpresso pós-D-14 fix:

| Tela | Antes | Depois | Δ |
|---|---|---|---|
| `Atendimento/Inbox/Index.tsx` switch conversa | ~300ms | **~50ms** | **-83%** |
| `Atendimento/Inbox/Index.tsx` initial visit | ~500ms síncrono | render + skeleton ~100-300ms async | UX progressiva |
| Esperado em Sells/Index, Repair/Index, OS/Index, etc | 300-800ms | 50-150ms | -75 a -85% |

## 9. Checklist aplicação por tela

Use este checklist em PRs que aplicam o pattern:

- [ ] Controller `index()`/`show()` identifica props caras (consultar §2.1)
- [ ] Props caras viram `Inertia::defer(fn () => $this->buildXxxPayload(...))`
- [ ] Método `buildXxxPayload(...)` extraído como `protected function`
- [ ] Props eager preservadas (§2.2): filters, IDs, config, tokens
- [ ] Frontend `Props` interface: defer props viram `?:` opcional
- [ ] Frontend wraps em `<Deferred>` com fallback skeleton apropriado
- [ ] Skeleton fallback usa tokens canon (`bg-muted/40`, `animate-pulse`)
- [ ] Pest test do Controller adaptado pra invocar `DeferProp` closure quando necessário
- [ ] Pest cross-tenant Tier 0 ADR 0093 ainda passa (defer não afeta isolamento)
- [ ] Smoke biz=1 prod pós-deploy — switch conversa percepção SPA-feel
- [ ] BRIEFING.md do módulo atualizado (skill `brief-update`)

## 10. Quando NÃO aplicar

- Telas que **já são modais ou client-side state** (Sheet/Drawer abre via state local — sem partial reload)
- Telas que o user passa <2s antes de outra navegação (ex: confirmation page after submit)
- Initial paint requirements críticos (Auth login screen, splash, error 500)
- Props que vêm via `Inertia::share()` no middleware (global shared) — comportamento diferente

## 11. Refs / fontes

- Inertia v3 deferred props docs: https://inertiajs.com/deferred-props
- [memory/decisions/0110-cockpit-pattern-v2-ativacao.md](../../decisions/0110-cockpit-pattern-v2-ativacao.md) — Cockpit V2 canon
- [memory/proibicoes.md](../../proibicoes.md) §Sempre fazer — regra Tier 0 derivada deste RUNBOOK
- skill `inertia-defer-default` Tier B — auto-trigger antes de Edit em qualquer Controller `Inertia::render(...)`
- Origem D-14: PR #873 (perf Inbox 300→50ms) + briefing Whatsapp 2026-05-15
