---
name: Receita criar módulo Laravel no oimpresso
description: Estrutura mínima e obrigatória para um módulo aparecer corretamente em /manage-modules + sidebar
type: reference
originSessionId: 409bf092-4605-44ef-b1e7-119c5935158b
---
Módulo aparecer com botão Install + sidebar exige 8 peças. Validado criando Modules/ADS/ em 2026-05-03.

Estrutura:
```
Modules/<Nome>/
├── module.json              ← provider list, "active": true
├── composer.json            ← psr-4
├── Config/config.php
├── Database/Migrations/
├── Providers/
│   ├── <Nome>ServiceProvider.php   ← migrations + translations + middleware + commands
│   └── RouteServiceProvider.php     ← mapWebRoutes + mapApiRoutes
├── Http/Controllers/
│   ├── DataController.php           ← OBRIGATÓRIO: 3 hooks UltimatePOS
│   └── InstallController.php        ← OBRIGATÓRIO: extends BaseModuleInstallController
├── Routes/web.php           ← inclui Route::get('install',...) com 3 ações
├── Resources/lang/pt-BR/<modulo>.php
└── Resources/menus/topnav.php
```

Falta de DataController → módulo não aparece no menu (auditoria 2026-04-26).
Falta de InstallController → tela /manage-modules sem botão Install.

Referências canônicas (imitar): Modules/NFSe/ (mais novo), Modules/Repair/, Modules/Jana/.

Os 3 hooks de DataController:
- `superadmin_package()` → array com 'name' do feature flag
- `user_permissions()` → array de permissões (Spatie)
- `modifyAdminMenu()` → Menu::modify('admin-sidebar-menu', fn) com checks isModuleInstalled + can()

Lang file vai em Resources/lang/pt-BR/<alias>.php; ServiceProvider precisa de `loadTranslationsFrom(__DIR__.'/../Resources/lang', '<alias>')` ou as chaves saem cruas.

`modules_statuses.json` na raiz exige entrada `"<Nome>": true` para Laravel ativar.

**Pegadinhas (descobertas ao criar Modules/ADS/ 2026-05-03):**
- ❌ NÃO usar `__('alias::file.key')` em DataController/topnav — `LegacyMenuAdapter` lê literal, não resolve traduções → labels saem crus tipo `"ads::ads.module_label"`. Hardcodar strings PT-BR (NFSe sempre fez assim).
- ❌ NÃO usar `route('xxx.yyy', id)` em Pages React — Ziggy não está disponível neste Inertia. Usar strings literais: `href={`/ads/admin/decisoes/${id}`}` (padrão `Pages/copiloto/Dashboard.tsx`).
- Pra validar página Inertia: Chrome MCP com cookies do user logado + `read_console_messages` pega erros JS instantâneo.
