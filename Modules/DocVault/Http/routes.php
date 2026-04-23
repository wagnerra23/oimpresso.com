<?php

/*
|--------------------------------------------------------------------------
| DocVault Module Routes
|--------------------------------------------------------------------------
|
| Todas as rotas dentro do prefixo /docs. Segue padrão UltimatePOS
| (middleware stack do admin, auth, session).
|
*/

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    Route::prefix('docs')->group(function () {
        // Dashboard
        Route::get('/', [\Modules\DocVault\Http\Controllers\DashboardController::class, 'index'])->name('docvault.dashboard');

        // Ingest (upload de evidências)
        Route::get('/ingest', [\Modules\DocVault\Http\Controllers\IngestController::class, 'show'])->name('docvault.ingest');
        Route::post('/ingest', [\Modules\DocVault\Http\Controllers\IngestController::class, 'store'])->name('docvault.ingest.store');

        // Inbox (classificar evidências)
        Route::get('/inbox', [\Modules\DocVault\Http\Controllers\InboxController::class, 'index'])->name('docvault.inbox');
        Route::post('/inbox/{evidence}/triage', [\Modules\DocVault\Http\Controllers\InboxController::class, 'triage'])->name('docvault.inbox.triage');
        Route::post('/inbox/{evidence}/apply', [\Modules\DocVault\Http\Controllers\InboxController::class, 'apply'])->name('docvault.inbox.apply');
        Route::delete('/inbox/{evidence}', [\Modules\DocVault\Http\Controllers\InboxController::class, 'destroy'])->name('docvault.inbox.destroy');

        // Módulo (ver requisitos consolidados)
        Route::get('/modulos/{module}', [\Modules\DocVault\Http\Controllers\ModuloController::class, 'show'])->name('docvault.modulo');

        // Chat assistente (pergunte pro conhecimento do DocVault)
        Route::get('/chat', [\Modules\DocVault\Http\Controllers\ChatController::class, 'index'])->name('docvault.chat');
        Route::post('/chat/ask', [\Modules\DocVault\Http\Controllers\ChatController::class, 'ask'])->name('docvault.chat.ask');
        Route::post('/chat/new', [\Modules\DocVault\Http\Controllers\ChatController::class, 'newSession'])->name('docvault.chat.new');

        // Install (padrão UltimatePOS)
        Route::get('/install', [\Modules\DocVault\Http\Controllers\InstallController::class, 'index'])->name('docvault.install');
    });
});
