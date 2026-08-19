<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * Tier 0 multi-tenant (ADR 0093) — /roles/{id} não alcança papel de outro negócio.
 *
 * O QUE ESTAVA ERRADO (medido no main em 2026-08-19): RoleController::update() carregava o papel
 * com `Role::findOrFail($id)`, sem filtrar business_id. O Role é o Spatie puro
 * (config/permission.php:27), que não tem global scope de tenant, e nenhum provider registra um.
 * Dos 7 acessos a Role no controller, 6 filtravam (index/store/edit/destroy) — só o update() não.
 *
 * O caso NEGATIVO sozinho não bastaria: um filtro errado demais (que bloqueasse tudo) também o
 * faria passar. Por isso o controle POSITIVO — papel do próprio negócio continua editável — está
 * aqui e é obrigatório. Sem ele, o teste provaria "ninguém edita nada" e chamaria isso de sucesso.
 *
 * Roda na lane acessos-pest.yml, com MySQL real e biz=1 + biz=2 semeados. Em sqlite pularia, e
 * skip sai exit 0 — verde por não-execução é o vetor da LC-13.
 */

use App\User;
use Spatie\Permission\Models\Role;

/** Papel do negócio informado. O nome carrega o sufixo '#<business_id>', convenção do UltimatePOS. */
function papelDoNegocio(string $nome, int $businessId): Role
{
    return Role::create([
        'name' => $nome.'#'.$businessId,
        'business_id' => $businessId,
        'guard_name' => 'web',
    ]);
}

/** Usuário do negócio com as permissões pedidas, via papel próprio. */
function usuarioComPermissoes(array $permissoes, int $businessId): User
{
    $user = User::factory()->create(['business_id' => $businessId]);

    $papel = papelDoNegocio('AutoTeste'.uniqid(), $businessId);
    foreach ($permissoes as $p) {
        \Spatie\Permission\Models\Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);

    return $user;
}

it('NEGATIVO: papel de outro negócio não é renomeado pelo update', function () {
    $alheio = papelDoNegocio('DoOutroNegocio'.uniqid(), 2);
    $nomeOriginal = $alheio->name;

    $invasor = usuarioComPermissoes(['roles.update'], 1);

    $this->actingAs($invasor);
    session(['user.business_id' => 1]);

    $this->put('/roles/'.$alheio->id, [
        'name' => 'Invadido',
        'permissions' => [],
    ]);

    $alheio->refresh();
    expect($alheio->name)->toBe($nomeOriginal);
    expect($alheio->business_id)->toBe(2);
});

it('POSITIVO: papel do próprio negócio continua editável (o filtro não fechou o caminho feliz)', function () {
    $proprio = papelDoNegocio('Balcao'.uniqid(), 1);

    $dono = usuarioComPermissoes(['roles.update'], 1);

    $this->actingAs($dono);
    session(['user.business_id' => 1]);

    $novoNome = 'BalcaoRenomeado'.uniqid();
    $this->put('/roles/'.$proprio->id, [
        'name' => $novoNome,
        'permissions' => [],
    ]);

    $proprio->refresh();
    expect($proprio->name)->toBe($novoNome.'#1');
});

it('NEGATIVO: papel de outro negócio não é excluído pelo destroy', function () {
    $alheio = papelDoNegocio('NaoApagar'.uniqid(), 2);

    $invasor = usuarioComPermissoes(['roles.delete'], 1);

    $this->actingAs($invasor);
    session(['user.business_id' => 1]);

    $this->deleteJson('/roles/'.$alheio->id);

    expect(Role::find($alheio->id))->not->toBeNull();
});
