<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-021 / ADR 0321 (PR-C) — schemaReforma seleciona o schema do Make
 * por business. Garante que `legacy` (default de todos hoje) → null → `new Make(null)`
 * ≡ `new Make()` atual (XML byte-idêntico, schema PL_009_V4). Só `full`/`hybrid_2026`
 * (opt-in explícito) → `PL_010_V1` (habilita grupo UB IBS/CBS na PR-D).
 *
 * Business isolado 999999 (não toca dogfood biz=1) — pattern de isolamento scope.
 */

const REFORMA_BIZ = 999999;

function schemaReformaDe(int $bizId): ?string
{
    $svc = new NfeService(\Mockery::mock(CertificadoService::class));
    $m = new ReflectionMethod(NfeService::class, 'schemaReforma');
    $m->setAccessible(true);

    return $m->invoke($svc, $bizId);
}

function criarConfigReforma(string $modo): void
{
    NfeBusinessConfig::create([
        'business_id'             => REFORMA_BIZ,
        'regime'                  => 'lucro_presumido',
        'tributacao_default'      => ['cfop' => '5102', 'cst' => '000'],
        'reforma_tributaria_modo' => $modo,
    ]);
}

beforeEach(function () {
    // NfeBrasil requer schema MySQL UltimatePOS (ADR 0101) — nfe_business_configs não
    // existe no SQLite :memory: da lane de sanidade. Roda só na lane MySQL (gate real).
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: nfe_business_configs requer schema MySQL (ADR 0101)');
    }
    session(['business.id' => REFORMA_BIZ]);
    NfeBusinessConfig::withoutGlobalScopes()->where('business_id', REFORMA_BIZ)->delete();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        NfeBusinessConfig::withoutGlobalScopes()->where('business_id', REFORMA_BIZ)->delete();
    }
    \Mockery::close();
});

it('sem config → legacy → schema null (byte-idêntico ao new Make() atual)', function () {
    expect(schemaReformaDe(REFORMA_BIZ))->toBeNull();
})->group('nfe');

it('modo legacy explícito → schema null', function () {
    criarConfigReforma('legacy');
    expect(schemaReformaDe(REFORMA_BIZ))->toBeNull();
})->group('nfe');

it('modo full → PL_010_V1 (habilita grupo UB)', function () {
    criarConfigReforma('full');
    expect(schemaReformaDe(REFORMA_BIZ))->toBe('PL_010_V1');
})->group('nfe');

it('modo hybrid_2026 → PL_010_V1', function () {
    criarConfigReforma('hybrid_2026');
    expect(schemaReformaDe(REFORMA_BIZ))->toBe('PL_010_V1');
})->group('nfe');
