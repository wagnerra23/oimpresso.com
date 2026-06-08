<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Database\Seeders\MaterialSeeder;
use Modules\ComunicacaoVisual\Entities\Material;

uses(Tests\TestCase::class);

/**
 * Testes do MaterialSeeder — ComunicacaoVisual Sprint 1.
 *
 * Cobre: criação dos 5 materiais default, idempotência, isolamento multi-tenant,
 * e respeito ao global scope ao consultar.
 *
 * Padrão:
 * - Tests biz=1 (Wagner WR2) e biz=99 (fictício) conforme ADR 0101 — nunca biz=4
 * - Cleanup via afterEach deletando rows inseridas pelos testes
 * - withoutGlobalScopes com comentário // SUPERADMIN: (ADR 0093)
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */

// Guard SQLite: tabela comvis_materiais requer migration MySQL do módulo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: comvis_materiais requer schema MySQL UltimatePOS (Wagner Pest local segue mandatory — ADR 0101)');
    }
    if (! Schema::hasTable('comvis_materiais')) {
        $this->markTestSkipped('comvis_materiais table missing — rode Modules/ComunicacaoVisual migrate primeiro');
    }
});

// Business IDs de teste — nunca biz=4 (ROTA LIVRE produção, ADR 0101)
const SEEDER_BIZ_WAGNER  = 1;
const SEEDER_BIZ_FICTICIO = 99;

// ------------------------------------------------------------------
// 1. Seeder cria 5 materiais para o business especificado
// ------------------------------------------------------------------

it('MaterialSeeder cria exatamente 5 materiais default para biz=1', function () {
    // Limpar rows pré-existentes dos materiais default pra biz=1 (seed pode ter rodado antes)
    Material::withoutGlobalScopes() // SUPERADMIN: setup de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();

    (new MaterialSeeder())->run(SEEDER_BIZ_WAGNER);

    $count = Material::withoutGlobalScopes() // SUPERADMIN: contagem de teste sem filtro de sessão
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->count();

    expect($count)->toBe(5);
})->afterEach(function () {
    Material::withoutGlobalScopes() // SUPERADMIN: cleanup de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();
});

// ------------------------------------------------------------------
// 2. Idempotência: rodar 2x não duplica (5 rows totais, não 10)
// ------------------------------------------------------------------

it('MaterialSeeder é idempotente: rodar 2x resulta em 5 rows (não 10)', function () {
    // Limpar rows pré-existentes pra biz=1
    Material::withoutGlobalScopes() // SUPERADMIN: setup de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();

    // Primeira execução
    (new MaterialSeeder())->run(SEEDER_BIZ_WAGNER);

    // Segunda execução — deve fazer skip de todos
    (new MaterialSeeder())->run(SEEDER_BIZ_WAGNER);

    $count = Material::withoutGlobalScopes() // SUPERADMIN: contagem de teste sem filtro de sessão
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->count();

    expect($count)->toBe(5);
})->afterEach(function () {
    Material::withoutGlobalScopes() // SUPERADMIN: cleanup de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();
});

// ------------------------------------------------------------------
// 3. Multi-tenant: run(1) cria pra biz=1, run(99) cria pra biz=99 (rows separadas)
// ------------------------------------------------------------------

it('MaterialSeeder isola por business_id: run(1) e run(99) criam rows independentes', function () {
    // Limpar rows dos dois businesses pra evitar interferência
    Material::withoutGlobalScopes() // SUPERADMIN: setup de teste
        ->whereIn('business_id', [SEEDER_BIZ_WAGNER, SEEDER_BIZ_FICTICIO])
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();

    (new MaterialSeeder())->run(SEEDER_BIZ_WAGNER);
    (new MaterialSeeder())->run(SEEDER_BIZ_FICTICIO);

    $countBiz1 = Material::withoutGlobalScopes() // SUPERADMIN: contagem multi-tenant de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->count();

    $countBiz99 = Material::withoutGlobalScopes() // SUPERADMIN: contagem multi-tenant de teste
        ->where('business_id', SEEDER_BIZ_FICTICIO)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->count();

    expect($countBiz1)->toBe(5);
    expect($countBiz99)->toBe(5);
})->afterEach(function () {
    Material::withoutGlobalScopes() // SUPERADMIN: cleanup de teste
        ->whereIn('business_id', [SEEDER_BIZ_WAGNER, SEEDER_BIZ_FICTICIO])
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();
});

// ------------------------------------------------------------------
// 4. Global scope: Material::find() filtra por business_id da sessão
// ------------------------------------------------------------------

it('Material::find() via global scope não retorna rows de outro business', function () {
    // Setup: inserir material no biz=1 via seeder
    Material::withoutGlobalScopes() // SUPERADMIN: setup de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();

    (new MaterialSeeder())->run(SEEDER_BIZ_WAGNER);

    // Pega o ID de um material do biz=1
    $materialBiz1 = Material::withoutGlobalScopes() // SUPERADMIN: busca sem scope pra obter ID
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->where('nome', 'ACM 3mm Branco')
        ->first();

    // Simular sessão do biz=99 (fictício)
    session(['user.business_id' => SEEDER_BIZ_FICTICIO]);

    // Global scope deve filtrar: material do biz=1 não pode aparecer pra sessão biz=99
    $resultado = Material::find($materialBiz1->id);

    expect($resultado)->toBeNull();
})->afterEach(function () {
    Material::withoutGlobalScopes() // SUPERADMIN: cleanup de teste
        ->where('business_id', SEEDER_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona Front 280g',
            'Lona Back 440g',
            'Vinil Adesivo Brilho Branco',
            'ACM 3mm Branco',
            'Vinil Plotter Recorte Branco',
        ])
        ->forceDelete();

    // Resetar sessão
    session(['user.business_id' => null]);
});
