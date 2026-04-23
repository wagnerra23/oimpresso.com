# ADR ARQ-0009 (DocVault) · TopNav declarativo por módulo em `Resources/menus/topnav.php`

- **Status**: accepted
- **Data**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Relacionado**: ADR UI-0001 (_DesignSystem)

## Contexto

14 módulos do UltimatePOS já têm `Resources/views/layouts/nav.blade.php` — navbar horizontal declarado em Blade, exibido acima do conteúdo da tela legada:

```blade
<nav class="navbar navbar-default">
    <a class="navbar-brand" href="{{ url('accounting/dashboard') }}">
        <i class="fas fa-book"></i> {{ __('accounting::lang.accounting') }}
    </a>
    <ul class="nav navbar-nav">
        @can('chart_of_accounts.view')
            <li @if (request()->segment(2) == 'chart_of_account') class="active" @endif>
                <a href="{{ url('accounting/chart_of_account') }}">
                    @lang('accounting::lang.view_charts_of_accounts')
                </a>
            </li>
        @endcan
        <!-- ... -->
    </ul>
</nav>
```

Ao migrar telas pra React/Inertia, precisávamos replicar esse padrão sem duplicar conhecimento. Opções consideradas:

1. **Middleware com `Menu::create()` (estilo CRM ContactSidebarMenu)**: overkill pra caso simples — só CRM precisa de lógica contextual (tipo do contato).
2. **Novo método `DataController::modifyModuleTopNav()`**: espelha `modifyAdminMenu`, mas adiciona verbosidade desnecessária.
3. **Arquivo PHP declarativo `Resources/menus/topnav.php`** (escolhido): mesmo espírito do Blade (arquivo por módulo, dentro da pasta do módulo), mas formato array puro.

## Decisão

Cada módulo que quiser top-nav React declara um arquivo:

```php
// Modules/<Nome>/Resources/menus/topnav.php
<?php
return [
    'label' => 'Ponto WR2',
    'icon'  => 'Clock',
    'items' => [
        ['label' => 'Dashboard',       'href' => '/ponto',                'icon' => 'LayoutDashboard', 'can' => 'ponto.access'],
        ['label' => 'Espelho',         'href' => '/ponto/espelho',        'icon' => 'ClipboardList',   'can' => 'ponto.access'],
        ['label' => 'Intercorrências', 'href' => '/ponto/intercorrencias', 'icon' => 'AlertTriangle',  'can' => 'ponto.access'],
        // ...
    ],
];
```

### Como é lido

`App\Services\LegacyMenuAdapter::buildTopNavs()`:

1. Lê `modules_statuses.json` → lista módulos ativos
2. Pra cada ativo: tenta `Modules/<Nome>/Resources/menus/topnav.php`
3. Se existir: `require` + valida shape + filtra items por `auth()->user()->can($item['can'])` (Spatie)
4. Resolve label i18n se contiver `::` (ex: `'label' => 'ponto::lang.dashboard'` → `trans()`)
5. Detecta `inertia=true/false` via `isInertiaRoute()` (Inertia `<Link>` vs `<a>`)
6. Retorna map `['PontoWr2' => [...], 'Accounting' => [...], ...]`

`App\Http\Middleware\HandleInertiaRequests::share()` expõe como:

```php
'shell' => [
    'menu'    => fn () => $user ? app(ShellMenuBuilder::class)->build($request) : [],
    'topnavs' => fn () => $user ? app(ShellMenuBuilder::class)->buildTopNavs($request) : [],
],
```

### Como é consumido no React

`useModuleNav(moduleKey)` hook tem 2 camadas:

```tsx
const moduleNav = useModuleNav('PontoWr2');
// 1. Prefere shell.topnavs['PontoWr2'] (declarativo)
// 2. Fallback: shell.menu[moduloAtivo].children (sidebar nwidart)
```

Pages passam como prop do AppShell:

```tsx
export default function DashboardIndex({...}: Props) {
    const moduleNav = useModuleNav('PontoWr2');
    return (
        <AppShell moduleNav={moduleNav}>
            {/* ... */}
        </AppShell>
    );
}
```

## Consequências

**Positivas:**
- **Convenção idiomática UltimatePOS**: arquivo por módulo, dentro da própria pasta do módulo. Wagner "já sabe onde os arquivos estão".
- **Declarativo**: array PHP sem lógica embutida (≠ controller ≠ middleware). Machine-readable por qualquer agente (IA, tooling, DocVault audit).
- **Permissões Spatie nativas**: campo `can` filtra no backend antes de chegar no front.
- **i18n pronto**: label pode ser literal ou chave `module::lang.x`.
- **Cache-friendly**: arquivo carregado 1x via `require` (Laravel opcache compila).
- **Fallback safe**: módulo sem `topnav.php` continua funcionando via children do sidebar.
- **Blade antigo preservado**: `nav.blade.php` continua renderizando pras telas ainda em Blade.

**Negativas:**
- Se módulo migrar 100% pra React, pode querer remover `nav.blade.php` (duplicação). Mitigação: deixar a remoção opcional (arquivo legado não atrapalha).

## Alternativas consideradas (resumo)

- **Middleware `<Nome>TopNavMenu`** estilo ContactSidebarMenu: descartado — overhead sem ganho (só CRM precisa de lógica contextual real).
- **`DataController::modifyModuleTopNav()`**: descartado — verbosidade (controller pra dado estático).
- **Manter hardcoded no React (prop items fixa)**: descartado — duplica conhecimento entre backend e frontend.
- **Ler `nav.blade.php` e parse HTML**: descartado — frágil (HTML não é machine-readable confiável).

## Sinais de conclusão

- [x] `LegacyMenuAdapter::buildTopNavs()` implementado
- [x] `ShellMenuBuilder::buildTopNavs()` wrapper implementado
- [x] `HandleInertiaRequests::share()` expõe `shell.topnavs`
- [x] `SharedProps['shell']['topnavs']` tipado em TypeScript
- [x] `useModuleNav()` atualizado pra preferir `topnavs` + fallback
- [x] `Modules/PontoWr2/Resources/menus/topnav.php` criado (piloto, 10 items)
- [x] 10 pages PontoWR2 migradas pra usar `useModuleNav('PontoWr2')`
- [ ] Portar `nav.blade.php` dos outros 13 módulos pra formato declarativo (sessões futuras)
- [ ] Audit DocVault ganha check `C16_HAS_TOPNAV` (opcional)
