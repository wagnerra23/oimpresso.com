<?php

declare(strict_types=1);

// @covers-us US-NFE-067 — filial em outro estado: emitente, UF de origem e série do local (playbook Fiscal thread 26).
// @covers R-NFE-031 · R-NFE-032

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeLocalEmissor;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Thread 26 · quem emite é o local da venda quando ele é estabelecimento emissor próprio.
 *
 * MySQL-only · tenant fictício 98 (`seededTenant`) e cliente fictício 99 — nunca biz=1 nem biz=4
 * (ADR 0358). CNPJ, IE e endereço da filial são fictícios. Os casos derivam do aceite da thread 26.
 */

const T26_NUMEROS = [996000, 996001, 996002]; // contador legado 995999 + 1, + 2, + 3
const T26_SERIE_FILIAL = '7';

function t26Biz(): int
{
    return test()->seededTenant()->id;
}

function t26Svc(): NfeService
{
    return new NfeService(\Mockery::mock(CertificadoService::class));
}

/**
 * Cria um local (filial) com scheme/layout de um local que a empresa já tem. O local semeado do
 * tenant de teste nasce com scheme/layout 0 (semente sem FK), então o insert desliga a checagem
 * de FK só nesta linha — o que se testa aqui é o emissor, não o cadastro de invoice scheme.
 */
function t26Local(int $biz, string $uf = 'PR'): int
{
    $base = DB::table('business_locations')->where('business_id', $biz)->orderBy('id')->first();

    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        return (int) DB::table('business_locations')->insertGetId([
            'business_id' => $biz, 'name' => 'T26 FILIAL ' . $uf, 'country' => 'Brasil', 'state' => $uf,
            'city' => 'CURITIBA', 'zip_code' => '8000000',
            'invoice_scheme_id' => $base->invoice_scheme_id ?? 0, 'invoice_layout_id' => $base->invoice_layout_id ?? 0,
            'cnpj' => '11222333000262', 'razao_social' => 'T26 FILIAL LTDA', // pii-allowlist (CNPJ fictício)
            'inscricao_estadual' => '9012345678', 'created_at' => now(), 'updated_at' => now(),
        ]);
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

function t26Config(int $biz, int $locationId, array $props = []): NfeLocalEmissor
{
    return NfeLocalEmissor::withoutGlobalScopes()->create(array_merge([
        'business_id' => $biz, 'location_id' => $locationId,
        'serie_nfe' => T26_SERIE_FILIAL, 'serie_nfce' => T26_SERIE_FILIAL,
        'logradouro' => 'RUA DA FILIAL', 'numero' => '10', 'bairro' => 'CENTRO',
        'cod_municipio' => '4106902', 'cep' => '80010000',
    ], $props));
}

function t26Limpar(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_locais_emissores')) {
        return;
    }
    $bizs = [test()->seededTenant()->id, test()->seededSupportClientTenant()->id];
    DB::table('nfe_locais_emissores')->whereIn('business_id', $bizs)->delete();
    NfeEmissao::withoutGlobalScopes()->withTrashed()->whereIn('business_id', $bizs)
        ->where(fn ($q) => $q->whereBetween('numero', [995990, 996010])->orWhere('serie', T26_SERIE_FILIAL))->forceDelete();
    DB::table('business_locations')->whereIn('business_id', $bizs)->where('name', 'like', 'T26 FILIAL%')->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: business_locations do UltimatePOS + nfe_emissoes.');
    }
    if (! Schema::hasTable('nfe_locais_emissores')) {
        $this->markTestSkipped('Migração 2026_10_14_000002 não rodou.');
    }
    t26Limpar();
    $this->bizAntes = DB::table('business')->where('id', t26Biz())->first(['ultimo_numero_nfe', 'numero_serie_nfe']);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_locais_emissores')) {
        return;
    }
    t26Limpar();
    if (isset($this->bizAntes)) {
        DB::table('business')->where('id', t26Biz())->update((array) $this->bizAntes);
    }
    \Mockery::close();
});

// ── R-NFE-031 · filial usa a própria UF ─────────────────────────────────────

it('R-NFE-031 · filial cadastrada emite com a UF, o CNPJ e o endereço dela', function () {
    $biz = t26Biz();
    $business = DB::table('business')->where('id', $biz)->first();
    $filial = t26Local($biz, 'PR');
    t26Config($biz, $filial);

    $e = t26Svc()->resolverEmitente($business, $filial, '65');

    expect($e['uf'])->toBe('PR')
        ->and($e['location_id'])->toBe($filial)
        ->and($e['serie'])->toBe(T26_SERIE_FILIAL)
        ->and($e['emit']['uf'])->toBe('PR')
        ->and($e['emit']['cnpj'])->toBe('11222333000262') // pii-allowlist (CNPJ fictício)
        ->and($e['emit']['ie'])->toBe('9012345678')
        ->and($e['emit']['cod_municipio'])->toBe('4106902')
        ->and($e['emit']['cep'])->toBe('80010000')
        ->and($e['emit']['logradouro'])->toBe('RUA DA FILIAL');
})->group('nfe', 'R-NFE-031');

it('R-NFE-031 · controle: local sem cadastro de emissor, ou venda sem local, emite como o business', function () {
    $biz = t26Biz();
    $business = DB::table('business')->where('id', $biz)->first();
    $semCadastro = t26Local($biz, 'PR');
    $svc = t26Svc();

    $ufDoBusiness = (new ReflectionMethod(NfeService::class, 'resolverUF'))->invoke($svc, $business);
    foreach ([$semCadastro, null] as $loc) {
        $e = $svc->resolverEmitente($business, $loc, '65');
        // P-155: o emitente padrão também diz que não há regime próprio do local (crt null).
        expect($e)->toBe(['uf' => $ufDoBusiness, 'emit' => [], 'serie' => null, 'location_id' => null, 'crt' => null]);
    }
})->group('nfe', 'R-NFE-031');

it('R-NFE-031 · filial incompleta, com a série da matriz ou de outra empresa recusa', function () {
    $biz = t26Biz();
    $business = DB::table('business')->where('id', $biz)->first();
    $svc = t26Svc();

    $semCep = t26Local($biz, 'PR');
    t26Config($biz, $semCep, ['cep' => null]);
    expect(fn () => $svc->resolverEmitente($business, $semCep, '65'))->toThrow(RuntimeException::class, 'CEP');

    $serieDaMatriz = t26Local($biz, 'SC');
    $padrao = (string) ($business->numero_serie_nfe ?? '1');
    t26Config($biz, $serieDaMatriz, ['serie_nfe' => $padrao, 'serie_nfce' => $padrao]);
    expect(fn () => $svc->resolverEmitente($business, $serieDaMatriz, '65'))->toThrow(RuntimeException::class, 'série padrão');

    // Tier 0: cadastro de emissor do biz 98 apontando para um local do cliente 99 não serve.
    $doOutro = t26Local(test()->seededSupportClientTenant()->id, 'RS');
    t26Config($biz, $doOutro, ['serie_nfe' => '8', 'serie_nfce' => '8']);
    expect(fn () => $svc->resolverEmitente($business, $doOutro, '65'))->toThrow(RuntimeException::class, 'local');
})->group('nfe', 'R-NFE-031');

// ── R-NFE-032 · série e numeração do local emissor ──────────────────────────

it('R-NFE-032 · série e numeração do local emissor', function () {
    $biz = t26Biz();
    $svc = t26Svc();
    $padrao = (string) (DB::table('business')->where('id', $biz)->value('numero_serie_nfe') ?? '1');
    // Contador legado da matriz em 995999: a matriz continua dele; a filial começa do 1.
    DB::table('business')->where('id', $biz)->update(['ultimo_numero_nfe' => 995999]);

    $emite = function (string $serie) use ($svc, $biz): int {
        return DB::transaction(function () use ($svc, $biz, $serie) {
            $n = $svc->proximoNumeroLocked($biz, '65', $serie);
            NfeEmissao::create(['business_id' => $biz, 'modelo' => '65', 'serie' => $serie, 'numero' => $n,
                'status' => 'enviando', 'valor_total' => 1]);

            return $n;
        });
    };

    // Matriz e filial alternadas: nenhuma pula nem repete número.
    $seq = [];
    foreach ([1, 2, 3] as $_) {
        $seq['matriz'][] = $emite($padrao);
        $seq['filial'][] = $emite(T26_SERIE_FILIAL);
    }
    expect($seq['matriz'])->toBe(T26_NUMEROS)       // 995999 + 1 = 996000, 996001, 996002
        ->and($seq['filial'])->toBe([1, 2, 3]);
})->group('nfe', 'R-NFE-032');

it('R-NFE-032 · autorização da filial não mexe no contador da matriz; a da matriz mexe', function () {
    Storage::fake();
    $biz = t26Biz();
    $svc = t26Svc();
    $padrao = (string) (DB::table('business')->where('id', $biz)->value('numero_serie_nfe') ?? '1');
    DB::table('business')->where('id', $biz)->update(['ultimo_numero_nfe' => 995999]);

    $autoriza = function (string $serie, int $numero) use ($svc, $biz): void {
        $emissao = NfeEmissao::create(['business_id' => $biz, 'modelo' => '65', 'serie' => $serie,
            'numero' => $numero, 'status' => 'enviando', 'valor_total' => 1]);
        $ret = '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb>'
            . '<cStat>104</cStat><xMotivo>Lote processado</xMotivo><protNFe versao="4.00"><infProt>'
            . '<cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo>'
            . '<chNFe>' . str_pad((string) $numero, 44, '4', STR_PAD_LEFT) . '</chNFe><nProt>1</nProt>'
            . '<dhRecbto>2026-10-14T10:00:00-03:00</dhRecbto></infProt></protNFe></retEnviNFe>';
        $m = new ReflectionMethod(NfeService::class, 'processarRetorno');
        $m->setAccessible(true);
        $m->invoke($svc, $emissao, $ret, '<NFe/>', $biz, $serie, $numero);
    };

    $autoriza(T26_SERIE_FILIAL, 1);
    expect((int) DB::table('business')->where('id', $biz)->value('ultimo_numero_nfe'))->toBe(995999);

    $autoriza($padrao, 996001);
    expect((int) DB::table('business')->where('id', $biz)->value('ultimo_numero_nfe'))->toBe(996001);
})->group('nfe', 'R-NFE-032');
