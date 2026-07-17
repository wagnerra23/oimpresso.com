<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\InstallController;
use Modules\Whatsapp\Http\Controllers\Admin\BroadcastController;
use Modules\Whatsapp\Http\Controllers\Admin\CaixaUnificadaController;
use Modules\Whatsapp\Http\Controllers\Admin\ChannelsController;
use Modules\Whatsapp\Http\Controllers\Admin\CsatController;
use Modules\Whatsapp\Http\Controllers\Admin\ClientFeedbackController;
use Modules\Whatsapp\Http\Controllers\Admin\InboxAiController;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Http\Controllers\Admin\MacrosController;
use Modules\Whatsapp\Http\Controllers\Admin\MacroVariantsController;
use Modules\Whatsapp\Http\Controllers\Admin\MetricsController;
use Modules\Whatsapp\Http\Controllers\Admin\QueuesController;
use Modules\Whatsapp\Http\Controllers\Admin\TemplatesController;
use Modules\Whatsapp\Http\Controllers\Admin\SettingsController;
use Modules\Whatsapp\Http\Controllers\Api\CustomerProfileController;
use Modules\Whatsapp\Http\Controllers\Api\EmployeeScorecardController;
use Modules\Whatsapp\Http\Controllers\Publico\FeedbackFormController;

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

// ── Canal PÚBLICO de sinal do cliente (US-INFRA-002 · ADR 0105 · ADR 0334) ───────────
//
// O órgão sensor: a Larissa reporta a dor dela sem depender de o [W] ouvir no WhatsApp e
// clicar "Capturar". Sem auth de propósito — o cliente não tem login no nosso admin.
//
// Tier 0: o business NÃO vem do input, vem da URL assinada. O middleware `signed` valida
// HMAC (APP_KEY) sobre a URL inteira, então adulterar ?biz=4→1 dá 403. Expiração de 30d
// é nativa do temporarySignedRoute — sem tabela de token pra manter ou vazar.
//
// GET e POST compartilham path E assinatura: o HMAC do Laravel cobre a URL, não o método,
// então o form posta na mesma URL que abriu (1 link só, sem 2º token).
// throttle espelha o portal público do ConsultaOs (anti-abuso em rota sem auth).
//
// Link: `php artisan feedback:link {business_id}`
Route::prefix('feedback')->name('feedback.')->middleware('signed')->group(function () {
    Route::get('/', [FeedbackFormController::class, 'show'])
        ->name('form')
        ->middleware('throttle:30,1');

    Route::post('/', [FeedbackFormController::class, 'store'])
        ->name('submit')
        ->middleware('throttle:10,1');
});

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

    // US-WA-310 Fase 2 (ADR 0202) — `/whatsapp/settings` agora é wizard Embedded
    // Signup v4 (substituiu o 301 legacy de US-WA-070). Templates HSM continuam
    // em `/atendimento/canais/jana-templates`. Caminho `/whatsapp/settings`
    // mantém compat de bookmarks antigos.
    Route::get('/settings', [SettingsController::class, 'settings'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.show');

    Route::get('/settings/meta-oauth-init', [SettingsController::class, 'metaOauthInit'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.meta.oauth_init');

    Route::post('/settings/meta-embedded-callback', [SettingsController::class, 'metaEmbeddedCallback'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('whatsapp.settings.meta.embedded_callback');

    // Legacy PUT mantém redirect 301 — templates HSM ficaram em jana-templates.
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
    // Wagner 2026-05-22 (refaz #1392): rota raiz /atendimento → Caixa Unificada V4
    // (entry-point canon do hub Atendimento). Shortcut topo Sidebar.tsx aponta
    // /atendimento zero-hop em vez de /atendimento/inbox que faz 301.
    Route::get('/', [CaixaUnificadaController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.index');

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

    // M7 fix 2026-05-28 — serve mídia via Controller (Hostinger LiteSpeed
    // bloqueia /storage/* direct serve 403). Path tem 4 partes:
    // whatsapp/<businessId>/<YYYY-MM>/<uuid>.<ext>
    Route::get('/midia/{path}', [CaixaUnificadaController::class, 'serveMedia'])
        ->where('path', 'whatsapp/[0-9]+/[0-9]{4}-[0-9]{2}/[a-f0-9-]+(_thumb)?\.[a-z0-9]+')
        ->middleware('can:whatsapp.access')
        ->name('atendimento.midia.show');

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

    // US-WA-302: assignee picker — atribui conversa a operador específico
    // (updateStatus só aceita assigned_to_me boolean; este aceita qualquer
    // user do MESMO business — Tier 0 validado no Controller).
    Route::patch('/inbox/{id}/assign', [InboxController::class, 'assign'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.assign');

    // US-WA-305: mover conversa entre filas (override manual vence heurística;
    // null volta pra automática) — ADR 0267.
    Route::patch('/inbox/{id}/queue', [InboxController::class, 'moveQueue'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.move_queue');

    // US-WA-307: + Nova conversa — find-or-create + mensagem inicial opcional
    // (reusa pipeline send). Tier 0: canal ativo do business + ACL.
    Route::post('/inbox/conversations', [InboxController::class, 'startConversation'])
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.start_conversation');

    // US-WA-306 (ADR 0268) — broadcast FASE 1: pre-flight real (opt-in LGPD +
    // janela 24h) + draft auditável. Disparo em massa = fase 2 gate [W].
    Route::post('/broadcast/preflight', [BroadcastController::class, 'preflight'])
        ->middleware('can:whatsapp.send')
        ->name('atendimento.broadcast.preflight');
    Route::post('/broadcast', [BroadcastController::class, 'store'])
        ->middleware('can:whatsapp.send')
        ->name('atendimento.broadcast.store');

    // PR-9 brief [CC] — IA na thread (laravel/ai, mesma infra dos Agents Jana).
    // dry_run da Jana gateia custo; PII redigida antes do provider (LGPD).
    Route::post('/inbox/{id}/ai/summarize', [InboxAiController::class, 'summarize'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.ai.summarize');
    Route::post('/inbox/{id}/ai/ask', [InboxAiController::class, 'ask'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.ai.ask');
    Route::post('/inbox/{id}/ai/suggest-reply', [InboxAiController::class, 'suggestReply'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.send')
        ->name('atendimento.inbox.ai.suggest_reply');

    // Wagner 2026-05-27 — Voice of Customer in-app capture (ADR UI-0016).
    // Captura feedback diretamente de mensagens do inbox WhatsApp.
    Route::post('/feedback/capture', [ClientFeedbackController::class, 'capture'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.feedback.capture');

    Route::get('/feedback', [ClientFeedbackController::class, 'index'])
        ->middleware('can:whatsapp.access')
        ->name('atendimento.feedback.index');

    Route::patch('/feedback/{id}/status', [ClientFeedbackController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.access')
        ->name('atendimento.feedback.update_status');

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

    // ADR 0206 Fase D — endpoint dedicado pra polling 2s do Dialog "Conectar".
    // Reconciler observa estado canon real do daemon (vs /status legacy que
    // mistura branches Baileys/whatsmeow). UI usa pra detectar paired e fechar
    // dialog automaticamente.
    Route::get('/canais/{id}/whatsmeow-status', [ChannelsController::class, 'whatsmeowStatus'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.channels.whatsmeow-status');

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

    // US-WA-301 (ADR 0267) — CRUD de filas do painel "Filas" da Caixa Unificada.
    // Leitura vai nos props do CaixaUnificadaController; aqui só mutações.
    Route::post('/filas', [QueuesController::class, 'store'])
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.filas.store');
    Route::put('/filas/{id}', [QueuesController::class, 'update'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.filas.update');
    Route::delete('/filas/{id}', [QueuesController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('can:whatsapp.settings.manage')
        ->name('atendimento.filas.destroy');

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
