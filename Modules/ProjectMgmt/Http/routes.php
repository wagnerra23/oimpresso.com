<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo ProjectMgmt
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/TeamMcp/Http/routes.php).
| Promovido a módulo próprio em 2026-05-04 (ADR 0070) — Jira-style PM
| sobre tabelas mcp_jira_projects/epics/cycles/tasks.
|
| Permissões herdadas do Copiloto (`copiloto.mcp.usage.all`) — mesmo padrão
| do TeamMcp pra evitar quebrar usuários com setup atual.
|
*/

// ===========================================================================
// 1) Rotas web — prefixo /project-mgmt
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'project-mgmt',
        'namespace'  => 'Modules\ProjectMgmt\Http\Controllers',
    ],
    function () {
        // Default: redireciona pra /project-mgmt/board
        Route::get('/', function () {
            return redirect()->route('project-mgmt.board.index');
        })->name('project-mgmt.index');

        // ---- Board (Kanban) — US-TR-201 ------------------------------------
        Route::get('/board', 'BoardController@index')
            ->name('project-mgmt.board.index');

        Route::patch('/board/{taskId}/status', 'BoardController@updateStatus')
            ->where('taskId', '[A-Z0-9\-]+')
            ->name('project-mgmt.board.update-status');
    }
);

// ===========================================================================
// 2) Rotas de instalação 1-clique — prefixo /project-mgmt/install
// ===========================================================================
// Padrão BaseModuleInstallController + ADR 0024.
// As 3 rotas abaixo são OBRIGATÓRIAS — sem elas o botão Install em
// /manage-modules fica sem ação (action() → '#').
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\ProjectMgmt\Http\Controllers',
        'prefix'     => 'project-mgmt/install',
    ],
    function () {
        Route::get('/',          'InstallController@index')->name('project-mgmt.install.index');
        Route::post('/',         'InstallController@install')->name('project-mgmt.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('project-mgmt.install.uninstall');
        Route::get('/update',    'InstallController@update')->name('project-mgmt.install.update');
    }
);
