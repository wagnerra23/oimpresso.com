<?php

declare(strict_types=1);

use App\Services\Support\SupportAccessService;
use App\Services\Support\SupportAuditService;
use App\SupportAccessLog;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (discovery morre antes do --filter).

/**
 * Modo Suporte (ADR 0305) — auditoria APPEND-ONLY (RF3) + anti-escalonamento.
 *
 * biz=1 operador (seededTenant — ADR 0101) · biz=99 cliente fictício. NUNCA biz=4.
 *
 * @see app/Services/Support/SupportAuditService.php
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */

const BIZ_OPERADOR_LOG = 1;
const BIZ_CLIENTE_LOG = 99;

function supportLogSchemaReady(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users')
        && Schema::hasTable('business')
        && Schema::hasTable('support_agents')
        && Schema::hasTable('support_access_logs');
}

function makeLogUser(string $username): User
{
    return User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => BIZ_CLIENTE_LOG, 'first_name' => 'Log']
    );
}

beforeEach(function () {
    config(['constants.operator_business_id' => BIZ_OPERADOR_LOG]);
    config(['constants.administrator_usernames' => 'um_admin_que_nao_e_o_agente']);
});

it('recordAccess grava uma linha de auditoria (RF3)', function () {
    if (! supportLogSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_access_logs ausente (ADR 0101).');
    }

    $this->seededSupportClientTenant();
    $user = makeLogUser('sup_log_user');

    $log = (new SupportAuditService())->recordAccess($user, BIZ_CLIENTE_LOG, '/suporte/entrar/99', '10.0.0.1', 'pest');

    expect($log->exists)->toBeTrue();
    expect($log->action)->toBe(SupportAuditService::ACTION_ENTROU);
    expect($log->business_id)->toBe(BIZ_CLIENTE_LOG);
    expect(
        SupportAccessLog::query()->where('support_user_id', $user->id)->where('business_id', BIZ_CLIENTE_LOG)->exists()
    )->toBeTrue();
});

it('o log é APPEND-ONLY: update é barrado', function () {
    if (! supportLogSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_access_logs ausente (ADR 0101).');
    }

    $this->seededSupportClientTenant();
    $log = (new SupportAuditService())->recordAccess(makeLogUser('sup_log_upd'), BIZ_CLIENTE_LOG);

    expect(fn () => $log->update(['action' => 'adulterado']))->toThrow(RuntimeException::class);
});

it('o log é APPEND-ONLY: delete é barrado', function () {
    if (! supportLogSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_access_logs ausente (ADR 0101).');
    }

    $this->seededSupportClientTenant();
    $log = (new SupportAuditService())->recordAccess(makeLogUser('sup_log_del'), BIZ_CLIENTE_LOG);

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});

it('recordDenied audita a tentativa negada contra a operadora', function () {
    if (! supportLogSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_access_logs ausente (ADR 0101).');
    }

    $this->seededSupportClientTenant();
    $log = (new SupportAuditService())->recordDenied(makeLogUser('sup_log_deny'), BIZ_OPERADOR_LOG, '/suporte/entrar/1');

    expect($log->action)->toBe(SupportAuditService::ACTION_NEGADO);
    expect($log->business_id)->toBe(BIZ_OPERADOR_LOG);
});

it('anti-escalonamento: agente que TAMBÉM é Admin do próprio business NÃO alcança a operadora', function () {
    if (! supportLogSchemaReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS + support_access_logs ausente (ADR 0101).');
    }

    $this->seededTenant(); // biz=1 operador
    $this->seededSupportClientTenant();

    $agent = makeLogUser('sup_admin_agent');
    SupportAgent::query()->updateOrCreate(['user_id' => $agent->id], ['granted_by' => BIZ_OPERADOR_LOG, 'revoked_at' => null]);

    // Cenário: o agente é Admin do PRÓPRIO business — o Gate::before daria `true` a qualquer
    // ability não-superadmin. Como a resolução do suporte é service-direct (não Gate), a
    // operadora segue inalcançável. (assignRole tolerante a config Spatie-teams — o assert do
    // service vale independente.)
    try {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Admin#'.BIZ_CLIENTE_LOG, 'business_id' => BIZ_CLIENTE_LOG, 'guard_name' => 'web']
        );
        $agent->assignRole($role);
    } catch (\Throwable $e) {
        // irrelevante pro assert abaixo (service não consulta role/Gate)
    }

    $svc = new SupportAccessService();
    expect($svc->canAccessBusiness($agent, BIZ_OPERADOR_LOG))->toBeFalse();
    expect($svc->canAccessBusiness($agent, BIZ_CLIENTE_LOG))->toBeTrue();
});
