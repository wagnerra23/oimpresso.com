<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do módulo PontoWr2
|--------------------------------------------------------------------------
|
| Padrão UltimatePOS (ref.: Modules/Jana/Http/routes.php).
| Um único arquivo agrupa rotas web e API via Route::group com middleware
| stack do UltimatePOS: web, SetSessionData, auth, language, timezone,
| AdminSidebarMenu, CheckUserLogin.
|
| API usa Passport (`auth:api`), não Sanctum, para casar com o core.
|
*/

// ===========================================================================
// 1) Rotas web (admin / UI) — prefixo /ponto
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'ponto.access'],
        'prefix'     => 'ponto',
        'namespace'  => 'Modules\PontoWr2\Http\Controllers',
    ],
    function () {
        // 1. Dashboard
        Route::get('/', 'DashboardController@index')->name('ponto.dashboard');

        // 2. Espelho de Ponto
        Route::get('/espelho', 'EspelhoController@index')->name('ponto.espelho.index');
        Route::get('/espelho/{colaborador}', 'EspelhoController@show')->name('ponto.espelho.show');
        Route::get('/espelho/{colaborador}/imprimir', 'EspelhoController@imprimir')->name('ponto.espelho.imprimir');

        // 3. Aprovações
        Route::get('/aprovacoes', 'AprovacaoController@index')->name('ponto.aprovacoes.index');
        Route::post('/aprovacoes/{id}/aprovar', 'AprovacaoController@aprovar')->name('ponto.aprovacoes.aprovar');
        Route::post('/aprovacoes/{id}/rejeitar', 'AprovacaoController@rejeitar')->name('ponto.aprovacoes.rejeitar');
        Route::post('/aprovacoes/lote', 'AprovacaoController@aprovarEmLote')->name('ponto.aprovacoes.lote');

        // 4. Intercorrências
        Route::resource('/intercorrencias', 'IntercorrenciaController')->names([
            'index'   => 'ponto.intercorrencias.index',
            'create'  => 'ponto.intercorrencias.create',
            'store'   => 'ponto.intercorrencias.store',
            'show'    => 'ponto.intercorrencias.show',
            'edit'    => 'ponto.intercorrencias.edit',
            'update'  => 'ponto.intercorrencias.update',
            'destroy' => 'ponto.intercorrencias.destroy',
        ]);
        Route::post('/intercorrencias/{id}/submeter', 'IntercorrenciaController@submeter')->name('ponto.intercorrencias.submeter');
        Route::post('/intercorrencias/{id}/cancelar', 'IntercorrenciaController@cancelar')->name('ponto.intercorrencias.cancelar');

        // 5. Banco de Horas
        Route::get('/banco-horas', 'BancoHorasController@index')->name('ponto.banco-horas.index');
        Route::get('/banco-horas/{colaborador}', 'BancoHorasController@show')->name('ponto.banco-horas.show');
        Route::post('/banco-horas/{colaborador}/ajuste', 'BancoHorasController@ajustarManual')->name('ponto.banco-horas.ajuste');

        // 6. Escalas
        Route::resource('/escalas', 'EscalaController')->names([
            'index'   => 'ponto.escalas.index',
            'create'  => 'ponto.escalas.create',
            'store'   => 'ponto.escalas.store',
            'show'    => 'ponto.escalas.show',
            'edit'    => 'ponto.escalas.edit',
            'update'  => 'ponto.escalas.update',
            'destroy' => 'ponto.escalas.destroy',
        ]);

        // 7. Importações
        Route::get('/importacoes', 'ImportacaoController@index')->name('ponto.importacoes.index');
        Route::get('/importacoes/novo', 'ImportacaoController@create')->name('ponto.importacoes.create');
        Route::post('/importacoes', 'ImportacaoController@store')->name('ponto.importacoes.store');
        Route::get('/importacoes/{id}', 'ImportacaoController@show')->name('ponto.importacoes.show');
        Route::get('/importacoes/{id}/original', 'ImportacaoController@baixarOriginal')->name('ponto.importacoes.original');

        // 8. Relatórios
        Route::get('/relatorios', 'RelatorioController@index')->name('ponto.relatorios.index');
        Route::get('/relatorios/{chave}', 'RelatorioController@gerar')->name('ponto.relatorios.gerar');

        // 9. Colaboradores
        Route::get('/colaboradores', 'ColaboradorController@index')->name('ponto.colaboradores.index');
        Route::get('/colaboradores/{id}/editar', 'ColaboradorController@edit')->name('ponto.colaboradores.edit');
        Route::put('/colaboradores/{id}', 'ColaboradorController@update')->name('ponto.colaboradores.update');

        // 10. Configurações
        Route::get('/configuracoes', 'ConfiguracaoController@index')->name('ponto.configuracoes.index');
        Route::get('/configuracoes/reps', 'ConfiguracaoController@reps')->name('ponto.configuracoes.reps');
        Route::post('/configuracoes/reps', 'ConfiguracaoController@storeRep')->name('ponto.configuracoes.reps.store');
    }
);

// ===========================================================================
// 2) Rotas API (REP-P mobile e integrações) — prefixo /ponto/api
// ===========================================================================
// Usa Passport (auth:api) para casar com o padrão UltimatePOS (ver Jana).
Route::group(
    [
        'middleware' => ['auth:api', 'timezone'],
        'prefix'     => 'ponto/api',
        'namespace'  => 'Modules\PontoWr2\Http\Controllers\Api',
    ],
    function () {
        // Marcação (REP-P mobile)
        Route::post('/marcar', function () { abort(501, 'Implementar em MarcacaoApiController::marcar'); });
        Route::get('/marcacoes/hoje', function () { abort(501); });
        Route::get('/saldo', function () { abort(501, 'Saldo banco de horas do usuário autenticado'); });

        // Intercorrências
        Route::get('/intercorrencias', function () { abort(501); });
        Route::post('/intercorrencias', function () { abort(501); });

        // Escala e dashboard
        Route::get('/escala/hoje', function () { abort(501); });
        Route::get('/dashboard/kpis', function () { abort(501); });
    }
);

// ===========================================================================
// 3) Rotas de instalação/manutenção — acessíveis só por superadmin
// ===========================================================================
Route::group(
    [
        'middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
        'namespace'  => 'Modules\PontoWr2\Http\Controllers',
        'prefix'     => 'ponto/install',
    ],
    function () {
        Route::get('/', 'InstallController@index')->name('ponto.install.index');
        Route::post('/', 'InstallController@install')->name('ponto.install.run');
        Route::get('/uninstall', 'InstallController@uninstall')->name('ponto.install.uninstall');
        Route::get('/update', 'InstallController@update')->name('ponto.install.update');
    }
);
