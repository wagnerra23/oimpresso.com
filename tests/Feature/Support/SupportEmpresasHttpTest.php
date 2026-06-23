<?php

declare(strict_types=1);

use App\Business;
use App\Services\Support\SupportAccessService;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(Tests\TestCase::class);

/**
 * Modo Suporte (ADR 0305) — tela Suporte/Empresas (lista read-only).
 *
 * Cobre os UCs de resources/js/Pages/Suporte/Empresas.casos.md (G-2: cada UC citado aqui).
 * biz=1 operador (seededTenant — ADR 0101) · biz=99 cliente. NUNCA biz=4.
 *
 * @see app/Http/Controllers/Support/SupportController.php
 * @see resources/js/Pages/Suporte/Empresas.casos.md
 */

const BIZ_OPERADOR_EMP = 1;
const BIZ_CLIENTE_EMP = 99;

function empSchemaReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users') && Schema::hasTable('business') && Schema::hasTable('support_agents');
}

function makeEmpAgent(string $username, bool $grant = true): User
{
    $user = User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_OPERADOR_EMP, 'first_name' => 'Ag']
    );

    if ($grant) {
        SupportAgent::query()->updateOrCreate(['user_id' => $user->id], ['granted_by' => BIZ_OPERADOR_EMP, 'revoked_at' => null]);
    } else {
        SupportAgent::query()->where('user_id', $user->id)->delete();
    }

    return $user;
}

beforeEach(function () {
    config(['constants.operator_business_id' => BIZ_OPERADOR_EMP]);
    config(['constants.administrator_usernames' => 'um_admin_que_nao_e_o_agente']);
});

it('UC-SUP-01 · agente vê a lista de empresas-cliente acessíveis', function () {
    if (! empSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_EMP], ['name' => 'Cliente Emp 99', 'currency_id' => 1]);
    $agent = makeEmpAgent('emp_agent_ok');

    $this->actingAs($agent)
        ->get('/suporte/empresas')
        ->assertInertia(fn (Assert $page) => $page->component('Suporte/Empresas')->has('empresas'));
});

it('UC-SUP-02 · operadora ausente — a resolução que alimenta a tela exclui a biz=1', function () {
    if (! empSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_EMP], ['name' => 'Cliente Emp 99', 'currency_id' => 1]);

    $ids = (new SupportAccessService())->accessibleBusinessIds();

    expect($ids)->not->toContain(BIZ_OPERADOR_EMP);
    expect($ids)->toContain(BIZ_CLIENTE_EMP);
});

it('UC-SUP-03 · sem capability 403 — usuário sem suporte é bloqueado na listagem', function () {
    if (! empSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $naoAgente = makeEmpAgent('emp_nao_agente', grant: false);

    $this->actingAs($naoAgente)->get('/suporte/empresas')->assertStatus(403);
});
