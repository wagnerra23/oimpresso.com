<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Equipe do app das lojas (tela 26) — GET /api/app/equipe, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.2 (formato fechado com a sessão do
 * app: iniciais, nome, função, carga e status; sem telefone e sem ponto). NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_EQ_BIZ = 98;
const APP_EQ_OUTRO = 2;

function appEqUsuario(array $permissoes, int $biz = APP_EQ_BIZ, array $extra = []): User
{
    $user = User::factory()->create(array_merge(['business_id' => $biz], $extra));
    $papel = Role::create(['name' => 'AppEq' . uniqid() . '#' . $biz, 'business_id' => $biz, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** OS de mecânica ainda sem pipeline = aberta (mesmo universo do quadro web da Oficina). */
function appEqOs(int $biz, ?int $responsavel, array $extra = []): void
{
    $veiculo = (int) DB::table('vehicles')->insertGetId([
        'business_id' => $biz, 'plate' => 'EQ' . random_int(10000, 99999),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('service_orders')->insert(array_merge([
        'business_id' => $biz, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica',
        'assigned_user_id' => $responsavel, 'current_stage_id' => null,
        'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

/** @return array<int, array<string, mixed>> itens por id */
function appEqItens($teste): array
{
    return collect($teste->getJson('/api/app/equipe')->assertOk()->json('itens'))->keyBy('id')->all();
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_EQ_BIZ, APP_EQ_OUTRO])->count() !== 2
        || ! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('Tenants 98/2 ou tabelas da Oficina ausentes nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-APP26-01: sem token 401; sem user.view 403 e a área equipe some do Início', function () {
    $this->getJson('/api/app/equipe')->assertStatus(401);

    Passport::actingAs(appEqUsuario([]), [], 'api');
    $this->getJson('/api/app/equipe')->assertForbidden()->assertJsonPath('erro', 'sem_permissao');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->not->toContain('equipe');

    Passport::actingAs(appEqUsuario(['user.view']), [], 'api');
    expect($this->getJson('/api/app/inicio')->assertOk()->json('areas'))->toContain('equipe');
});

it('UC-APP26-02: lista pessoas do meu business com função, carga em OS abertas e status derivado', function () {
    $gestor = appEqUsuario(['user.view'], APP_EQ_BIZ, ['first_name' => 'Gestora', 'last_name' => 'EQ']);
    $cargo = (int) DB::table('categories')->insertGetId([
        'name' => 'Mecânico EQ', 'business_id' => APP_EQ_BIZ, 'category_type' => 'hrm_designation',
        'parent_id' => 0, 'created_by' => $gestor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ocupado = appEqUsuario([], APP_EQ_BIZ, ['first_name' => 'Ocupado', 'last_name' => 'EQ', 'essentials_designation_id' => $cargo]);
    $livre = appEqUsuario([], APP_EQ_BIZ, ['first_name' => 'Livre', 'last_name' => 'EQ']);
    $inativo = appEqUsuario([], APP_EQ_BIZ, ['first_name' => 'Inativo', 'last_name' => 'EQ', 'status' => 'inactive']);
    appEqOs(APP_EQ_BIZ, $ocupado->id);
    appEqOs(APP_EQ_BIZ, $ocupado->id);
    appEqOs(APP_EQ_BIZ, $livre->id, ['deleted_at' => now()]); // apagada não conta
    Passport::actingAs($gestor, [], 'api');

    $itens = appEqItens($this);

    expect($itens[$ocupado->id])->toBe([
        'id' => $ocupado->id, 'nome' => 'Ocupado EQ', 'funcao' => 'Mecânico EQ', 'carga' => '2 OS',
        'status' => ['rotulo' => 'Em serviço', 'tom' => 'ocupado'],
    ]);
    expect($itens[$livre->id]['carga'])->toBeNull();
    expect($itens[$livre->id]['funcao'])->toBeNull();
    expect($itens[$livre->id]['status'])->toBe(['rotulo' => 'Disponível', 'tom' => 'livre']);
    expect($itens[$inativo->id]['status'])->toBe(['rotulo' => 'Inativo', 'tom' => 'ausente']);
    expect(array_keys($itens[$livre->id]))->toBe(['id', 'nome', 'funcao', 'carga', 'status']);
});

it('UC-APP26-03: pessoa e OS de OUTRO business não aparecem nem somam carga (Tier 0)', function () {
    $gestor = appEqUsuario(['user.view']);
    $meu = appEqUsuario([], APP_EQ_BIZ, ['first_name' => 'Meu', 'last_name' => 'EQ']);
    $alheio = appEqUsuario([], APP_EQ_OUTRO, ['first_name' => 'Alheio', 'last_name' => 'EQ']);
    appEqOs(APP_EQ_OUTRO, $meu->id); // OS do outro business apontando para alguém daqui
    appEqOs(APP_EQ_OUTRO, $alheio->id);
    Passport::actingAs($gestor, [], 'api');

    $itens = appEqItens($this);

    expect($itens)->toHaveKey($meu->id); // controle positivo: a lista não está vazia por vácuo
    expect($itens)->not->toHaveKey($alheio->id);
    expect($itens[$meu->id]['carga'])->toBeNull();
});
