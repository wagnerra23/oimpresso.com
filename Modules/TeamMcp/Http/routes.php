<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo TeamMcp
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/Copiloto/Http/routes.php).
| Concentra governança: tokens MCP + Kanban backlog + auditoria CC sessions.
| Permissions herdadas do Copiloto (NÃO renomeadas — risco de quebrar usuários
| existentes; rename vira task de revisão futura).
|
*/

// 1) Rotas web /team-mcp MUDARAM-SE pra Modules/Forja/Http/routes.php em
//    2026-07-31 (hub Equipe). URLs e names inalterados. O grupo /forja e o
//    /team-mcp/install seguem AQUI — saem nas etapas 5 e 7.
// 1b) Cockpit /forja MUDOU-SE pra Modules/Forja/Http/routes.php em 2026-07-31.
//     URLs e names forja.* inalterados. Só o /team-mcp/install segue aqui.

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /team-mcp/install
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\TeamMcp\Http\Controllers',
        'prefix'     => 'team-mcp/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('team-mcp.install.index');
        Route::post('/',         'InstallController@install')->name('team-mcp.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('team-mcp.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('team-mcp.install.update');
    }
);
