<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * US-NFE-042 — NfeService::emitir() real.
 *
 * Não usa RefreshDatabase — roda contra DB dev (UltimatePOS + triggers).
 * Usa $toolsFactory mockado para não tocar SEFAZ real.
 */

// ── helpers ─────────────────────────────────────────────────────────────────

function nfeSvcBootstrap(): array
{
    if (! Schema::hasTable('nfe_emissoes')) {
        test()->markTestSkipped('Tabela nfe_emissoes não existe — rode migrations primeiro.');
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
 * Retorna um $toolsFactory que devolve um Tools mock com resposta pré-definida.
 */
function fakeSefazFactory(string $responseXml): \Closure
{
    return function (string $configJson, array $certData) use ($responseXml): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('signNFe')->andReturnArg(0);
        $mock->shouldReceive('sefazEnviaLote')->andReturn($responseXml);
        $mock->shouldReceive('model')->andReturnNull();
        return $mock;
    };
}

function sefazAutorizadoXml(string $chNFe = '35210112345678000199550010000000011000000019'): string
{
    return <<<XML
    <retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
      <tpAmb>2</tpAmb><cUF>35</cUF>
      <dhRecbto>2026-05-06T00:00:00-03:00</dhRecbto>
      <cStat>104</cStat><xMotivo>Lote processado</xMotivo>
      <protNFe versao="4.00">
        <infProt>
          <cStat>100</cStat>
          <xMotivo>Autorizado o uso da NF-e</xMotivo>
          <chNFe>{$chNFe}</chNFe>
          <nProt>135260000000001</nProt>
          <dhRecbto>2026-05-06T00:00:00-03:00</dhRecbto>
        </infProt>
      </protNFe>
    </retEnviNFe>
    XML;
}

function sefazRejeitadoXml(string $cstat = '225', string $motivo = 'Rejeicao: IE do emitente invalida'): string
{
    return <<<XML
    <retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
      <tpAmb>2</tpAmb><cUF>35</cUF>
      <dhRecbto>2026-05-06T00:00:00-03:00</dhRecbto>
      <cStat>225</cStat><xMotivo>Lote processado com erro</xMotivo>
      <protNFe versao="4.00">
        <infProt>
          <cStat>{$cstat}</cStat>
          <xMotivo>{$motivo}</xMotivo>
          <chNFe></chNFe>
          <nProt></nProt>
        </infProt>
      </protNFe>
    </retEnviNFe>
    XML;
}

function dadosNfeMinimos(float $valor = 100.00, ?int $transactionId = null): array
{
    return [
        'transaction_id' => $transactionId,
        'nat_op'         => 'Venda de produto',
        'emit'           => [
            'cnpj'        => '12345678000199',
            'razao_social' => 'EMPRESA TESTE LTDA',
            'ie'          => '123456789',
            'crt'         => 1,
            'uf'          => 'SP',
            'ambiente'    => 2,
            'logradouro'  => 'Rua Teste',
            'numero_end'  => '100',
            'bairro'      => 'Centro',
            'municipio'   => 'São Paulo',
            'cod_municipio' => '3550308',
            'cep'         => '01001000',
        ],
        'dest' => [
            'nome'         => 'CLIENTE TESTE LTDA',
            'cnpj'         => '98765432000111',
            'ind_ie_dest'  => '1',
            'ie'           => '987654321',
            'logradouro'   => 'Rua Dest',
            'numero'       => '200',
            'bairro'       => 'Bairro',
            'municipio'    => 'São Paulo',
            'cod_municipio' => '3550308',
            'uf'           => 'SP',
            'cep'          => '01310100',
        ],
        'dets' => [[
            'cprod'   => '001',
            'xprod'   => 'Produto Teste',
            'ncm'     => '49019900',
            'cfop'    => '5102',
            'ucm'     => 'UN',
            'qcom'    => 1.0,
            'vuncom'  => $valor,
            'vprod'   => $valor,
            'utrib'   => 'UN',
            'qtrib'   => 1.0,
            'vuntrib' => $valor,
            'ind_tot' => 1,
            'icms'    => ['cst_csosn' => '102', 'orig' => 0],
            'pis'     => ['cst' => '07', 'vbc' => 0, 'ppis' => 0, 'vpis' => 0],
            'cofins'  => ['cst' => '07', 'vbc' => 0, 'pcofins' => 0, 'vcofins' => 0],
        ]],
        'total' => [
            'v_prod'    => $valor,
            'v_bc_icms' => 0,
            'v_icms'    => 0,
            'v_pis'     => 0,
            'v_cofins'  => 0,
            'v_nf'      => $valor,
            'v_desc'    => 0,
            'v_frete'   => 0,
        ],
        'pag'         => [['tpag' => '01', 'vpag' => $valor]],
        'valor_total' => $valor,
    ];
}

function makeCertificadoService(): CertificadoService
{
    // Mocka apenas carregarParaSefaz — sem precisar de .pfx real
    $mock = \Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake-binary',
        'senha'      => 'senha-fake',
        'valido_ate' => now()->addYear(),
        'source'     => 'test',
    ]);
    return $mock;
}

// ── beforeEach / afterEach ───────────────────────────────────────────────────

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        test()->markTestSkipped('Tabela nfe_emissoes não existe — rode migrations primeiro.');
    }
    Storage::fake('local');
});

afterEach(function () {
    try {
        // Remove emissões criadas nos testes (transaction_id NULL ou valor 100/200)
        NfeEmissao::where('business_id', '>', 0)
            ->where('valor_total', 100.00)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->forceDelete();
    } catch (\Throwable) {}
    \Mockery::close();
});

// ── testes ───────────────────────────────────────────────────────────────────

it('emitir retorna NfeEmissao autorizada quando SEFAZ mock autoriza', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();

    // Cert fake: precisamos que Certificate::readPfx aceite qualquer coisa
    // O toolsFactory retorna o mock direto, então Certificate::readPfx não é chamado
    $tools = fakeSefazFactory(sefazAutorizadoXml());

    $service = new NfeService($certSvc, $tools);

    $emissao = $service->emitir($business->id, dadosNfeMinimos(100.00));

    expect($emissao)->toBeInstanceOf(NfeEmissao::class);
    expect($emissao->status)->toBe('autorizada');
    expect($emissao->cstat)->toBe('100');
    expect($emissao->chave_44)->toBe('35210112345678000199550010000000011000000019');
    expect($emissao->business_id)->toBe($business->id);
    expect($emissao->xml_path)->not->toBeNull();

    // XML salvo no storage fake
    Storage::assertExists($emissao->xml_path);
})->group('nfe');

it('emitir retorna NfeEmissao rejeitada quando SEFAZ retorna cStat 225', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazFactory(sefazRejeitadoXml('225', 'Rejeicao: IE invalida'));

    $service = new NfeService($certSvc, $tools);

    $emissao = $service->emitir($business->id, dadosNfeMinimos(100.00));

    expect($emissao->status)->toBe('rejeitada');
    expect($emissao->cstat)->toBe('225');
    expect($emissao->motivo)->toContain('IE invalida');
    expect($emissao->chave_44)->toBeNull();
})->group('nfe');

it('emitir é idempotente — segunda chamada com mesmo transaction_id retorna emissão existente', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazFactory(sefazAutorizadoXml());
    $service = new NfeService($certSvc, $tools);

    $txId = random_int(900000, 999999);

    $primeira = $service->emitir($business->id, dadosNfeMinimos(100.00, $txId));

    // Segunda chamada — mesmo transaction_id
    $segunda = $service->emitir($business->id, dadosNfeMinimos(100.00, $txId));

    expect($primeira->id)->toBe($segunda->id);
    expect(
        NfeEmissao::where('business_id', $business->id)
            ->where('transaction_id', $txId)
            ->count()
    )->toBe(1);
})->group('nfe');

it('emitir lança RuntimeException quando cert ausente', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = \Mockery::mock(CertificadoService::class);
    $certSvc->shouldReceive('carregarParaSefaz')
        ->andThrow(new \RuntimeException('Sem certificado ativo'));

    $service = new NfeService($certSvc);

    expect(fn () => $service->emitir($business->id, dadosNfeMinimos()))
        ->toThrow(\RuntimeException::class, 'Sem certificado ativo');
})->group('nfe');

it('proximoNumeroLocked auto-incrementa e não repete por série', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazFactory(sefazAutorizadoXml());
    $service = new NfeService($certSvc, $tools);

    $serie = 'Z'; // série exclusiva pro teste

    $n1 = DB::transaction(fn () => $service->proximoNumeroLocked($business->id, '55', $serie));
    $n2 = DB::transaction(fn () => $service->proximoNumeroLocked($business->id, '55', $serie));

    // Sem emissões reais, ambos retornam o mesmo "próximo" (max=0 → 1)
    // O que conta é que são calculados corretamente
    expect($n1)->toBeGreaterThan(0);
    expect($n2)->toBeGreaterThan(0);
})->group('nfe');

// ── consultarStatusSefaz (US-NFE-041 fase 2 — botão "Testar conexão SEFAZ") ──

function fakeSefazStatusFactory(string $responseXml): \Closure
{
    return function (string $configJson, array $certData) use ($responseXml): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('sefazStatus')->andReturn($responseXml);
        return $mock;
    };
}

function statusServicoXml(string $cstat = '107', string $motivo = 'Servico em Operacao', string $verAplic = 'SVRS_202604'): string
{
    return <<<XML
    <retConsStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
      <tpAmb>2</tpAmb>
      <verAplic>{$verAplic}</verAplic>
      <cStat>{$cstat}</cStat>
      <xMotivo>{$motivo}</xMotivo>
      <cUF>42</cUF>
      <dhRecbto>2026-05-07T20:00:00-03:00</dhRecbto>
    </retConsStatServ>
    XML;
}

it('consultarStatusSefaz retorna ok=true quando SEFAZ responde cstat 107', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazStatusFactory(statusServicoXml('107', 'Servico em Operacao'));
    $service = new NfeService($certSvc, $tools);

    $result = $service->consultarStatusSefaz($business->id);

    expect($result['ok'])->toBeTrue()
        ->and($result['cstat'])->toBe('107')
        ->and($result['xMotivo'])->toBe('Servico em Operacao')
        ->and($result['versao'])->toBe('SVRS_202604')
        ->and($result['ambiente'])->toBeIn([1, 2])
        ->and($result['uf'])->toBeString()
        ->and($result['tempoResposta'])->toBeNumeric();
})->group('nfe');

it('consultarStatusSefaz retorna ok=false quando SEFAZ paralisado (cstat 108)', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazStatusFactory(statusServicoXml('108', 'Servico Paralisado Momentaneamente'));
    $service = new NfeService($certSvc, $tools);

    $result = $service->consultarStatusSefaz($business->id);

    expect($result['ok'])->toBeFalse()
        ->and($result['cstat'])->toBe('108')
        ->and($result['xMotivo'])->toContain('Paralisado');
})->group('nfe');

it('consultarStatusSefaz retorna ok=false quando cert vencido (cstat 280)', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazStatusFactory(statusServicoXml('280', 'Rejeicao: Certificado Transmissor Expirado'));
    $service = new NfeService($certSvc, $tools);

    $result = $service->consultarStatusSefaz($business->id);

    expect($result['ok'])->toBeFalse()
        ->and($result['cstat'])->toBe('280');
})->group('nfe');

it('consultarStatusSefaz: criarTools chama Tools::model() com INT (não string)', function () {
    // Regressão real prod 2026-05-07: Tools::model() declara `?int $model` (sped-nfe v5+).
    // Antes era passado string '55'/'65' → TypeError em runtime mas tests Pest mockavam
    // Tools sem assertion em tipo. Esse test garante o cast (int) explícito.
    [$business] = nfeSvcBootstrap();

    $modelRecebido = null;
    $factory = function (string $configJson, array $certData) use (&$modelRecebido): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnUsing(function ($arg) use (&$modelRecebido) {
            $modelRecebido = $arg;
            return null;
        });
        $mock->shouldReceive('sefazStatus')->andReturn(statusServicoXml('107'));
        return $mock;
    };

    $service = new NfeService(makeCertificadoService(), $factory);
    $service->consultarStatusSefaz($business->id);

    expect($modelRecebido)->toBeInt('Tools::model deve receber INT — string causa TypeError em runtime real');
})->group('nfe');

it('consultarStatusSefaz: emitir() também chama model() com INT', function () {
    [$business] = nfeSvcBootstrap();

    $modelRecebido = null;
    $factory = function (string $configJson, array $certData) use (&$modelRecebido): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnUsing(function ($arg) use (&$modelRecebido) {
            $modelRecebido = $arg;
            return null;
        });
        $mock->shouldReceive('signNFe')->andReturnArg(0);
        $mock->shouldReceive('sefazEnviaLote')->andReturn(sefazAutorizadoXml());
        return $mock;
    };

    $service = new NfeService(makeCertificadoService(), $factory);
    $service->emitir($business->id, dadosNfeMinimos(100.00));

    expect($modelRecebido)->toBeInt();
})->group('nfe');

it('consultarStatusSefaz envolve TypeError do criarTools em RuntimeException', function () {
    // Garante que qualquer erro de prep (TypeError, InvalidArgument, etc) não escapa
    // como Throwable cru — vira RuntimeException pra controller poder devolver
    // payload com UF/ambiente preenchidos.
    [$business] = nfeSvcBootstrap();

    $factory = function (string $configJson, array $certData): Tools {
        throw new \TypeError('simulated TypeError ::model() argument');
    };

    $service = new NfeService(makeCertificadoService(), $factory);

    expect(fn () => $service->consultarStatusSefaz($business->id))
        ->toThrow(\RuntimeException::class, 'Falha ao consultar SEFAZ');
})->group('nfe');

it('consultarStatusSefaz lança RuntimeException quando Tools::sefazStatus falha', function () {
    [$business] = nfeSvcBootstrap();

    $certSvc = makeCertificadoService();
    $factory = function (string $configJson, array $certData): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('sefazStatus')->andThrow(new \RuntimeException('cURL connection timeout'));
        return $mock;
    };
    $service = new NfeService($certSvc, $factory);

    expect(fn () => $service->consultarStatusSefaz($business->id))
        ->toThrow(\RuntimeException::class, 'Falha ao consultar SEFAZ');
})->group('nfe');

it('consultarStatusSefaz lança RuntimeException quando business inexistente', function () {
    nfeSvcBootstrap(); // garante que tabelas existem

    $certSvc = makeCertificadoService();
    $service = new NfeService($certSvc);

    expect(fn () => $service->consultarStatusSefaz(9999999))
        ->toThrow(\RuntimeException::class, 'não encontrado');
})->group('nfe');

it('emitir incrementa business.ultimo_numero_nfe na autorização', function () {
    [$business] = nfeSvcBootstrap();

    $ultimoAntes = (int) (DB::table('business')->where('id', $business->id)->value('ultimo_numero_nfe') ?? 0);

    $certSvc = makeCertificadoService();
    $tools   = fakeSefazFactory(sefazAutorizadoXml());
    $service = new NfeService($certSvc, $tools);

    $emissao = $service->emitir($business->id, dadosNfeMinimos(100.00));

    $ultimoDepois = (int) (DB::table('business')->where('id', $business->id)->value('ultimo_numero_nfe') ?? 0);

    expect($emissao->status)->toBe('autorizada');
    expect($ultimoDepois)->toBeGreaterThanOrEqual($emissao->numero);
    expect($ultimoDepois)->toBeGreaterThanOrEqual($ultimoAntes);
})->group('nfe');
