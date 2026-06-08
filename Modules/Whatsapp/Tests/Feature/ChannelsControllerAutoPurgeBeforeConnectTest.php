<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * Regression test pro fix do bug "QR não abre" (sessão 2026-05-13).
 *
 * Bug original: ChannelsController::connect() reusava o mesmo instance_id
 * sem purgar quando channel estava `banned` (logged_out / multidevice_mismatch).
 * Daemon não emitia QR novo → poll 15× timeout → fallback pairing code
 * também falhava → "QR não abre" reportado.
 *
 * Fix: detectar state=banned|disconnected|error no daemon ANTES de POST connect
 * e fazer DELETE automático (purge creds revogadas), depois POST connect emite
 * QR limpo.
 *
 * @see Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php::connect()
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md
 */
beforeEach(function () {
    Schema::dropIfExists('channels');
    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->string('display_identifier', 100)->nullable();
        $table->text('config_json')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'a'.str_repeat('b', 31),
        'whatsapp.baileys.request_timeout' => 5,
    ]);

    // Auth stub — Spatie permission `whatsapp.settings.manage` necessária.
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool { return true; } // stub
    };
    $user->id = 1;
    $user->business_id = 1;
    auth()->setUser($user);
    session()->put('user.business_id', 1);
    session()->put('user.id', 1);
});

function makeBaileysChannelForConnect(int $id, string $uuid, string $phone = '+5511999998888'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => $id,
        'business_id' => 1,
        'channel_uuid' => $uuid,
        'label' => 'Suporte teste',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'setup',
        'display_identifier' => $phone,
        'config_json' => ['baileys_phone_e164' => $phone],
    ]);
}

it('R-WA-QRPURGE-001 — channel banned dispara DELETE antes do POST connect (fix QR)', function () {
    $ch = makeBaileysChannelForConnect(101, 'da8c23c5-5a6c-4538-b82f-1a05c47ac5da');
    $instanceId = 'ch-da8c23c55a6c4538b82f1a05c47ac5da';

    Http::fake([
        // 1º hit: status retorna banned
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'instance_id' => $instanceId,
            'state' => 'banned',
            'ban_reason' => 'logged_out',
        ], 200),
        // 2º hit: DELETE retorna ok
        "https://daemon.test/instances/{$instanceId}" => Http::response(['ok' => true], 200),
        // 3º hit: POST connect retorna 202 + state=connecting
        "https://daemon.test/instances/{$instanceId}/connect" => Http::response([
            'instance_id' => $instanceId,
            'state' => 'connecting',
        ], 202),
    ]);

    $response = $this->postJson("/atendimento/canais/{$ch->id}/connect");

    // Verifica que DELETE foi chamado (auto-purge ativado)
    Http::assertSent(function ($request) use ($instanceId) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), "/instances/{$instanceId}")
            && ! str_contains($request->url(), '/status')
            && ! str_contains($request->url(), '/connect');
    });

    // Verifica que POST connect foi chamado DEPOIS
    Http::assertSent(function ($request) use ($instanceId) {
        return $request->method() === 'POST'
            && str_contains($request->url(), "/instances/{$instanceId}/connect");
    });
});

it('R-WA-QRPURGE-002 — channel disconnected dispara DELETE antes do POST connect', function () {
    $ch = makeBaileysChannelForConnect(102, '3bcafcfc-7506-48cd-843d-72116460d95b');
    $instanceId = 'ch-3bcafcfc750648cd843d72116460d95b';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'disconnected',
            'ban_reason' => null,
        ], 200),
        "https://daemon.test/instances/{$instanceId}" => Http::response(['ok' => true], 200),
        "https://daemon.test/instances/{$instanceId}/connect" => Http::response([
            'state' => 'connecting',
        ], 202),
    ]);

    $this->postJson("/atendimento/canais/{$ch->id}/connect");

    Http::assertSent(fn ($req) => $req->method() === 'DELETE'
        && str_contains($req->url(), "/instances/{$instanceId}"));
});

it('R-WA-QRPURGE-003 — channel 404 (não existe no daemon) NÃO dispara DELETE — fresh start', function () {
    $ch = makeBaileysChannelForConnect(103, 'fresh-1111-2222-3333-444444444444');
    $instanceId = 'ch-fresh1111222233334444444444444';

    Http::fake([
        // 404 = instância não existe (primeiro pareamento)
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'error' => 'instance_not_found',
        ], 404),
        "https://daemon.test/instances/{$instanceId}/connect" => Http::response([
            'state' => 'connecting',
        ], 202),
    ]);

    $this->postJson("/atendimento/canais/{$ch->id}/connect");

    // NÃO deve haver DELETE
    Http::assertNotSent(fn ($req) => $req->method() === 'DELETE');
});

it('R-WA-QRPURGE-004 — channel já connected NÃO dispara DELETE (idempotente)', function () {
    $ch = makeBaileysChannelForConnect(104, 'already-1234-5678-9abc-def012345678');
    $instanceId = 'ch-already1234567889abcdef012345678';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'connected',
            'last_seen' => now()->toIso8601String(),
        ], 200),
        "https://daemon.test/instances/{$instanceId}/connect" => Http::response([
            'state' => 'connected',
        ], 200),
    ]);

    $this->postJson("/atendimento/canais/{$ch->id}/connect");

    Http::assertNotSent(fn ($req) => $req->method() === 'DELETE');
});

it('R-WA-QRPURGE-005 — channel state error dispara DELETE (recuperação broad)', function () {
    $ch = makeBaileysChannelForConnect(105, 'erro1234-5678-9abc-def0-123456789012');
    $instanceId = 'ch-erro1234567889abcdef0123456789012';

    Http::fake([
        "https://daemon.test/instances/{$instanceId}/status" => Http::response([
            'state' => 'error',
            'last_error' => 'unknown',
        ], 200),
        "https://daemon.test/instances/{$instanceId}" => Http::response(['ok' => true], 200),
        "https://daemon.test/instances/{$instanceId}/connect" => Http::response([
            'state' => 'connecting',
        ], 202),
    ]);

    $this->postJson("/atendimento/canais/{$ch->id}/connect");

    Http::assertSent(fn ($req) => $req->method() === 'DELETE');
});
