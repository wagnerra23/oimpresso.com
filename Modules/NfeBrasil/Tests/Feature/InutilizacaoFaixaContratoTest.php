<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * CU-FISC-10 (inutilizar faixa numérica) e CU-NFE-04 (o número fiscal nunca é reaproveitado) ·
 * lote 0.4. Inutilizar só pode atingir número que a SEFAZ não usou. Nota autorizada ou cancelada
 * segue valendo: a faixa é recusada antes de ir à SEFAZ. Tenant 98 × 99 (ADR 0358).
 */
const FAIXA_SERIE = '996';
const FAIXA_JUSTIFICATIVA = 'Numeracao pulada por falha de sistema';

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_inutilizacoes')) {
        $this->markTestSkipped('Schema do NfeBrasil ausente.');
    }
});

function faixaEmissao(int $businessId, int $numero, string $status): NfeEmissao
{
    return NfeEmissao::withoutGlobalScopes()->create([
        'business_id' => $businessId, 'modelo' => '55', 'serie' => FAIXA_SERIE,
        'numero' => $numero, 'status' => $status, 'valor_total' => 10,
    ]);
}

function faixaServico(bool $sefazPodeSerChamada = true): NfeInutilizacaoService
{
    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'x', 'senha' => 'x', 'valido_ate' => now()->addYear(), 'source' => 'teste',
    ]);

    $tools = Mockery::mock(Tools::class);
    $tools->shouldReceive('model')->andReturn(55);
    if ($sefazPodeSerChamada) {
        $tools->shouldReceive('sefazInutiliza')->andReturn('<retInutNFe><infInut><cStat>102</cStat><xMotivo>Homologada</xMotivo></infInut></retInutNFe>');
    } else {
        $tools->shouldNotReceive('sefazInutiliza');
    }

    return new NfeInutilizacaoService($cert, fn () => $tools);
}

it('CU-FISC-10 · faixa com nota que a SEFAZ registrou é recusada antes de ir à SEFAZ', function (string $status) {
    $biz = (int) $this->seededTenant()->id;
    $nota = faixaEmissao($biz, 9001, $status);

    expect(fn () => faixaServico(false)->inutilizar($biz, '55', FAIXA_SERIE, 9000, 9002, FAIXA_JUSTIFICATIVA))
        ->toThrow(InvalidArgumentException::class, (string) 9001);

    expect($nota->refresh()->status)->toBe($status);
    expect(NfeInutilizacao::withoutGlobalScopes()->where('business_id', $biz)->where('serie', FAIXA_SERIE)->count())->toBe(0);
})->with(['autorizada', 'cancelada', 'denegada', 'contingencia', 'enviando', 'pendente']);

it('CU-FISC-10 · nota autorizada apagada também barra a faixa', function () {
    $biz = (int) $this->seededTenant()->id;
    faixaEmissao($biz, 9011, 'autorizada')->delete();

    expect(fn () => faixaServico(false)->inutilizar($biz, '55', FAIXA_SERIE, 9010, 9012, FAIXA_JUSTIFICATIVA))
        ->toThrow(InvalidArgumentException::class);
});

it('CU-NFE-04 · só rejeitada e erro_envio viram inutilizada; apagada fica como está', function () {
    $biz = (int) $this->seededTenant()->id;
    $rejeitada = faixaEmissao($biz, 9021, 'rejeitada');
    $erro = faixaEmissao($biz, 9022, 'erro_envio');
    $apagada = faixaEmissao($biz, 9023, 'rejeitada');
    $apagada->delete();

    faixaServico()->inutilizar($biz, '55', FAIXA_SERIE, 9020, 9024, FAIXA_JUSTIFICATIVA);

    expect($rejeitada->refresh()->status)->toBe('inutilizada');
    expect($erro->refresh()->status)->toBe('inutilizada');
    expect(NfeEmissao::withoutGlobalScopes()->withTrashed()->find($apagada->id)->status)->toBe('rejeitada');
});

it('CU-FISC-10 · a nota autorizada de outra empresa não barra a faixa nem é alterada', function () {
    $biz = (int) $this->seededTenant()->id;
    $outra = faixaEmissao(99, 9031, 'autorizada');
    $minha = faixaEmissao($biz, 9031, 'rejeitada');

    faixaServico()->inutilizar($biz, '55', FAIXA_SERIE, 9030, 9032, FAIXA_JUSTIFICATIVA);

    expect($minha->refresh()->status)->toBe('inutilizada');
    expect($outra->refresh()->status)->toBe('autorizada');
});
