<?php

declare(strict_types=1);

use App\Business;
use App\Services\Support\SupportAccessService;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (discovery morre antes do --filter).

/**
 * Modo Suporte (ADR 0305) — invariantes Tier 0 da resolução de tenants acessíveis.
 *
 * Regra-mestre: `suporte ⊂ (todas as empresas \ operador)`. biz=1 (operador, via
 * seededTenant — ADR 0101) · biz=99 (cliente fictício). NUNCA biz=4 (cliente real prod).
 *
 * @see app/Services/Support/SupportAccessService.php
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */

const BIZ_OPERADOR_SUP = 1;
const BIZ_CLIENTE_SUP = 99;

function supportSchemaReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users') && Schema::hasTable('business') && Schema::hasTable('support_agents');
}

function makeSupportAgentUser(string $username): User
{
    $user = User::firstOrCreate(
        ['username' => $username],
        [
            'email'       => $username.'@test.local',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_CLIENTE_SUP,
            'first_name'  => 'Sup',
        ]
    );

    SupportAgent::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['granted_by' => BIZ_OPERADOR_SUP, 'revoked_at' => null, 'reason' => 'teste']
    );

    return $user;
}

beforeEach(function () {
    config(['constants.operator_business_id' => BIZ_OPERADOR_SUP]);
    // Agente NÃO está na lista de superadmin — base do "sem escalonamento".
    config(['constants.administrator_usernames' => 'um_admin_que_nao_e_o_agente']);
});

// ── Invariantes puras (qualquer driver): a operadora NUNCA é alcançável ──────────

it('operatorBusinessId vem do config (fonte única, default 1)', function () {
    expect((new SupportAccessService())->operatorBusinessId())->toBe(1);

    config(['constants.operator_business_id' => 7]);
    expect((new SupportAccessService())->operatorBusinessId())->toBe(7);
});

it('canAccessBusiness nega a operadora ANTES de qualquer consulta (protege o operador)', function () {
    expect((new SupportAccessService())->canAccessBusiness(123, BIZ_OPERADOR_SUP))->toBeFalse();

    config(['constants.operator_business_id' => 50]);
    expect((new SupportAccessService())->canAccessBusiness(123, 50))->toBeFalse();
});

// ── Invariantes com schema (MySQL UltimatePOS) ───────────────────────────────────

it('accessibleBusinessIds inclui o cliente e EXCLUI a operadora', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    $this->seededTenant(); // garante biz=1 (operador)
    Business::firstOrCreate(['id' => BIZ_CLIENTE_SUP], ['name' => 'Cliente Sup 99', 'currency_id' => 1]);

    $ids = (new SupportAccessService())->accessibleBusinessIds();

    expect($ids)->toContain(BIZ_CLIENTE_SUP);
    expect($ids)->not->toContain(BIZ_OPERADOR_SUP);
});

it('agente de suporte ALCANÇA o cliente mas NÃO a operadora', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_SUP], ['name' => 'Cliente Sup 99', 'currency_id' => 1]);
    $agent = makeSupportAgentUser('sup_agent_ok');

    $svc = new SupportAccessService();
    expect($svc->canAccessBusiness($agent, BIZ_CLIENTE_SUP))->toBeTrue();
    expect($svc->canAccessBusiness($agent, BIZ_OPERADOR_SUP))->toBeFalse();
});

it('usuário SEM capability de suporte não alcança o cliente', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    Business::firstOrCreate(['id' => BIZ_CLIENTE_SUP], ['name' => 'Cliente Sup 99', 'currency_id' => 1]);
    $naoAgente = User::firstOrCreate(
        ['username' => 'sup_nao_agente'],
        ['email' => 'sup_nao_agente@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_CLIENTE_SUP, 'first_name' => 'No']
    );
    SupportAgent::query()->where('user_id', $naoAgente->id)->delete();

    expect((new SupportAccessService())->canAccessBusiness($naoAgente, BIZ_CLIENTE_SUP))->toBeFalse();
});

it('revogar a concessão tira o acesso (isSupportAgent vira false)', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    $agent = makeSupportAgentUser('sup_agent_revoke');
    $svc = new SupportAccessService();

    expect($svc->isSupportAgent($agent))->toBeTrue();

    SupportAgent::query()->where('user_id', $agent->id)->update(['revoked_at' => now()]);
    expect($svc->isSupportAgent($agent))->toBeFalse();
});

it('usuário da operadora (biz=1) é agente SEM concessão explícita (ADR 0309)', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_SUP], ['name' => 'Cliente Sup 99', 'currency_id' => 1]);

    // biz=1 (operadora) + NENHUMA linha em support_agents — agente só por membership.
    $operadorStaff = User::firstOrCreate(
        ['username' => 'sup_operador_staff'],
        ['email' => 'sup_operador_staff@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_OPERADOR_SUP, 'first_name' => 'Op']
    );
    SupportAgent::query()->where('user_id', $operadorStaff->id)->delete();

    $svc = new SupportAccessService();
    expect($svc->isSupportAgent($operadorStaff))->toBeTrue();                          // membership
    expect($svc->canAccessBusiness($operadorStaff, BIZ_CLIENTE_SUP))->toBeTrue();      // alcança cliente
    expect($svc->canAccessBusiness($operadorStaff, BIZ_OPERADOR_SUP))->toBeFalse();    // nunca a própria operadora
});

it('agente de suporte NÃO escala pra superadmin (fora de ADMINISTRATOR_USERNAMES)', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    $agent = makeSupportAgentUser('sup_agent_noesc');

    // Gate::before só dá superadmin a quem está em administrator_usernames — o agente não está.
    expect($agent->can('superadmin'))->toBeFalse();
    expect($agent->can('backup'))->toBeFalse();
    expect($agent->can('manage_modules'))->toBeFalse();
});

it('exclusão do operador é dirigida por config (muda o config, muda a exclusão)', function () {
    if (! supportSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_agents ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_SUP], ['name' => 'Cliente Sup 99', 'currency_id' => 1]);

    config(['constants.operator_business_id' => BIZ_CLIENTE_SUP]); // finge que 99 é o operador
    $ids = (new SupportAccessService())->accessibleBusinessIds();

    expect($ids)->not->toContain(BIZ_CLIENTE_SUP);
    expect($ids)->toContain(BIZ_OPERADOR_SUP);
});
