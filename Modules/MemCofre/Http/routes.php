<?php

/*
|--------------------------------------------------------------------------
| MemCofre Module Routes
|--------------------------------------------------------------------------
|
| Todas as rotas dentro do prefixo /docs. Segue padrão UltimatePOS
| (middleware stack do admin, auth, session).
|
*/

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    Route::prefix('memcofre')->group(function () {
        // Dashboard
        Route::get('/', [\Modules\MemCofre\Http\Controllers\DashboardController::class, 'index'])->name('memcofre.dashboard');

        // Ingest (upload de evidências)
        Route::get('/ingest', [\Modules\MemCofre\Http\Controllers\IngestController::class, 'show'])->name('memcofre.ingest');
        Route::post('/ingest', [\Modules\MemCofre\Http\Controllers\IngestController::class, 'store'])->name('memcofre.ingest.store');

        // Inbox (classificar evidências)
        Route::get('/inbox', [\Modules\MemCofre\Http\Controllers\InboxController::class, 'index'])->name('memcofre.inbox');
        Route::post('/inbox/{evidence}/triage', [\Modules\MemCofre\Http\Controllers\InboxController::class, 'triage'])->name('memcofre.inbox.triage');
        Route::post('/inbox/{evidence}/apply', [\Modules\MemCofre\Http\Controllers\InboxController::class, 'apply'])->name('memcofre.inbox.apply');
        Route::delete('/inbox/{evidence}', [\Modules\MemCofre\Http\Controllers\InboxController::class, 'destroy'])->name('memcofre.inbox.destroy');

        // Módulo (ver requisitos consolidados)
        Route::get('/modulos/{module}', [\Modules\MemCofre\Http\Controllers\ModuloController::class, 'show'])->name('memcofre.modulo');

        // Memória unificada (CLAUDE.md + memory/ + ~/.claude/.../memory/)
        Route::get('/memoria', [\Modules\MemCofre\Http\Controllers\MemoriaController::class, 'index'])->name('memcofre.memoria');
        Route::get('/memoria/file', [\Modules\MemCofre\Http\Controllers\MemoriaController::class, 'file'])->name('memcofre.memoria.file');

        // Chat assistente (pergunte pro conhecimento do MemCofre)
        Route::get('/chat', [\Modules\MemCofre\Http\Controllers\ChatController::class, 'index'])->name('memcofre.chat');
        Route::post('/chat/ask', [\Modules\MemCofre\Http\Controllers\ChatController::class, 'ask'])->name('memcofre.chat.ask');
        Route::post('/chat/new', [\Modules\MemCofre\Http\Controllers\ChatController::class, 'newSession'])->name('memcofre.chat.new');

        // Install (padrão UltimatePOS)
        Route::get('/install', [\Modules\MemCofre\Http\Controllers\InstallController::class, 'index'])->name('memcofre.install');
    });
});
