<?php

declare(strict_types=1);

use App\Business;
use App\SupportAccessLog;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(Tests\TestCase::class);

/**
 * Modo Suporte (ADR 0305) — guarda HTTP read-only (v1 "B"): acesso · auditoria · 403.
 *
 * biz=1 operador (seededTenant — ADR 0101) · biz=99 cliente. NUNCA biz=4.
 *
 * @see app/Http/Middleware/EnsureSupportAccess.php
 * @see app/Http/Controllers/Support/SupportController.php
 */

const BIZ_OPERADOR_HTTP = 1;
const BIZ_CLIENTE_HTTP = 99;

function supportHttpReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users')
        && Schema::hasTable('business')
        && Schema::hasTable('support_agents')
        && Schema::hasTable('support_access_logs');
}

function makeHttpAgent(string $username, bool $grant = true): User
{
    $user = User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_OPERADOR_HTTP, 'first_name' => 'Ag']
    );

    if ($grant) {
        SupportAgent::query()->updateOrCreate(['user_id' => $user->id], ['granted_by' => BIZ_OPERADOR_HTTP, 'revoked_at' => null]);
    } else {
        SupportAgent::query()->where('user_id', $user->id)->delete();
    }

    return $user;
}

beforeEach(function () {
    config(['constants.operator_business_id' => BIZ_OPERADOR_HTTP]);
    config(['constants.administrator_usernames' => 'um_admin_que_nao_e_o_agente']);

    if (supportHttpReady()) {
        Business::firstOrCreate(['id' => BIZ_OPERADOR_HTTP], ['name' => 'Operador', 'currency_id' => 1]);
        Business::firstOrCreate(['id' => BIZ_CLIENTE_HTTP], ['name' => 'Cliente HTTP 99', 'currency_id' => 1]);
    }
});

it('agente acessa o cliente e o acesso é AUDITADO (entrou)', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $agent = makeHttpAgent('http_agent_ok');

    $this->actingAs($agent)
        ->get('/suporte/empresas/'.BIZ_CLIENTE_HTTP)
        ->assertInertia(fn (Assert $page) => $page->component('Suporte/Visao')->has('empresa'));

    expect(
        SupportAccessLog::query()
            ->where('support_user_id', $agent->id)
            ->where('business_id', BIZ_CLIENTE_HTTP)
            ->where('action', 'entrou')
            ->exists()
    )->toBeTrue();
});

it('agente é BLOQUEADO na operadora (403) e a negação é AUDITADA', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $agent = makeHttpAgent('http_agent_op');

    $this->actingAs($agent)
        ->get('/suporte/empresas/'.BIZ_OPERADOR_HTTP)
        ->assertStatus(403);

    expect(
        SupportAccessLog::query()
            ->where('support_user_id', $agent->id)
            ->where('business_id', BIZ_OPERADOR_HTTP)
            ->where('action', 'negado')
            ->exists()
    )->toBeTrue();
});

it('usuário SEM capability é bloqueado (403) na visão e na listagem', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $naoAgente = makeHttpAgent('http_nao_agente', grant: false);

    $this->actingAs($naoAgente)->get('/suporte/empresas/'.BIZ_CLIENTE_HTTP)->assertStatus(403);
    $this->actingAs($naoAgente)->get('/suporte/empresas')->assertStatus(403);
});

it('a listagem do agente NÃO inclui a operadora', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $agent = makeHttpAgent('http_agent_list');

    $this->actingAs($agent)
        ->get('/suporte/empresas')
        ->assertInertia(fn (Assert $page) => $page->component('Suporte/Empresas')->has('empresas'));
});
