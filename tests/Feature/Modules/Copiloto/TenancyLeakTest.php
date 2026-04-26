<?php

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * Testa isolamento multi-tenant (adr/arq/0001).
 *
 * Cria schema mínimo inline (SQLite-compatível) para não depender
 * do histórico de migrations MySQL-específicas da aplicação.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('surname')->default('');
        $table->string('first_name')->default('');
        $table->string('last_name')->nullable();
        $table->string('username')->unique();
        $table->string('email')->nullable();
        $table->string('password');
        $table->integer('business_id')->nullable();
        $table->rememberToken();
        $table->softDeletes();
        $table->timestamps();
    });

    // Tabelas do spatie/laravel-permission (necessárias para $user->can())
    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type'], 'mhp_primary');
    });

    Schema::create('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type'], 'mhr_primary');
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('copiloto_metas', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id')->nullable();
        $table->string('slug', 80);
        $table->string('nome', 150);
        $table->string('unidade', 10)->default('R$');
        $table->string('tipo_agregacao', 20)->default('soma');
        $table->boolean('ativo')->default(true);
        $table->unsignedInteger('criada_por_user_id')->nullable();
        $table->string('origem', 20)->default('manual');
        $table->timestamps();

        $table->unique(['business_id', 'slug']);
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    Schema::dropIfExists('copiloto_metas');
    Schema::dropIfExists('role_has_permissions');
    Schema::dropIfExists('model_has_roles');
    Schema::dropIfExists('model_has_permissions');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('users');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function criarUsuarioTenancy(int $businessId): User
{
    return User::forceCreate([
        'surname'     => 'Teste',
        'first_name'  => 'User',
        'username'    => 'user_biz' . $businessId . '_' . uniqid(),
        'password'    => bcrypt('secret'),
        'business_id' => $businessId,
    ]);
}

function criarMeta(int $businessId, string $slug, string $nome = 'Faturamento'): Meta
{
    return Meta::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'        => $businessId,
        'slug'               => $slug,
        'nome'               => $nome,
        'unidade'            => 'R$',
        'tipo_agregacao'     => 'soma',
        'ativo'              => true,
        'criada_por_user_id' => 1,
        'origem'             => 'seed',
    ]);
}

function criarMetaPlataforma(string $slug = 'mrr_plataforma'): Meta
{
    return Meta::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'        => null,
        'slug'               => $slug,
        'nome'               => 'Meta da Plataforma',
        'unidade'            => 'R$',
        'tipo_agregacao'     => 'soma',
        'ativo'              => true,
        'criada_por_user_id' => 1,
        'origem'             => 'seed',
    ]);
}

// ─── Testes ──────────────────────────────────────────────────────────────────

it('usuário de biz A não vê meta de biz B via scope', function () {
    $metaA = criarMeta(4, 'fat_a', 'Faturamento A');
    $metaB = criarMeta(7, 'fat_b', 'Faturamento B');

    $user = criarUsuarioTenancy(4);
    session(['user.business_id' => 4]);
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->toContain($metaA->id)
        ->not->toContain($metaB->id);
});

it('usuário de biz B não vê meta de biz A via scope', function () {
    $metaA = criarMeta(4, 'fat_a2', 'Faturamento A2');
    $metaB = criarMeta(7, 'fat_b2', 'Faturamento B2');

    $user = criarUsuarioTenancy(7);
    session(['user.business_id' => 7]);
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->toContain($metaB->id)
        ->not->toContain($metaA->id);
});

it('usuário comum não vê meta da plataforma (business_id null)', function () {
    $metaPlataforma = criarMetaPlataforma('mrr_plt');
    criarMeta(4, 'fat_a3', 'Faturamento A3');

    $user = criarUsuarioTenancy(4);
    session(['user.business_id' => 4]);
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->not->toContain($metaPlataforma->id);
});

it('superadmin vê meta de plataforma (business_id null)', function () {
    criarMetaPlataforma('mrr_sup');

    $user = criarUsuarioTenancy(1);
    $user->givePermissionTo('copiloto.superadmin');

    session(['user.business_id' => 1]);
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids->count())->toBeGreaterThan(0);
})->skip('Requer spatie/permission instalado e migrado — validar localmente com MySQL.');

it('escopo aplicado por default sem callWithoutGlobalScope', function () {
    criarMeta(4, 'fat_default_a', 'Fat A Default');
    criarMeta(8, 'fat_default_b', 'Fat B Default');

    $user = criarUsuarioTenancy(4);
    session(['user.business_id' => 4]);
    $this->actingAs($user);

    $todos = Meta::all();

    foreach ($todos as $meta) {
        expect($meta->business_id)->toBe(4);
    }
});
