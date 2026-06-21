<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\SideEffects\InutilizarFaixaNfe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;

/**
 * US-SELL-030 — InutilizarFaixaNfe (SideEffect FSM, wrapper de NfeInutilizacaoService).
 *
 * Specs FAILING-FIRST:
 *   1. Delega payload válido pro Service e persiste NfeInutilizacao
 *   2. Propaga InvalidArgumentException (justificativa < 15 chars)
 *   3. Propaga UnauthorizedActionException (cross-tenant biz=99 vs biz=1)
 *   4. Subject sem business_id mas auth() user tem → resolve via auth (admin standalone)
 *   5. Cross-tenant biz=99 isolado de biz=1 (Tier 0 — não vaza dados)
 *
 * Wire-up:
 *   - Service real (NfeInutilizacaoService) com `toolsFactory` mockado pra
 *     simular SEFAZ retornando cstat=102 — não bate na rede.
 *   - SideEffect injeta Service via constructor pra teste isolado.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 default + biz=99 cross-tenant (ADR 0101).
 *
 * Refs:
 *   - app/Domain/Fsm/SideEffects/InutilizarFaixaNfe.php
 *   - Modules/NfeBrasil/Services/NfeInutilizacaoService.php
 *   - SPEC.md US-SELL-030
 */

class InutilizarFaixaTestSubject extends Model
{
    protected $table = 'inutilizar_faixa_test_subjects';

    protected $guarded = ['id'];

    public $timestamps = false;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

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

    Schema::create('inutilizar_faixa_test_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
    });

    DB::table('business')->insert([
        ['id' => 1, 'name' => 'WR2 SC', 'numero_serie_nfe' => '1', 'tax_number' => '12345678000199', 'state' => 'SC'],
        ['id' => 99, 'name' => 'Cross-tenant adversário', 'numero_serie_nfe' => '1', 'tax_number' => '99999999000199', 'state' => 'SP'],
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('inutilizar_faixa_test_subjects');
        Schema::dropIfExists('nfe_inutilizacoes');
        Schema::dropIfExists('nfe_emissoes');
        Schema::dropIfExists('business');
    }
    \Mockery::close();
});

// ── Helpers ────────────────────────────────────────────────────────────────

function inutilFaixaBindFakeCert(): void
{
    $mock = \Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake', 'senha' => 'x', 'valido_ate' => now()->addYear(), 'source' => 'test',
    ]);
    app()->instance(CertificadoService::class, $mock);
}

function inutilFaixaServiceMockSuccess(): NfeInutilizacaoService
{
    $toolsFactory = function (string $config, array $certData) {
        $tools = \Mockery::mock(\NFePHP\NFe\Tools::class);
        $tools->shouldReceive('model')->andReturn(55);
        $tools->shouldReceive('sefazInutiliza')
            ->andReturn('<?xml version="1.0"?><retInutNFe><infInut><cStat>102</cStat><xMotivo>Inutilizacao de numero homologado</xMotivo></infInut></retInutNFe>');

        return $tools;
    };

    return new NfeInutilizacaoService(app(CertificadoService::class), $toolsFactory);
}

function inutilFaixaSubject(int $bizId): InutilizarFaixaTestSubject
{
    $s = new InutilizarFaixaTestSubject();
    $s->business_id = $bizId;
    $s->save();

    return $s;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. delega payload válido pro Service e persiste NfeInutilizacao', function () {
    inutilFaixaBindFakeCert();
    $service = inutilFaixaServiceMockSuccess();
    $subject = inutilFaixaSubject(1);

    $sideEffect = new InutilizarFaixaNfe($service);

    $sideEffect->execute($subject, [
        'modelo' => '55',
        'serie' => '1',
        'numero_de' => 100,
        'numero_ate' => 100,
        'justificativa' => 'Erro no XML — número não enviado pra SEFAZ',
    ]);

    $inut = NfeInutilizacao::where('business_id', 1)->first();

    expect($inut)->not->toBeNull()
        ->and((int) $inut->business_id)->toBe(1)
        ->and($inut->modelo)->toBe('55')
        ->and((int) $inut->numero_de)->toBe(100)
        ->and($inut->status)->toBe('autorizado')
        ->and($inut->cstat)->toBe('102');
});

it('2. propaga InvalidArgumentException quando justificativa < 15 chars', function () {
    inutilFaixaBindFakeCert();
    $service = inutilFaixaServiceMockSuccess();
    $subject = inutilFaixaSubject(1);

    $sideEffect = new InutilizarFaixaNfe($service);

    expect(fn () => $sideEffect->execute($subject, [
        'modelo' => '55',
        'serie' => '1',
        'numero_de' => 100,
        'numero_ate' => 100,
        'justificativa' => 'Curta', // 5 chars < 15
    ]))->toThrow(InvalidArgumentException::class, 'Justificativa');
});

it('3. propaga UnauthorizedActionException quando cross-tenant via session', function () {
    inutilFaixaBindFakeCert();
    $service = inutilFaixaServiceMockSuccess();

    // Subject biz=1, mas session simula contexto biz=99 (admin spoofing)
    $subject = inutilFaixaSubject(1);
    session(['user.business_id' => 99]);

    $sideEffect = new InutilizarFaixaNfe($service);

    expect(fn () => $sideEffect->execute($subject, [
        'modelo' => '55',
        'serie' => '1',
        'numero_de' => 100,
        'numero_ate' => 100,
        'justificativa' => 'Tentativa cross-tenant — deve falhar via guard do Service',
    ]))->toThrow(UnauthorizedActionException::class);
});

it('4. payload incompleto lança InvalidArgumentException com mensagem instrutiva', function () {
    inutilFaixaBindFakeCert();
    $service = inutilFaixaServiceMockSuccess();
    $subject = inutilFaixaSubject(1);

    $sideEffect = new InutilizarFaixaNfe($service);

    // Payload faltando justificativa + numero_ate
    expect(fn () => $sideEffect->execute($subject, [
        'modelo' => '55',
        'serie' => '1',
        'numero_de' => 100,
    ]))->toThrow(InvalidArgumentException::class, 'payload incompleto');
});

it('5. cross-tenant biz=99 isolado de biz=1 (Tier 0 — Service vincula inutilização ao biz correto)', function () {
    inutilFaixaBindFakeCert();
    $service = inutilFaixaServiceMockSuccess();

    // Subject biz=99 — session matching biz=99 (cenário legítimo do tenant 99)
    $subject = inutilFaixaSubject(99);
    session(['user.business_id' => 99]);

    $sideEffect = new InutilizarFaixaNfe($service);

    $sideEffect->execute($subject, [
        'modelo' => '55',
        'serie' => '1',
        'numero_de' => 500,
        'numero_ate' => 500,
        'justificativa' => 'Inutilização legítima do tenant 99 — escopo isolado de biz=1',
    ]);

    // Inutilização criada SÓ pra biz=99 — biz=1 não enxerga
    $bizCount = NfeInutilizacao::where('business_id', 99)->count();
    $vazamento = NfeInutilizacao::where('business_id', 1)->count();

    expect($bizCount)->toBe(1)
        ->and($vazamento)->toBe(0);
});
