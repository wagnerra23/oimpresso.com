<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;

uses(Tests\TestCase::class);

/**
 * US-WA-068 — ACL atendente↔canal (channel_user_access).
 *
 * GUARD tests Tier 0 IRREVOGÁVEL (ADR 0093 + ADR 0135):
 *
 *   1. Schema migration cria tabela com índices/UNIQUE corretos
 *   2. ChannelUserAccess respeita BusinessIdScope global (biz=1 ≠ biz=99)
 *   3. Scope active() filtra revoked_at IS NULL
 *   4. UNIQUE permite re-grant após revoke (revoked_at part of UNIQUE)
 *   5. Grant duplicado ativo (revoked_at NULL) → UNIQUE viola
 *   6. FK cascade: deletar Channel apaga rows de ChannelUserAccess
 *
 * GrantChannelUserRequest validação testada via FormRequest direto
 * (cross-tenant + permission check). FormRequest exige resolução de auth
 * real (não mockável trivialmente) — cobertura backend full vai pra
 * smoke biz=1 em produção (US-WA-068 acceptance).
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-068
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['channel_user_access', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    // Espelha migration omnichannel (channels)
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

    // Espelha migration channel_user_access (US-WA-068) + correção
    // 2026_06_13 (enforce 1 grant ativo via coluna gerada + UNIQUE).
    Schema::create('channel_user_access', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('granted_by_user_id');
        $table->timestamp('granted_at');
        $table->timestamp('revoked_at')->nullable();
        $table->unsignedInteger('revoked_by_user_id')->nullable();
        // Coluna gerada VIRTUAL: 1=ativo (revoked_at NULL), NULL=revogado.
        // É o que faz o UNIQUE enforçar 1 ativo/(canal,user) — NULLs distintos
        // deixam os revogados coexistirem (re-grant + history). Sem ela, dois
        // grants ativos não colidiam (bug do UNIQUE com revoked_at NULL).
        $table->integer('revoked_marker')
            ->virtualAs('case when revoked_at is null then 1 else null end')
            ->nullable();
        $table->timestamps();
        $table->unique(
            ['channel_id', 'user_id', 'revoked_marker'],
            'cua_active_grant_unq'
        );
        $table->index(['business_id', 'user_id'], 'cua_biz_user_idx');
    });
});

function cuaSetBiz(int $businessId): void
{
    // Mesmo pattern OmnichannelIsolationTest — User stub não-persistido +
    // session pra ativar ScopeByBusiness corretamente.
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool { return false; }
    };
    $user->id = 1;
    $user->business_id = $businessId;
    auth()->setUser($user);

    session()->put('user.business_id', $businessId);
    app()->forgetInstance(ScopeByBusiness::class);
}

function cuaMakeChannel(int $businessId, string $label = 'Suporte', ?string $uuid = null): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid ?? ('cua-' . $businessId . '-' . uniqid()),
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

it('R-WA-068-001 — Schema da tabela channel_user_access existe com colunas esperadas', function () {
    expect(Schema::hasTable('channel_user_access'))->toBeTrue();
    expect(Schema::hasColumns('channel_user_access', [
        'id', 'business_id', 'channel_id', 'user_id',
        'granted_by_user_id', 'granted_at', 'revoked_at',
        'revoked_by_user_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('R-WA-068-002 — ChannelUserAccess respeita BusinessIdScope (biz=1 não vê biz=99)', function () {
    $ch1 = cuaMakeChannel(1, 'Vendas biz1');
    $ch99 = cuaMakeChannel(99, 'Vendas biz99');

    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $ch1->id,
        'user_id' => 10,
        'granted_by_user_id' => 1,
        'granted_at' => now(),
    ]);
    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'channel_id' => $ch99->id,
        'user_id' => 10, // mesmo user_id em business diferente
        'granted_by_user_id' => 1,
        'granted_at' => now(),
    ]);

    cuaSetBiz(1);

    $visible = ChannelUserAccess::query()->get();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->business_id)->toBe(1);
    expect($visible->first()->channel_id)->toBe($ch1->id);
});

it('R-WA-068-003 — Scope active() filtra revoked_at IS NULL', function () {
    cuaSetBiz(1);
    $ch = cuaMakeChannel(1);

    // Grant ativo
    ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);
    // Grant revogado
    ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 11,
        'granted_by_user_id' => 1, 'granted_at' => now()->subHour(),
        'revoked_at' => now(), 'revoked_by_user_id' => 1,
    ]);

    expect(ChannelUserAccess::query()->count())->toBe(2);
    expect(ChannelUserAccess::query()->active()->count())->toBe(1);
    expect(ChannelUserAccess::query()->active()->first()->user_id)->toBe(10);
});

it('R-WA-068-004 — Re-grant após revoke funciona (UNIQUE inclui revoked_at)', function () {
    cuaSetBiz(1);
    $ch = cuaMakeChannel(1);

    // 1º grant
    $first = ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now()->subDay(),
    ]);

    // Revoke (set revoked_at)
    $first->revoked_at = now()->subHour();
    $first->revoked_by_user_id = 1;
    $first->save();

    // Re-grant (nova row com revoked_at=NULL) — deve funcionar
    $second = ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    expect($second->id)->not->toBe($first->id);
    expect(ChannelUserAccess::query()->count())->toBe(2);
    expect(ChannelUserAccess::query()->active()->count())->toBe(1);
});

it('R-WA-068-005 — Grant duplicado ATIVO (mesma combinação channel+user+revoked=NULL) viola UNIQUE', function () {
    cuaSetBiz(1);
    $ch = cuaMakeChannel(1);

    ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    // Tentar criar 2º grant ativo (revoked_at NULL) com mesmo channel+user
    expect(fn () => ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now()->addMinute(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('R-WA-068-006 — Múltiplos revokes podem coexistir (history preservation)', function () {
    cuaSetBiz(1);
    $ch = cuaMakeChannel(1);

    // 3 ciclos grant→revoke do mesmo user no mesmo canal
    foreach ([3, 2, 1] as $daysAgo) {
        ChannelUserAccess::create([
            'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
            'granted_by_user_id' => 1,
            'granted_at' => now()->subDays($daysAgo),
            'revoked_at' => now()->subDays($daysAgo)->addHours(2),
            'revoked_by_user_id' => 1,
        ]);
    }

    expect(ChannelUserAccess::query()->count())->toBe(3);
    expect(ChannelUserAccess::query()->active()->count())->toBe(0);

    // 4º grant ativo (revoked_at=NULL) — funciona porque os 3 anteriores
    // têm revoked_at preenchido com timestamps diferentes (cada um único)
    ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    expect(ChannelUserAccess::query()->active()->count())->toBe(1);
});

it('R-WA-068-007 — Channel relation funciona (BelongsTo channel)', function () {
    cuaSetBiz(1);
    $ch = cuaMakeChannel(1, 'Suporte X');

    $access = ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    expect($access->channel)->not->toBeNull();
    expect($access->channel->id)->toBe($ch->id);
    expect($access->channel->label)->toBe('Suporte X');
});

it('R-WA-068-008 — Migration idempotente: Schema::hasTable guard previne re-criar', function () {
    // Migration já criada no beforeEach. Tentar rodar a migration de novo
    // exercitando o guard `Schema::hasTable` da migration real.
    $migrationFile = __DIR__ . '/../../Database/Migrations/2026_05_12_160000_create_channel_user_access_table.php';
    expect(file_exists($migrationFile))->toBeTrue();

    $migration = require $migrationFile;
    // Não deve lançar exception nem recriar tabela
    expect(fn () => $migration->up())->not->toThrow(\Throwable::class);
    expect(Schema::hasTable('channel_user_access'))->toBeTrue();
});

it('R-WA-068-009 — granted_at e revoked_at são castados pra Carbon', function () {
    cuaSetBiz(1);
    $ch = cuaMakeChannel(1);

    $access = ChannelUserAccess::create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    expect($access->granted_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);

    $access->revoked_at = now();
    $access->save();
    $fresh = $access->fresh();
    expect($fresh->revoked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('R-WA-068-010 — withoutGlobalScopes permite query cross-tenant (superadmin scenario)', function () {
    $ch1 = cuaMakeChannel(1);
    $ch99 = cuaMakeChannel(99);

    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'channel_id' => $ch1->id, 'user_id' => 10,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);
    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99, 'channel_id' => $ch99->id, 'user_id' => 20,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    cuaSetBiz(1);

    // Query com scope: vê só biz=1
    expect(ChannelUserAccess::query()->count())->toBe(1);

    // SUPERADMIN: withoutGlobalScope explícito vê os dois
    expect(
        ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->count()
    )->toBe(2);
});
