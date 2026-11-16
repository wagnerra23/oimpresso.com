<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Isolamento multi-tenant Tier 0 dos Models NFSe (ADR 0093, trait NfseBusinessScope).
 *
 * Empresa da sessão = 98 (fictícia canônica, ADR 0358); adversário = 99. Nunca biz=4,
 * nunca biz=1 (WR2, empresa real: no CT 100 a base é clone de produção e não se limpa).
 * Tudo roda dentro de DatabaseTransactions — o rollback desfaz só o que o teste gravou.
 *
 * ⚠️ `rps_numero` é varchar(20). A versão anterior gravava 'RPS-TESTE-ISOL-CROSS-F'
 * (22 caracteres): o valor era truncado e a busca exata devolvia 0 linhas — foi o
 * vermelho do run 43865896173 (:154), defeito do teste, não do escopo.
 *
 * Modelos cobertos: NfseEmissao (nfse_emissoes) · NfseProviderConfig (nfse_provider_configs).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see Modules/NFSe/Models/Concerns/NfseBusinessScope.php
 */

const NFSE_ISOL_TENANT = 98;
const NFSE_ISOL_ADVERSARIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requer MySQL (lane nfse-pest).');
    }
    if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfse_provider_configs')) {
        $this->markTestSkipped('Tabelas nfse_emissoes / nfse_provider_configs ausentes.');
    }
    if (! \App\Business::find(NFSE_ISOL_TENANT) || ! \App\Business::find(NFSE_ISOL_ADVERSARIO)) {
        $this->markTestSkipped('Lane sem os tenants 98/99 semeados (ADR 0358).');
    }
});

/** Sessão de uma empresa sem login — basta para o NfseBusinessScope (modo aberto lê a sessão). */
function nfseIsolSessao(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

/** Emissão crua de uma empresa, gravada sem escopo (setup do teste). */
function nfseIsolEmissao(int $businessId, string $rps): NfseEmissao
{
    expect(strlen($rps))->toBeLessThanOrEqual(20, "rps_numero '{$rps}' passa de varchar(20) e seria truncado.");

    return NfseEmissao::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'        => $businessId,
        'serie'              => 'RPS',
        'rps_numero'         => $rps,
        'competencia'        => now()->startOfMonth()->toDateString(),
        'tomador_nome'       => 'TOMADOR FICTICIO TESTE LTDA',
        'descricao'          => 'Servico teste isolamento multi-tenant',
        'valor_servicos'     => 100.00,
        'valor_base_calculo' => 100.00,
        'aliquota_iss'       => 0.02,
        'valor_iss'          => 2.00,
        'status'             => 'rascunho',
        'idempotency_key'    => 'TEST-ISOL-NFSE-' . uniqid('', true),
    ]);
}

// ------------------------------------------------------------------
// NfseEmissao
// ------------------------------------------------------------------

it('[T0] NfseEmissao da empresa 99 nao aparece com sessao 98', function () {
    $alheia = nfseIsolEmissao(NFSE_ISOL_ADVERSARIO, 'ISOL-A-99');

    nfseIsolSessao(NFSE_ISOL_TENANT);

    expect(NfseEmissao::where('id', $alheia->id)->get())->toHaveCount(0);
    // A linha existe: o 0 acima é o escopo, não ausência do dado.
    expect(NfseEmissao::withoutGlobalScopes()->whereKey($alheia->id)->exists())->toBeTrue(); // SUPERADMIN: prova de existência
});

it('[T0] NfseEmissao da empresa 98 aparece com sessao 98 (controle positivo)', function () {
    $propria = nfseIsolEmissao(NFSE_ISOL_TENANT, 'ISOL-P-98');

    nfseIsolSessao(NFSE_ISOL_TENANT);
    $resultado = NfseEmissao::where('id', $propria->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->rps_numero)->toBe('ISOL-P-98');
});

it('[T0] NfseEmissao com sessao 99 so devolve as notas da 99', function () {
    nfseIsolEmissao(NFSE_ISOL_TENANT, 'ISOL-X-98');
    nfseIsolEmissao(NFSE_ISOL_ADVERSARIO, 'ISOL-X-99');

    nfseIsolSessao(NFSE_ISOL_ADVERSARIO);
    $resultado = NfseEmissao::whereIn('rps_numero', ['ISOL-X-98', 'ISOL-X-99'])->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->rps_numero)->toBe('ISOL-X-99');
    expect((int) $resultado->first()->business_id)->toBe(NFSE_ISOL_ADVERSARIO);
});

// ------------------------------------------------------------------
// NfseProviderConfig — configuração de prefeitura por empresa
// ------------------------------------------------------------------

function nfseIsolConfig(int $businessId, string $ibge, string $ambiente, float $aliquota): NfseProviderConfig
{
    return NfseProviderConfig::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'           => $businessId,
        'provider'              => 'sn_nfse_federal',
        'municipio_codigo_ibge' => $ibge,
        'serie_default'         => 'RPS',
        'cnae'                  => '6201-5/00',
        'lc116_codigo_default'  => '1.05',
        'aliquota_iss'          => $aliquota,
        'ambiente'              => $ambiente,
    ]);
}

it('[T0] NfseProviderConfig da empresa 99 nao aparece com sessao 98', function () {
    $alheia = nfseIsolConfig(NFSE_ISOL_ADVERSARIO, '4218905', 'homologacao', 0.02);

    nfseIsolSessao(NFSE_ISOL_TENANT);

    expect(NfseProviderConfig::where('id', $alheia->id)->get())->toHaveCount(0);
    expect(NfseProviderConfig::withoutGlobalScopes()->whereKey($alheia->id)->exists())->toBeTrue(); // SUPERADMIN: prova de existência
});

it('[T0] NfseProviderConfig da empresa 98 aparece com sessao 98 (controle positivo)', function () {
    $propria = nfseIsolConfig(NFSE_ISOL_TENANT, '3550308', 'homologacao', 0.05);

    nfseIsolSessao(NFSE_ISOL_TENANT);
    $resultado = NfseProviderConfig::where('id', $propria->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->municipio_codigo_ibge)->toBe('3550308');
    expect($resultado->first()->ambiente)->toBe('homologacao');
});

it('[T0] NfseProviderConfig 98 e 99 no mesmo municipio: sessao 99 nao ve a producao da 98', function () {
    // unique(business_id, municipio_codigo_ibge) permite a mesma cidade em empresas diferentes
    nfseIsolConfig(NFSE_ISOL_TENANT, '4314902', 'producao', 0.04);
    nfseIsolConfig(NFSE_ISOL_ADVERSARIO, '4314902', 'homologacao', 0.02);

    nfseIsolSessao(NFSE_ISOL_ADVERSARIO);
    $resultado = NfseProviderConfig::where('municipio_codigo_ibge', '4314902')->get();

    expect($resultado)->toHaveCount(1);
    expect((int) $resultado->first()->business_id)->toBe(NFSE_ISOL_ADVERSARIO);
    expect($resultado->first()->ambiente)->toBe('homologacao');
});
