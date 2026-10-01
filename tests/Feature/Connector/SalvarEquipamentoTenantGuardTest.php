<?php

/**
 * Tier 0 (ADR 0093) — POST /connector/api/salvar-equipamento/{business_id}.
 *
 * Antes do guard, qualquer token válido gravava licenca_computador no negócio
 * da URL. O desktop Delphi usa UM usuário central da WR para vários negócios
 * (medido em prod: user_id=1 em 62 negócios), então a regra é: passa o dono
 * do negócio OU um id de `connector.delphi_master_user_ids`.
 *
 * Exercita o controller direto (o guard `auth:api` exige as chaves OAuth que
 * a lane não tem) + prova que a ROTA aponta pro método com o guard.
 */

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Connector\Http\Controllers\Api\LicencaComputadorController;
use Modules\Officeimpresso\Entities\Licenca_Computador;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Connector + UltimatePOS exigem schema MySQL');
    }
    if (! Schema::hasTable('licenca_computador') || DB::table('business')->whereIn('id', [1, 2])->count() !== 2) {
        $this->markTestSkipped('Seed biz=1/biz=2 ausente');
    }
});

function equipamentoGuardUser(int $id, int $businessId): User
{
    $user = new User();
    $user->forceFill(['id' => $id, 'business_id' => $businessId]);

    return $user;
}

function equipamentoGuardCall(User $user, int $businessId, string $hd)
{
    $request = Request::create("/connector/api/salvar-equipamento/{$businessId}", 'POST', [
        'HD' => $hd,
        'DESCRICAO' => 'guard-test',
    ]);
    $request->setUserResolver(fn () => $user);

    return app(LicencaComputadorController::class)->saveEquipamentoRota($request, $businessId);
}

it('a rota salvar-equipamento aponta pro método com o guard', function () {
    $route = app('router')->getRoutes()->getByName('connector.delphi.salvar-equipamento');

    expect($route)->not->toBeNull();
    expect($route->getActionMethod())->toBe('saveEquipamentoRota');
    expect($route->gatherMiddleware())->toContain('auth:api');
});

it('token de OUTRO negócio não grava equipamento (cross-tenant 2 → 1)', function () {
    config(['connector.delphi_master_user_ids' => [1]]);
    $hd = 'GUARD-X-' . uniqid();

    $r = equipamentoGuardCall(equipamentoGuardUser(990002, 2), 1, $hd);

    expect($r->getStatusCode())->toBe(403);
    expect(str_starts_with($r->getContent(), 'N;'))->toBeTrue();
    expect(DB::table('licenca_computador')->where('hd', $hd)->count())->toBe(0);
});

it('negócio inexistente na URL é recusado mesmo para o usuário central', function () {
    config(['connector.delphi_master_user_ids' => [990001]]);
    $hd = 'GUARD-N-' . uniqid();

    $r = equipamentoGuardCall(equipamentoGuardUser(990001, 1), 987654321, $hd);

    expect($r->getStatusCode())->toBe(403);
    expect(DB::table('licenca_computador')->where('hd', $hd)->count())->toBe(0);
});

it('dono do negócio grava no próprio negócio', function () {
    config(['connector.delphi_master_user_ids' => []]);
    $hd = 'GUARD-O-' . uniqid();

    $r = equipamentoGuardCall(equipamentoGuardUser(990003, 2), 2, $hd);

    expect($r)->toBeInstanceOf(Licenca_Computador::class);
    expect((int) DB::table('licenca_computador')->where('hd', $hd)->value('business_id'))->toBe(2);
});

it('usuário central WR grava em outro negócio (fluxo Delphi preservado)', function () {
    config(['connector.delphi_master_user_ids' => [990001]]);
    $hd = 'GUARD-M-' . uniqid();

    $r = equipamentoGuardCall(equipamentoGuardUser(990001, 1), 2, $hd);

    expect($r)->toBeInstanceOf(Licenca_Computador::class);
    expect((int) DB::table('licenca_computador')->where('hd', $hd)->value('business_id'))->toBe(2);
});
