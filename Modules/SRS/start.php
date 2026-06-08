<?php

/*
|--------------------------------------------------------------------------
| Register Namespaces and Routes (padrão UltimatePOS, ref.: Modules/Jana)
|--------------------------------------------------------------------------
*/

if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
