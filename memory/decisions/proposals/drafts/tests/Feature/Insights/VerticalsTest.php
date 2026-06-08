<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO ATÉ migrations + seeder existirem.
 *
 * Cobertura: tabela `verticals` (52 verticais oimpresso) — gap #1 schema multi-vertical.
 *
 * Pré-requisitos (Felipe local):
 *   1. Copiar migrations de memory/decisions/proposals/drafts/migrations/ pra
 *      Modules/Insights/Database/Migrations/
 *   2. Criar Modules/Insights/Database/Seeders/VerticalsSeeder.php (52 entries)
 *   3. Criar Modules/Insights/Models/Vertical.php (Eloquent, sem business_id — global)
 *   4. php artisan module:make-test Insights VerticalsTest --feature
 *   5. mover este arquivo pra Modules/Insights/Tests/Feature/VerticalsTest.php
 *   6. registrar suite Insights em phpunit.xml (proibição: skill mwart-quality)
 *   7. ./vendor/bin/pest --testsuite=Insights
 *
 * Origem: memory/decisions/proposals/gap-schema-oimpresso-multi-cliente-multi-vertical.md
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Insights\Models\Vertical;
use Modules\Insights\Database\Seeders\VerticalsSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    // RefreshDatabase já roda migrations de Modules/Insights/Database/Migrations
    // garantir que tabela existe antes do teste
    expect(Schema::hasTable('verticals'))->toBeTrue('Migration verticals não rodou');
});

// ---------------------------------------------------------------------------
// SCHEMA — colunas e constraints
// ---------------------------------------------------------------------------

it('cria tabela verticals com colunas obrigatórias', function () {
    expect(Schema::hasColumns('verticals', [
        'id', 'slug', 'name', 'name_plural', 'parent_id',
        'cnae_codes', 'attributes_schema', 'benchmark_metrics',
        'active', 'sort_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('aplica unique constraint em slug', function () {
    Vertical::create([
        'slug' => 'comunicacao_visual',
        'name' => 'Comunicação Visual',
    ]);

    expect(fn () => Vertical::create([
        'slug' => 'comunicacao_visual',
        'name' => 'Duplicado',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('default active=true e sort_order=0', function () {
    $v = Vertical::create([
        'slug' => 'teste',
        'name' => 'Teste',
    ]);

    expect($v->active)->toBeTrue();
    expect((int) $v->sort_order)->toBe(0);
});

// ---------------------------------------------------------------------------
// SEEDER — 52 verticais
// ---------------------------------------------------------------------------

it('seeder VerticalsSeeder popula >= 52 verticais', function () {
    $this->seed(VerticalsSeeder::class);

    expect(Vertical::count())->toBeGreaterThanOrEqual(52);
});

it('seeder cria slug canonico comunicacao_visual', function () {
    $this->seed(VerticalsSeeder::class);

    $cv = Vertical::where('slug', 'comunicacao_visual')->first();

    expect($cv)->not->toBeNull();
    expect($cv->name)->toBe('Comunicação Visual');
    expect($cv->active)->toBeTrue();
});

it('todas verticais seedadas têm slug + name preenchidos', function () {
    $this->seed(VerticalsSeeder::class);

    $invalidas = Vertical::whereNull('slug')
        ->orWhereNull('name')
        ->orWhere('slug', '')
        ->orWhere('name', '')
        ->count();

    expect($invalidas)->toBe(0);
});

it('seeder não duplica slugs em re-execução (idempotente)', function () {
    $this->seed(VerticalsSeeder::class);
    $count1 = Vertical::count();

    $this->seed(VerticalsSeeder::class);
    $count2 = Vertical::count();

    expect($count2)->toBe($count1);
});

// ---------------------------------------------------------------------------
// HIERARQUIA — parent_id self-referencing
// ---------------------------------------------------------------------------

it('hierarquia parent_id funciona (Saúde > Odontologia)', function () {
    $saude = Vertical::create(['slug' => 'saude', 'name' => 'Saúde']);
    $odonto = Vertical::create([
        'slug' => 'odontologia',
        'name' => 'Odontologia',
        'parent_id' => $saude->id,
    ]);

    expect($odonto->parent_id)->toBe($saude->id);
});

it('on delete pai SET NULL no filho (preserva integridade)', function () {
    $pai = Vertical::create(['slug' => 'pai', 'name' => 'Pai']);
    $filho = Vertical::create([
        'slug' => 'filho',
        'name' => 'Filho',
        'parent_id' => $pai->id,
    ]);

    $pai->delete();

    expect($filho->fresh()->parent_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// JSON cnae_codes
// ---------------------------------------------------------------------------

it('aceita json válido em cnae_codes', function () {
    $v = Vertical::create([
        'slug' => 'comunicacao_visual',
        'name' => 'Comunicação Visual',
        'cnae_codes' => ['1813-0/01', '7319-0/02', '8299-7/99'],
    ]);

    $fresh = $v->fresh();
    expect($fresh->cnae_codes)->toBeArray();
    expect($fresh->cnae_codes)->toContain('1813-0/01');
});

it('aceita null em cnae_codes (vertical sem mapping CNAE)', function () {
    $v = Vertical::create([
        'slug' => 'outros',
        'name' => 'Outros',
        'cnae_codes' => null,
    ]);

    expect($v->fresh()->cnae_codes)->toBeNull();
});

it('cnae_codes seedados nas 10 verticais top são strings formato XXXX-X/XX', function () {
    $this->seed(VerticalsSeeder::class);

    $top10 = Vertical::whereNotNull('cnae_codes')->take(10)->get();

    foreach ($top10 as $v) {
        foreach ($v->cnae_codes as $code) {
            expect($code)->toMatch('/^\d{4}-\d\/\d{2}$/');
        }
    }
});

// ---------------------------------------------------------------------------
// MULTI-TENANT — verticals é GLOBAL (não tem business_id)
// ---------------------------------------------------------------------------

it('tabela verticals NÃO tem coluna business_id (é tabela global)', function () {
    expect(Schema::hasColumn('verticals', 'business_id'))->toBeFalse();
});

it('Vertical model NÃO aplica BelongsToBusiness scope', function () {
    // verticals é catálogo global compartilhado por todos tenants
    $traits = class_uses(Vertical::class) ?: [];

    expect($traits)->not->toContain(\App\Traits\BelongsToBusiness::class);
});
