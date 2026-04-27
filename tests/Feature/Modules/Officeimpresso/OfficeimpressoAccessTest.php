<?php

/**
 * Modules\Officeimpresso — controle de acesso & tenancy.
 *
 * Regra de negócio (ADRs 0017-0021): Officeimpresso só deve ser
 * operado por superadmin (administrador WR2). Usuários comuns nem
 * devem ver licenças que NÃO sejam do business deles.
 *
 * IMPORTANTE: o contrato Delphi (cliente offline) é IMUTÁVEL —
 * `feedback_delphi_contrato_imutavel`. Estes testes NÃO devem
 * forçar mudança em endpoints/payloads consumidos pelo Delphi.
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\Schema;
use Modules\Officeimpresso\Entities\Licenca_Computador;

beforeEach(function () {
    if (!Schema::hasTable('licenca_computador')) {
        $this->markTestSkipped('Migrações do módulo Officeimpresso não rodadas no ambiente de teste.');
    }
});

it('redireciona usuário não autenticado para login', function () {
    $response = $this->get('/officeimpresso/licenca_computador');
    expect($response->status())->toBeIn([302, 401]);
});

it('aplica stack de middleware UltimatePOS no grupo /officeimpresso', function () {
    $middleware = routeMiddleware('officeimpresso/licenca_computador', 'GET');

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('SetSessionData')
        ->and($middleware)->toContain('language')
        ->and($middleware)->toContain('AdminSidebarMenu');
});

it('isola licenças por business_id (multi-tenant)', function () {
    $b1 = $this->makeBusiness(['name' => 'Empresa A']);
    $b2 = $this->makeBusiness(['name' => 'Empresa B']);

    $own = Licenca_Computador::create([
        'business_id' => $b1->id,
        'identificador' => 'PC-A-001',
    ]);

    $other = Licenca_Computador::create([
        'business_id' => $b2->id,
        'identificador' => 'PC-B-999',
    ]);

    $userA = User::create([
        'business_id' => $b1->id,
        'surname' => 'Sr.',
        'first_name' => 'Dono',
        'last_name' => 'A',
        'username' => 'dono_a_' . uniqid(),
        'email' => uniqid() . '@a.local',
        'password' => bcrypt('x'),
        'language' => 'pt',
        'allow_login' => 1,
    ]);

    $response = $this->actingAs($userA)
        ->withSession(['user' => ['business_id' => $b1->id]])
        ->get('/officeimpresso/licenca_computador');

    $response->assertOk();
    $response->assertSee($own->identificador);
    $response->assertDontSee($other->identificador);
});

it('expõe rotas de licenca_computador esperadas pelo cliente Delphi (CONTRATO IMUTÁVEL)', function () {
    expect(routeExists('officeimpresso/licenca_computador', 'GET'))->toBeTrue()
        ->and(routeExists('officeimpresso/licenca_computador', 'POST'))->toBeTrue()
        ->and(routeExists('officeimpresso/licenca_computador/{licenca_computador}', 'PUT'))->toBeTrue()
        ->and(routeExists('officeimpresso/licenca_computador/{licenca_computador}', 'DELETE'))->toBeTrue();
});

it('preserva endpoint legado businessall (consumido pelo Delphi)', function () {
    expect(routeExists('officeimpresso/businessall', 'GET'))->toBeTrue();
});

it('mantém toggle-block que bloqueia uma licença remota', function () {
    expect(moduleRoute('licenca_computador.toggleBlock'))->not->toBeNull();
});

it('protege rotas de install com middleware authh+CheckUserLogin', function () {
    $middleware = routeMiddleware('officeimpresso/install', 'GET');

    expect($middleware)->toContain('auth')
        ->and($middleware)->toContain('authh')
        ->and($middleware)->toContain('CheckUserLogin');
});
