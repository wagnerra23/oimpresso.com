<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Support\ArquivoFiscal;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * CU-NFE-12 (segredo/PII do documento fiscal) · R-NFE-010 (guarda do XML) · lote 0.1.
 *
 * O disco `local` tem raiz public/uploads, que o Apache serve direto. XML e DANFE com dado de
 * destinatário não podem nascer ali. Tenant 98 (ADR 0358); discos falsos, nada toca o disco real.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('nfe_fiscal');
    Storage::fake('nfe_certs');
});

function retornoSefazAutorizado(string $chave): string
{
    return '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb>'
        . '<verAplic>T</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>35</cUF>'
        . '<dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto><protNFe versao="4.00"><infProt><tpAmb>2</tpAmb>'
        . "<verAplic>T</verAplic><chNFe>{$chave}</chNFe><dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto>"
        . '<nProt>135000000000001</nProt><digVal>abc=</digVal><cStat>100</cStat><xMotivo>Autorizado</xMotivo>'
        . '</infProt></protNFe></retEnviNFe>';
}

it('CU-NFE-12 · o disco fiscal fica fora da pasta pública', function () {
    $raiz = str_replace('\\', '/', (string) config('filesystems.disks.nfe_fiscal.root'));
    $publica = str_replace('\\', '/', public_path());

    expect($raiz)->not->toBe('');
    expect(str_starts_with($raiz, $publica))->toBeFalse();
    expect(str_starts_with($raiz, str_replace('\\', '/', storage_path())))->toBeTrue();
});

it('CU-NFE-12 · nota autorizada grava o XML no disco privado e nada em public/uploads', function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes ausente neste schema.');
    }

    $biz = (int) $this->seededTenant()->id;
    $chave = '35261112345678000199550010000012341000012345';
    $emissao = NfeEmissao::create([
        'business_id' => $biz, 'modelo' => 55, 'serie' => '1', 'numero' => 1234,
        'status' => 'enviando', 'valor_total' => 10,
    ]);
    app()->instance(DanfeService::class, Mockery::mock(DanfeService::class)->shouldReceive('salvar')->andReturn(null)->getMock());

    $servico = new NfeService(Mockery::mock(CertificadoService::class));
    (new ReflectionMethod($servico, 'processarRetorno'))
        ->invoke($servico, $emissao, retornoSefazAutorizado($chave), '<NFe>assinada</NFe>', $biz, '1', 1234);

    $caminho = $emissao->refresh()->xml_path;
    expect($emissao->status)->toBe('autorizada');
    Storage::disk('nfe_fiscal')->assertExists($caminho);
    Storage::disk('local')->assertMissing($caminho);
});

it('CU-NFE-12 · lê do disco privado e, até a migração, cai no caminho antigo', function () {
    Storage::disk('local')->put('nfe-brasil/98/notas/1-1.xml', 'antigo');
    expect(ArquivoFiscal::ler('nfe-brasil/98/notas/1-1.xml'))->toBe('antigo');

    Storage::disk('nfe_fiscal')->put('nfe-brasil/98/notas/1-1.xml', 'novo');
    expect(ArquivoFiscal::ler('nfe-brasil/98/notas/1-1.xml'))->toBe('novo');

    expect(ArquivoFiscal::ler('nfe-brasil/98/notas/nao-existe.xml'))->toBeNull();
    expect(ArquivoFiscal::ler(null))->toBeNull();
});

it('CU-NFE-12 · sem --executar o comando conta e não move nada', function () {
    Storage::disk('local')->put('nfe-brasil/98/notas/1-1.xml', '<a/>');
    Storage::disk('local')->put('nfe-brasil/98/danfe/x.pdf', 'pdf');

    $this->artisan('nfe:mover-arquivos-fiscais')
        ->expectsOutputToContain('Nenhum arquivo foi movido')
        ->assertSuccessful();

    Storage::disk('local')->assertExists('nfe-brasil/98/notas/1-1.xml');
    Storage::disk('nfe_fiscal')->assertMissing('nfe-brasil/98/notas/1-1.xml');
});

it('CU-NFE-12 · --executar move, aponta o registro de arquivos e apaga a origem', function () {
    Storage::disk('local')->put('nfe-brasil/98/notas/1-1.xml', '<a/>');
    Storage::disk('local')->put('nfe-brasil/98/cert/u.pfx.enc', Crypt::encrypt('pfx'));

    $this->artisan('nfe:mover-arquivos-fiscais', ['--executar' => true])->assertSuccessful();

    Storage::disk('nfe_fiscal')->assertExists('nfe-brasil/98/notas/1-1.xml');
    Storage::disk('local')->assertMissing('nfe-brasil/98/notas/1-1.xml');
    Storage::disk('nfe_certs')->assertExists('98/cert/u.pfx.enc');
    Storage::disk('local')->assertMissing('nfe-brasil/98/cert/u.pfx.enc');
});

it('CU-NFE-12 · conteúdo diferente no destino não é sobrescrito e a origem fica', function () {
    Storage::disk('local')->put('nfe-brasil/98/notas/1-1.xml', 'origem');
    Storage::disk('nfe_fiscal')->put('nfe-brasil/98/notas/1-1.xml', 'destino');

    $this->artisan('nfe:mover-arquivos-fiscais', ['--executar' => true])->assertFailed();

    expect(Storage::disk('nfe_fiscal')->get('nfe-brasil/98/notas/1-1.xml'))->toBe('destino');
    Storage::disk('local')->assertExists('nfe-brasil/98/notas/1-1.xml');
});

it('CU-NFE-12 · --business-id só mexe nos arquivos daquela empresa', function () {
    Storage::disk('local')->put('nfe-brasil/98/notas/1-1.xml', 'a');
    Storage::disk('local')->put('nfe-brasil/99/notas/1-1.xml', 'b');

    $this->artisan('nfe:mover-arquivos-fiscais', ['--executar' => true, '--business-id' => 98])->assertSuccessful();

    Storage::disk('nfe_fiscal')->assertExists('nfe-brasil/98/notas/1-1.xml');
    Storage::disk('local')->assertExists('nfe-brasil/99/notas/1-1.xml');
    Storage::disk('nfe_fiscal')->assertMissing('nfe-brasil/99/notas/1-1.xml');
});
