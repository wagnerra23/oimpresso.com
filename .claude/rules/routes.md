---
paths:
  - "routes/web.php"
  - "routes/api.php"
  - "routes/*.php"
  - "Modules/**/Routes/*.php"
---

# Rule path-scoped — `routes/*.php`

> Carrega quando Claude lê/edita arquivos de rotas. **FQCN obrigatório** desde incident 2026-05-14 PR #843.

## FQCN obrigatório — strings legacy quebram `route:cache`

Lição cara catalogada [handoff 2026-05-14 11:30](../../memory/handoffs/2026-05-14-1130-saga-maratona-fluxo-jana-routes-boletos.md) §2:

> `'SellController@method'` (string sem namespace) só funcionava em runtime via fallback Laravel; quebrava `route:cache`/`route:list` com `ReflectionException`. Wagner ativou cache em prod sem perceber. 10 strings em `routes/web.php` linhas 231-239 + 259 — todas convertidas pra `[Class::class, 'method']`. PR #843 resolveu, route cache 2.5MB ATIVO em prod, ~30% melhor cold start.

### Pattern OBRIGATÓRIO

```php
// ✅ FQCN com ::class
Route::get('/sells/{id}', [\Modules\Sells\Http\Controllers\SellController::class, 'show']);
Route::resource('sells', \Modules\Sells\Http\Controllers\SellController::class);

// ✅ use no topo + ::class
use Modules\Sells\Http\Controllers\SellController;
Route::get('/sells/{id}', [SellController::class, 'show']);

// ❌ PROIBIDO — string legacy
Route::get('/sells/{id}', 'SellController@show');
Route::resource('sells', 'SellController');
```

### Por que isso importa

- `php artisan route:cache` é ativado em prod → strings legacy = `ReflectionException` silenciosa em build, **rotas começam a 404 sem aviso**
- `route:list` quebra → grep manual no código
- IDE refactor (rename controller) não atualiza strings → bug latente

## Stack middlewares UltimatePOS

Toda rota web nova autenticada DEVE usar stack canônico (skill `runtime-rules-hostinger-ct100`):

```php
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('admin')
    ->group(function () { ... });
```

## Proibições

- ⛔ `Mcp::web()` (laravel/mcp) sem `if (config('mcp.tools_exposed'))` — MCP tools só CT 100, Hostinger crasheia ([ADR 0062](../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md))

## Skills relacionadas

`mcp-first` (Tier A) · `runtime-rules-hostinger-ct100` (Tier B)
