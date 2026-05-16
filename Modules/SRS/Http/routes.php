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

// D8.a Security — throttle:60,1 (60 req/min por user) em todas as rotas SRS.
// SRS é tool interna Wagner (uso raro), mas throttle defense-in-depth
// previne loop runaway no chat (pode chamar OpenAI = custo $) e flood
// no ingest (upload 20MB). Stack middleware UltimatePOS preservada.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'throttle:60,1'])->group(function () {
    Route::prefix('memcofre')->group(function () {
        // Dashboard
        Route::get('/', [\Modules\SRS\Http\Controllers\DashboardController::class, 'index'])->name('memcofre.dashboard');

        // Ingest (upload de evidências)
        Route::get('/ingest', [\Modules\SRS\Http\Controllers\IngestController::class, 'show'])->name('memcofre.ingest');
        Route::post('/ingest', [\Modules\SRS\Http\Controllers\IngestController::class, 'store'])->name('memcofre.ingest.store');

        // Inbox (classificar evidências)
        Route::get('/inbox', [\Modules\SRS\Http\Controllers\InboxController::class, 'index'])->name('memcofre.inbox');
        Route::post('/inbox/{evidence}/triage', [\Modules\SRS\Http\Controllers\InboxController::class, 'triage'])->name('memcofre.inbox.triage');
        Route::post('/inbox/{evidence}/apply', [\Modules\SRS\Http\Controllers\InboxController::class, 'apply'])->name('memcofre.inbox.apply');
        Route::delete('/inbox/{evidence}', [\Modules\SRS\Http\Controllers\InboxController::class, 'destroy'])->name('memcofre.inbox.destroy');

        // Módulo (ver requisitos consolidados)
        Route::get('/modulos/{module}', [\Modules\SRS\Http\Controllers\ModuloController::class, 'show'])->name('memcofre.modulo');

        // Memória unificada (CLAUDE.md + memory/ + ~/.claude/.../memory/)
        Route::get('/memoria', [\Modules\SRS\Http\Controllers\MemoriaController::class, 'index'])->name('memcofre.memoria');
        Route::get('/memoria/file', [\Modules\SRS\Http\Controllers\MemoriaController::class, 'file'])->name('memcofre.memoria.file');

        // Chat assistente (pergunte pro conhecimento do MemCofre)
        Route::get('/chat', [\Modules\SRS\Http\Controllers\ChatController::class, 'index'])->name('memcofre.chat');
        Route::post('/chat/ask', [\Modules\SRS\Http\Controllers\ChatController::class, 'ask'])->name('memcofre.chat.ask');
        Route::post('/chat/new', [\Modules\SRS\Http\Controllers\ChatController::class, 'newSession'])->name('memcofre.chat.new');

    });
});

// ===========================================================================
// Install (padrão UltimatePOS) — prefixo /srs/install (canônico após rename)
// ===========================================================================
// Fix da regressão do PR #97 (Fase 3.7 PR-2): tinha só 1 rota Install no
// prefixo /memcofre — botão em /manage-modules ficava com action() apontando
// pra nome antigo. Agora 4 rotas no prefixo novo /srs/install + 301 das
// URLs antigas pra compat de bookmarks.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'throttle:60,1'])->group(function () {
    Route::prefix('srs/install')->group(function () {
        Route::get('/',           [\Modules\SRS\Http\Controllers\InstallController::class, 'index'])->name('srs.install.index');
        Route::post('/',          [\Modules\SRS\Http\Controllers\InstallController::class, 'install'])->name('srs.install.run');
        Route::get('/uninstall',  [\Modules\SRS\Http\Controllers\InstallController::class, 'uninstall'])->name('srs.install.uninstall');
        Route::get('/update',     [\Modules\SRS\Http\Controllers\InstallController::class, 'update'])->name('srs.install.update');
    });
});

// 301 das URLs antigas /memcofre/install/* → /srs/install/* (compat bookmarks)
Route::redirect('/memcofre/install',           '/srs/install',           301);
Route::redirect('/memcofre/install/uninstall', '/srs/install/uninstall', 301);
Route::redirect('/memcofre/install/update',    '/srs/install/update',    301);
