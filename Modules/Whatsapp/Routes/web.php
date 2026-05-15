<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\InstallController;
use Modules\Whatsapp\Http\Controllers\Admin\CaixaUnificadaController;
use Modules\Whatsapp\Http\Controllers\Admin\ChannelsController;
use Modules\Whatsapp\Http\Controllers\Admin\CsatController;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Http\Controllers\Admin\MacrosController;
use Modules\Whatsapp\Http\Controllers\Admin\MacroVariantsController;
use Modules\Whatsapp\Http\Controllers\Admin\MetricsController;
use Modules\Whatsapp\Http\Controllers\Admin\TemplatesController;
use Modules\Whatsapp\Http\Controllers\Admin\SettingsController;
use Modules\Whatsapp\Http\Controllers\Api\CustomerProfileController;
use Modules\Whatsapp\Http\Controllers\Api\EmployeeScorecardController;

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

// Rotas admin (placeholder Lote 2a; Inertia pages em Lote 2c).
//
// US-WA-091 (Wagner 2026-05-11 ordem): rotas legacy `/whatsapp/conversations*`
// REMOVIDAS. `/atendimento/inbox` (ADR 0135 schema novo polimórfico) é o
// caminho único. Schema legacy `whatsapp_conversations`/`whatsapp_messages`
// continua existindo no DB pra histórico (2 convs do biz=1 testes), mas
// SEM UI. Webhooks Zapi/Meta legacy seguem populando whatsapp_messages
// até refactor drivers pro Channel polimórfico (PR seguinte).
Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'whatsapp',
], function () {
    Route::get('/templates', [TemplatesController::class, 'index'])
        ->middleware('can:whatsapp.templates.manage')
        ->name('whatsapp.templates.index');

    Route::post('/templates/sync-meta', [TemplatesController::class, 'syncMeta'])
        ->middleware('can:whatsapp.templates.manage')
        ->name('whatsapp.templates.sync_meta');

    Route::post('/templates', [TemplatesController::class, 'store'])
        ->middleware('can:whatsapp.templates.manage')
        ->name('whatsapp.templates.store');

    // US-WA-070: rota legacy `/whatsapp/settings` → 301 pra
    // `/atendimento/canais/jana-templates`. Mantém compat de bookmark e
    // qualquer middleware/handler externo que ainda aponte pra cá.
    Route::get('/settings', fn () => redirect()->route('atendimento.canais.jana_templates.show', [], 301))
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.show');

    Route::put('/settings', fn () => redirect()->route('atendimento.canais.jana_templates.show', [], 301))
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.update');
});

// Omnichannel routes (ADR 0135 Fase 0) — coexistem com /whatsapp/settings legacy
// até refactor drivers em PR seguinte. Permission reusa whatsapp.settings.manage.
Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'atendimento',
], function () {
    // CUTOVER 2026-05-15: GET /inbox redireciona pra Caixa Unificada V4 (301 permanent).
    // Inventário §6 do INVENTARIO-CUTOVER-CAIXA-UNIFICADA-V4.md.
    // - Route name `atendimento.inbox.index` preservado (compat Pest + frontend legacy)
    // - Query string preserved automaticamente pelo Laravel Route::redirect
    // - Sub-rotas POST/PATCH (/inbox/{id}/send, /inbox/{id}/tags, etc) PERMANECEM
    //   intactas — Caixa Unificada V4 reusa todos os endpoints (sem duplicar contrato)
    // - Charter Inbox/Index.charter.md vira lifecycle: historical no mesmo PR
    // - Pages/Atendimento/Inbox/ removida em F6 (PR seguinte, 1 sprint depois)
    Route::redirect('/inbox', '/atendimento/caixa-unificada', 301)
        ->name('atendimento.inbox.index');

    // US-WA-VOZ-001 — Customer Profile (sidebar Customer 360).
    // GET /atendimento/customer/{external_id}/profile → JSON memória persistente
    // do cliente final (stats, identidade Contact CRM, conversas recentes, LGPD).
    // Sidebar React consome via Inertia::defer pra não bloquear abertura conv.
    Route::get('/customer/{external_id}/profile', [CustomerProfileController::class, 'show'])
        ->where('external_id', '[0-9+]+')
        ->middleware('can:whatsapp.access')
        ->name('atendimento.customer.profile');

    // US-WA-VOZ-003 — Employee Scorecard (perfil do atendente).
    // GET /atendimento/employee/scorecards              → lista ranking time
    // GET /atendimento/employee/{user_identifier}/scorecard → 1 atendente
    //   user_identifier formato: "42" (user_id real) OU "heur:Maiara" (heurístico)
    Route::get('/employee/scorecards', [EmployeeScorecardController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.employee.scorecards');
    Route::get('/employee/{user_identifier}/scorecard', [EmployeeScorecardController::class, 'show'])
        ->where('user_identifier', '[0-9]+|heur:[A-Za-zÀ-ÿ]+')
        ->middleware('can:whatsapp.access')
        ->name('atendimento.employee.scorecard');

    // Caixa Unificada V4 — substituiu /atendimento/inbox no cutover 2026-05-15.
    // Fonte visual canônica: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
    Route::get('/caixa-unificada', [CaixaUnificadaController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.caixa-unificada.index');

    Route::post('/inbox/{id}/send', [InboxController::class, 'send'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.send');

    // US-WA-072: upload de mídia outbound (image/audio/document/video)
    Route::post('/inbox/{id}/send-media', [InboxController::class, 'sendMedia'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.send_media');

    // US-WA-045b: envio de mensagem interativa (HSM buttons / list / cta_url).
    // Path com prefix `conversations/{id}` pra consistência com a chamada UI
    // (frontend usa `/inbox/conversations/{id}/send-interactive`).
    Route::post('/inbox/conversations/{id}/send-interactive', [InboxController::class, 'sendInteractive'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.send_interactive');

    Route::patch('/inbox/{id}', [InboxController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.update_status');

    // US-WA-063: sync tags da conversa
    Route::patch('/inbox/{id}/tags', [InboxController::class, 'updateTags'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.update_tags');

    // US-WA-064: busca de Contacts UltimatePOS (debounced search modal)
    Route::get('/inbox/contacts/search', [InboxController::class, 'searchContacts'])
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.contacts.search');

    // US-WA-064: vincula/desvincula Contact à Conversation
    Route::patch('/inbox/{id}/contact', [InboxController::class, 'linkContact'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.link_contact');
    // US-WA-078: cria Contact UltimatePOS a partir do phone da conversa + linka
    Route::post('/inbox/{id}/contact/create-from-phone', [InboxController::class, 'createContactFromPhone'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.contact.create_from_phone');
    // US-WA-066: bloquear/desbloquear contato (toggle is_blocked + daemon Baileys)
    Route::patch('/inbox/{id}/block', [InboxController::class, 'blockContact'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.block');

    Route::get('/canais', [ChannelsController::class, 'index'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.index');

    Route::post('/canais', [ChannelsController::class, 'store'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.store');

    // US-WA-068: Page Detail do canal + tabs (Config | Usuários | Histórico)
    Route::get('/canais/{id}', [ChannelsController::class, 'show'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.show');

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

    // Wagner request 2026-05-14: importar histórico ~90d retroativo gated por
    // feature flag por business_id (`config('whatsapp.history_import.enabled_business_ids')`).
    // Endpoint valida de novo no Controller — defense-in-depth.
    Route::post('/canais/{id}/import-history', [ChannelsController::class, 'importHistory'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.import-history');

    // US-WA-068: ACL atendente↔canal (grant/revoke)
    Route::post('/canais/{id}/users', [ChannelsController::class, 'grantUser'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.users.grant');

    Route::delete('/canais/{id}/users/{userId}', [ChannelsController::class, 'revokeUser'])
        ->whereNumber('id')
        ->whereNumber('userId')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.users.revoke');

    // US-WA-070: Templates HSM + toggle Bot Jana — herda Controller
    // SettingsController pós-067 (já enxuto: só 5 campos bot_enabled +
    // 4 templates). Substitui legacy /whatsapp/settings.
    Route::get('/canais/jana-templates', [SettingsController::class, 'show'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.canais.jana_templates.show');

    Route::put('/canais/jana-templates', [SettingsController::class, 'update'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.canais.jana_templates.update');

    // US-WA-048 — Macros (quick replies + automation actions, Chatwoot pattern).
    // CRUD na tela settings + endpoint JSON pra dropdown do composer + apply.
    // Permission CRUD = settings.manage (config); apply = whatsapp.send (operacional).
    Route::get('/macros', [MacrosController::class, 'index'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.index');
    Route::get('/macros/list', [MacrosController::class, 'list'])
        ->middleware('can:whatsapp.send')
        ->name('atendimento.macros.list');
    Route::post('/macros', [MacrosController::class, 'store'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.store');
    Route::put('/macros/{id}', [MacrosController::class, 'update'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.update');
    Route::delete('/macros/{id}', [MacrosController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.destroy');
    Route::post('/inbox/conversations/{id}/apply-macro/{macroId}', [MacrosController::class, 'apply'])
        ->whereNumber('id')->whereNumber('macroId')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.apply_macro');

    // US-WA-049 — Variantes A/B de Macros (gap P2 #18, Take Blip pattern).
    // CRUD nested em macro_id. Sorteio ponderado por weight + tracking
    // response em 24h pra calcular response_rate por variante.
    Route::get('/macros/{macro}/variants', [MacroVariantsController::class, 'index'])
        ->whereNumber('macro')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.variants.index');
    Route::post('/macros/{macro}/variants', [MacroVariantsController::class, 'store'])
        ->whereNumber('macro')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.variants.store');
    Route::put('/macros/{macro}/variants/{variant}', [MacroVariantsController::class, 'update'])
        ->whereNumber('macro')->whereNumber('variant')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.variants.update');
    Route::delete('/macros/{macro}/variants/{variant}', [MacroVariantsController::class, 'destroy'])
        ->whereNumber('macro')->whereNumber('variant')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.variants.destroy');
    Route::post('/macros/{macro}/variants/{variant}/mark-winner', [MacroVariantsController::class, 'markWinner'])
        ->whereNumber('macro')->whereNumber('variant')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.macros.variants.mark_winner');

    // US-WA-021/041 (CYCLE-07 PR-3) — Dashboard métricas omnichannel
    // (gap P0 #4 do COMPARATIVO-MERCADO-2026-05-12). Lê snapshot diário
    // agregado em `whatsapp_conversation_metricas` (cron 02:30 BRT).
    Route::get('/metricas', [MetricsController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.metricas.index');

    // PR-6 CYCLE-07 — Dashboard CSAT (pesquisa pós-resolução).
    // Reusa permissão `whatsapp.access` (mesma do Inbox — quem acessa atendimento
    // pode ver indicadores agregados de satisfação do próprio business).
    Route::get('/csat', [CsatController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.csat.index');
});
