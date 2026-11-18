<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * `/officeimpresso/regenerate` (ClientController::regenerate) roda `passport:install --force`,
 * que regenera as chaves do Passport e derruba o token de TODOS os desktops em campo.
 *
 * Até 2026-11 a rota era GET: sem CSRF, um `<img src>` ou um prefetch numa página aberta por um
 * superadmin logado disparava a ação (auditoria Officeimpresso 2026-11-05, eixo 5 A3). Agora é
 * POST. O `catch (Exception)` sem `use` nunca pegava nada e o ramo de erro respondia
 * `success=1`; agora pega `\Throwable` e responde `success=0` com mensagem genérica.
 *
 * O Artisan é SEMPRE mockado: este teste nunca roda `passport:install` de verdade.
 * Tenant 98 (operadora) — ADR 0358. MySQL-only.
 *
 * @see Modules\Officeimpresso\Http\Controllers\ClientController::regenerate
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    Permission::firstOrCreate(['name' => 'officeimpresso.clientes.liberar', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $this->oiRegenOperadora = $this->seededTenant();
    config(['constants.operator_business_id' => (int) $this->oiRegenOperadora->id]);
    $this->oiRegenUsers = [];
});

afterEach(function () {
    foreach ($this->oiRegenUsers ?? [] as $u) {
        $u->forceDelete();
    }
});

it('GET não responde mais: 405, e o Artisan não é chamado', function () {
    Artisan::shouldReceive('call')->never();
    $this->actingAs(oiRegenUser($this, 'superadmin'));

    $this->get('/officeimpresso/regenerate')->assertStatus(405);
});

it('POST sem superadmin é 403, mesmo com a permissão delegável de clientes', function () {
    Artisan::shouldReceive('call')->never();
    $this->actingAs(oiRegenUser($this, 'officeimpresso.clientes.liberar'));

    $this->post('/officeimpresso/regenerate')->assertForbidden();
});

it('POST de superadmin chama passport:install --force e responde sucesso', function () {
    Artisan::shouldReceive('call')->once()->with('passport:install --force')->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('apidoc:generate')->andReturn(0);
    $this->actingAs(oiRegenUser($this, 'superadmin'));

    $this->post('/officeimpresso/regenerate')
        ->assertRedirect()
        ->assertSessionHas('status.success', 1);
});

it('POST de superadmin com falha responde success=0 e não devolve a mensagem crua', function () {
    Artisan::shouldReceive('call')->once()->with('passport:install --force')
        ->andThrow(new RuntimeException('OIREGEN-DETALHE-INTERNO'));
    $this->actingAs(oiRegenUser($this, 'superadmin'));

    $r = $this->post('/officeimpresso/regenerate')->assertRedirect();

    $r->assertSessionHas('status.success', 0);
    $this->assertStringNotContainsString('OIREGEN-DETALHE-INTERNO', (string) session('status.msg'));
});

// ── Helpers (prefixo oiRegen — outros arquivos do módulo rodam no mesmo processo) ──

/** User da operadora SEM role — senão o Gate::before faria bypass de tudo. */
function oiRegenUser($test, string $permissao): User
{
    $biz = (int) $test->oiRegenOperadora->id;
    $user = User::create([
        'business_id' => $biz,
        'first_name'  => 'OI',
        'surname'     => 'Regen',
        'username'    => 'oi_regen_'.$biz.'_'.uniqid(),
        'email'       => 'oi_regen_'.$biz.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $user->givePermissionTo($permissao);
    $test->oiRegenUsers[] = $user;

    return $user;
}
