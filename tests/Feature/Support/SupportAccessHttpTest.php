<?php

declare(strict_types=1);

use App\SupportAccessLog;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (discovery morre antes do --filter).

/**
 * Modo Suporte (ADR 0305) — guarda HTTP do middleware EnsureSupportAccess (v1 "B").
 *
 * Exercita o middleware via rota sintética (sem controller/Inertia ainda — as telas + rotas
 * reais vêm na PR-C2 como unidade design-gated, com aprovação visual). Prova: acesso ao
 * cliente passa + é auditado; operadora 403 + negação auditada; sem capability 403.
 *
 * biz=1 operador (seededTenant — ADR 0101) · biz=99 cliente. NUNCA biz=4.
 *
 * @see app/Http/Middleware/EnsureSupportAccess.php
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

function makeHttpAgent(string $username, bool $grant = true, int $businessId = BIZ_OPERADOR_HTTP): User
{
    $user = User::firstOrCreate(
        ['username' => $username],
        ['email' => $username.'@test.local', 'password' => bcrypt('x'), 'business_id' => $businessId, 'first_name' => 'Ag']
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

    // Rotas sintéticas protegidas só pelo middleware sob teste (sem controller/Inertia).
    Route::middleware('support.access')->get('/__suporte_probe/{business}', fn () => response('ok'))->whereNumber('business');
    Route::middleware('support.access')->get('/__suporte_probe', fn () => response('list'));
});

it('agente acessa o cliente (200) e o acesso é AUDITADO (entrou)', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->seededSupportClientTenant();
    $agent = makeHttpAgent('http_agent_ok');

    $this->actingAs($agent)->get('/__suporte_probe/'.BIZ_CLIENTE_HTTP)->assertStatus(200);

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

    $this->actingAs($agent)->get('/__suporte_probe/'.BIZ_OPERADOR_HTTP)->assertStatus(403);

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

    // Sem capability = usuário de CLIENTE (biz≠operadora) sem concessão. NÃO pode ser
    // biz=1: pela ADR 0309 todo usuário da operadora já é agente (senão o teste mentiria).
    $this->seededSupportClientTenant();
    $naoAgente = makeHttpAgent('http_nao_agente', grant: false, businessId: BIZ_CLIENTE_HTTP);

    $this->actingAs($naoAgente)->get('/__suporte_probe/'.BIZ_CLIENTE_HTTP)->assertStatus(403);
    $this->actingAs($naoAgente)->get('/__suporte_probe')->assertStatus(403);
});

it('agente autorizado passa na listagem (sem param business, sem auditoria)', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $agent = makeHttpAgent('http_agent_list');

    $this->actingAs($agent)->get('/__suporte_probe')->assertStatus(200);
});

it('usuário da operadora (biz=1) é agente SEM concessão explícita (ADR 0309)', function () {
    if (! supportHttpReady()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    // biz=1 (operadora) e SEM linha em support_agents — passa só pela regra de membership.
    $this->seededSupportClientTenant();
    $operadorStaff = makeHttpAgent('http_operador_staff', grant: false, businessId: BIZ_OPERADOR_HTTP);

    $this->actingAs($operadorStaff)->get('/__suporte_probe/'.BIZ_CLIENTE_HTTP)->assertStatus(200);
});
