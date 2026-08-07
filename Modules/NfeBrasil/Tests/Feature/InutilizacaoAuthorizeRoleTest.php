<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * US-GOV-059 classe A — `fiscal.inutilizar` é ROLE, não permissão.
 *
 * O `CancelarNfeRequest::authorize()` fazia `can('fiscal.inutilizar')`, e esse
 * nome não existe como permissão Spatie em fonte nenhuma: quem o cria é o
 * `NfeFiscalActionsSeeder`, como ROLE per-business (sufixo `#{business_id}`, a
 * convenção UltimatePOS) pra autorizar a action FSM crítica `inutilizar_faixa`.
 * `can()` procura PERMISSÃO e nunca casa com role homônima ⇒ o gate caía sempre
 * em false e só o superadmin passava, pelo `Gate::before` — contra a intenção
 * declarada no docblock do Controller e no comentário da rota.
 *
 * COMO ESTE TESTE PROVA SEM FALAR COM A SEFAZ: o payload é inválido de
 * propósito. `authorize()` roda ANTES da validação, então:
 *   · sem a role  → 403 (o gate barrou)
 *   · com a role  → 422 (o gate PASSOU e a validação barrou)
 * O 422 é a prova positiva: chegou-se além da autorização sem jamais atingir o
 * `NfeInutilizacaoService::inutilizar()`, que é quem chama a SEFAZ.
 *
 * @see Modules/NfeBrasil/Http/Requests/CancelarNfeRequest.php
 * @see database/seeders/NfeFiscalActionsSeeder.php (cria a role com sufixo)
 * @see memory/requisitos/Governance/SPEC.md — US-GOV-059
 */

const INUT_ROTA = '/nfe-brasil/inutilizacoes';

/** Payload deliberadamente inválido: `modelo` fora do enum SEFAZ (55|65). */
const INUT_PAYLOAD_INVALIDO = [
    'modelo' => '99',
    'serie' => '1',
    'numero_de' => 1,
    'numero_ate' => 2,
    'justificativa' => 'justificativa com mais de quinze caracteres pra passar ABRASF',
];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil exige schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('nfe_inutilizacoes')) {
        $this->markTestSkipped('nfe_inutilizacoes ausente — rode as migrations do NfeBrasil');
    }
});

/** Cria a role no formato que o NfeFiscalActionsSeeder usa (sufixo só se a coluna existir). */
function inutRoleFiscal(int $businessId): Role
{
    $temColuna = Schema::hasColumn('roles', 'business_id');
    $nome = $temColuna ? "fiscal.inutilizar#{$businessId}" : 'fiscal.inutilizar';

    $attrs = ['name' => $nome, 'guard_name' => 'web'];
    if ($temColuna) {
        $attrs['business_id'] = $businessId;
    }

    return Role::firstOrCreate($attrs);
}

/** Usuário comum do business (sem role, sem superadmin). */
function inutUsuarioDe(int $businessId): App\User
{
    return App\User::where('business_id', $businessId)->firstOrFail();
}

it('BARRA quem não tem a role fiscal.inutilizar (era o caso de TODOS, pois can() nunca casava)', function () {
    $user = inutUsuarioDe(1);
    $user->roles()->detach();

    $this->actingAs($user)
        ->withSession(['business.id' => 1, 'user.business_id' => 1])
        ->postJson(INUT_ROTA, INUT_PAYLOAD_INVALIDO)
        ->assertStatus(403);
});

it('LIBERA quem tem a role — 422 da validação prova que passou do gate sem tocar a SEFAZ', function () {
    $user = inutUsuarioDe(1);
    $user->roles()->detach();
    $user->assignRole(inutRoleFiscal(1));

    $this->actingAs($user)
        ->withSession(['business.id' => 1, 'user.business_id' => 1])
        ->postJson(INUT_ROTA, INUT_PAYLOAD_INVALIDO)
        ->assertStatus(422);
});

it('CROSS-TENANT: role de OUTRO business não autoriza (Tier 0 · ADR 0093)', function () {
    if (! Schema::hasColumn('roles', 'business_id')) {
        $this->markTestSkipped('sem roles.business_id não há sufixo — o cross-tenant não se aplica');
    }

    // O "outro business" é o 2, NÃO o 99: `roles.business_id` tem FK pra
    // `business`, e o `pest-mysql-setup` da lane semeia apenas biz=1 e biz=2.
    // Criar a role apontando pra um business inexistente estoura QueryException
    // (FK) — o teste morreria no setup sem chegar a exercer o gate.
    $outroBusiness = 2;

    $user = inutUsuarioDe(1);
    $user->roles()->detach();
    $user->assignRole(inutRoleFiscal($outroBusiness)); // role do outro tenant, não do dele

    $this->actingAs($user)
        ->withSession(['business.id' => 1, 'user.business_id' => 1])
        ->postJson(INUT_ROTA, INUT_PAYLOAD_INVALIDO)
        ->assertStatus(403);
});

it('sem contexto de business na sessão, barra (business_id vem da sessão, nunca do request)', function () {
    $user = inutUsuarioDe(1);
    $user->roles()->detach();
    $user->assignRole(inutRoleFiscal(1));

    $this->actingAs($user)
        ->withSession(['user.business_id' => 1]) // 'business.id' ausente de propósito
        ->postJson(INUT_ROTA, INUT_PAYLOAD_INVALIDO)
        ->assertStatus(403);
});
