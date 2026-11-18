<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * POST /api/officeimpresso/audit valida o corpo (auditoria Officeimpresso 2026-11-05,
 * Lote 3 PR 3).
 *
 * Antes o endpoint gravava em licenca_log qualquer `event` vindo do desktop, inclusive
 * rótulos que só o servidor deveria gravar (admin_action, login_success), e aceitava
 * `licenca_id` de qualquer empresa. Os casos sobem a requisição inteira (auth:api +
 * log.desktop + FormRequest + serviço) e conferem a linha gravada, ou a ausência dela.
 *
 * Tenant 98 (seed) e 99 (adversário). Nunca biz=4 nem biz=1.
 */
beforeEach(function () {
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358).');
    }
});

function usuarioDesktopAudit(int $businessId): User
{
    $user = User::create([
        'business_id' => $businessId,
        'first_name' => 'OI',
        'surname' => 'DesktopAudit',
        'username' => 'oi_dkaudit_'.$businessId.'_'.uniqid(),
        'email' => 'oi_dkaudit_'.$businessId.'_'.uniqid().'@test.local',
        'password' => bcrypt('test12345'),
        'language' => 'pt_BR',
    ]);
    Passport::actingAs($user, [], 'api');

    return $user;
}

function maquinaDesktopAudit(int $businessId): int
{
    return (int) DB::table('licenca_computador')->insertGetId([
        'business_id' => $businessId,
        'hd'          => 'OIDKAUDIT-'.uniqid(),
        'hostname'    => 'OIDKAUDIT',
        'user_win'    => 'OIDKAUDIT',
        'bloqueado'   => 0,
    ]);
}

function auditsDe(int $userId): \Illuminate\Support\Collection
{
    return DB::table('licenca_log')->where('user_id', $userId)->where('source', 'desktop_audit')->get();
}

it('evento de servidor (admin_action, login_success) volta 422 e não grava', function (string $evento) {
    $user = usuarioDesktopAudit($this->seededTenant()->id);

    $this->postJson('/api/officeimpresso/audit', ['event' => $evento])
        ->assertStatus(422)
        ->assertJsonValidationErrors('event');

    expect(auditsDe($user->id))->toHaveCount(0);
})->with(['admin_action', 'login_success', 'block', 'desktop_inventado']);

it('evento da lista do desktop grava com source desktop_audit', function () {
    $user = usuarioDesktopAudit($this->seededTenant()->id);

    $this->postJson('/api/officeimpresso/audit', ['event' => 'desktop_timeout', 'duration_ms' => 31000, 'hd' => 'SERIAL-X'])
        ->assertStatus(201);

    $logs = auditsDe($user->id);
    expect($logs)->toHaveCount(1);
    expect($logs->first()->event)->toBe('desktop_timeout');
    expect((int) $logs->first()->business_id)->toBe((int) $this->seededTenant()->id);
});

it('sem event grava o genérico desktop_audit', function () {
    $user = usuarioDesktopAudit($this->seededTenant()->id);

    $this->postJson('/api/officeimpresso/audit', ['error_code' => 'E1'])->assertStatus(201);

    expect(auditsDe($user->id)->first()?->event)->toBe('desktop_audit');
});

it('licenca_id de outra empresa volta 422; da própria empresa grava', function () {
    $user = usuarioDesktopAudit($this->seededTenant()->id);
    $propria = maquinaDesktopAudit($this->seededTenant()->id);
    $alheia = maquinaDesktopAudit($this->seededSupportClientTenant()->id);

    $this->postJson('/api/officeimpresso/audit', ['event' => 'desktop_hardware', 'licenca_id' => $alheia])
        ->assertStatus(422)
        ->assertJsonValidationErrors('licenca_id');
    expect(auditsDe($user->id))->toHaveCount(0);

    $this->postJson('/api/officeimpresso/audit', ['event' => 'desktop_hardware', 'licenca_id' => $propria])
        ->assertStatus(201);
    expect((int) auditsDe($user->id)->first()->licenca_id)->toBe($propria);
});
