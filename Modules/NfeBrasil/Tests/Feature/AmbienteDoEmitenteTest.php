<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use Modules\NfeBrasil\Support\AmbienteDoEmitente;

uses(Tests\TestCase::class);

/**
 * Auditoria Fiscal 2026-11-05, lote 0.2 — o ambiente SEFAZ (tpAmb) de manifestação,
 * DF-e e inutilização sai de `business.ambiente`, a mesma coluna da emissão de NF-e.
 *
 * Antes: `config('nfebrasil.ambiente', 2)` (chave inexistente) e `env('NFE_AMBIENTE', 2)`
 * davam sempre 2, mesmo com a empresa em produção. Por isso cada caso roda nos DOIS
 * ambientes: com a empresa em 1, a leitura antiga devolveria 2 e o assert cai.
 *
 * Tenant de teste 98 (ADR 0358). O valor original de `business.ambiente` é restaurado.
 */
function comAmbienteDoTenant(int $ambiente, Closure $fn): void
{
    if (! Schema::hasColumn('business', 'ambiente')) {
        test()->markTestSkipped('business.ambiente ausente neste schema.');
    }

    $businessId = (int) test()->seededTenant()->id;
    $original = DB::table('business')->where('id', $businessId)->value('ambiente');

    DB::table('business')->where('id', $businessId)->update(['ambiente' => $ambiente]);
    try {
        $fn($businessId);
    } finally {
        DB::table('business')->where('id', $businessId)->update(['ambiente' => $original]);
    }
}

function chamarPrivado(object $alvo, string $metodo, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($alvo, $metodo);
    $ref->setAccessible(true);

    return $ref->invoke($alvo, ...$args);
}

it('resolve o ambiente gravado na empresa (1 = produção, 2 = homologação)', function (int $ambiente) {
    comAmbienteDoTenant($ambiente, function (int $businessId) use ($ambiente) {
        expect(AmbienteDoEmitente::resolver($businessId))->toBe($ambiente);
    });
})->with([1, 2]);

it('empresa inexistente cai em homologação', function () {
    if (! Schema::hasColumn('business', 'ambiente')) {
        $this->markTestSkipped('business.ambiente ausente neste schema.');
    }

    expect(AmbienteDoEmitente::resolver(999_999_999))->toBe(2);
});

it('manifestação usa o ambiente da empresa', function (int $ambiente) {
    comAmbienteDoTenant($ambiente, function (int $businessId) use ($ambiente) {
        $service = new ManifestacaoService(Mockery::mock(CertificadoService::class));
        expect(chamarPrivado($service, 'ambienteAtual', $businessId))->toBe($ambiente);
    });
})->with([1, 2]);

it('inutilização monta o config com o tpAmb da empresa', function (int $ambiente) {
    comAmbienteDoTenant($ambiente, function (int $businessId) use ($ambiente) {
        $service = new NfeInutilizacaoService(Mockery::mock(CertificadoService::class));
        $config = json_decode((string) chamarPrivado($service, 'buildConfig', $businessId), true);
        expect($config['tpAmb'])->toBe($ambiente);
    });
})->with([1, 2]);
