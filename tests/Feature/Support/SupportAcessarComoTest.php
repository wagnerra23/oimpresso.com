<?php

declare(strict_types=1);

use App\Business;
use App\Services\Support\SupportAccessService;
use App\SupportAccessLog;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (discovery morre antes do --filter).

/**
 * Modo Suporte fase A (ADR 0308) — "Acessar como" (login-as guardado). Invariantes Tier 0.
 *
 * Regra: o agente vira QUALQUER usuário do cliente (incl. Admin), mas NUNCA a operadora
 * (biz=1) nem um superadmin/admin-username; alvo precisa estar ativo; tudo auditado.
 * biz=1 operador (seededTenant — ADR 0101) · biz=99 cliente. NUNCA biz=4 (cliente real prod).
 *
 * @see app/Services/Support/SupportAccessService.php (canImpersonate)
 * @see app/Http/Controllers/Support/SupportController.php (acessarComo)
 * @see memory/decisions/0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md
 */

const BIZ_OPERADOR_ACO = 1;
const BIZ_CLIENTE_ACO = 99;

function acoSchemaReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users')
        && Schema::hasTable('business')
        && Schema::hasTable('support_agents')
        && Schema::hasTable('support_access_logs')
        && Schema::hasColumn('support_access_logs', 'target_user_id');
}

function makeAcoAgent(string $username, bool $grant = true, int $businessId = BIZ_OPERADOR_ACO): User
{
    $user = User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => $businessId, 'first_name' => 'Ag']
    );

    if ($grant) {
        SupportAgent::query()->updateOrCreate(['user_id' => $user->id], ['granted_by' => BIZ_OPERADOR_ACO, 'revoked_at' => null]);
    } else {
        SupportAgent::query()->where('user_id', $user->id)->delete();
    }

    return $user;
}

/** @param  array<string,mixed>  $overrides */
function makeAcoUser(string $username, int $businessId, array $overrides = []): User
{
    return User::firstOrCreate(
        ['username' => $username],
        array_merge([
            'email'       => $username.'@test.local',
            'password'    => bcrypt('x'),
            'business_id' => $businessId,
            'first_name'  => 'Cli',
            'status'      => 'active',
            'allow_login' => 1,
        ], $overrides)
    );
}

beforeEach(function () {
    config(['constants.operator_business_id' => BIZ_OPERADOR_ACO]);
    // O agente NÃO está aqui; o "dono" (superadmin) está — base do "sem escalonamento".
    config(['constants.administrator_usernames' => 'o_dono_superadmin']);
});

// ── canImpersonate (trava Tier 0, ponto único) ───────────────────────────────────

it('canImpersonate LIBERA usuário comum, ativo, de empresa-cliente acessível', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    $agent = makeAcoAgent('aco_agent_ok');
    $alvo = makeAcoUser('aco_alvo_comum', BIZ_CLIENTE_ACO);

    expect((new SupportAccessService())->canImpersonate($agent, $alvo))->toBeTrue();
});

it('canImpersonate NEGA alvo na operadora (biz=1) — protege o operador', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    $agent = makeAcoAgent('aco_agent_biz1');
    $alvoOperador = makeAcoUser('aco_alvo_operador', BIZ_OPERADOR_ACO);

    expect((new SupportAccessService())->canImpersonate($agent, $alvoOperador))->toBeFalse();
});

it('canImpersonate NEGA alvo superadmin (username em administrator_usernames) — sem escalonamento', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    $agent = makeAcoAgent('aco_agent_noesc');
    // alvo cujo username está na lista de superadmin, ainda que dentro do cliente
    $alvoSuper = makeAcoUser('o_dono_superadmin', BIZ_CLIENTE_ACO);

    expect((new SupportAccessService())->canImpersonate($agent, $alvoSuper))->toBeFalse();
});

it('canImpersonate NEGA alvo inativo / sem allow_login', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    $agent = makeAcoAgent('aco_agent_inativo');
    $inativo = makeAcoUser('aco_alvo_inativo', BIZ_CLIENTE_ACO, ['status' => 'inactive']);
    $semLogin = makeAcoUser('aco_alvo_semlogin', BIZ_CLIENTE_ACO, ['allow_login' => 0]);

    $svc = new SupportAccessService();
    expect($svc->canImpersonate($agent, $inativo))->toBeFalse();
    expect($svc->canImpersonate($agent, $semLogin))->toBeFalse();
});

it('canImpersonate NEGA quando o iniciador não é agente de suporte', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    // Não-agente = usuário de CLIENTE (biz≠operadora) sem concessão. NÃO pode ser biz=1:
    // pela ADR 0309 todo usuário da operadora já é agente (senão o teste mentiria).
    $naoAgente = makeAcoAgent('aco_nao_agente', grant: false, businessId: BIZ_CLIENTE_ACO);
    $alvo = makeAcoUser('aco_alvo_p_naoagente', BIZ_CLIENTE_ACO);

    expect((new SupportAccessService())->canImpersonate($naoAgente, $alvo))->toBeFalse();
});

// ── HTTP: POST /suporte/empresas/{business}/acessar-como/{user} ───────────────────

it('UC-SUP-04 · agente acessa-como usuário do cliente → loga como ele + audita acessou_como', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    $agent = makeAcoAgent('aco_http_ok');
    $alvo = makeAcoUser('aco_http_alvo', BIZ_CLIENTE_ACO);

    $this->actingAs($agent)
        ->post("/suporte/empresas/".BIZ_CLIENTE_ACO."/acessar-como/{$alvo->id}")
        ->assertRedirect(route('home'));

    // virou o usuário do cliente (login-as completo)
    $this->assertAuthenticatedAs($alvo->fresh());

    // RF3: a impersonação ficou registrada com o alvo explícito
    expect(SupportAccessLog::query()
        ->where('support_user_id', $agent->id)
        ->where('business_id', BIZ_CLIENTE_ACO)
        ->where('target_user_id', $alvo->id)
        ->where('action', 'acessou_como')
        ->exists())->toBeTrue();
});

it('UC-SUP-05 · acessar-como na operadora (biz=1) é 403 — barrado no nível-empresa', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    $agent = makeAcoAgent('aco_http_biz1');
    $alvoOperador = makeAcoUser('aco_http_operador', BIZ_OPERADOR_ACO);

    $this->actingAs($agent)
        ->post("/suporte/empresas/".BIZ_OPERADOR_ACO."/acessar-como/{$alvoOperador->id}")
        ->assertStatus(403);

    // não trocou a identidade
    $this->assertAuthenticatedAs($agent->fresh());
});

it('UC-SUP-06 · acessar-como um superadmin do cliente é 403 + negação auditada', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    $agent = makeAcoAgent('aco_http_noesc');
    $alvoSuper = makeAcoUser('o_dono_superadmin', BIZ_CLIENTE_ACO);

    $this->actingAs($agent)
        ->post("/suporte/empresas/".BIZ_CLIENTE_ACO."/acessar-como/{$alvoSuper->id}")
        ->assertStatus(403);

    $this->assertAuthenticatedAs($agent->fresh());

    expect(SupportAccessLog::query()
        ->where('support_user_id', $agent->id)
        ->where('target_user_id', $alvoSuper->id)
        ->where('action', 'negado')
        ->exists())->toBeTrue();
});

it('UC-SUP-07 · tela Visao renderiza resumo + usuários da empresa-cliente', function () {
    if (! acoSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededTenant();
    Business::firstOrCreate(['id' => BIZ_CLIENTE_ACO], ['name' => 'Cliente ACO 99', 'currency_id' => 1]);
    $agent = makeAcoAgent('aco_show_ok');
    makeAcoUser('aco_show_user', BIZ_CLIENTE_ACO);

    $this->actingAs($agent)
        ->get('/suporte/empresas/'.BIZ_CLIENTE_ACO)
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Suporte/Visao')
            ->has('empresa')
            ->has('contagens')
            ->has('usuarios'));
});
