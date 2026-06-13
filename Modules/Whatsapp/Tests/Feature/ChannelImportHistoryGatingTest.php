<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * Regression test pro botão "Importar Histórico" gated por feature flag
 * por business_id (Wagner request 2026-05-14).
 *
 * Wagner: "coloca botão na tela para eu fazer isso se eu precisar, e
 * deixa desabilitado, vou fazer em casos de clientes pagantes altos
 * pode ser?".
 *
 * Default: lista vazia em config → 403 pra todos. Wagner adiciona biz_id
 * no .env quando cliente paga.
 *
 * Cenários cobertos:
 *   1. biz_id NÃO na whitelist → 403 com flag gated=true
 *   2. biz_id na whitelist → 202 + Artisan command enqueued
 *   3. type != baileys → 422 (Z-API/Meta não suportam fetchMessageHistory)
 *   4. channel.status != active → 422
 *   5. channel.channel_health != healthy → 422
 *   6. UI flag history_import_enabled reflete config corretamente
 *
 * @see Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php::importHistory()
 * @see Modules/Whatsapp/Config/config.php history_import.enabled_business_ids
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

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

    // Auth stub com permission `whatsapp.settings.manage`
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool { return true; }
    };
    $user->id = 1;
    $user->business_id = 1;
    auth()->setUser($user);
    session()->put('user.business_id', 1);
    session()->put('user.id', 1);
});

function makeImportTestChannel(int $bizId = 1, string $status = 'active', string $health = 'healthy', string $type = 'whatsapp_baileys'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'channel_uuid' => 'import-test-' . uniqid(),
        'label' => 'Suporte Import',
        'type' => $type,
        'status' => $status,
        'channel_health' => $health,
        'display_identifier' => '5511999998888',
    ]);
}

it('R-WA-IMPORT-001 — biz_id NÃO whitelist → 403 + gated=true', function () {
    config(['whatsapp.history_import.enabled_business_ids' => []]); // vazio = todos bloqueados
    $ch = makeImportTestChannel(bizId: 1);

    $r = $this->postJson("/atendimento/canais/{$ch->id}/import-history");

    $r->assertStatus(403)
        ->assertJson([
            'ok' => false,
            'gated' => true,
        ])
        ->assertJsonPath('error', fn ($e) => str_contains($e, 'Enterprise'));
});

it('R-WA-IMPORT-002 — biz_id NA whitelist → 202 + Artisan command enqueued', function () {
    config(['whatsapp.history_import.enabled_business_ids' => [1, 7]]);
    Queue::fake();
    Artisan::fake();
    $ch = makeImportTestChannel(bizId: 1);

    $r = $this->postJson("/atendimento/canais/{$ch->id}/import-history");

    $r->assertStatus(202)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('estimated_minutes', 10);

    // Verifica que artisan command foi enfileirado (mas não executado)
    // Artisan::queue dispara CallQueuedClosure via Job — Queue::fake captura
    Queue::assertPushed(\Illuminate\Foundation\Console\Kernel::class === \Illuminate\Foundation\Console\Kernel::class
        ? \Illuminate\Foundation\Bus\PendingDispatch::class
        : \Closure::class, function () { return true; });
});

it('R-WA-IMPORT-003 — type != baileys → 422 (só Baileys suporta fetchMessageHistory)', function () {
    config(['whatsapp.history_import.enabled_business_ids' => [1]]);
    $ch = makeImportTestChannel(bizId: 1, type: 'whatsapp_zapi');

    $r = $this->postJson("/atendimento/canais/{$ch->id}/import-history");

    $r->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', fn ($e) => str_contains($e, 'Baileys'));
});

it('R-WA-IMPORT-004 — channel não-active → 422', function () {
    config(['whatsapp.history_import.enabled_business_ids' => [1]]);
    $ch = makeImportTestChannel(bizId: 1, status: 'disconnected', health: 'disconnected');

    $r = $this->postJson("/atendimento/canais/{$ch->id}/import-history");

    $r->assertStatus(422)
        ->assertJsonPath('error', fn ($e) => str_contains($e, 'conectado e saudável'));
});

it('R-WA-IMPORT-005 — channel não-healthy → 422', function () {
    config(['whatsapp.history_import.enabled_business_ids' => [1]]);
    $ch = makeImportTestChannel(bizId: 1, status: 'active', health: 'degraded');

    $r = $this->postJson("/atendimento/canais/{$ch->id}/import-history");

    $r->assertStatus(422);
});

it('R-WA-IMPORT-006 — multi-tenant Tier 0: biz_id whitelist isolado por biz', function () {
    config(['whatsapp.history_import.enabled_business_ids' => [7]]); // só biz=7
    $chBiz1 = makeImportTestChannel(bizId: 1);

    // biz=1 não está na whitelist → 403 mesmo session aponta biz=1
    $r = $this->postJson("/atendimento/canais/{$chBiz1->id}/import-history");
    $r->assertStatus(403);
});

it('R-WA-IMPORT-007 — toUiArray expõe flag history_import_enabled correta', function () {
    $controller = app(\Modules\Whatsapp\Http\Controllers\Admin\ChannelsController::class);
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('toUiArray');
    $method->setAccessible(true);

    // Cenário A: biz_id NÃO na whitelist → flag false
    config(['whatsapp.history_import.enabled_business_ids' => []]);
    $ch = makeImportTestChannel(bizId: 1);
    $payload = $method->invoke($controller, $ch);
    expect($payload['history_import_enabled'])->toBeFalse();

    // Cenário B: biz_id NA whitelist → flag true
    config(['whatsapp.history_import.enabled_business_ids' => [1]]);
    $payload2 = $method->invoke($controller, $ch);
    expect($payload2['history_import_enabled'])->toBeTrue();

    // Cenário C: Z-API mesmo whitelist → false (só Baileys)
    config(['whatsapp.history_import.enabled_business_ids' => [1]]);
    $chZapi = makeImportTestChannel(bizId: 1, type: 'whatsapp_zapi');
    $payload3 = $method->invoke($controller, $chZapi);
    expect($payload3['history_import_enabled'])->toBeFalse();
});
