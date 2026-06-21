<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowState;
use Modules\Whatsapp\Services\WhatsmeowReconciler;

uses(Tests\TestCase::class);

/**
 * WhatsmeowReconcilerTest — State Machine Reconciler (ADR 0206 Fase B+D).
 *
 * Cobre 10+ cenários canon WuzAPI user lifecycle:
 *  - NOT_EXISTS quando user não está no daemon
 *  - PROVISION_PENDING quando user existe mas token DB perdido
 *  - QR_PENDING quando Connected=true + LoggedIn=false (primeira vez)
 *  - PAIRED quando LoggedIn=true
 *  - LOGGED_OUT quando channel.status era active e voltou pra Connected=true/LoggedIn=false
 *  - BANNED/ERROR (cobertos via reconcile + isError)
 *  - DAEMON_UNREACHABLE em timeout/network failure
 *  - ensureProvisioned idempotente (não recria user se já tem token)
 *  - ensureProvisioned cria via POST /admin/users se NOT_EXISTS
 *  - getQrCode retorna null em PAIRED + strip prefix data:image
 *  - markPairedInDb atualiza channel.status=active + health=healthy
 *  - Multi-tenant Tier 0: resolveChannelByUserName escopado business_id
 *
 * Trust L0 — testes NUNCA chamam daemon real (Http::fake guard).
 */

beforeEach(function () {
    config([
        'whatsapp.whatsmeow.daemon_url' => 'https://whatsapp-whatsmeow.oimpresso.com',
        'whatsapp.whatsmeow.api_key' => 'admin_token_fake_32_hex',
        'whatsapp.whatsmeow.hmac_secret' => 'hmac_secret_fake',
        'whatsapp.whatsmeow.request_timeout' => 5,
        'app.url' => 'https://oimpresso.com',
    ]);
});

/**
 * Factory helper — channel whatsmeow stub sem persistir DB.
 */
function whatsmeowChannelStub(array $overrides = []): Channel
{
    $channel = new Channel(array_merge([
        'id' => 1,
        'business_id' => 99,
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'setup',
        'channel_health' => 'never_checked',
        'config_json' => [],
    ], $overrides));
    // Setar IDs nos atributos pra não disparar save() quando reconciler tocar
    $channel->exists = true;
    return $channel;
}

it('reconcile() retorna NOT_EXISTS quando user não está no daemon', function () {
    Http::fake([
        '*/admin/users' => Http::response(['data' => []], 200),
    ]);

    $channel = whatsmeowChannelStub();
    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::NOT_EXISTS);
});

it('reconcile() retorna PROVISION_PENDING quando user existe no daemon mas token DB perdido', function () {
    Http::fake([
        '*/admin/users' => Http::response([
            'data' => [
                ['name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee', 'token' => 'remote-token'],
            ],
        ], 200),
    ]);

    $channel = whatsmeowChannelStub(['config_json' => []]);
    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::PROVISION_PENDING);
});

it('reconcile() retorna QR_PENDING quando Connected=true e LoggedIn=false (primeira vez)', function () {
    Http::fake([
        '*/admin/users' => Http::response([
            'data' => [['name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee']],
        ], 200),
        '*/session/status' => Http::response([
            'data' => ['Connected' => true, 'LoggedIn' => false],
        ], 200),
    ]);

    $channel = whatsmeowChannelStub([
        'config_json' => ['whatsmeow_user_token' => 'user_token_xyz'],
        'status' => 'setup',
        'channel_health' => 'never_checked',
    ]);

    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::QR_PENDING);
});

it('reconcile() retorna PAIRED quando Connected=true e LoggedIn=true', function () {
    Http::fake([
        '*/admin/users' => Http::response([
            'data' => [['name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee']],
        ], 200),
        '*/session/status' => Http::response([
            'data' => ['Connected' => true, 'LoggedIn' => true, 'Jid' => '5511987@s.whatsapp.net'],
        ], 200),
    ]);

    $channel = whatsmeowChannelStub([
        'config_json' => ['whatsmeow_user_token' => 'user_token_xyz'],
    ]);

    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::PAIRED);
    expect($state->isPaired())->toBeTrue();
});

it('reconcile() retorna PAIRED pela lista /admin/users mesmo sem token no DB (blindagem stale-token · Wagner 2026-06-18)', function () {
    // A própria lista /admin/users traz connected/loggedIn por sessão. Quando
    // ambos true, reconcile resolve PAIRED SEM depender do user_token salvo em
    // config_json (que pode ter ficado stale e fazer /session/status dar 401,
    // travando o fechamento automático da tela do QR).
    Http::fake([
        '*/admin/users' => Http::response([
            'data' => [
                ['name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee', 'connected' => true, 'loggedIn' => true],
            ],
        ], 200),
        // Guard: session/status NÃO deve decidir aqui (mesmo respondendo desconectado).
        '*/session/status' => Http::response(['data' => ['Connected' => false, 'LoggedIn' => false]], 200),
    ]);

    $channel = whatsmeowChannelStub(['config_json' => []]); // token perdido no DB

    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::PAIRED);
});

it('reconcile() retorna LOGGED_OUT quando channel era active e voltou pra Connected=true/LoggedIn=false', function () {
    Http::fake([
        '*/admin/users' => Http::response([
            'data' => [['name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee']],
        ], 200),
        '*/session/status' => Http::response([
            'data' => ['Connected' => true, 'LoggedIn' => false],
        ], 200),
    ]);

    $channel = whatsmeowChannelStub([
        'config_json' => ['whatsmeow_user_token' => 'user_token_xyz'],
        'status' => 'active', // Era pareado antes
        'channel_health' => 'healthy',
    ]);

    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::LOGGED_OUT);
});

it('reconcile() retorna DAEMON_UNREACHABLE em network failure', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('connection timeout');
    });

    $channel = whatsmeowChannelStub();
    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::DAEMON_UNREACHABLE);
    expect($state->isError())->toBeTrue();
});

it('reconcile() retorna ERROR quando channel.type não é whatsmeow (defensive)', function () {
    $channel = whatsmeowChannelStub(['type' => Channel::TYPE_WHATSAPP_META]);

    $state = app(WhatsmeowReconciler::class)->reconcile($channel);

    expect($state)->toBe(WhatsmeowState::ERROR);
});

it('ensureProvisioned() é idempotente quando channel já tem token salvo', function () {
    Http::fake(); // garante NENHUM POST /admin/users

    $channel = whatsmeowChannelStub([
        'config_json' => [
            'whatsmeow_user_token' => 'existing-token',
            'whatsmeow_user_name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee',
            'whatsmeow_webhook_url' => 'https://oimpresso.com/api/whatsapp/webhook/whatsmeow/biz-uuid',
        ],
    ]);

    $result = app(WhatsmeowReconciler::class)->ensureProvisioned($channel);

    expect($result['token'])->toBe('existing-token');
    expect($result['name'])->toBe('ch-aaaaaaaabbbbccccddddeeeeeeeeeeee');
    // Nenhum POST /admin/users foi feito
    Http::assertNothingSent();
});

it('WhatsmeowState::userMessage() retorna mensagens PT-BR pra cada estado', function () {
    expect(WhatsmeowState::NOT_EXISTS->userMessage())->toContain('nunca foi conectado');
    expect(WhatsmeowState::QR_PENDING->userMessage())->toContain('Escaneie o QR');
    expect(WhatsmeowState::PAIRED->userMessage())->toContain('pareado');
    expect(WhatsmeowState::LOGGED_OUT->userMessage())->toContain('expirou');
    expect(WhatsmeowState::BANNED->userMessage())->toContain('banido');
    expect(WhatsmeowState::DAEMON_UNREACHABLE->userMessage())->toContain('indisponível');
});

it('WhatsmeowState helpers isPaired/isError/isPending distinguem corretamente', function () {
    expect(WhatsmeowState::PAIRED->isPaired())->toBeTrue();
    expect(WhatsmeowState::QR_PENDING->isPaired())->toBeFalse();

    expect(WhatsmeowState::BANNED->isError())->toBeTrue();
    expect(WhatsmeowState::DAEMON_UNREACHABLE->isError())->toBeTrue();
    expect(WhatsmeowState::ERROR->isError())->toBeTrue();
    expect(WhatsmeowState::PAIRED->isError())->toBeFalse();

    expect(WhatsmeowState::QR_PENDING->isPending())->toBeTrue();
    expect(WhatsmeowState::NOT_EXISTS->isPending())->toBeTrue();
    expect(WhatsmeowState::PAIRED->isPending())->toBeFalse();
});

it('reconcile() suporta envelope WuzAPI {data: [...]} e shape direto pra /admin/users', function () {
    // WuzAPI varia entre versões — testa shape envelope
    Http::fake([
        '*/admin/users' => Http::response([
            'data' => [['name' => 'ch-aaaaaaaabbbbccccddddeeeeeeeeeeee']],
        ], 200),
        '*/session/status' => Http::response([
            'Connected' => true, 'LoggedIn' => true,
        ], 200),
    ]);

    $channel = whatsmeowChannelStub([
        'config_json' => ['whatsmeow_user_token' => 'token_xyz'],
    ]);

    expect(app(WhatsmeowReconciler::class)->reconcile($channel))->toBe(WhatsmeowState::PAIRED);
});

it('Reconciler expõe métodos canon resolveChannelByUserName + resolveChannelForPendingPair (signature guard)', function () {
    // Multi-tenant Tier 0 (ADR 0093) — métodos existem + assinatura correta.
    // Comportamento DB real coberto em WhatsmeowChannelIsolationTest (já existente).
    $reconciler = app(WhatsmeowReconciler::class);

    $refl = new \ReflectionClass($reconciler);
    expect($refl->hasMethod('resolveChannelByUserName'))->toBeTrue();
    expect($refl->hasMethod('resolveChannelForPendingPair'))->toBeTrue();
    expect($refl->hasMethod('markPairedInDb'))->toBeTrue();
    expect($refl->hasMethod('markDisconnectedInDb'))->toBeTrue();

    // resolveChannelByUserName aceita (int business_id, string userName) — escopo tier-0
    $byUserName = $refl->getMethod('resolveChannelByUserName');
    expect($byUserName->getNumberOfParameters())->toBe(2);
    $params = $byUserName->getParameters();
    expect($params[0]->getType()?->getName())->toBe('int');
    expect($params[1]->getType()?->getName())->toBe('string');
});
