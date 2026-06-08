<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * US-SELL-034 — NfeService::cancelar() real (cancelamento SEFAZ tpEvento=110111).
 *
 * Não usa RefreshDatabase — roda contra DB dev (UltimatePOS schema).
 * Usa $toolsFactory mockado para não tocar SEFAZ real.
 *
 * Cobre 5 cenários:
 *   1. Happy path — SEFAZ aceita (cstat=135) → NfeEvento autorizado + NfeEmissao.status='cancelada'
 *   2. Idempotência — emissao já cancelada retorna evento existente sem chamar SEFAZ
 *   3. Justificativa < 15 chars → InvalidArgumentException
 *   4. Cross-tenant — businessId divergente de emissao.business_id → UnauthorizedActionException
 *   5. cstat != 135/136 → RuntimeException
 */

// ── helpers ─────────────────────────────────────────────────────────────────

function nfeCancelarBootstrap(): array
{
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_eventos')) {
        test()->markTestSkipped('Tabelas nfe_emissoes/nfe_eventos não existem — rode migrations.');
    }

    try {
        $business = \App\Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    return [$business];
}

/**
 * Factory Tools com resposta mockada pro sefazCancela().
 *
 * @param string $responseXml XML retEnvEvento já formatado
 * @param callable|null $onCancela callback opcional pra capturar args
 */
function fakeSefazCancelaFactory(string $responseXml, ?callable $onCancela = null): \Closure
{
    return function (string $configJson, array $certData) use ($responseXml, $onCancela): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('sefazCancela')
            ->andReturnUsing(function (...$args) use ($responseXml, $onCancela) {
                if ($onCancela) {
                    $onCancela(...$args);
                }
                return $responseXml;
            });
        return $mock;
    };
}

function sefazCancelaAceitoXml(string $cstat = '135', string $motivo = 'Evento registrado e vinculado a NF-e'): string
{
    return <<<XML
    <retEnvEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">
      <idLote>1</idLote>
      <tpAmb>2</tpAmb>
      <verAplic>SVRS_202604</verAplic>
      <cOrgao>35</cOrgao>
      <cStat>128</cStat>
      <xMotivo>Lote de Evento Processado</xMotivo>
      <retEvento versao="1.00">
        <infEvento>
          <tpAmb>2</tpAmb>
          <verAplic>SVRS_202604</verAplic>
          <cOrgao>35</cOrgao>
          <cStat>{$cstat}</cStat>
          <xMotivo>{$motivo}</xMotivo>
          <chNFe>35210112345678000199550010000000011000000019</chNFe>
          <tpEvento>110111</tpEvento>
          <nSeqEvento>1</nSeqEvento>
          <dhRegEvento>2026-05-12T10:00:00-03:00</dhRegEvento>
          <nProt>135260000000099</nProt>
        </infEvento>
      </retEvento>
    </retEnvEvento>
    XML;
}

function sefazCancelaRejeitadoXml(string $cstat = '573', string $motivo = 'Rejeicao: Duplicidade de Evento'): string
{
    return sefazCancelaAceitoXml($cstat, $motivo);
}

function makeCertificadoServiceCancelar(): CertificadoService
{
    $mock = \Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake-binary',
        'senha'      => 'senha-fake',
        'valido_ate' => now()->addYear(),
        'source'     => 'test',
    ]);
    return $mock;
}

/**
 * Cria NfeEmissao autorizada fake pra usar como entrada nos testes.
 *
 * Popula metadata.nProt e chave_44 (44 chars) — exigidos por cancelar().
 */
function criarEmissaoAutorizadaParaCancelar(int $businessId, string $modelo = '55'): NfeEmissao
{
    return NfeEmissao::create([
        'business_id' => $businessId,
        'transaction_id' => random_int(900000, 999999),
        'modelo'      => $modelo,
        'serie'       => 'C',
        'numero'      => random_int(900000, 999999),
        'status'      => 'autorizada',
        'cstat'       => '100',
        'chave_44'    => '35210112345678000199550010000000011000000019',
        'valor_total' => 100.00,
        'emitido_em'  => now()->subHour(),
        'metadata'    => ['nProt' => '135260000000001'],
    ]);
}

// ── beforeEach / afterEach ───────────────────────────────────────────────────

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_eventos')) {
        test()->markTestSkipped('Tabelas nfe_emissoes/nfe_eventos não existem.');
    }
});

afterEach(function () {
    // Cleanup defensivo — emissões criadas nos últimos 5min com série 'C'
    try {
        $emissoes = NfeEmissao::withoutGlobalScopes()
            ->withTrashed()
            ->where('serie', 'C')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();

        foreach ($emissoes as $em) {
            NfeEvento::withoutGlobalScopes()->where('emissao_id', $em->id)->delete();
            $em->forceDelete();
        }
    } catch (\Throwable) {
    }

    \Mockery::close();
});

// ── testes ───────────────────────────────────────────────────────────────────

it('1. cancela NFe autorizada via SEFAZ (mock) cria NfeEvento + muda status', function () {
    [$business] = nfeCancelarBootstrap();

    $emissao = criarEmissaoAutorizadaParaCancelar((int) $business->id);

    $argsCapturados = null;
    $tools = fakeSefazCancelaFactory(
        sefazCancelaAceitoXml('135'),
        function (string $chave, string $just, string $nProt) use (&$argsCapturados) {
            $argsCapturados = compact('chave', 'just', 'nProt');
        }
    );

    $service = new NfeService(makeCertificadoServiceCancelar(), $tools);

    $justificativa = 'Cancelamento por solicitacao do cliente — teste';

    $evento = $service->cancelar(
        businessId: (int) $business->id,
        nfeEmissaoId: (int) $emissao->id,
        justificativa: $justificativa,
    );

    // Assert evento criado
    expect($evento)->toBeInstanceOf(NfeEvento::class)
        ->and($evento->tipo)->toBe('110111')
        ->and($evento->status)->toBe('autorizado')
        ->and($evento->cstat_evento)->toBe('135')
        ->and($evento->emissao_id)->toBe($emissao->id)
        ->and($evento->business_id)->toBe((int) $business->id)
        ->and($evento->justificativa)->toBe($justificativa);

    // Assert NfeEmissao mudou pra cancelada
    $emissao->refresh();
    expect($emissao->status)->toBe('cancelada');

    // Assert SEFAZ chamado com args corretos
    expect($argsCapturados)->not->toBeNull()
        ->and($argsCapturados['chave'])->toBe($emissao->chave_44)
        ->and($argsCapturados['just'])->toBe($justificativa)
        ->and($argsCapturados['nProt'])->toBe('135260000000001');
})->group('nfe', 'nfe-cancelar');

it('2. idempotência: cancelar já cancelada retorna evento existente sem chamar SEFAZ', function () {
    [$business] = nfeCancelarBootstrap();

    $emissao = criarEmissaoAutorizadaParaCancelar((int) $business->id);

    // Primeira chamada — cancela normalmente
    $tools = fakeSefazCancelaFactory(sefazCancelaAceitoXml('135'));
    $service = new NfeService(makeCertificadoServiceCancelar(), $tools);
    $just = 'Cancelamento por engano operacional — teste idempotencia';
    $primeiro = $service->cancelar((int) $business->id, (int) $emissao->id, $just);

    // Segunda chamada com factory que LANÇA se SEFAZ for tocada
    $toolsBomb = function () {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('sefazCancela')->andThrow(new \RuntimeException('SEFAZ NÃO deveria ter sido chamada'));
        return $mock;
    };
    $serviceBomb = new NfeService(makeCertificadoServiceCancelar(), $toolsBomb);

    $segundo = $serviceBomb->cancelar((int) $business->id, (int) $emissao->id, $just);

    // Mesmo evento, sem novo registro
    expect($segundo->id)->toBe($primeiro->id);

    $totalEventos = NfeEvento::withoutGlobalScopes()
        ->where('emissao_id', $emissao->id)
        ->where('tipo', '110111')
        ->where('status', 'autorizado')
        ->count();
    expect($totalEventos)->toBe(1);
})->group('nfe', 'nfe-cancelar');

it('3. justificativa < 15 chars lança InvalidArgumentException', function () {
    [$business] = nfeCancelarBootstrap();

    $service = new NfeService(makeCertificadoServiceCancelar());

    expect(fn () => $service->cancelar(
        businessId: (int) $business->id,
        nfeEmissaoId: 1,
        justificativa: 'Curta', // 5 chars
    ))->toThrow(InvalidArgumentException::class, '15-255 caracteres');
})->group('nfe', 'nfe-cancelar');

it('4. cross-tenant: businessId não bate com emissao.business_id lança UnauthorizedActionException', function () {
    [$business] = nfeCancelarBootstrap();

    $emissao = criarEmissaoAutorizadaParaCancelar((int) $business->id);

    $service = new NfeService(makeCertificadoServiceCancelar());

    $businessIdAtacante = (int) $business->id + 99999;

    expect(fn () => $service->cancelar(
        businessId: $businessIdAtacante,
        nfeEmissaoId: (int) $emissao->id,
        justificativa: 'Cross-tenant attempt — teste de seguranca',
    ))->toThrow(UnauthorizedActionException::class, 'Cross-tenant');
})->group('nfe', 'nfe-cancelar');

it('5. cstat != 135/136 lança RuntimeException', function () {
    [$business] = nfeCancelarBootstrap();

    $emissao = criarEmissaoAutorizadaParaCancelar((int) $business->id);

    // SEFAZ devolve cstat=573 (duplicidade) — qualquer cstat fora 135/136 vira erro
    $tools = fakeSefazCancelaFactory(sefazCancelaRejeitadoXml('573', 'Rejeicao: Duplicidade de Evento'));

    $service = new NfeService(makeCertificadoServiceCancelar(), $tools);

    expect(fn () => $service->cancelar(
        businessId: (int) $business->id,
        nfeEmissaoId: (int) $emissao->id,
        justificativa: 'Cancelamento que SEFAZ vai rejeitar — teste cstat erro',
    ))->toThrow(RuntimeException::class, 'cstat=573');

    // Emissão NÃO deve ter mudado pra cancelada
    $emissao->refresh();
    expect($emissao->status)->toBe('autorizada');

    // Mas evento rejeitado deve ter sido persistido pra rastreabilidade
    $eventoRejeitado = NfeEvento::withoutGlobalScopes()
        ->where('emissao_id', $emissao->id)
        ->where('tipo', '110111')
        ->where('status', 'rejeitado')
        ->first();
    expect($eventoRejeitado)->not->toBeNull()
        ->and($eventoRejeitado->cstat_evento)->toBe('573');
})->group('nfe', 'nfe-cancelar');
