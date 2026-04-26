<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Scopes\ScopeByBusiness;
use Tests\TestCase;

/**
 * Testa isolamento multi-tenant (adr/arq/0001).
 *
 * Usa SQLite in-memory via RefreshDatabase.
 * Verifica que usuário de biz A nunca vê metas de biz B e que
 * metas da plataforma (business_id=null) só aparecem pra superadmin.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Garante que as migrations do módulo rodam
    $this->loadMigrationsFrom(base_path('Modules/Copiloto/Database/Migrations'));
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function criarUsuario(int $businessId): \App\Models\User
{
    return \App\Models\User::factory()->create(['business_id' => $businessId]);
}

function criarMeta(int $businessId, string $slug = 'fat', string $nome = 'Faturamento'): Meta
{
    return Meta::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'        => $businessId,
        'slug'               => $slug . '_' . $businessId,
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

    // Simula sessão do business 4
    session(['user.business_id' => 4]);
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->toContain($metaA->id)
        ->not->toContain($metaB->id);
});

it('usuário de biz B não vê meta de biz A via scope', function () {
    $metaA = criarMeta(4, 'fat_a2', 'Faturamento A2');
    $metaB = criarMeta(7, 'fat_b2', 'Faturamento B2');

    session(['user.business_id' => 7]);
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->toContain($metaB->id)
        ->not->toContain($metaA->id);
});

it('usuário comum não vê meta da plataforma (business_id null)', function () {
    $metaPlataforma = criarMetaPlataforma('mrr_plt');
    $metaBiz        = criarMeta(4, 'fat_a3', 'Faturamento A3');

    session(['user.business_id' => 4]);
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->not->toContain($metaPlataforma->id);
});

it('superadmin vê meta de plataforma (business_id null)', function () {
    $metaPlataforma = criarMetaPlataforma('mrr_sup');

    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('copiloto.superadmin');

    session(['user.business_id' => 1]);
    $this->actingAs($user);

    $ids = Meta::all()->pluck('id');

    expect($ids)->toContain($metaPlataforma->id);
})->skip('Requer spatie/permission instalado e migrado — validar localmente com MySQL.');

it('escopo aplicado por default sem callWithoutGlobalScope', function () {
    $metaA = criarMeta(4, 'fat_default_a', 'Fat A Default');
    $metaB = criarMeta(8, 'fat_default_b', 'Fat B Default');

    session(['user.business_id' => 4]);
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    // Verificação regressiva: query padrão nunca retorna outro business
    $todos = Meta::all();

    foreach ($todos as $meta) {
        expect($meta->business_id)->toBe(4);
    }
});
