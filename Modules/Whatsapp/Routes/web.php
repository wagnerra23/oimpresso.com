<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\InstallController;
use Modules\Whatsapp\Http\Controllers\Admin\ChannelsController;
use Modules\Whatsapp\Http\Controllers\Admin\ConversationsController;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Http\Controllers\Admin\TemplatesController;
use Modules\Whatsapp\Http\Controllers\Admin\SettingsController;

/*
|--------------------------------------------------------------------------
| Whatsapp — rotas web
|--------------------------------------------------------------------------
|
| Decisão arquitetural mãe: ADR 0096
| - Z-API/Baileys = driver default (ZapiDriver — Lote 2b)
| - Meta Cloud = fallback obrigatório (MetaCloudDriver — Lote 2b)
| - Evolution API = PROIBIDO Tier 0
|
| Lote 2a (este arquivo): scaffold + 3 rotas Install + 3 rotas admin placeholder.
| Lote 2b (próximo): FormRequest wizard 2 passos + Drivers + DriverFactory.
| Lote 2c: Inertia pages Cockpit pattern + webhook controllers.
|
| @see memory/requisitos/Whatsapp/SPEC.md
| @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md (3 rotas Install obrigatórias)
*/

// Rotas de instalação 1-click (via /manage-modules → botão Install)
// Pattern: ADR 0024 / feedback_pattern_install_modulos
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('whatsapp')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// Rotas admin (placeholder Lote 2a; Inertia pages em Lote 2c)
Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'whatsapp',
], function () {
    Route::get('/conversations', [ConversationsController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('whatsapp.conversations.index');

    Route::get('/conversations/{id}', [ConversationsController::class, 'show'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.access')
        ->name('whatsapp.conversations.show');

    Route::post('/conversations/{id}/send', [ConversationsController::class, 'send'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('whatsapp.conversations.send');

    Route::patch('/conversations/{id}', [ConversationsController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('whatsapp.conversations.update_status');

    Route::get('/templates', [TemplatesController::class, 'index'])
        ->middleware('can:whatsapp.templates.manage')
        ->name('whatsapp.templates.index');

    Route::post('/templates/sync-meta', [TemplatesController::class, 'syncMeta'])
        ->middleware('can:whatsapp.templates.manage')
        ->name('whatsapp.templates.sync_meta');

    Route::post('/templates', [TemplatesController::class, 'store'])
        ->middleware('can:whatsapp.templates.manage')
        ->name('whatsapp.templates.store');

    Route::get('/settings', [SettingsController::class, 'show'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.show');

    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.update');
});

// Omnichannel routes (ADR 0135 Fase 0) — coexistem com /whatsapp/settings legacy
// até refactor drivers em PR seguinte. Permission reusa whatsapp.settings.manage.
Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'atendimento',
], function () {
    // Inbox omnichannel — lê schema novo (Channel + Conversation + Message)
    Route::get('/inbox', [InboxController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.inbox.index');

    Route::post('/inbox/{id}/send', [InboxController::class, 'send'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.send');

    Route::patch('/inbox/{id}', [InboxController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.update_status');

    // US-WA-063: sync tags da conversa
    Route::patch('/inbox/{id}/tags', [InboxController::class, 'updateTags'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.update_tags');

    Route::get('/canais', [ChannelsController::class, 'index'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.index');

    Route::post('/canais', [ChannelsController::class, 'store'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.store');

    Route::delete('/canais/{id}', [ChannelsController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.destroy');

    Route::post('/canais/{id}/connect', [ChannelsController::class, 'connect'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.connect');

    Route::get('/canais/{id}/status', [ChannelsController::class, 'status'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.status');
});
