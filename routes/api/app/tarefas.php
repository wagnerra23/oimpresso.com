<?php

use Illuminate\Support\Facades\Route;

// Carregado dentro do grupo auth:api + prefixo app + nome app. de routes/api.php.
// Um arquivo por área do app, para cada PR tocar só o seu (contrato: memory/requisitos/AppMobile/).

Route::get('/tarefas', [\App\Http\Controllers\Api\App\TarefasController::class, 'index'])->name('tarefas.index');
Route::get('/tarefas/todo/{id}', [\App\Http\Controllers\Api\App\TarefasController::class, 'todo'])->whereNumber('id')->name('tarefas.todo.show');
Route::post('/tarefas/todo/{id}/concluir', [\App\Http\Controllers\Api\App\TarefasController::class, 'concluirTodo'])->whereNumber('id')->name('tarefas.todo.concluir');
