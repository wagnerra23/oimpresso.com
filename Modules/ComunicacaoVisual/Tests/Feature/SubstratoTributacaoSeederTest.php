<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Database\Seeders\SubstratoTributacaoCnae1813Seeder;
use Modules\ComunicacaoVisual\Entities\Substrato;

uses(Tests\TestCase::class);

/**
 * Testes seeder SubstratoTributacaoCnae1813Seeder (US-COMVIS-006).
 *
 * Cobre:
 *   - Cria 8 substratos canon com NCM/CFOP/CSOSN preenchidos
 *   - Idempotente (rodar 2x não duplica)
 *   - Multi-tenant Tier 0 (biz=1 não contamina biz=99)
 *
 * @see Modules\ComunicacaoVisual\Database\Seeders\SubstratoTributacaoCnae1813Seeder
 */

const SEED_BIZ_WAGNER = 1;
const SEED_BIZ_OUTRO = 99;

beforeEach(function () {
    if (! Schema::hasTable('cv_substratos')) {
        $this->markTestSkipped('cv_substratos table missing.');
    }
    // Limpeza idempotência
    Substrato::withoutGlobalScopes()
        ->whereIn('business_id', [SEED_BIZ_WAGNER, SEED_BIZ_OUTRO])
        ->where('nome', 'like', 'Lona %')
        ->orWhere('nome', 'like', 'Banner %')
        ->orWhere('nome', 'like', 'Adesivo %')
        ->orWhere('nome', 'like', 'ACM %')
        ->orWhere('nome', 'like', 'Acrílico %')
        ->orWhere('nome', 'like', 'MDF %')
        ->forceDelete();
});

afterEach(function () {
    Substrato::withoutGlobalScopes()
        ->whereIn('business_id', [SEED_BIZ_WAGNER, SEED_BIZ_OUTRO])
        ->whereIn('nome', [
            'Lona FrontLight 440g', 'Lona BlockOut 510g', 'Banner 13oz PVC',
            'Adesivo Vinil 80μm', 'Adesivo Refletivo Grau Engenharia',
            'ACM 3mm Branco Brilhante', 'Acrílico 4mm Transparente', 'MDF 9mm Cru',
        ])
        ->forceDelete();
});

it('Seeder cria 8 substratos canon com NCM/CFOP/CSOSN preenchidos', function () {
    $seeder = new SubstratoTributacaoCnae1813Seeder();
    $criados = $seeder->runForBusiness(SEED_BIZ_WAGNER);

    expect($criados)->toBe(8);

    $todos = Substrato::withoutGlobalScopes()
        ->where('business_id', SEED_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona FrontLight 440g', 'Lona BlockOut 510g', 'Banner 13oz PVC',
            'Adesivo Vinil 80μm', 'Adesivo Refletivo Grau Engenharia',
            'ACM 3mm Branco Brilhante', 'Acrílico 4mm Transparente', 'MDF 9mm Cru',
        ])->get();

    expect($todos)->toHaveCount(8);

    // Cada substrato tem NCM/CFOP/CSOSN preenchidos
    foreach ($todos as $s) {
        expect($s->ncm)->not->toBeNull("Substrato {$s->nome} sem NCM");
        expect($s->cfop_padrao)->not->toBeNull("Substrato {$s->nome} sem CFOP");
        expect($s->csosn_padrao)->not->toBeNull("Substrato {$s->nome} sem CSOSN");
        expect($s->ativo)->toBeTrue();
        expect((float) $s->preco_venda_m2)->toBeGreaterThan(0);
    }
});

it('Seeder é idempotente — segunda chamada cria 0 substratos novos', function () {
    $seeder = new SubstratoTributacaoCnae1813Seeder();

    $primeira = $seeder->runForBusiness(SEED_BIZ_WAGNER);
    expect($primeira)->toBe(8);

    $segunda = $seeder->runForBusiness(SEED_BIZ_WAGNER);
    expect($segunda)->toBe(0); // tudo já existe

    $total = Substrato::withoutGlobalScopes()
        ->where('business_id', SEED_BIZ_WAGNER)
        ->whereIn('nome', [
            'Lona FrontLight 440g', 'Lona BlockOut 510g', 'Banner 13oz PVC',
            'Adesivo Vinil 80μm', 'Adesivo Refletivo Grau Engenharia',
            'ACM 3mm Branco Brilhante', 'Acrílico 4mm Transparente', 'MDF 9mm Cru',
        ])->count();
    expect($total)->toBe(8); // sem duplicação
});

it('Seeder é multi-tenant — substratos biz=1 NÃO aparecem em biz=99 session', function () {
    $seeder = new SubstratoTributacaoCnae1813Seeder();
    $seeder->runForBusiness(SEED_BIZ_WAGNER);

    // Session biz=99 — global scope deve ocultar substratos biz=1
    session(['user.business_id' => SEED_BIZ_OUTRO]);
    $visiveis = Substrato::where('nome', 'Lona FrontLight 440g')->get();
    expect($visiveis)->toHaveCount(0);

    // Volta pra biz=1 — vê tudo
    session(['user.business_id' => SEED_BIZ_WAGNER]);
    $visiveis2 = Substrato::where('nome', 'Lona FrontLight 440g')->get();
    expect($visiveis2)->toHaveCount(1);
});

it('Seeder NCMs canon: ACM=7610.90, MDF=4411.13, Vinil=3919.90, Refletivo=3919.10', function () {
    $seeder = new SubstratoTributacaoCnae1813Seeder();
    $seeder->runForBusiness(SEED_BIZ_WAGNER);

    $acm = Substrato::withoutGlobalScopes()
        ->where('business_id', SEED_BIZ_WAGNER)
        ->where('nome', 'ACM 3mm Branco Brilhante')->first();
    expect($acm->ncm)->toBe('7610.90');
    expect($acm->cfop_padrao)->toBe('5102'); // adquirido de terceiros

    $mdf = Substrato::withoutGlobalScopes()
        ->where('business_id', SEED_BIZ_WAGNER)
        ->where('nome', 'MDF 9mm Cru')->first();
    expect($mdf->ncm)->toBe('4411.13');

    $vinil = Substrato::withoutGlobalScopes()
        ->where('business_id', SEED_BIZ_WAGNER)
        ->where('nome', 'Adesivo Vinil 80μm')->first();
    expect($vinil->ncm)->toBe('3919.90');
    expect($vinil->cfop_padrao)->toBe('5101'); // produção própria (gráfica imprime)

    $refletivo = Substrato::withoutGlobalScopes()
        ->where('business_id', SEED_BIZ_WAGNER)
        ->where('nome', 'Adesivo Refletivo Grau Engenharia')->first();
    expect($refletivo->ncm)->toBe('3919.10');
});
