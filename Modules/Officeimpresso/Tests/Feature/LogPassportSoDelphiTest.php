<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Events\AccessTokenCreated;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * LogPassportAccessToken só grava login de licença para o client do desktop Delphi
 * (auditoria Officeimpresso 2026-11-05, Lote 3 PR 2).
 *
 * Antes, todo AccessTokenCreated do sistema (CRM, fieldforce, app) virava
 * licenca_log.event='login_success'. Agora o listener confere o client contra
 * config('officeimpresso.delphi_client_ids').
 *
 * Os casos disparam o evento real do Passport (o listener está registrado pelo
 * provider do módulo) com dois clients fictícios: um configurado como Delphi e
 * outro não. Tenant 98 (seed); nunca biz=4 nem biz=1.
 */
const OI_CLIENT_DELPHI = '990039';
const OI_CLIENT_OUTRO = '990040';

beforeEach(function () {
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: licenca_log + schema UltimatePOS exigem MySQL (ADR 0358).');
    }

    config(['officeimpresso.delphi_client_ids' => [OI_CLIENT_DELPHI]]);
});

function novoUsuarioLoginTeste(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name' => 'OI',
        'surname' => 'LoginDelphi',
        'username' => 'oi_login_'.$businessId.'_'.uniqid(),
        'email' => 'oi_login_'.$businessId.'_'.uniqid().'@test.local',
        'password' => bcrypt('test12345'),
        'language' => 'pt_BR',
    ]);
}

function loginsDe(int $userId): \Illuminate\Support\Collection
{
    return DB::table('licenca_log')
        ->where('user_id', $userId)
        ->where('event', 'login_success')
        ->get();
}

it('token do client Delphi vira login_success em licenca_log', function () {
    $usuario = novoUsuarioLoginTeste($this->seededTenant()->id);

    event(new AccessTokenCreated('tok'.uniqid().'abcd', (string) $usuario->id, OI_CLIENT_DELPHI));

    $logs = loginsDe($usuario->id);
    expect($logs)->toHaveCount(1);
    expect($logs->first()->client_id)->toBe(OI_CLIENT_DELPHI);
    expect((int) $logs->first()->business_id)->toBe((int) $this->seededTenant()->id);
    expect($logs->first()->source)->toBe('passport_event');
});

it('token de outro client (CRM, app) não grava nada em licenca_log', function () {
    $usuario = novoUsuarioLoginTeste($this->seededTenant()->id);

    event(new AccessTokenCreated('tok'.uniqid().'abcd', (string) $usuario->id, OI_CLIENT_OUTRO));

    expect(loginsDe($usuario->id))->toHaveCount(0);
});

it('os dois clients no mesmo teste: só o do Delphi aparece', function () {
    $delphi = novoUsuarioLoginTeste($this->seededTenant()->id);
    $outro = novoUsuarioLoginTeste($this->seededTenant()->id);

    event(new AccessTokenCreated('tok'.uniqid().'abcd', (string) $outro->id, OI_CLIENT_OUTRO));
    event(new AccessTokenCreated('tok'.uniqid().'abcd', (string) $delphi->id, OI_CLIENT_DELPHI));

    expect(loginsDe($delphi->id))->toHaveCount(1);
    expect(loginsDe($outro->id))->toHaveCount(0);
});

it('sem client Delphi configurado nada é gravado', function () {
    config(['officeimpresso.delphi_client_ids' => []]);
    $usuario = novoUsuarioLoginTeste($this->seededTenant()->id);

    event(new AccessTokenCreated('tok'.uniqid().'abcd', (string) $usuario->id, OI_CLIENT_DELPHI));

    expect(loginsDe($usuario->id))->toHaveCount(0);
});
