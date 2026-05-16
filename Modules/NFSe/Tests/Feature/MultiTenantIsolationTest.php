<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 dos Models NFSe.
 *
 * NFSe = Nota Fiscal de Serviço Eletrônica (fiscal/compliance). Vazar dado
 * cross-tenant aqui é incidente regulatório — IRS estadual/municipal exposto.
 *
 * ADR 0093: global scope business_id IRREVOGÁVEL (trait NfseBusinessScope).
 * Dados do biz=1 NÃO podem aparecer em queries com session biz=99 e vice-versa.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — conforme ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados reais).
 *
 * Modelos cobertos:
 *   - NfseEmissao        (tabela nfse_emissoes)
 *   - NfseProviderConfig (tabela nfse_provider_configs)
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/NFSe/Models/Concerns/NfseBusinessScope.php
 */

// Guard SQLite: Models NFSe com global scope requerem schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models NFSe com NfseBusinessScope requerem schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfse_provider_configs')) {
        $this->markTestSkipped('Tabelas nfse_emissoes / nfse_provider_configs ausentes — rode Modules/NFSe migrate primeiro');
    }
});

// IDs usados nos testes — biz=1 (Wagner WR2) e biz=99 (fictício isolamento)
const BIZ_NFSE_WAGNER   = 1;
const BIZ_NFSE_FICTICIO = 99;

// ------------------------------------------------------------------
// Helpers internos
// ------------------------------------------------------------------

/**
 * Simula sessão de um business sem autenticar usuário (suficiente pro global scope NFSe).
 */
function setNfseBizSession(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

// ------------------------------------------------------------------
// NfseEmissao — emissão de nota fiscal de serviço
// ------------------------------------------------------------------

it('NfseEmissao biz=1 não aparece com session biz=99', function () {
    // Insere emissão biz=1 bypassando scope (setup de teste)
    $emissao = NfseEmissao::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'      => BIZ_NFSE_WAGNER,
        'serie'            => 'RPS',
        'rps_numero'       => 'RPS-TESTE-ISOL-001',
        'competencia'      => now()->startOfMonth()->toDateString(),
        'tomador_cnpj'     => '00000000000000', // PII redacted — CNPJ fictício
        'tomador_nome'     => 'TOMADOR FICTICIO TESTE LTDA',
        'descricao'        => 'Servico teste isolamento multi-tenant',
        'valor_servicos'   => 100.00,
        'valor_base_calculo' => 100.00,
        'aliquota_iss'     => 0.02,
        'valor_iss'        => 2.00,
        'status'           => 'rascunho',
        'idempotency_key'  => 'TEST-ISOL-NFSE-EMISSAO-001-' . uniqid(),
    ]);

    // Consulta com session biz=99 — NÃO deve aparecer (Tier 0 isolation)
    setNfseBizSession(BIZ_NFSE_FICTICIO);
    $resultado = NfseEmissao::where('id', $emissao->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    NfseEmissao::withoutGlobalScopes()
        ->where('rps_numero', 'RPS-TESTE-ISOL-001')
        ->forceDelete();
});

it('NfseEmissao biz=1 aparece com session biz=1', function () {
    $emissao = NfseEmissao::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'      => BIZ_NFSE_WAGNER,
        'serie'            => 'RPS',
        'rps_numero'       => 'RPS-TESTE-ISOL-002',
        'competencia'      => now()->startOfMonth()->toDateString(),
        'tomador_cnpj'     => '00000000000000',
        'tomador_nome'     => 'TOMADOR FICTICIO TESTE 2 LTDA',
        'descricao'        => 'Servico teste mesmo-tenant',
        'valor_servicos'   => 250.00,
        'valor_base_calculo' => 250.00,
        'aliquota_iss'     => 0.02,
        'valor_iss'        => 5.00,
        'status'           => 'rascunho',
        'idempotency_key'  => 'TEST-ISOL-NFSE-EMISSAO-002-' . uniqid(),
    ]);

    setNfseBizSession(BIZ_NFSE_WAGNER);
    $resultado = NfseEmissao::where('id', $emissao->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->rps_numero)->toBe('RPS-TESTE-ISOL-002');
})->afterEach(function () {
    NfseEmissao::withoutGlobalScopes()
        ->where('rps_numero', 'RPS-TESTE-ISOL-002')
        ->forceDelete();
});

it('NfseEmissao all() com session biz=99 retorna apenas dados do biz=99', function () {
    // Cria 2 emissões — uma biz=1, outra biz=99
    NfseEmissao::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'      => BIZ_NFSE_WAGNER,
        'serie'            => 'RPS',
        'rps_numero'       => 'RPS-TESTE-ISOL-CROSS-W',
        'competencia'      => now()->startOfMonth()->toDateString(),
        'tomador_nome'     => 'WAGNER WR2 TESTE FICTICIO',
        'descricao'        => 'Servico biz=1',
        'valor_servicos'   => 100.00,
        'valor_base_calculo' => 100.00,
        'valor_iss'        => 2.00,
        'status'           => 'rascunho',
        'idempotency_key'  => 'TEST-ISOL-NFSE-CROSS-W-' . uniqid(),
    ]);

    NfseEmissao::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'      => BIZ_NFSE_FICTICIO,
        'serie'            => 'RPS',
        'rps_numero'       => 'RPS-TESTE-ISOL-CROSS-F',
        'competencia'      => now()->startOfMonth()->toDateString(),
        'tomador_nome'     => 'FICTICIO BIZ 99 TESTE',
        'descricao'        => 'Servico biz=99',
        'valor_servicos'   => 50.00,
        'valor_base_calculo' => 50.00,
        'valor_iss'        => 1.00,
        'status'           => 'rascunho',
        'idempotency_key'  => 'TEST-ISOL-NFSE-CROSS-F-' . uniqid(),
    ]);

    // Session biz=99 só enxerga RPS-TESTE-ISOL-CROSS-F
    setNfseBizSession(BIZ_NFSE_FICTICIO);
    $resultado = NfseEmissao::whereIn('rps_numero', ['RPS-TESTE-ISOL-CROSS-W', 'RPS-TESTE-ISOL-CROSS-F'])->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->rps_numero)->toBe('RPS-TESTE-ISOL-CROSS-F');
    expect($resultado->first()->business_id)->toBe(BIZ_NFSE_FICTICIO);
})->afterEach(function () {
    NfseEmissao::withoutGlobalScopes()
        ->whereIn('rps_numero', ['RPS-TESTE-ISOL-CROSS-W', 'RPS-TESTE-ISOL-CROSS-F'])
        ->forceDelete();
});

// ------------------------------------------------------------------
// NfseProviderConfig — configuração de prefeitura por business
// ------------------------------------------------------------------

it('NfseProviderConfig biz=1 não aparece com session biz=99', function () {
    $config = NfseProviderConfig::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'           => BIZ_NFSE_WAGNER,
        'provider'              => 'sn_nfse_federal',
        'municipio_codigo_ibge' => '4218905', // Termas do Gravatal/SC (fictício para teste — único por business)
        'serie_default'         => 'RPS',
        'cnae'                  => '6201-5/00',
        'lc116_codigo_default'  => '1.05',
        'aliquota_iss'          => 0.02,
        'ambiente'              => 'homologacao',
    ]);

    // Consulta com session biz=99 — NÃO deve aparecer
    setNfseBizSession(BIZ_NFSE_FICTICIO);
    $resultado = NfseProviderConfig::where('id', $config->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    NfseProviderConfig::withoutGlobalScopes()
        ->where('business_id', BIZ_NFSE_WAGNER)
        ->where('municipio_codigo_ibge', '4218905')
        ->forceDelete();
});

it('NfseProviderConfig biz=1 aparece com session biz=1', function () {
    $config = NfseProviderConfig::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'           => BIZ_NFSE_WAGNER,
        'provider'              => 'sn_nfse_federal',
        'municipio_codigo_ibge' => '3550308', // São Paulo (fictício para teste)
        'serie_default'         => 'RPS',
        'cnae'                  => '6201-5/00',
        'lc116_codigo_default'  => '1.05',
        'aliquota_iss'          => 0.05,
        'ambiente'              => 'homologacao',
    ]);

    setNfseBizSession(BIZ_NFSE_WAGNER);
    $resultado = NfseProviderConfig::where('id', $config->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->municipio_codigo_ibge)->toBe('3550308');
    expect($resultado->first()->ambiente)->toBe('homologacao');
})->afterEach(function () {
    NfseProviderConfig::withoutGlobalScopes()
        ->where('business_id', BIZ_NFSE_WAGNER)
        ->where('municipio_codigo_ibge', '3550308')
        ->forceDelete();
});

it('NfseProviderConfig biz=1 e biz=99 coexistem isolados no mesmo município', function () {
    // unique(business_id, municipio_codigo_ibge) permite mesma cidade em business diferente
    $configWagner = NfseProviderConfig::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'           => BIZ_NFSE_WAGNER,
        'provider'              => 'sn_nfse_federal',
        'municipio_codigo_ibge' => '4314902', // Porto Alegre
        'ambiente'              => 'producao',
        'aliquota_iss'          => 0.04,
    ]);

    $configFicticio = NfseProviderConfig::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste cross-tenant
        'business_id'           => BIZ_NFSE_FICTICIO,
        'provider'              => 'sn_nfse_federal',
        'municipio_codigo_ibge' => '4314902', // mesmo município, outro tenant
        'ambiente'              => 'homologacao',
        'aliquota_iss'          => 0.02,
    ]);

    // Session biz=99 SÓ enxerga config do biz=99 (não vaza ambiente=producao do biz=1)
    setNfseBizSession(BIZ_NFSE_FICTICIO);
    $resultado = NfseProviderConfig::where('municipio_codigo_ibge', '4314902')->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->business_id)->toBe(BIZ_NFSE_FICTICIO);
    expect($resultado->first()->ambiente)->toBe('homologacao');
})->afterEach(function () {
    NfseProviderConfig::withoutGlobalScopes()
        ->where('municipio_codigo_ibge', '4314902')
        ->whereIn('business_id', [BIZ_NFSE_WAGNER, BIZ_NFSE_FICTICIO])
        ->forceDelete();
});
