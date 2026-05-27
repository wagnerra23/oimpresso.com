# ADR MWART-0001 — Contrato Module Web App React Transition

**Status:** Accepted
**Date:** 2026-05-06
**Sprint:** 2
**Supersedes:** —

## Context

O Oimpresso ERP tem 23 módulos nWidart, 21 ativos, com >200 rotas Blade. Migrar tudo de uma vez é inviável e arriscado. Precisamos de um padrão que permita migração **rota a rota**, com rollback instantâneo e zero re-aprendizado pelo cliente.

## Decision

Adotar o padrão **MWART** (Module Web App React Transition), com três pilares:

### 1. Menu unificado via `LegacyMenuAdapter`

Cada módulo expõe `Modules/<Modulo>/Config/menu.php` retornando `MenuItem[]`:

```php
return [
    [
        'id' => 'os.index',
        'label' => 'OS',
        'icon' => 'clipboard-list',
        'route' => 'officeimpresso.os.index',
        'inertia' => true,           // ← flag MWART
        'order' => 10,
        'group' => 'operacional',
    ],
    [
        'id' => 'os.create',
        'label' => 'Nova OS',
        'route' => 'officeimpresso.os.create',
        'inertia' => false,          // ainda Blade
        'order' => 11,
        'group' => 'operacional',
    ],
];
```

`LegacyMenuAdapter::collect()` agrega todos os `menu.php` em ordem, retorna ao `AppShell.tsx`. **Migrar uma rota = virar a flag** `inertia: false → true`. Sem mexer em label, ordem, ícone ou grupo.

### 2. Controller dual-mode

Durante a migração, o controller responde **dois modos** baseado na flag:

```php
class OsController extends Controller
{
    public function index(Request $req)
    {
        $data = $this->buildIndexData($req);

        if (config('mwart.os_index_enabled') && $req->user()->canMwart()) {
            return Inertia::render('Os/Index', $data);
        }

        return view('officeimpresso::os.index', $data);
    }
}
```

`config/mwart.php`:

```php
return [
    'os_index_enabled' => env('MWART_OS_INDEX', false),
    // ... uma flag por rota migrada
];
```

`User::canMwart()` permite gradual rollout (interno → beta → 100%).

### 3. Props compartilhadas

Toda página Inertia recebe shared props canônicas via `HandleInertiaRequests` middleware:

```php
'auth' => fn () => ['user' => $req->user()?->only(['id','name','email','empresa_id'])],
'empresa' => fn () => $req->user()?->empresaAtiva(),
'menu' => fn () => app(LegacyMenuAdapter::class)->collect($req->user()),
'flash' => fn () => ['success' => $req->session()->get('success'), 'error' => $req->session()->get('error')],
'features' => fn () => app(FeatureFlags::class)->forUser($req->user()),
```

Páginas React leem via `usePage<SharedProps>()`.

## Consequences

✅ **Pros**
- Migração rota a rota, baixo risco
- Rollback = mudar 1 env var
- Cliente final não percebe (menu igual)
- Padrão replicável em todos os 21 módulos
- Permite A/B testing por usuário

❌ **Cons**
- Controller mantém código duplicado durante transição (Blade + Inertia view)
- 2 caminhos de teste por rota durante soak
- Risco de drift se Blade e React divergirem em correções

## Mitigações

- **Drift:** todo bug fix em rota MWART tem que ser aplicado nos 2 caminhos até flag virar 100% e Blade ser deletado.
- **Code duplication:** Controller compartilha `buildIndexData()`; só o `return` muda.
- **Deletar Blade:** após 30 dias com flag 100% on e zero rollback, deletar `*.blade.php` + cleanup do controller.

## Compliance

- [ ] Toda PR MWART precisa do label `mwart`
- [ ] Toda PR MWART precisa atualizar `memory/migrations.md` com data/rota/PR
- [ ] CI roda testes Blade **e** Inertia até a flag ir pra 100%

## References

- `OPUS-MISSION-BRIEF.md` §3 (Padrão MWART)
- `CLAUDE.md` projeto root
- `resources/js/Layouts/AppShell.tsx`
- Inertia v3 docs: https://inertiajs.com/
