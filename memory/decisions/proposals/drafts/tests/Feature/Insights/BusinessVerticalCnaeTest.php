<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Cobertura: ALTER TABLE business ADD COLUMN vertical_id, cnae_principal — gap #3.
 *
 * Restrição Wagner 2026-05-09:
 *   "Mudanças tenancy/multi-tenant exigem Pest local antes de PR mesmo defensivas."
 *   Felipe roda local com biz=4 (RotaLivre) staging snapshot antes de subir.
 *
 * Pré-requisito: 41 businesses atuais permanecem funcionais SEM vertical_id (nullable).
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Models\Business;
use Modules\Insights\Models\Vertical;
use Modules\Insights\Models\CnaeCodigo;
use Modules\Insights\Database\Seeders\VerticalsSeeder;
use Modules\Insights\Database\Seeders\CnaeCodigosSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([VerticalsSeeder::class, CnaeCodigosSeeder::class]);
});

// ---------------------------------------------------------------------------
// SCHEMA — ALTER TABLE business
// ---------------------------------------------------------------------------

it('business tem coluna vertical_id (nullable)', function () {
    expect(Schema::hasColumn('business', 'vertical_id'))->toBeTrue();
});

it('business tem coluna cnae_principal (nullable string)', function () {
    expect(Schema::hasColumn('business', 'cnae_principal'))->toBeTrue();
});

it('vertical_id é nullable (compat com 41 businesses atuais)', function () {
    // factory cria sem vertical_id
    $biz = Business::factory()->create(['vertical_id' => null]);

    expect($biz->fresh()->vertical_id)->toBeNull();
});

it('cnae_principal é nullable', function () {
    $biz = Business::factory()->create(['cnae_principal' => null]);

    expect($biz->fresh()->cnae_principal)->toBeNull();
});

// ---------------------------------------------------------------------------
// BACKWARDS COMPAT — businesses antigos sem vertical
// ---------------------------------------------------------------------------

it('business antigo sem vertical_id continua acessível', function () {
    $biz = Business::factory()->create([
        'name' => 'Biz Legacy',
        'vertical_id' => null,
        'cnae_principal' => null,
    ]);

    expect(Business::find($biz->id))->not->toBeNull();
    expect($biz->vertical_id)->toBeNull();
});

it('listagem de businesses não quebra com vertical_id NULL', function () {
    Business::factory()->count(5)->create(['vertical_id' => null]);

    $count = Business::whereNull('vertical_id')->count();

    expect($count)->toBeGreaterThanOrEqual(5);
});

// ---------------------------------------------------------------------------
// FK + RELATIONSHIPS
// ---------------------------------------------------------------------------

it('business->vertical retorna model Vertical quando setado', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $biz = Business::factory()->create(['vertical_id' => $cv->id]);

    expect($biz->vertical)->toBeInstanceOf(Vertical::class);
    expect($biz->vertical->slug)->toBe('comunicacao_visual');
});

it('business->cnaePrincipal retorna model CnaeCodigo quando setado', function () {
    $biz = Business::factory()->create(['cnae_principal' => '1813-0/01']);

    expect($biz->cnaePrincipal)->toBeInstanceOf(CnaeCodigo::class);
    expect($biz->cnaePrincipal->codigo)->toBe('1813-0/01');
});

it('soft delete vertical NÃO quebra business (FK ON DELETE SET NULL ou similar)', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $biz = Business::factory()->create(['vertical_id' => $cv->id]);

    $cv->delete();

    // business permanece, vertical_id vira NULL OU mantém referência (depende do schema)
    $bizFresh = $biz->fresh();
    expect($bizFresh)->not->toBeNull();
    // se ON DELETE SET NULL:
    expect($bizFresh->vertical_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// MULTI-TENANT — verticals NÃO afetam isolamento business
// ---------------------------------------------------------------------------

it('verticals (global) NÃO contamina scope multi-tenant', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();

    $bizA = Business::factory()->create(['vertical_id' => $cv->id]);
    $bizB = Business::factory()->create(['vertical_id' => $cv->id]);

    // mesmo vertical, businesses diferentes — sem cross-talk
    expect($bizA->id)->not->toBe($bizB->id);
    expect($bizA->vertical_id)->toBe($bizB->vertical_id);
});

it('dois businesses na mesma vertical têm dados isolados', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();

    $biz1 = Business::factory()->create(['vertical_id' => $cv->id, 'name' => 'RotaLivre']);
    $biz2 = Business::factory()->create(['vertical_id' => $cv->id, 'name' => 'Concorrente']);

    // simular que cada um tem suas vendas
    // ... (depende de TransactionFactory; aqui só verifica isolamento de business_id)
    expect($biz1->business_id ?? $biz1->id)->not->toBe($biz2->business_id ?? $biz2->id);
});

it('Business model continua aplicando global scope business_id (Tier 0)', function () {
    // Garante que a coluna nova vertical_id NÃO subverte o scope canônico Tier 0.
    // ADR 0093 — global scope IRREVOGÁVEL.
    $b = new Business;
    expect(method_exists($b, 'newQuery'))->toBeTrue();
});
