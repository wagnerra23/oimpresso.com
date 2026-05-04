<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo KB (Knowledge Base)
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/Copiloto/Http/routes.php).
| Etapa 2 da modularização — split do Copiloto. Rotas migradas de
| /copiloto/admin/memoria* para /kb*. Redirects 301 ficam no Copiloto.
|
*/

// ===========================================================================
// 1) Rotas web — prefixo /kb
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'kb',
        'namespace'  => 'Modules\KB\Http\Controllers',
    ],
    function () {
        // ---- KB browser dos docs servidos via MCP server (MEM-KB-1, ADR 0053) ----
        Route::get('/',                      'KbController@index')->name('kb.index');
        Route::get('/{slug}/show',           'KbController@show')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.show');
        Route::get('/{slug}/history',        'KbController@history')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.history');
        Route::delete('/{slug}',             'KbController@softDelete')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.softdelete');
        Route::post('/{slug}/restore',       'KbController@restore')
            ->where('slug', '[A-Za-z0-9\-_]+')
            ->name('kb.restore');
    }
);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /kb/install
// ===========================================================================
// Padrão BaseModuleInstallController + ADR memory/decisions/0023.
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\KB\Http\Controllers',
        'prefix'     => 'kb/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('kb.install.index');
        Route::post('/',         'InstallController@install')->name('kb.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('kb.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('kb.install.update');
    }
);
