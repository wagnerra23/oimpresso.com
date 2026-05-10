<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Cobertura: artisan command `insights:backfill-vertical` que popula
 * business.vertical_id + cnae_principal a partir de BrasilAPI/CNPJá.ws
 * pros 41 businesses atuais.
 *
 * Pré-requisitos:
 *   - Modules/Insights/Console/BackfillBusinessVerticalCommand.php
 *   - Service Modules/Insights/Services/CnpjLookup.php (HTTP client)
 *   - Mock obrigatório com Http::fake() — NUNCA bater BrasilAPI real em test
 *
 * Restrição Wagner 2026-05-09: Pest local antes de PR. Felipe valida com biz=4.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
// DRY-RUN — não persiste mudanças
// ---------------------------------------------------------------------------

it('command roda com flag --dry-run sem persistir', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnpj' => '12345678000199',
            'cnae_fiscal' => '1813-0/01',
            'cnae_fiscal_descricao' => 'Impressão de material para uso publicitário',
        ], 200),
    ]);

    $biz = Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => null,
        'cnae_principal' => null,
    ]);

    Artisan::call('insights:backfill-vertical', ['--dry-run' => true]);

    expect($biz->fresh()->vertical_id)->toBeNull();
    expect($biz->fresh()->cnae_principal)->toBeNull();
});

it('dry-run mostra plano de mudanças no output', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnpj' => '12345678000199',
            'cnae_fiscal' => '1813-0/01',
        ], 200),
    ]);

    Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => null,
    ]);

    Artisan::call('insights:backfill-vertical', ['--dry-run' => true]);
    $output = Artisan::output();

    expect($output)->toContain('1813-0/01');
    expect(strtolower($output))->toContain('dry-run');
});

// ---------------------------------------------------------------------------
// EXECUÇÃO REAL — backfill biz=1 atribui vertical correto
// ---------------------------------------------------------------------------

it('backfill biz=1 atribui vertical comunicacao_visual via CNAE 1813-0/01', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnpj' => '12345678000199',
            'cnae_fiscal' => '1813-0/01',
        ], 200),
    ]);

    $biz = Business::factory()->create([
        'id' => 1,
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => null,
        'cnae_principal' => null,
    ]);

    Artisan::call('insights:backfill-vertical', ['--business-id' => $biz->id]);

    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $fresh = $biz->fresh();
    expect($fresh->cnae_principal)->toBe('1813-0/01');
    expect($fresh->vertical_id)->toBe($cv->id);
});

it('backfill skip de business sem CNPJ (tax_number_1 NULL)', function () {
    $biz = Business::factory()->create([
        'tax_number_1' => null,
        'vertical_id' => null,
    ]);

    Artisan::call('insights:backfill-vertical');

    expect($biz->fresh()->vertical_id)->toBeNull();
});

it('backfill não sobrescreve vertical_id já preenchido (default behavior)', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $outra = Vertical::factory()->create(['slug' => 'outra', 'name' => 'Outra']);

    $biz = Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => $outra->id,
    ]);

    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnae_fiscal' => '1813-0/01',
        ], 200),
    ]);

    Artisan::call('insights:backfill-vertical');

    expect($biz->fresh()->vertical_id)->toBe($outra->id);
});

it('flag --force sobrescreve vertical_id existente', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $outra = Vertical::factory()->create(['slug' => 'outra', 'name' => 'Outra']);

    $biz = Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => $outra->id,
    ]);

    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnae_fiscal' => '1813-0/01',
        ], 200),
    ]);

    Artisan::call('insights:backfill-vertical', ['--force' => true]);

    expect($biz->fresh()->vertical_id)->toBe($cv->id);
});

// ---------------------------------------------------------------------------
// FAILURES — gracefully handle erros HTTP/CNPJ inválido
// ---------------------------------------------------------------------------

it('falha gracefully se CNPJ inválido (HTTP 404)', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['message' => 'CNPJ não encontrado'], 404),
    ]);

    $biz = Business::factory()->create([
        'tax_number_1' => '00.000.000/0000-00',
        'vertical_id' => null,
    ]);

    $exitCode = Artisan::call('insights:backfill-vertical');

    expect($exitCode)->toBe(0); // não quebra
    expect($biz->fresh()->vertical_id)->toBeNull();
});

it('loga warning se BrasilAPI retornar 5xx', function () {
    Log::spy();

    Http::fake([
        'brasilapi.com.br/*' => Http::response('Server error', 500),
    ]);

    Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => null,
    ]);

    Artisan::call('insights:backfill-vertical');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains(strtolower($msg), 'brasilapi'))
        ->atLeast()->once();
});

it('CNAE não mapeado pra vertical: salva cnae_principal mas vertical_id NULL', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnae_fiscal' => '9999-9/99',
        ], 200),
    ]);

    $biz = Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => null,
        'cnae_principal' => null,
    ]);

    Artisan::call('insights:backfill-vertical');

    $fresh = $biz->fresh();
    expect($fresh->cnae_principal)->toBe('9999-9/99');
    expect($fresh->vertical_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// RATE-LIMIT — sleep 1s entre chamadas
// ---------------------------------------------------------------------------

it('respeita rate-limit (sleep 1s) entre businesses', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(['cnae_fiscal' => '1813-0/01'], 200),
    ]);

    Business::factory()->count(3)->create([
        'tax_number_1' => fn () => fake()->numerify('##.###.###/####-##'),
        'vertical_id' => null,
    ]);

    $start = microtime(true);
    Artisan::call('insights:backfill-vertical');
    $elapsed = microtime(true) - $start;

    // 3 calls * 1s sleep = mín 2s (sleep entre, não antes do primeiro)
    expect($elapsed)->toBeGreaterThanOrEqual(2.0);
});

it('PII: tax_number_1 NÃO aparece em log do command', function () {
    Log::spy();

    Http::fake([
        'brasilapi.com.br/*' => Http::response(['cnae_fiscal' => '1813-0/01'], 200),
    ]);

    Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => null,
    ]);

    Artisan::call('insights:backfill-vertical');

    // proibição: PII em log
    Log::shouldNotHaveReceived('info', function ($msg) {
        return str_contains($msg, '12.345.678/0001-99');
    });
});
