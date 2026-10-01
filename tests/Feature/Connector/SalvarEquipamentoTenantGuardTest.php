<?php

/**
 * Tier 0 (ADR 0093) — POST /connector/api/salvar-equipamento/{business_id}.
 *
 * Antes do guard, qualquer token válido gravava licenca_computador no negócio
 * da URL. O desktop Delphi usa UM usuário central da WR para vários negócios
 * (medido em prod: user_id=1 em 62 negócios), então a regra é: passa o dono
 * do negócio OU um id de `connector.delphi_master_user_ids`.
 */

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

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

function equipamentoGuardPost($test, int $businessId, string $hd)
{
    return $test->withHeaders(['Accept' => 'application/json'])
        ->postJson("/connector/api/salvar-equipamento/{$businessId}", [
            'HD' => $hd,
            'DESCRICAO' => 'guard-test',
        ]);
}

it('token de OUTRO negócio não grava equipamento (cross-tenant 2 → 1)', function () {
    config(['connector.delphi_master_user_ids' => [1]]);
    Passport::actingAs(equipamentoGuardUser(990002, 2), [], 'api');
    $hd = 'GUARD-X-' . uniqid();

    $r = equipamentoGuardPost($this, 1, $hd);

    expect($r->getStatusCode())->toBe(403);
    expect(str_starts_with($r->getContent(), 'N;'))->toBeTrue();
    expect(DB::table('licenca_computador')->where('hd', $hd)->count())->toBe(0);
});

it('negócio inexistente na URL é recusado mesmo para o usuário central', function () {
    config(['connector.delphi_master_user_ids' => [990001]]);
    Passport::actingAs(equipamentoGuardUser(990001, 1), [], 'api');
    $hd = 'GUARD-N-' . uniqid();

    $r = equipamentoGuardPost($this, 987654321, $hd);

    expect($r->getStatusCode())->toBe(403);
    expect(DB::table('licenca_computador')->where('hd', $hd)->count())->toBe(0);
});

it('dono do negócio grava no próprio negócio', function () {
    config(['connector.delphi_master_user_ids' => []]);
    Passport::actingAs(equipamentoGuardUser(990003, 2), [], 'api');
    $hd = 'GUARD-O-' . uniqid();

    $r = equipamentoGuardPost($this, 2, $hd);

    expect($r->getStatusCode())->toBe(200);
    expect((int) DB::table('licenca_computador')->where('hd', $hd)->value('business_id'))->toBe(2);
});

it('usuário central WR grava em outro negócio (fluxo Delphi preservado)', function () {
    config(['connector.delphi_master_user_ids' => [990001]]);
    Passport::actingAs(equipamentoGuardUser(990001, 1), [], 'api');
    $hd = 'GUARD-M-' . uniqid();

    $r = equipamentoGuardPost($this, 2, $hd);

    expect($r->getStatusCode())->toBe(200);
    expect((int) DB::table('licenca_computador')->where('hd', $hd)->value('business_id'))->toBe(2);
});
