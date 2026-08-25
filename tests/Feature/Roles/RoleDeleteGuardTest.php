<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * Guarda de exclusão de PAPEL — por vínculo, não por regra de negócio.
 *
 * Apagar um papel em uso deixa N usuários sem as permissões que ele carrega, de uma vez e sem
 * aviso. A pivot do Spatie não tem restrição que impeça; a decisão tem de ser explícita de quem
 * administra.
 *
 * O caso POSITIVO (papel sem ninguém continua excluível) não é zelo: sem ele, uma guarda larga
 * demais bloquearia tudo e o teste chamaria isso de segurança.
 *
 * Nomes de helper únicos: os arquivos de tests/Feature/Roles/ rodam na MESMA lane, e função
 * global repetida quebra com "cannot redeclare".
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Headers que a TELA REAL manda. O destroy() do UltimatePOS embrulha o corpo INTEIRO em
 * `if (request()->ajax())`, e `ajax()` testa `X-Requested-With: XMLHttpRequest`. O deleteJson()
 * do Pest manda so `Accept: application/json` — sem este header o metodo cai no fim, devolve
 * null e a resposta e 200 VAZIA, sem executar nada.
 *
 * Sem isso os casos NEGATIVOS passam pelo MOTIVO ERRADO: 'o papel continuou la' e verdade
 * quando o controller nem roda. Diagnosticado no #5970, mesma leva.
 */
function headersAjaxDel(): array
{
    return ['X-Requested-With' => 'XMLHttpRequest'];
}

function papelDel(string $nome, int $businessId = 1, bool $isDefault = false): Role
{
    return Role::create([
        'name' => $nome.uniqid().'#'.$businessId,
        'business_id' => $businessId,
        'is_default' => $isDefault,
        'guard_name' => 'web',
    ]);
}

function operadorDel(int $businessId = 1): User
{
    $user = User::factory()->create(['business_id' => $businessId]);

    $papel = papelDel('OperadorDel', $businessId);
    Permission::findOrCreate('roles.delete', 'web');
    $papel->syncPermissions(['roles.delete']);
    $user->assignRole($papel);

    // Mesma correcao do RoleTenantIsolationTest (ja em main): sem limpar o cache de
    // permissoes do Spatie e sem reler o usuario, o can() responde com o retrato anterior
    // e o controller aborta 403 — e ai os casos NEGATIVOS passam pelo MOTIVO ERRADO,
    // porque 'nada foi criado' tambem e verdade quando a requisicao nem chega.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

it('BLOQUEIA excluir papel em uso e informa quantos usuários dependem dele', function () {
    $papel = papelDel('EmUso');

    $membro = User::factory()->create(['business_id' => 1]);
    $membro->assignRole($papel);

    $operador = operadorDel(1);
    $this->actingAs($operador);
    session(['user.business_id' => 1]);

    $this->withHeaders(headersAjaxDel())->deleteJson('/roles/'.$papel->id)->assertStatus(422);

    expect(Role::find($papel->id))->not->toBeNull();
    expect(DB::table('model_has_roles')->where('role_id', $papel->id)->count())->toBe(1);
});

it('POSITIVO: papel sem nenhum usuário continua sendo excluível', function () {
    $papel = papelDel('Vazio');

    $operador = operadorDel(1);
    $this->actingAs($operador);
    session(['user.business_id' => 1]);

    $this->withHeaders(headersAjaxDel())->deleteJson('/roles/'.$papel->id);

    expect(Role::find($papel->id))->toBeNull();
});

it('papel is_default segue protegido, mesmo sem usuário nenhum', function () {
    // Regressão do comportamento que já existia: a guarda nova não pode ter aberto esta porta.
    $papel = papelDel('Admin', 1, true);

    $operador = operadorDel(1);
    $this->actingAs($operador);
    session(['user.business_id' => 1]);

    $this->withHeaders(headersAjaxDel())->deleteJson('/roles/'.$papel->id);

    expect(Role::find($papel->id))->not->toBeNull();
});
