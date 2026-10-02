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
    Route::post('/tarefas/todo/{id}/concluir', [\App\Http\Controllers\Api\App\TarefasController::class, 'concluirTodo'])->whereNumber('id')->name('tarefas.todo.concluir');
    Route::get('/pessoas', [\App\Http\Controllers\Api\App\PessoasController::class, 'index'])->name('pessoas.index');
    Route::get('/pessoas/{id}', [\App\Http\Controllers\Api\App\PessoasController::class, 'show'])->whereNumber('id')->name('pessoas.show');
    Route::get('/pedidos', [\App\Http\Controllers\Api\App\PedidosController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/{id}', [\App\Http\Controllers\Api\App\PedidosController::class, 'show'])->whereNumber('id')->name('pedidos.show');
    Route::get('/producao', [\App\Http\Controllers\Api\App\PedidosController::class, 'producao'])->name('producao.index');
    Route::get('/notificacoes', [\App\Http\Controllers\Api\App\NotificacoesController::class, 'index'])->name('notificacoes.index');
    Route::get('/inicio', [\App\Http\Controllers\Api\App\InicioController::class, 'show'])->name('inicio');
});
