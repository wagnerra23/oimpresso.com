<?php

/*
|--------------------------------------------------------------------------
| Modules/Governance — entry point
|--------------------------------------------------------------------------
|
| ADR 0086 — MVP Fase 5. Carrega routes admin de governance dashboard.
|
*/

if (! app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
