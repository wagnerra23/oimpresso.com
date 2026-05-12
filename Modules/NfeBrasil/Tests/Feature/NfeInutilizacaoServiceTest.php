<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;

uses(Tests\TestCase::class);

/**
 * US-SELL-030 — NfeInutilizacaoService unit tests (focused on service contract).
 *
 * Complementa SequencialNfeAposCancelamentoTest (que cobre G1+G2 fluxo end-to-end).
 * Aqui foca em edge cases do service isoladamente:
 *   - happy path (cstat=102 autorizado, faixa simples)
 *   - faixa múltipla atualiza N emissões
 *   - validação justificativa (15 chars limite, 255 limite, vazia)
 *   - validação modelo (55, 65 ok; 57 não)
 *   - validação faixa (numero_de=0 reject, numero_ate < numero_de reject)
 *   - cstat=102 → autorizado + emissoes marcadas
 *   - cstat≠102 → rejeitado + emissoes preservadas
 *   - cross-tenant guard (session biz≠businessId param)
 *
 * Não usa SEFAZ real — toolsFactory mock injetado via constructor.
 *
 * Nota: SequencialNfeAposCancelamentoTest já tem cobertura broad — esse teste
 * é defensa em profundidade e doc dos edge cases pra próximas features.
 */

beforeEach(function () {
    Schema::create('business', function (Blueprint $t) {
        $t->increments('id');
        $t->string('name')->nullable();
        $t->string('tax_number')->nullable();
        $t->string('state')->nullable();
        $t->unsignedTinyInteger('ambiente')->default(2);
        $t->timestamps();
    });

    Schema::create('nfe_emissoes', function (Blueprint $t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('transaction_id')->nullable();
        $t->string('modelo', 2);
        $t->string('serie', 3);
        $t->unsignedInteger('numero');
        $t->string('chave_44', 44)->nullable();
        $t->string('status', 30)->default('pendente');
        $t->string('cstat', 5)->nullable();
        $t->text('motivo')->nullable();
        $t->decimal('valor_total', 15, 2)->default(0);
        $t->dateTime('emitido_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('nfe_inutilizacoes', function (Blueprint $t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('modelo', 2);
        $t->string('serie', 3);
        $t->unsignedInteger('numero_de');
        $t->unsignedInteger('numero_ate');
        $t->text('justificativa');
        $t->string('status', 20)->default('pendente');
        $t->string('cstat', 5)->nullable();
        $t->dateTime('autorizada_em')->nullable();
        $t->json('payload_json')->nullable();
        $t->timestamps();
    });

    DB::table('business')->insert([
        ['id' => 1, 'name' => 'WR2 SC', 'tax_number' => '12345678000199', 'state' => 'SC'],
        ['id' => 99, 'name' => 'Adversário', 'tax_number' => '99999999000199', 'state' => 'SP'],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('nfe_inutilizacoes');
    Schema::dropIfExists('nfe_emissoes');
    Schema::dropIfExists('business');
    \Mockery::close();
});

// ── helpers ─────────────────────────────────────────────────────────────────

function inutCertMock(): CertificadoService
{
    $mock = \Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake', 'senha' => 'x', 'valido_ate' => now()->addYear(), 'source' => 'test',
    ]);
    return $mock;
}

function inutToolsFactory(string $cstat = '102', string $motivo = 'Inutilizacao homologada'): Closure
{
    return function (string $config, array $certData) use ($cstat, $motivo) {
        $tools = \Mockery::mock(\NFePHP\NFe\Tools::class);
        $tools->shouldReceive('model')->andReturnSelf();
        $tools->shouldReceive('sefazInutiliza')
            ->andReturn("<?xml version=\"1.0\"?><retInutNFe><infInut><cStat>{$cstat}</cStat><xMotivo>{$motivo}</xMotivo></infInut></retInutNFe>");
        return $tools;
    };
}

function emissaoFakeForInut(int $businessId, int $numero, string $status = 'rejeitada'): NfeEmissao
{
    $e = new NfeEmissao();
    $e->setRawAttributes([
        'business_id' => $businessId,
        'transaction_id' => null,
        'modelo' => '55',
        'serie' => '1',
        'numero' => $numero,
        'status' => $status,
        'chave_44' => str_pad((string) $numero, 44, '0', STR_PAD_LEFT),
        'valor_total' => 100.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $e->save();
    return $e;
}

// ── tests ───────────────────────────────────────────────────────────────────

it('happy path — cstat=102 cria registro autorizado em nfe_inutilizacoes', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));

    $inut = $service->inutilizar(1, '55', '1', 100, 100, 'Justificativa válida com mais de 15 chars');

    expect($inut->status)->toBe('autorizado')
        ->and($inut->cstat)->toBe('102')
        ->and($inut->autorizada_em)->not->toBeNull();
});

it('faixa múltipla (100..110) — quantidadeNumeros() retorna 11', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));

    $inut = $service->inutilizar(1, '55', '1', 100, 110, 'Inutilizando faixa lote inválido — XML rejeitado');

    expect($inut->quantidadeNumeros())->toBe(11);
});

it('justificativa exatamente 15 chars passa (limite mínimo SEFAZ)', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));

    $just = str_repeat('x', 15); // exatamente 15
    $inut = $service->inutilizar(1, '55', '1', 100, 100, $just);

    expect($inut->status)->toBe('autorizado');
});

it('justificativa exatamente 255 chars passa (limite máximo SEFAZ)', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));

    $just = str_repeat('x', 255);
    $inut = $service->inutilizar(1, '55', '1', 100, 100, $just);

    expect($inut->status)->toBe('autorizado');
});

it('justificativa 14 chars rejeita (< 15 SEFAZ)', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory());

    expect(fn () => $service->inutilizar(1, '55', '1', 100, 100, str_repeat('x', 14)))
        ->toThrow(InvalidArgumentException::class, '15-255');
});

it('justificativa 256 chars rejeita (> 255 SEFAZ)', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory());

    expect(fn () => $service->inutilizar(1, '55', '1', 100, 100, str_repeat('x', 256)))
        ->toThrow(InvalidArgumentException::class, '15-255');
});

it('modelo 65 (NFC-e) é aceito', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));

    $inut = $service->inutilizar(1, '65', '1', 100, 100, 'Inutilizando NFC-e número 100 lote rejeitado');

    expect($inut->modelo)->toBe('65');
});

it('modelo 57 (CT-e) é rejeitado — só 55/65 permitidos', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory());

    expect(fn () => $service->inutilizar(1, '57', '1', 100, 100, 'Justificativa válida 15+ chars aqui'))
        ->toThrow(InvalidArgumentException::class, 'Modelo inválido');
});

it('numero_de=0 rejeita (faixa inválida)', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory());

    expect(fn () => $service->inutilizar(1, '55', '1', 0, 0, 'Justificativa válida 15+ chars aqui'))
        ->toThrow(InvalidArgumentException::class, 'Faixa inválida');
});

it('numero_ate < numero_de rejeita (faixa invertida)', function () {
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory());

    expect(fn () => $service->inutilizar(1, '55', '1', 110, 100, 'Justificativa válida 15+ chars aqui'))
        ->toThrow(InvalidArgumentException::class, 'Faixa inválida');
});

it('cstat=102 marca emissoes da faixa como inutilizada', function () {
    foreach (range(100, 102) as $n) {
        emissaoFakeForInut(1, $n, 'rejeitada');
    }

    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));
    $service->inutilizar(1, '55', '1', 100, 102, 'Justificativa válida pra inutilizacao 102');

    $statuses = NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->whereBetween('numero', [100, 102])
        ->pluck('status')->all();

    expect($statuses)->each->toBe('inutilizada');
});

it('cstat≠102 (rejeitado) NÃO atualiza emissoes — preserva status anterior', function () {
    emissaoFakeForInut(1, 100, 'rejeitada');

    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('215', 'Falha schema XML'));
    $service->inutilizar(1, '55', '1', 100, 100, 'Justificativa válida 15+ chars aqui');

    $original = NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('numero', 100)->first();

    expect($original->status)->toBe('rejeitada');
});

it('cross-tenant — session biz=99 tentando inutilizar biz=1 lança UnauthorizedAction', function () {
    session(['user.business_id' => 99]);
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory());

    expect(fn () => $service->inutilizar(1, '55', '1', 100, 100, 'Justificativa válida 15+ chars cross-tenant'))
        ->toThrow(UnauthorizedActionException::class, 'Cross-tenant');
});

it('session sem biz — service permite (CLI/seeder context)', function () {
    // session sem user.business_id — guard permite (caller é responsável: CLI, seeder, job)
    session()->forget('user.business_id');
    $service = new NfeInutilizacaoService(inutCertMock(), inutToolsFactory('102'));

    $inut = $service->inutilizar(1, '55', '1', 100, 100, 'Justificativa válida 15+ chars CLI ctx');

    expect($inut->status)->toBe('autorizado');
});
