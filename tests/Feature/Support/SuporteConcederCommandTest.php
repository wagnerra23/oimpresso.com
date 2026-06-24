<?php

declare(strict_types=1);

use App\Services\Support\SupportAccessService;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Modo Suporte — comando `suporte:conceder` (concede/revoga a capability, RF4 interino).
 *
 * @see app/Console/Commands/SuporteConcederCommand.php
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */

function concSchemaReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users') && Schema::hasTable('support_agents');
}

function makeConcUser(string $username): User
{
    return User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'Conc']
    );
}

it('concede a capability por id e o usuário vira agente de suporte', function () {
    if (! concSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $user = makeConcUser('conc_por_id');
    SupportAgent::query()->where('user_id', $user->id)->delete();

    $this->artisan('suporte:conceder', ['user' => (string) $user->id])->assertExitCode(0);

    expect((new SupportAccessService())->isSupportAgent($user))->toBeTrue();
});

it('concede por username', function () {
    if (! concSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $user = makeConcUser('conc_por_username');
    SupportAgent::query()->where('user_id', $user->id)->delete();

    $this->artisan('suporte:conceder', ['user' => 'conc_por_username'])->assertExitCode(0);

    expect((new SupportAccessService())->isSupportAgent($user))->toBeTrue();
});

it('revoga com --revogar (deixa de ser agente)', function () {
    if (! concSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $user = makeConcUser('conc_revoga');
    $this->artisan('suporte:conceder', ['user' => (string) $user->id])->assertExitCode(0);
    expect((new SupportAccessService())->isSupportAgent($user))->toBeTrue();

    $this->artisan('suporte:conceder', ['user' => (string) $user->id, '--revogar' => true])->assertExitCode(0);
    expect((new SupportAccessService())->isSupportAgent($user))->toBeFalse();
});

it('re-concede após revogar reativa a concessão', function () {
    if (! concSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $user = makeConcUser('conc_reativa');
    $this->artisan('suporte:conceder', ['user' => (string) $user->id])->assertExitCode(0);
    $this->artisan('suporte:conceder', ['user' => (string) $user->id, '--revogar' => true])->assertExitCode(0);
    expect((new SupportAccessService())->isSupportAgent($user))->toBeFalse();

    $this->artisan('suporte:conceder', ['user' => (string) $user->id])->assertExitCode(0);
    expect((new SupportAccessService())->isSupportAgent($user))->toBeTrue();
});

it('usuário inexistente falha (exit 1) e não cria concessão', function () {
    if (! concSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $antes = SupportAgent::query()->count();
    $this->artisan('suporte:conceder', ['user' => 'usuario_que_nao_existe_zzz'])->assertExitCode(1);
    expect(SupportAgent::query()->count())->toBe($antes);
});
