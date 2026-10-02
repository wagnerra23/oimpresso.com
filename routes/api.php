<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// App das lojas (oimpresso-app, telas próprias — decisão [W] 2026-10-01, D5/D13). Token Passport
// do próprio app; tenant = business do usuário do token. Contrato:
// memory/requisitos/AppMobile/API-CONTRATO-v1.md.
Route::middleware('auth:api')->prefix('app')->name('app.')->group(function () {
    Route::get('/tarefas', [\App\Http\Controllers\Api\App\TarefasController::class, 'index'])->name('tarefas.index');
    Route::get('/tarefas/todo/{id}', [\App\Http\Controllers\Api\App\TarefasController::class, 'todo'])->whereNumber('id')->name('tarefas.todo.show');
    Route::post('/tarefas/todo/{id}/concluir', [\App\Http\Controllers\Api\App\TarefasController::class, 'concluirTodo'])->whereNumber('id')->name('tarefas.todo.concluir');
    Route::get('/pessoas', [\App\Http\Controllers\Api\App\PessoasController::class, 'index'])->name('pessoas.index');
    Route::post('/pessoas', [\App\Http\Controllers\Api\App\PessoasController::class, 'store'])->name('pessoas.store');
    Route::get('/pessoas/{id}', [\App\Http\Controllers\Api\App\PessoasController::class, 'show'])->whereNumber('id')->name('pessoas.show');
    Route::patch('/pessoas/{id}', [\App\Http\Controllers\Api\App\PessoasController::class, 'update'])->whereNumber('id')->name('pessoas.update');
    Route::get('/pessoas/{id}/cadastro', [\App\Http\Controllers\Api\App\PessoasController::class, 'cadastro'])->whereNumber('id')->name('pessoas.cadastro');
    Route::get('/pedidos', [\App\Http\Controllers\Api\App\PedidosController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/{id}', [\App\Http\Controllers\Api\App\PedidosController::class, 'show'])->whereNumber('id')->name('pedidos.show');
    Route::get('/orcamentos', [\App\Http\Controllers\Api\App\PedidosController::class, 'orcamentos'])->name('orcamentos.index');
    Route::get('/producao', [\App\Http\Controllers\Api\App\PedidosController::class, 'producao'])->name('producao.index');
    Route::get('/notificacoes', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'index'])->name('notificacoes.index');
    Route::post('/notificacoes/lidas', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'marcarTodasLidas'])->name('notificacoes.lidas');
    Route::post('/notificacoes/{id}/lida', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'marcarLida'])->whereUuid('id')->name('notificacoes.lida');
    // Throttle como o lookup da web (60/min): o ERP fala com o ViaCEP por todos os tenants.
    Route::get('/cep/{cep}', [\App\Http\Controllers\Api\App\CepController::class, 'show'])
        ->where('cep', '[0-9-]{1,12}')->middleware('throttle:60,1')->name('cep.show');
    Route::put('/perfil-menu', [\App\Http\Controllers\Api\App\PerfilMenuController::class, 'update'])->name('perfil-menu.update');
    Route::get('/produtos', [\App\Http\Controllers\Api\App\ProdutosController::class, 'produtos'])->name('produtos.index');
    Route::get('/inicio', [\App\Http\Controllers\Api\App\InicioController::class, 'show'])->name('inicio');
    Route::get('/os', [\App\Http\Controllers\Api\App\OficinaController::class, 'index'])->name('os.index');
});
