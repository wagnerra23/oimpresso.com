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

// ===========================================================================
// 1b) Forja — cockpit do cowork loop (Onda Forja). Prefixo /forja (segmento
//     PRÓPRIO: useAutoModuleNav casa o topnav por 1º segmento, e /team-mcp já é
//     do hub Equipe — colidiria). Controller mora aqui no TeamMcp (absorção, não
//     módulo novo). Permissão jana.mcp.usage.all (superadmin), igual Scorecard.
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'prefix'     => 'forja',
        'namespace'  => 'Modules\TeamMcp\Http\Controllers',
    ],
    function () {
        Route::get('/',          'ForjaController@triagem')->name('forja.triagem');
        Route::get('/backlog',   'ForjaController@backlog')->name('forja.backlog');
        Route::get('/quadro',    'ForjaController@quadro')->name('forja.quadro');
        Route::get('/changelog', 'ForjaController@changelog')->name('forja.changelog');
        Route::get('/mcp',       'ForjaController@mcp')->name('forja.mcp');
        // Saúde foi fundida no Scorecard real (/team-mcp/scorecard) — sem rota própria.

        // PR-7b (ADR 0283 · Fase 2) — levers do loop de handoff (re-disparar/devolver/
        // supersede) dos botões da aba MCP. Mesma mutação governada do tool MCP
        // handoff-lever (HandoffLeverService é a fonte única). 3 segmentos → não
        // colide com /{taskId}/* (2 segmentos).
        Route::post('/handoff/{slug}/lever', 'ForjaController@handoffLever')
            ->where('slug', '[A-Za-z0-9_\-]+')->name('forja.handoff.lever');

        // Triagem (aba 1) — dossiê do Analista (read-only) + ações [W] aprova.
        // Espelha /project-mgmt/triage/{id}/{dossier,aprovar,rejeitar,fundir} (PR-5a).
        // taskId aceita FORJA-150/identifier ou US-XXX legacy.
        Route::get('/{taskId}/dossier',   'ForjaController@dossier')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.dossier');
        Route::post('/{taskId}/aprovar',  'ForjaController@aprovar')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.aprovar');
        Route::post('/{taskId}/rejeitar', 'ForjaController@rejeitar')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.rejeitar');
        Route::post('/{taskId}/fundir',   'ForjaController@fundir')
            ->where('taskId', '[A-Za-z0-9_\-]+')->name('forja.fundir');
    }
);

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
