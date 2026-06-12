# ADR ARQ-0011 (MemCofre) · Sidebar e TopNav como duas fontes independentes

- **Status**: accepted
- **Data**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Supersede**: ADR arq/0010 (sidebar accordion — mantida, agora acompanhada de TopNav opcional)

## Contexto

Após exploração de 4 padrões de navegação (2 colunas fixas, 1 coluna + topnav global, sidebar accordion pura, sidebar accordion + topnav por módulo), Wagner definiu a versão final:

1. **Sidebar accordion mantida** como está (alimentada por `DataController::modifyAdminMenu()` via `Menu::modify('admin-sidebar-menu', ...)`).
2. **ModuleTopNav horizontal no topo da página** vem de **outro arquivo**, declarativo por módulo.
3. **Sem sincronização** entre os dois — são 2 fontes independentes. Se o módulo quer mostrar algo nos dois lugares, declara em ambos. Se quer apenas num, declara só naquele.

Motivação principal: evitar que sub-items poluam a sidebar quando já estão nas abas no topo. Dev escolhe onde colocar o quê, sem automação forçada.

## Decisão

### Fonte 1 — Sidebar

- **Arquivo do módulo**: `Modules/<Nome>/Http/Controllers/DataController.php::modifyAdminMenu()`
- **Mecanismo**: `Menu::modify('admin-sidebar-menu', ...)` (nwidart/laravel-menus)
- **Orquestração**: middleware `AdminSidebarMenu` + `ModuleUtil::getModuleData('modifyAdminMenu')`
- **Adaptação pro React**: `LegacyMenuAdapter::build()` retorna array em `shell.menu`
- **Render**: `AppShell` sidebar com accordion expansível (ADR arq/0010 preservado)

**Sem mudanças** — continua como está hoje.

### Fonte 2 — ModuleTopNav

- **Arquivo do módulo**: `Modules/<Nome>/Resources/menus/topnav.php` (novo, opcional)
- **Mecanismo**: array PHP retornado por `return [...]`
- **Orquestração**: `LegacyMenuAdapter::buildTopNavs()` itera `modules_statuses.json` e carrega cada arquivo
- **Filtros**: Spatie (`can` field) + i18n (`label` aceita `module::key` ou literal)
- **Expose**: `shell.topnavs[<Nome>]` via `HandleInertiaRequests`
- **Hook**: `useModuleNav(moduleKey)` lê do `shell.topnavs`
- **Prop**: `<AppShell moduleNav={moduleNav}>` renderiza barra horizontal
- **Render**: `ModuleTopNav.tsx` — barra entre topbar e breadcrumb

Shape do arquivo:

```php
<?php
return [
    'label' => 'Ponto WR2',
    'icon'  => 'Clock',
    'items' => [
        ['label' => 'Dashboard',       'href' => '/ponto',                'icon' => 'LayoutDashboard', 'can' => 'ponto.access'],
        ['label' => 'Espelho',         'href' => '/ponto/espelho',        'icon' => 'ClipboardList',   'can' => 'ponto.access'],
        // ...
    ],
];
```

## Independência dos dois sistemas

| Situação | Sidebar | TopNav |
|---|---|---|
| Módulo sem nenhum dos dois arquivos | Não aparece | Não aparece |
| Só `DataController::modifyAdminMenu()` | Aparece | Não aparece |
| Só `Resources/menus/topnav.php` | Não aparece | Aparece quando page passa prop |
| Ambos | Aparece nos dois locais |

**Nada sincroniza**. Dev decide o que vai em cada. Exemplo Manufacturing hoje:
- `DataController::modifyAdminMenu` adiciona "Fabricação" com 4 sub-items (dropdown na sidebar)
- Se criar `Modules/Manufacturing/Resources/menus/topnav.php`, também aparece topnav. Pode remover o dropdown do sidebar deixando só o link pai — aí sub-items ficam só no topo.

## Preservações

- **Ordem**: cada fonte tem sua própria ordem (array index no topnav, campo `order` do nwidart na sidebar).
- **Permissões Spatie**: ambas filtram no backend antes de expor ao React.
- **i18n**: ambas resolvem via `__()`/`trans()` no backend — React recebe string pronta.
- **Rotas Inertia vs Blade**: ambas detectam via `LegacyMenuAdapter::isInertiaRoute()`.

## Consequências

**Positivas:**
- **Zero mudança na sidebar** — UX que Wagner conhece do Blade permanece.
- **TopNav opcional por página** — MemCofre (tabs internas ricas) não precisa, PontoWR2 ganha nav do Blade original.
- **Arquivo único por módulo pra topnav** — dev acha rápido (`Resources/menus/topnav.php`), igual `Resources/views/layouts/nav.blade.php` do Blade.
- **Permissões + i18n + ícones Lucide** resolvidos no backend.
- **Cache-friendly**: arquivo PHP `require` + opcache compila 1x.

**Negativas:**
- Duplicação de declarações quando os dois arquivos listam os mesmos itens.
- Mitigação: apenas módulos que REALMENTE querem topnav criam o arquivo (opcional).

## Piloto

`Modules/PontoWr2/Resources/menus/topnav.php` com 10 items. 10 pages PontoWR2 (Dashboard, Espelho, Intercorrencias, Aprovacoes, BancoHoras, Colaboradores, Configuracoes, Escalas, Importacoes, Relatorios) consumem via `useModuleNav('PontoWr2')` e passam no `<AppShell moduleNav={...}>`.

## Alternativas consideradas

- **ModuleTopNav global com todos módulos horizontais** (ADR arq/0009 tentado): rejeitado — 20+ módulos viram scroll horizontal infinito.
- **Mesma fonte pra sidebar e topnav** (shell.menu children): rejeitado — força sincronização e impede diferenciação.
- **Middleware próprio por módulo estilo ContactSidebarMenu do CRM**: rejeitado — overhead sem ganho pra caso simples (CRM é exceção por precisar lógica `$contact->type`).

## Sinais de conclusão

- [x] `LegacyMenuAdapter::buildTopNavs()` + `resolveLabel()` implementados
- [x] `ShellMenuBuilder::buildTopNavs()` wrapper
- [x] `HandleInertiaRequests::share()` expõe `shell.topnavs`
- [x] `SharedProps['shell']['topnavs']` tipado TypeScript
- [x] `useModuleNav()` hook
- [x] `ModuleTopNav.tsx` componente
- [x] `AppShell` aceita prop `moduleNav`
- [x] `Modules/PontoWr2/Resources/menus/topnav.php` criado
- [x] 10 pages PontoWR2 aplicadas
- [x] Build OK
- [ ] Portar `nav.blade.php` dos outros 13 módulos (conforme forem migrando telas pra React)
