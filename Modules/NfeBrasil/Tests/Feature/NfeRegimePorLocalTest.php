<?php

declare(strict_types=1);

// @covers R-NFE-031 · R-NFE-032 — local emissor próprio, agora com o regime dele (pedido P-155).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeLocalEmissor;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * P-155 · dois CNPJs com regimes diferentes na mesma empresa (Sabor Brasil, decisão [W] 2026-10-29:
 * uma empresa com dois locais emissores). O local guarda o próprio CRT; vazio = o da empresa.
 *
 * MySQL-only · tenant fictício 98 (`seededTenant`) — nunca biz=1 nem biz=4 (ADR 0358). CNPJ, IE e
 * endereço do local são fictícios.
 *
 * Dupla prova da conta (fixture, à mão): tempero de 1.000,00 de SP para supermercado contribuinte
 * em SP, ICMS próprio 18%, MVA 62,3%, interna 18%, sem IPI.
 *   ICMS próprio = 1.000,00 × 0,18 = 180,00
 *   base ST = 1.000,00 × 1,623 = 1.623,00 · ST = 1.623,00 × 0,18 − 180,00 = 292,14 − 180,00 = 112,14
 * A mesma regra traz CST 10 (para o local do regime normal) e CSOSN 500 (para o do Simples, ST já
 * retido). Antes, sem regime por local, o CSOSN 500 zerava o ST dos dois.
 */

const P155_NCM = '77155001';

function p155Biz(): int
{
    return test()->seededTenant()->id;
}

function p155Svc(): NfeService
{
    return new NfeService(\Mockery::mock(CertificadoService::class));
}

function p155Local(int $biz): int
{
    $base = DB::table('business_locations')->where('business_id', $biz)->orderBy('id')->first();

    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        return (int) DB::table('business_locations')->insertGetId([
            'business_id' => $biz, 'name' => 'P155 LOCAL', 'country' => 'Brasil', 'state' => 'SP',
            'city' => 'SAO PAULO', 'zip_code' => '0100000',
            'invoice_scheme_id' => $base->invoice_scheme_id ?? 0, 'invoice_layout_id' => $base->invoice_layout_id ?? 0,
            'cnpj' => '11222333000343', 'razao_social' => 'P155 LOCAL LTDA', // pii-allowlist (CNPJ fictício)
            'inscricao_estadual' => '111222333444', 'created_at' => now(), 'updated_at' => now(),
        ]);
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

function p155Config(int $biz, int $locationId, ?int $crt): void
{
    NfeLocalEmissor::withoutGlobalScopes()->create([
        'business_id' => $biz, 'location_id' => $locationId,
        'serie_nfe' => '8', 'serie_nfce' => '8', 'crt' => $crt,
        'logradouro' => 'RUA DO LOCAL', 'numero' => '20', 'bairro' => 'CENTRO',
        'cod_municipio' => '3550308', 'cep' => '01001000',
    ]);
}

function p155Regra(int $biz): void
{
    NfeFiscalRule::create([
        'business_id' => $biz, 'ncm' => P155_NCM, 'uf_origem' => 'SP', 'uf_destino' => 'SP',
        'cfop' => '5405', 'csosn' => '500', 'cst' => '10',
        'aliquota_icms' => 0.18, 'aliquota_pis' => 0.0, 'aliquota_cofins' => 0.0, 'aliquota_ipi' => 0.0,
        'mva' => 0.623,
    ]);
}

function p155Itens(?int $crt): array
{
    return p155Svc()->montarItensNfe(
        linhas: [['sell_line_id' => 1, 'product_id' => 9155, 'cprod' => 'TEMPERO', 'xprod' => 'Tempero 1',
            'ncm' => P155_NCM, 'unidade' => 'UN', 'quantidade' => 1.0, 'valor_unitario' => 1000.00]],
        valorNota: 1000.00, frete: 0, ncmDefault: '49111090', motor: new MotorTributarioService(),
        businessId: p155Biz(), ufOrigem: 'SP', ufDestino: 'SP', internaDestino: 0.18,
        destinatarioContribuinte: true, crtEmitente: $crt,
    );
}

function p155Limpar(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('nfe_locais_emissores', 'crt')) {
        return;
    }
    $biz = test()->seededTenant()->id;
    DB::table('nfe_locais_emissores')->where('business_id', $biz)->where('serie_nfe', '8')->delete();
    DB::table('business_locations')->where('business_id', $biz)->where('name', 'P155 LOCAL')->delete();
    DB::table('nfe_fiscal_rules')->where('business_id', $biz)->where('ncm', P155_NCM)->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: business_locations do UltimatePOS + nfe_fiscal_rules.');
    }
    if (! Schema::hasColumn('nfe_locais_emissores', 'crt')) {
        $this->markTestSkipped('Migração 2026_10_29_000001 não rodou.');
    }
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    p155Limpar();
});

afterEach(function () {
    p155Limpar();
    \Mockery::close();
});

// ── O local leva o regime dele ao emitente ─────────────────────────────────

it('P-155 · local com regime normal emite com CRT 3', function () {
    $biz = p155Biz();
    $business = DB::table('business')->where('id', $biz)->first();
    $local = p155Local($biz);
    p155Config($biz, $local, 3);

    $e = p155Svc()->resolverEmitente($business, $local, '55');

    expect($e['crt'])->toBe(3);
    expect($e['emit']['crt'])->toBe(3);
});

it('P-155 · local sem regime segue o da empresa: o emitente não leva CRT, como antes', function () {
    $biz = p155Biz();
    $business = DB::table('business')->where('id', $biz)->first();
    $local = p155Local($biz);
    p155Config($biz, $local, null);

    $e = p155Svc()->resolverEmitente($business, $local, '55');

    expect($e['crt'])->toBeNull();
    expect($e['emit'])->not->toHaveKey('crt');
});

it('P-155 · regime que não existe é recusado com a saída', function () {
    $biz = p155Biz();
    $business = DB::table('business')->where('id', $biz)->first();
    $local = p155Local($biz);
    p155Config($biz, $local, 7);

    expect(fn () => p155Svc()->resolverEmitente($business, $local, '55'))
        ->toThrow(RuntimeException::class, 'CRT');
});

// ── O ST e o código do item seguem o regime do local ───────────────────────

it('P-155 · motor: no regime normal a regra com CST 10 calcula o ST (112,14)', function () {
    p155Regra(p155Biz());
    $produto = new ProdutoFiscalContext(ncm: P155_NCM, valor: 1000.00);

    $t = (new MotorTributarioService())->calcularComRegimeDoLocal(
        $produto, businessId: p155Biz(), ufOrigem: 'SP', ufDestino: 'SP', crtEmitente: 3,
        aliquotaInternaDestino: 0.18, destinatarioContribuinte: true,
    );

    expect($t->base_st)->toBe(1623.0);
    expect($t->valor_st)->toBe(112.14);
});

it('P-155 · motor: no Simples a mesma regra (CSOSN 500, ST já retido) não calcula ST', function () {
    p155Regra(p155Biz());
    $produto = new ProdutoFiscalContext(ncm: P155_NCM, valor: 1000.00);

    $t = (new MotorTributarioService())->calcularComRegimeDoLocal(
        $produto, businessId: p155Biz(), ufOrigem: 'SP', ufDestino: 'SP', crtEmitente: 1,
        aliquotaInternaDestino: 0.18, destinatarioContribuinte: true,
    );

    expect($t->valor_st)->toBe(0.0);
});

it('P-155 · controle: sem regime do local, o motor faz o de antes (CSOSN 500 zera o ST)', function () {
    p155Regra(p155Biz());
    $produto = new ProdutoFiscalContext(ncm: P155_NCM, valor: 1000.00);

    $t = (new MotorTributarioService())->calcularComDestino(
        $produto, businessId: p155Biz(), ufOrigem: 'SP', ufDestino: 'SP',
        aliquotaInternaDestino: 0.18, destinatarioContribuinte: true,
    );

    expect($t->valor_st)->toBe(0.0);
});

it('P-155 · item: regime normal leva CST 10, base do ICMS 1.000,00 e ST 112,14 no total', function () {
    p155Regra(p155Biz());

    $r = p155Itens(3);

    expect($r['dets'][0]['icms']['cst_csosn'])->toBe('10');
    expect($r['dets'][0]['icms']['vbc'])->toBe(1000.0);
    expect($r['total']['v_st'])->toBe(112.14);
});

it('P-155 · item: Simples leva CSOSN 500, sem base do ICMS e sem ST', function () {
    p155Regra(p155Biz());

    $r = p155Itens(1);

    expect($r['dets'][0]['icms']['cst_csosn'])->toBe('500');
    expect($r['dets'][0]['icms']['vbc'])->toBe(0.0);
    expect($r['total']['v_st'])->toBe(0.0);
});

it('P-155 · controle: item sem regime do local sai igual a antes (CSOSN 500, sem ST)', function () {
    p155Regra(p155Biz());

    $r = p155Itens(null);

    expect($r['dets'][0]['icms']['cst_csosn'])->toBe('500');
    expect($r['total']['v_st'])->toBe(0.0);
});
