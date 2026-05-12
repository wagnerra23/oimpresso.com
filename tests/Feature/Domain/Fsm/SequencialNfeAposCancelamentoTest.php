<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\NfeService;

/**
 * CU-01 (G1+G2) — NFe cancelada não pula sequencial fiscal.
 *
 * Specs FAILING-FIRST — código alvo ainda não existe:
 *   1. NfeService.emitir() não pode forceDelete registros status='cancelada'
 *   2. NfeInutilizacaoService::inutilizar() não existe ainda (G2)
 *
 * Pain point Wagner 2026-05-12:
 *   "cancelam nota perdem número pula sequencial"
 *
 * Base legal: CONFAZ Ajuste SINIEF 07/2005 Art. 14 — sequencial NFe é
 * controle fiscal obrigatório. Gap entre número cancelado e próximo
 * autorizado é infração sujeita a multa.
 *
 * Ver: memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-01
 */

beforeEach(function () {
    // Schema mínimo pra rodar o teste isolado — adequar quando rodar full suite.
    if (! Schema::hasTable('business')) {
        Schema::create('business', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('numero_serie_nfe')->default('1');
            $t->unsignedInteger('ultimo_numero_nfe')->default(0);
            $t->timestamps();
        });
    }
    DB::table('business')->insert(['id' => 1, 'name' => 'WR2 SC', 'numero_serie_nfe' => '1']);
    DB::table('business')->insert(['id' => 99, 'name' => 'Cross-tenant adversário', 'numero_serie_nfe' => '1']);
});

afterEach(function () {
    DB::table('business')->whereIn('id', [1, 99])->delete();
});

/**
 * Helper — cria emissão NFe com status especificado.
 */
function nfeFake(int $businessId, int $numero, string $status, ?int $transactionId = null): NfeEmissao
{
    return NfeEmissao::create([
        'business_id' => $businessId,
        'transaction_id' => $transactionId,
        'modelo' => '55',
        'serie' => '1',
        'numero' => $numero,
        'status' => $status,
        'chave' => str_pad((string) $numero, 44, '0', STR_PAD_LEFT),
    ]);
}

// ─── G1 — NFe cancelada NÃO sofre forceDelete ─────────────────────────────

it('1. NFe cancelada via SEFAZ permanece no banco (não sofre forceDelete)', function () {
    nfeFake(businessId: 1, numero: 100, status: 'autorizada', transactionId: 5000);

    // Simula evento cancelamento SEFAZ aceito
    NfeEmissao::where('business_id', 1)
        ->where('transaction_id', 5000)
        ->update(['status' => 'cancelada']);

    // Tentativa de re-emitir mesma transaction deve BLOQUEAR (não fazer forceDelete)
    expect(fn () => app(NfeService::class)->emitir(1, [
        'modelo' => '55',
        'transaction_id' => 5000,
        'serie' => '1',
        // payload mínimo — service real exige mais campos, mas o guard deve disparar antes
    ]))->toThrow(RuntimeException::class, 'cancelada via SEFAZ');

    // Registro com numero=100 status=cancelada AINDA existe
    expect(NfeEmissao::where('business_id', 1)->where('numero', 100)->first())
        ->not->toBeNull()
        ->status->toBe('cancelada');
});

it('2. proximoNumeroLocked após cancelada retorna sequencial + 1 (não pula)', function () {
    nfeFake(businessId: 1, numero: 100, status: 'cancelada', transactionId: 5000);

    $proximo = app(NfeService::class)->proximoNumeroLocked(1, '55', '1');

    expect($proximo)->toBe(101);
});

it('3. tentativa re-emitir transaction com NFe cancelada lança RuntimeException com mensagem clara', function () {
    nfeFake(businessId: 1, numero: 100, status: 'cancelada', transactionId: 5000);

    expect(fn () => app(NfeService::class)->emitir(1, [
        'modelo' => '55',
        'transaction_id' => 5000,
        'serie' => '1',
    ]))->toThrow(RuntimeException::class, 'execute action FSM `emitir_nova_apos_cancelamento`');
});

// ─── G1 — Caso correlato: rejeitada permite retry via inutilização ────────

it('4. NFe rejeitada NÃO é hard-deletada — marca como inutilizada pra preservar sequencial', function () {
    nfeFake(businessId: 1, numero: 100, status: 'rejeitada', transactionId: 5000);

    // Retry chamando emitir() invoca fluxo que marca status=inutilizada (preserva
    // registro), ao invés de forceDelete (US-SELL-029). Pode falhar por payload
    // incompleto adiante (cert/etc), mas o guard inicial é o que importa.
    try {
        app(NfeService::class)->emitir(1, [
            'modelo' => '55',
            'transaction_id' => 5000,
            'serie' => '1',
        ]);
    } catch (\Throwable $e) {
        // Falha de cert/payload é esperada — o que importa é o guard inicial
    }

    $original = NfeEmissao::withTrashed()
        ->where('business_id', 1)
        ->where('numero', 100)
        ->first();

    expect($original)->not->toBeNull();
    expect($original->status)->toBe('inutilizada');
});

// ─── G2 — NfeInutilizacaoService (US-SELL-030) ─────────────────────────────

/**
 * Helper: factory de Tools mock que retorna xmlRet com cstat=102 (autorizado).
 * Evita SEFAZ HTTP real em testes.
 */
function nfeInutilizacaoMockSuccess(): Closure
{
    return function (string $config, array $certData) {
        $tools = Mockery::mock(\NFePHP\NFe\Tools::class);
        $tools->shouldReceive('model')->andReturnSelf();
        $tools->shouldReceive('sefazInutiliza')
            ->andReturn('<?xml version="1.0"?><retInutNFe><infInut><cStat>102</cStat><xMotivo>Inutilizacao de numero homologado</xMotivo></infInut></retInutNFe>');
        return $tools;
    };
}

it('5. NfeInutilizacaoService::inutilizar cria registro em nfe_inutilizacoes', function () {
    $service = new \Modules\NfeBrasil\Services\NfeInutilizacaoService(
        app(\Modules\NfeBrasil\Services\CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    // Mock CertificadoService::carregarParaSefaz pra evitar leitura de PFX
    $this->instance(
        \Modules\NfeBrasil\Services\CertificadoService::class,
        Mockery::mock(\Modules\NfeBrasil\Services\CertificadoService::class, function ($m) {
            $m->shouldReceive('carregarParaSefaz')->andReturn([
                'pfx_binary' => 'fake', 'senha' => 'x', 'valido_ate' => now(), 'source' => 'test',
            ]);
        }),
    );

    $inut = $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Erro no XML — número não enviado pra SEFAZ',
    );

    expect($inut)->toBeInstanceOf(NfeInutilizacao::class);
    expect($inut->business_id)->toBe(1);
    expect($inut->modelo)->toBe('55');
    expect($inut->numero_de)->toBe(100);
    expect($inut->status)->toBe('autorizado');
    expect($inut->cstat)->toBe('102');
});

it('6. inutilização de faixa (100..105) marca todos como inutilizada em nfe_emissoes', function () {
    foreach (range(100, 105) as $n) {
        nfeFake(businessId: 1, numero: $n, status: 'rejeitada');
    }

    $this->instance(
        \Modules\NfeBrasil\Services\CertificadoService::class,
        Mockery::mock(\Modules\NfeBrasil\Services\CertificadoService::class, function ($m) {
            $m->shouldReceive('carregarParaSefaz')->andReturn([
                'pfx_binary' => 'fake', 'senha' => 'x', 'valido_ate' => now(), 'source' => 'test',
            ]);
        }),
    );

    $service = new \Modules\NfeBrasil\Services\NfeInutilizacaoService(
        app(\Modules\NfeBrasil\Services\CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 105,
        justificativa: 'Lote rejeitado — XML inválido, inutilizando faixa',
    );

    $statuses = NfeEmissao::where('business_id', 1)
        ->whereBetween('numero', [100, 105])
        ->pluck('status')
        ->all();

    expect($statuses)->each->toBe('inutilizada');
});

it('7. justificativa < 15 chars lança InvalidArgumentException (regra SEFAZ)', function () {
    /** @var \Modules\NfeBrasil\Services\NfeInutilizacaoService $service */
    $service = app(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class);

    expect(fn () => $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Curta', // 5 chars < 15
    ))->toThrow(InvalidArgumentException::class, 'justificativa');
});

it('8. inutilização cross-tenant (biz=99 tentando inutilizar biz=1) falha por isolation', function () {
    nfeFake(businessId: 1, numero: 100, status: 'rejeitada');

    // Simula contexto autenticado biz=99
    session(['user.business_id' => 99]);

    /** @var \Modules\NfeBrasil\Services\NfeInutilizacaoService $service */
    $service = app(\Modules\NfeBrasil\Services\NfeInutilizacaoService::class);

    // Service deve rejeitar tentativa cross-tenant (business_id 1 não bate com contexto 99)
    expect(fn () => $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Tentativa cross-tenant — deve falhar',
    ))->toThrow(\App\Domain\Fsm\Exceptions\UnauthorizedActionException::class);
});
