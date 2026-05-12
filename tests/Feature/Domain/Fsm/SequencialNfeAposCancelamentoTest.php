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
use Modules\NfeBrasil\Services\NfeService;

/**
 * US-SELL-029 (G1) + US-SELL-030 (G2) — NFe cancelada não pula sequencial fiscal.
 *
 * Validações:
 *   G1 (US-SELL-029):
 *     1. NfeService::emitir() bloqueia retry de transaction com NFe `cancelada`
 *        (CONFAZ SINIEF 07/2005 Art. 14 — número usado oficialmente)
 *     2. NfeService::emitir() NÃO faz forceDelete em emissão `rejeitada` —
 *        marca como `inutilizada` preservando registro
 *     3. proximoNumeroLocked respeita registros cancelados/inutilizados
 *
 *   G2 (US-SELL-030):
 *     5. NfeInutilizacaoService::inutilizar persiste em nfe_inutilizacoes
 *     6. Marca emissões da faixa como `inutilizada` em nfe_emissoes
 *     7. Justificativa < 15 chars rejeita (regra SEFAZ)
 *     8. Cross-tenant guard (biz=99 tentando inutilizar biz=1)
 *
 * Pain point Wagner 2026-05-12:
 *   "cancelam nota perdem número pula sequencial"
 *
 * Base legal: CONFAZ Ajuste SINIEF 07/2005 Art. 14
 *
 * Ver: memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-01
 */

beforeEach(function () {
    // ── Schema mínimo SQLite in-memory ─────────────────────────────────────
    Schema::create('business', function (Blueprint $t) {
        $t->increments('id');
        $t->string('name')->nullable();
        $t->string('numero_serie_nfe')->default('1');
        $t->unsignedInteger('ultimo_numero_nfe')->default(0);
        $t->string('tax_number')->nullable();
        $t->string('state')->nullable();
        $t->unsignedTinyInteger('ambiente')->default(2);
        $t->timestamps();
    });

    // Replica essencial de nfe_emissoes (sem ENUMs, SQLite usa VARCHAR)
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
        $t->string('xml_path', 255)->nullable();
        $t->string('danfe_path', 255)->nullable();
        $t->decimal('valor_total', 15, 2)->default(0);
        $t->dateTime('emitido_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['business_id', 'transaction_id'], 'nfe_emissoes_biz_tx_unique');
        $t->unique(['business_id', 'modelo', 'serie', 'numero'], 'nfe_emissoes_biz_seq_unique');
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
        ['id' => 1, 'name' => 'WR2 SC', 'numero_serie_nfe' => '1', 'tax_number' => '12345678000199', 'state' => 'SC'],
        ['id' => 99, 'name' => 'Cross-tenant adversário', 'numero_serie_nfe' => '1', 'tax_number' => '99999999000199', 'state' => 'SP'],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('nfe_inutilizacoes');
    Schema::dropIfExists('nfe_emissoes');
    Schema::dropIfExists('business');
    \Mockery::close();
});

/**
 * Helper — cria emissão NFe com status especificado. Bypassa global scope
 * pra permitir popular dados de qualquer business sem auth.
 */
function nfeFake(int $businessId, int $numero, string $status, ?int $transactionId = null): NfeEmissao
{
    $emissao = new NfeEmissao();
    $emissao->setRawAttributes([
        'business_id' => $businessId,
        'transaction_id' => $transactionId,
        'modelo' => '55',
        'serie' => '1',
        'numero' => $numero,
        'status' => $status,
        'chave_44' => str_pad((string) $numero, 44, '0', STR_PAD_LEFT),
        'valor_total' => 100.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $emissao->save();
    return $emissao;
}

/**
 * Helper — mock CertificadoService bound no container pra evitar leitura de PFX.
 */
function bindFakeCertificadoService(): void
{
    $mock = \Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake', 'senha' => 'x', 'valido_ate' => now()->addYear(), 'source' => 'test',
    ]);
    app()->instance(CertificadoService::class, $mock);
}

// ─── G1 — US-SELL-029: NFe cancelada NÃO sofre forceDelete ────────────────

it('1. NFe cancelada via SEFAZ permanece no banco (não sofre forceDelete)', function () {
    nfeFake(businessId: 1, numero: 100, status: 'cancelada', transactionId: 5000);

    bindFakeCertificadoService();

    // Tentativa de re-emitir mesma transaction deve BLOQUEAR (não fazer forceDelete)
    expect(fn () => app(NfeService::class)->emitir(1, [
        'modelo' => '55',
        'transaction_id' => 5000,
        'serie' => '1',
    ]))->toThrow(RuntimeException::class, 'cancelada via SEFAZ');

    // Registro com numero=100 status=cancelada AINDA existe
    $registro = NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('numero', 100)
        ->first();

    expect($registro)->not->toBeNull()
        ->and($registro->status)->toBe('cancelada');
});

it('2. proximoNumeroLocked após cancelada retorna sequencial + 1 (não pula)', function () {
    nfeFake(businessId: 1, numero: 100, status: 'cancelada', transactionId: 5000);

    $proximo = app(NfeService::class)->proximoNumeroLocked(1, '55', '1');

    expect($proximo)->toBe(101);
});

it('3. tentativa re-emitir transaction com NFe cancelada lança RuntimeException com mensagem instrutiva', function () {
    nfeFake(businessId: 1, numero: 100, status: 'cancelada', transactionId: 5000);
    bindFakeCertificadoService();

    expect(fn () => app(NfeService::class)->emitir(1, [
        'modelo' => '55',
        'transaction_id' => 5000,
        'serie' => '1',
    ]))->toThrow(RuntimeException::class, 'execute action FSM `emitir_nova_apos_cancelamento`');
});

it('4. NFe rejeitada NÃO é hard-deletada — marca como inutilizada pra preservar sequencial', function () {
    nfeFake(businessId: 1, numero: 100, status: 'rejeitada', transactionId: 5000);
    bindFakeCertificadoService();

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
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('numero', 100)
        ->first();

    expect($original)->not->toBeNull()
        ->and($original->status)->toBe('inutilizada');
});

// ─── G2 — US-SELL-030: NfeInutilizacaoService ──────────────────────────────

/**
 * Helper: factory de Tools mock que retorna xmlRet com cstat=102 (autorizado).
 */
function nfeInutilizacaoMockSuccess(): Closure
{
    return function (string $config, array $certData) {
        $tools = \Mockery::mock(\NFePHP\NFe\Tools::class);
        $tools->shouldReceive('model')->andReturn(55); // NFePHP\Tools::model retorna ?int (não chainable)
        $tools->shouldReceive('sefazInutiliza')
            ->andReturn('<?xml version="1.0"?><retInutNFe><infInut><cStat>102</cStat><xMotivo>Inutilizacao de numero homologado</xMotivo></infInut></retInutNFe>');
        return $tools;
    };
}

it('5. NfeInutilizacaoService::inutilizar cria registro em nfe_inutilizacoes com cstat=102', function () {
    bindFakeCertificadoService();

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    $inut = $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Erro no XML — número não enviado pra SEFAZ',
    );

    expect($inut)->toBeInstanceOf(NfeInutilizacao::class)
        ->and($inut->business_id)->toBe(1)
        ->and($inut->modelo)->toBe('55')
        ->and($inut->numero_de)->toBe(100)
        ->and($inut->status)->toBe('autorizado')
        ->and($inut->cstat)->toBe('102');
});

it('6. inutilização de faixa (100..105) marca todos como inutilizada em nfe_emissoes', function () {
    foreach (range(100, 105) as $n) {
        nfeFake(businessId: 1, numero: $n, status: 'rejeitada');
    }

    bindFakeCertificadoService();

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
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

    $statuses = NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->whereBetween('numero', [100, 105])
        ->pluck('status')
        ->all();

    expect($statuses)->each->toBe('inutilizada');
});

it('7. justificativa < 15 chars lança InvalidArgumentException (regra SEFAZ)', function () {
    bindFakeCertificadoService();

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    expect(fn () => $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Curta', // 5 chars < 15
    ))->toThrow(InvalidArgumentException::class, 'Justificativa');
});

it('8. inutilização cross-tenant (biz=99 tentando inutilizar biz=1) falha por isolation', function () {
    nfeFake(businessId: 1, numero: 100, status: 'rejeitada');

    // Simula contexto autenticado biz=99
    session(['user.business_id' => 99]);

    bindFakeCertificadoService();

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    // Service deve rejeitar tentativa cross-tenant (business_id=1 não bate com contexto=99)
    expect(fn () => $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Tentativa cross-tenant — deve falhar',
    ))->toThrow(UnauthorizedActionException::class);
});

it('9. modelo inválido (57=CT-e) lança InvalidArgumentException — só 55/65 permitidos', function () {
    bindFakeCertificadoService();

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    expect(fn () => $service->inutilizar(
        businessId: 1,
        modelo: '57', // CT-e — não suportado pelo service
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Justificativa válida com mais de 15 chars pra passar validação',
    ))->toThrow(InvalidArgumentException::class, 'Modelo inválido');
});

it('10. faixa inválida (numero_de > numero_ate) lança InvalidArgumentException', function () {
    bindFakeCertificadoService();

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
        nfeInutilizacaoMockSuccess(),
    );

    expect(fn () => $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 105,
        numeroAte: 100, // < numero_de
        justificativa: 'Faixa invertida — deve falhar antes de chamar SEFAZ',
    ))->toThrow(InvalidArgumentException::class, 'Faixa inválida');
});

it('11. cstat != 102 (rejeitado) NÃO marca emissões como inutilizada', function () {
    nfeFake(businessId: 1, numero: 100, status: 'rejeitada');

    bindFakeCertificadoService();

    // Mock retorna cstat=999 (rejeitado SEFAZ)
    $toolsRejeitado = function (string $config, array $certData) {
        $tools = \Mockery::mock(\NFePHP\NFe\Tools::class);
        $tools->shouldReceive('model')->andReturn(55); // NFePHP\Tools::model retorna ?int (não chainable)
        $tools->shouldReceive('sefazInutiliza')
            ->andReturn('<?xml version="1.0"?><retInutNFe><infInut><cStat>999</cStat><xMotivo>Erro generico</xMotivo></infInut></retInutNFe>');
        return $tools;
    };

    $service = new NfeInutilizacaoService(
        app(CertificadoService::class),
        $toolsRejeitado,
    );

    $inut = $service->inutilizar(
        businessId: 1,
        modelo: '55',
        serie: '1',
        numeroDe: 100,
        numeroAte: 100,
        justificativa: 'Tentativa que SEFAZ vai rejeitar pra teste de cstat erro',
    );

    expect($inut->status)->toBe('rejeitado')
        ->and($inut->cstat)->toBe('999');

    // Emissão original NÃO mudou pra inutilizada (SEFAZ rejeitou)
    $original = NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('numero', 100)
        ->first();

    expect($original->status)->toBe('rejeitada');
});
