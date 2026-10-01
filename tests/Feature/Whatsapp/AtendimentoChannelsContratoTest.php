<?php

declare(strict_types=1);

use App\Scopes\ScopeByBusiness;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\DeferProp;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Http\Controllers\Admin\ChannelsController;
use Modules\Whatsapp\Http\Requests\ChannelRequest;
use Modules\Whatsapp\Http\Requests\GrantChannelUserRequest;

/**
 * Contrato das telas /atendimento/canais (Index) e /atendimento/canais/{id} (Show).
 *
 * UCs em Modules/Whatsapp/Resources/js/Pages/Atendimento/Channels/{Index,Show}.casos.md,
 * derivados dos charters + SPEC US-WA-068 (nunca do .tsx).
 *
 * Tenant "meu" = 98 (fictício, ADR 0358) · vizinho = 99. NUNCA biz=4.
 * Schema sintético sqlite — mesmo idioma de CaixaUnificadaControllerTest.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual — lane sqlite (.github/ci-sqlite-pest.list).');
    }
    config(['activitylog.enabled' => false]);

    foreach (['channel_user_access', 'channels', 'users'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('first_name', 100)->nullable();
        $table->string('surname', 100)->nullable();
        $table->string('last_name', 100)->nullable();
        $table->string('username', 100)->nullable();
        $table->string('email', 100)->nullable();
        $table->string('password')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });
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
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });
    Schema::create('channel_user_access', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('granted_by_user_id');
        $table->timestamp('granted_at');
        $table->timestamp('revoked_at')->nullable();
        $table->unsignedInteger('revoked_by_user_id')->nullable();
        $table->timestamps();
    });

    // Sessão do gestor do business 98 (o controller lê session('user.business_id')).
    $stub = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
    };
    $stub->id = 10;
    $stub->business_id = 98;
    auth()->setUser($stub);
    session()->put('user.business_id', 98);
    session()->put('user.id', 10);
    app()->forgetInstance(ScopeByBusiness::class);
});

function achtChannel(int $businessId, string $label, string $type = Channel::TYPE_WHATSAPP_WHATSMEOW): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId, 'label' => $label, 'type' => $type, 'status' => 'active',
    ]);
}

function achtUser(int $id, int $businessId, string $nome): void
{
    DB::table('users')->insert(['id' => $id, 'business_id' => $businessId, 'first_name' => $nome]);
}

function achtGrant(int $channelId, int $userId, ?string $revokedAt = null): void
{
    DB::table('channel_user_access')->insert([
        'business_id' => 98, 'channel_id' => $channelId, 'user_id' => $userId, 'granted_by_user_id' => 10,
        'granted_at' => now(), 'revoked_at' => $revokedAt, 'revoked_by_user_id' => $revokedAt ? 10 : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function achtProps($response): array
{
    $p = (new \ReflectionClass($response))->getProperty('props');
    $p->setAccessible(true);
    return $p->getValue($response);
}

function achtResolve($prop): array
{
    return $prop instanceof DeferProp ? $prop() : (array) $prop;
}

// ─── Index ───────────────────────────────────────────────────────────────────

it('UC-CNL-01 · lista só os canais do business da sessão (Tier 0) e carrega a lista sob demanda', function () {
    achtChannel(98, 'Suporte');
    achtChannel(98, 'Financeiro');
    achtChannel(99, 'Canal do vizinho');

    $props = achtProps((new ChannelsController())->index());

    expect($props['businessId'])->toBe(98);
    expect($props['channels'])->toBeInstanceOf(DeferProp::class);
    $labels = collect(achtResolve($props['channels']))->pluck('label')->all();
    expect($labels)->toBe(['Suporte', 'Financeiro']);
    expect($labels)->not->toContain('Canal do vizinho');
});

it('UC-CNL-02 · canais em pré-visualização aparecem no cadastro mas não podem ser escolhidos', function () {
    $tipos = collect(achtProps((new ChannelsController())->index())['availableTypes'])->keyBy('value');

    foreach ([Channel::TYPE_INSTAGRAM, Channel::TYPE_MESSENGER, Channel::TYPE_EMAIL_IMAP] as $preview) {
        expect($tipos[$preview]['enabled'])->toBeFalse();
    }
    expect($tipos[Channel::TYPE_WHATSAPP_META]['enabled'])->toBeTrue();
});

it('UC-CNL-03 · novo canal nasce em setup, no business da sessão, com aceite LGPD se não-oficial', function () {
    $req = Mockery::mock(ChannelRequest::class);
    $req->shouldReceive('validated')->andReturn([
        'label' => 'Comercial', 'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'config' => ['whatsmeow_phone_e164' => '+5548999990000'],
    ]);

    (new ChannelsController())->store($req);

    $ch = Channel::withoutGlobalScope(ScopeByBusiness::class)->where('label', 'Comercial')->firstOrFail();
    expect($ch->business_id)->toBe(98)
        ->and($ch->status)->toBe('setup')
        ->and($ch->display_identifier)->toBe('+5548999990000')
        ->and($ch->lgpd_acknowledged_at)->not->toBeNull()
        ->and($ch->lgpd_acknowledged_by_user_id)->toBe(10);
});

it('UC-CNL-04 · não remove canal de outro business — 404 e o canal continua lá', function () {
    $alheio = achtChannel(99, 'Canal do vizinho');

    expect(fn () => (new ChannelsController())->destroy($alheio->id))
        ->toThrow(ModelNotFoundException::class);
    expect(Channel::withoutGlobalScope(ScopeByBusiness::class)->whereKey($alheio->id)->exists())->toBeTrue();
});

// ─── Show ────────────────────────────────────────────────────────────────────

it('UC-CNLD-01 · abre o detalhe do canal do meu business com a configuração', function () {
    $ch = achtChannel(98, 'Suporte');

    $props = achtProps((new ChannelsController())->show($ch->id));

    expect($props['channel']['id'])->toBe($ch->id)
        ->and($props['channel']['label'])->toBe('Suporte')
        ->and($props['channel']['type'])->toBe(Channel::TYPE_WHATSAPP_WHATSMEOW);
    foreach (['users', 'availableUsers', 'audit'] as $aba) {
        expect($props[$aba])->toBeInstanceOf(DeferProp::class);
    }
});

it('UC-CNLD-02 · canal de outro business devolve 404 antes de renderizar', function () {
    $alheio = achtChannel(99, 'Canal do vizinho');

    expect(fn () => (new ChannelsController())->show($alheio->id))
        ->toThrow(ModelNotFoundException::class);
});

it('UC-CNLD-03 · aba Usuários mostra só quem tem acesso ativo', function () {
    $ch = achtChannel(98, 'Suporte');
    achtUser(10, 98, 'Gestor');
    achtUser(21, 98, 'Ana');
    achtUser(22, 98, 'Bruno');
    achtGrant($ch->id, 21);
    achtGrant($ch->id, 22, now()->subDay()->toDateTimeString());

    $users = achtResolve(achtProps((new ChannelsController())->show($ch->id))['users']);

    expect(collect($users)->pluck('user_id')->all())->toBe([21])
        ->and($users[0]['name'])->toBe('Ana');
});

it('UC-CNLD-04 · revogar preserva o registro e o Histórico mostra o acesso como revogado', function () {
    $ch = achtChannel(98, 'Suporte');
    achtUser(10, 98, 'Gestor');
    achtUser(21, 98, 'Ana');
    achtGrant($ch->id, 21);

    (new ChannelsController())->revokeUser($ch->id, 21);

    expect(DB::table('channel_user_access')->where('user_id', 21)->count())->toBe(1);
    $audit = achtResolve(achtProps((new ChannelsController())->show($ch->id))['audit']);
    expect($audit)->toHaveCount(1)
        ->and($audit[0]['is_active'])->toBeFalse()
        ->and($audit[0]['revoked_at'])->not->toBeNull();
});

it('UC-CNLD-05 · conceder acesso a quem já tem acesso ativo não cria duplicata', function () {
    $ch = achtChannel(98, 'Suporte');
    achtGrant($ch->id, 21);
    $req = Mockery::mock(GrantChannelUserRequest::class);
    $req->shouldReceive('validated')->with('user_id')->andReturn(21);

    (new ChannelsController())->grantUser($req, $ch->id);

    expect(DB::table('channel_user_access')->where('user_id', 21)->count())->toBe(1);
});
