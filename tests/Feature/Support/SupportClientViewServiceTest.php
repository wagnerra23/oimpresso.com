<?php

declare(strict_types=1);

use App\Business;
use App\Services\Support\SupportClientViewService;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Modo Suporte (ADR 0305) — montagem READ-ONLY da visão do cliente (business_id explícito).
 *
 * Invariante central: a operadora é INALCANÇÁVEL e o resumo vem do cliente X (nunca da
 * sessão/auth-user). biz=1 operador (seededTenant — ADR 0101) · biz=99 cliente. NUNCA biz=4.
 *
 * @see app/Services/Support/SupportClientViewService.php
 */

const BIZ_OPERADOR_VIEW = 1;
const BIZ_CLIENTE_VIEW = 99;

function viewSchemaReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users') && Schema::hasTable('business') && Schema::hasTable('support_agents');
}

function makeViewAgent(string $username, bool $grant = true): User
{
    $user = User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_OPERADOR_VIEW, 'first_name' => 'Ag']
    );

    if ($grant) {
        SupportAgent::query()->updateOrCreate(['user_id' => $user->id], ['granted_by' => BIZ_OPERADOR_VIEW, 'revoked_at' => null]);
    } else {
        SupportAgent::query()->where('user_id', $user->id)->delete();
    }

    return $user;
}

beforeEach(function () {
    config(['constants.operator_business_id' => BIZ_OPERADOR_VIEW]);
    config(['constants.administrator_usernames' => 'um_admin_que_nao_e_o_agente']);
});

it('recusa a empresa operadora (biz=1) — protege o operador', function () {
    if (! viewSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $agent = makeViewAgent('view_agent_op');

    expect(fn () => (new SupportClientViewService(app(\App\Services\Support\SupportAccessService::class)))
        ->clientSummary($agent, BIZ_OPERADOR_VIEW))
        ->toThrow(RuntimeException::class);
});

it('recusa quem não tem capability de suporte', function () {
    if (! viewSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_VIEW], ['name' => 'Cliente View 99', 'currency_id' => 1]);
    $naoAgente = makeViewAgent('view_nao_agente', grant: false);

    expect(fn () => (new SupportClientViewService(app(\App\Services\Support\SupportAccessService::class)))
        ->clientSummary($naoAgente, BIZ_CLIENTE_VIEW))
        ->toThrow(RuntimeException::class);
});

it('monta o resumo do cliente X (empresa correta + contagens numéricas, scopadas por X)', function () {
    if (! viewSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_VIEW], ['name' => 'Cliente View 99', 'currency_id' => 1]);
    $agent = makeViewAgent('view_agent_ok');

    $resumo = (new SupportClientViewService(app(\App\Services\Support\SupportAccessService::class)))
        ->clientSummary($agent, BIZ_CLIENTE_VIEW);

    expect($resumo['empresa']['id'])->toBe(BIZ_CLIENTE_VIEW);
    expect($resumo['contagens'])->toHaveKeys(['usuarios', 'contatos', 'produtos', 'vendas', 'compras']);
    foreach ($resumo['contagens'] as $v) {
        expect($v)->toBeInt();
        expect($v)->toBeGreaterThanOrEqual(0);
    }
});

it('a contagem de usuários é scopada ao cliente X (não global)', function () {
    if (! viewSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_VIEW], ['name' => 'Cliente View 99', 'currency_id' => 1]);
    $agent = makeViewAgent('view_agent_scope');

    // Um usuário do cliente X (além do agente, que é da operadora biz=1).
    User::firstOrCreate(
        ['username' => 'view_cliente_user'],
        ['email' => 'view_cliente_user@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_CLIENTE_VIEW, 'first_name' => 'Cli']
    );

    $svc = new SupportClientViewService(app(\App\Services\Support\SupportAccessService::class));
    $resumoCliente = $svc->clientSummary($agent, BIZ_CLIENTE_VIEW)['contagens']['usuarios'];

    // Contagem direta cross-tenant pra comparar (a do cliente NÃO inclui os da operadora).
    $usuariosCliente = (int) DB::table('users')->where('business_id', BIZ_CLIENTE_VIEW)->count();
    $usuariosOperador = (int) DB::table('users')->where('business_id', BIZ_OPERADOR_VIEW)->count();

    expect($resumoCliente)->toBe($usuariosCliente);
    expect($resumoCliente)->toBeGreaterThanOrEqual(1);
    // Prova de scoping: o resumo do cliente ignora os usuários da operadora.
    expect($resumoCliente)->not->toBe($usuariosCliente + $usuariosOperador);
});
