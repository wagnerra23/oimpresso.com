<?php

/*
|--------------------------------------------------------------------------
| Register Routes (padrão UltimatePOS, ref.: Modules/Copiloto/start.php)
|--------------------------------------------------------------------------
|
| Carregado automaticamente pelo nWidart/laravel-modules ao dar boot no módulo,
| conforme declarado em module.json ("files": ["start.php"]).
|
*/

if (! app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
