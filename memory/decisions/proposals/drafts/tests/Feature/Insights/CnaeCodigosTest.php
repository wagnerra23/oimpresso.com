<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Cobertura: tabela `cnae_codigos` (~1.330 CNAEs IBGE) — gap #2 schema multi-vertical.
 *
 * Pré-requisitos:
 *   - Migration draft create_cnae_codigos_table.php
 *   - Modules/Insights/Database/Seeders/CnaeCodigosSeeder.php (~1.330 entries do IBGE)
 *   - Modules/Insights/Models/CnaeCodigo.php
 *   - PK = string `codigo` (formato XXXX-X/XX)
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Insights\Models\CnaeCodigo;
use Modules\Insights\Models\Vertical;
use Modules\Insights\Database\Seeders\CnaeCodigosSeeder;
use Modules\Insights\Database\Seeders\VerticalsSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(Schema::hasTable('cnae_codigos'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// SCHEMA
// ---------------------------------------------------------------------------

it('tabela cnae_codigos tem PK string codigo', function () {
    $cols = Schema::getColumnListing('cnae_codigos');
    expect($cols)->toContain('codigo');
    expect($cols)->toContain('descricao');
    expect($cols)->toContain('secao');
    expect($cols)->toContain('divisao');
    expect($cols)->toContain('grupo');
    expect($cols)->toContain('classe');
    expect($cols)->toContain('subclasse');
    expect($cols)->toContain('vertical_id');
});

it('PK codigo aceita formato XXXX-X/XX', function () {
    $cnae = CnaeCodigo::create([
        'codigo' => '1813-0/01',
        'descricao' => 'Impressão de material para uso publicitário',
        'secao' => 'C',
        'divisao' => '18',
        'grupo' => '181',
        'classe' => '1813-0',
        'subclasse' => '1813-0/01',
    ]);

    expect($cnae->codigo)->toBe('1813-0/01');
    expect(CnaeCodigo::find('1813-0/01'))->not->toBeNull();
});

it('rejeita codigo duplicado (PK constraint)', function () {
    CnaeCodigo::create([
        'codigo' => '1813-0/01',
        'descricao' => 'Original',
        'secao' => 'C', 'divisao' => '18', 'grupo' => '181',
        'classe' => '1813-0', 'subclasse' => '1813-0/01',
    ]);

    expect(fn () => CnaeCodigo::create([
        'codigo' => '1813-0/01',
        'descricao' => 'Duplicado',
        'secao' => 'C', 'divisao' => '18', 'grupo' => '181',
        'classe' => '1813-0', 'subclasse' => '1813-0/01',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

// ---------------------------------------------------------------------------
// SEEDER — ~1.330 CNAEs IBGE
// ---------------------------------------------------------------------------

it('seeder CnaeCodigosSeeder popula >= 1300 CNAEs', function () {
    $this->seed(CnaeCodigosSeeder::class);

    expect(CnaeCodigo::count())->toBeGreaterThanOrEqual(1300);
    expect(CnaeCodigo::count())->toBeLessThanOrEqual(1400);
});

it('seeder cria CNAE 1813-0/01 (Impressão publicitária — RotaLivre)', function () {
    $this->seed(CnaeCodigosSeeder::class);

    $cnae = CnaeCodigo::find('1813-0/01');

    expect($cnae)->not->toBeNull();
    expect($cnae->descricao)->toContain('publicitário');
});

it('todos CNAEs seedados têm seção/divisão/grupo/classe/subclasse', function () {
    $this->seed(CnaeCodigosSeeder::class);

    $invalidos = CnaeCodigo::whereNull('secao')
        ->orWhereNull('divisao')
        ->orWhereNull('grupo')
        ->orWhereNull('classe')
        ->orWhereNull('subclasse')
        ->count();

    expect($invalidos)->toBe(0);
});

// ---------------------------------------------------------------------------
// MAPPING CNAE → vertical_id
// ---------------------------------------------------------------------------

it('mapping CNAE → vertical funciona pros top 10', function () {
    $this->seed([VerticalsSeeder::class, CnaeCodigosSeeder::class]);

    // 1813-0/01 deve mapear pra vertical 'comunicacao_visual'
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $cnae = CnaeCodigo::find('1813-0/01');

    expect($cnae->vertical_id)->toBe($cv->id);
});

it('CNAE sem vertical mapeada permanece NULL (compat)', function () {
    $cnae = CnaeCodigo::create([
        'codigo' => '9999-9/99',
        'descricao' => 'Atividade não mapeada',
        'secao' => 'X', 'divisao' => '99', 'grupo' => '999',
        'classe' => '9999-9', 'subclasse' => '9999-9/99',
        'vertical_id' => null,
    ]);

    expect($cnae->vertical_id)->toBeNull();
});

it('relação CnaeCodigo->vertical retorna model Vertical', function () {
    $this->seed([VerticalsSeeder::class, CnaeCodigosSeeder::class]);

    $cnae = CnaeCodigo::find('1813-0/01');

    expect($cnae->vertical)->toBeInstanceOf(Vertical::class);
    expect($cnae->vertical->slug)->toBe('comunicacao_visual');
});

// ---------------------------------------------------------------------------
// INDEX — secao/divisao/grupo/classe/subclasse pesquisáveis
// ---------------------------------------------------------------------------

it('query por seção retorna múltiplos CNAEs', function () {
    $this->seed(CnaeCodigosSeeder::class);

    $secaoC = CnaeCodigo::where('secao', 'C')->count(); // Indústrias de transformação

    expect($secaoC)->toBeGreaterThan(100);
});

it('query por divisão retorna subset esperado', function () {
    $this->seed(CnaeCodigosSeeder::class);

    $div18 = CnaeCodigo::where('divisao', '18')->count(); // Impressão e reprodução

    expect($div18)->toBeGreaterThanOrEqual(5);
    expect($div18)->toBeLessThanOrEqual(30);
});

// ---------------------------------------------------------------------------
// MULTI-TENANT — CnaeCodigo é GLOBAL
// ---------------------------------------------------------------------------

it('cnae_codigos NÃO tem business_id (catálogo global)', function () {
    expect(Schema::hasColumn('cnae_codigos', 'business_id'))->toBeFalse();
});
