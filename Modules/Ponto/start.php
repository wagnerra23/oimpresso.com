<?php

/*
|--------------------------------------------------------------------------
| Register Namespaces and Routes (padrão UltimatePOS, ref.: Modules/Jana)
|--------------------------------------------------------------------------
|
| Carregado automaticamente pelo nWidart/laravel-modules ao dar boot no módulo,
| conforme declarado em module.json ("files": ["start.php"]).
| Mantemos este arquivo minimalista: só carrega as rotas.
|
*/

if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
