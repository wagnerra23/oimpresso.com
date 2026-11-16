<?php

declare(strict_types=1);

// @covers-us US-NFSE-004 US-NFSE-005
// Sem nota em dobro (auditoria NFSe 2026-11-05, lote 2, PR 2.2 · decisão B6):
//  (a) timeout esgotado deixa a linha `processando`, e a retentativa do job reenvia a MESMA
//      linha com o MESMO nº de DPS — antes ela virava `erro` e o job criava linha e nº novos;
//  (b) erro terminal (ISS, tomador, certificado...) não é retentado pelo job nem recria linha;
//  (c) DPS duplicada não grava `emitida` sem número: consulta a nota pela chave, ou fica
//      `processando` com o aviso;
//  (d) sem config ou certificado a nota é gravada em `erro` com o motivo (antes sumia), e o
//      reenvio reusa a mesma linha — criar outra estourava o UNIQUE de idempotency_key.
// Tenant 98 (ADR 0358). Nunca biz=4.

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Exceptions\IssInvalidoException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Jobs\EmitirNfseJob;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Models\NfeCertificado;

uses(DatabaseTransactions::class);

const NFSDOBRO_TENANT = 98;

function nfsdobroPayload(): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(
        businessId: NFSDOBRO_TENANT,
        rpsNumero: '1',
        competencia: Carbon::create(2026, 10, 1),
        tomadorNome: 'Tomador Ficticio Ltda',
        tomadorCnpj: null,
        tomadorCpf: null,
        tomadorEmail: null,
        // Descrição única por teste: a chave de idempotência não colide com linha de outro run.
        descricao: 'Servico de teste ' . Str::uuid(),
        lc116Codigo: '1.05',
        valorServicos: 100.00,
        aliquotaIss: 0.02,
        ambiente: 'homologacao',
    );
}

/** Provedor-dublê com uma fila de respostas (resultado ou exceção) e registro dos envios. */
function nfsdobroProvider(array $respostas, ?NfseResultado $consulta = null): object
{
    return new class($respostas, $consulta) implements NfseProviderInterface {
        /** @var list<string> nº de DPS de cada envio */
        public array $envios = [];
        public array $consultas = [];

        public function __construct(private array $respostas, private readonly ?NfseResultado $consulta) {}

        public function emitir(NfseEmissaoPayload $payload): NfseResultado
        {
            $this->envios[] = $payload->rpsNumero;
            $r = array_shift($this->respostas);
            if ($r instanceof \Throwable) {
                throw $r;
            }

            return $r;
        }

        public function consultar(string $protocolo): NfseResultado
        {
            $this->consultas[] = $protocolo;

            return $this->consulta ?? NfseResultado::erro('sem consulta no dublê');
        }

        public function cancelar(string $numero, string $motivo): bool
        {
            throw new RuntimeException('cancelar não é exercitado aqui');
        }
    };
}

function nfsdobroLinhas(NfseEmissaoPayload $p)
{
    return NfseEmissao::withoutGlobalScopes()
        ->where('business_id', NFSDOBRO_TENANT)
        ->where('idempotency_key', $p->idempotencyKey())
        ->get();
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes e nfe_certificados (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSDOBRO_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    config(['nfse.backoff_base_segundos' => 0]);

    $cert = NfeCertificado::withoutGlobalScopes()->create([
        'business_id'        => NFSDOBRO_TENANT,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000191',
        'valido_ate'         => now()->addYear(),
        'encrypted_password' => Crypt::encryptString('senha-ficticia'),
        'ativo'              => true,
    ]);
    $existentes = NfseProviderConfig::withoutGlobalScopes()->where('business_id', NFSDOBRO_TENANT);
    if ((clone $existentes)->exists()) {
        $existentes->update(['ambiente' => 'homologacao', 'cert_id' => $cert->id]);
    } else {
        NfseProviderConfig::withoutGlobalScopes()->create([
            'business_id'           => NFSDOBRO_TENANT,
            'provider'              => 'sn_nfse_federal',
            'municipio_codigo_ibge' => '4218707',
            'ambiente'              => 'homologacao',
            'cert_id'               => $cert->id,
        ]);
    }

    // A troca para `emitida` aciona o observer de retenção do Financeiro — fora do escopo aqui.
    $retencao = Mockery::spy(RetencaoNfseTituloService::class);
    $retencao->allows('aplicar')->andReturn('desligado');
    $retencao->allows('estornar')->andReturn('nada_a_estornar');
    app()->instance(RetencaoNfseTituloService::class, $retencao);
});

it('timeout esgotado deixa a linha processando e reenvia sempre o mesmo nº de DPS', function () {
    $p = nfsdobroPayload();
    $provider = nfsdobroProvider([new ProviderTimeoutException(), new ProviderTimeoutException(), new ProviderTimeoutException()]);

    expect(fn () => (new NfseEmissaoService($provider))->emitir($p))->toThrow(ProviderTimeoutException::class);

    $linhas = nfsdobroLinhas($p);
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->status)->toBe('processando');
    expect($provider->envios)->toBe([(string) $linhas[0]->id, (string) $linhas[0]->id, (string) $linhas[0]->id]);
});

it('retentativa do job reenvia a mesma linha com o mesmo nº de DPS e não cria nota nova', function () {
    $p = nfsdobroPayload();
    $timeouts = nfsdobroProvider([new ProviderTimeoutException(), new ProviderTimeoutException(), new ProviderTimeoutException()]);
    expect(fn () => (new NfseEmissaoService($timeouts))->emitir($p))->toThrow(ProviderTimeoutException::class);
    $linha = nfsdobroLinhas($p)->first();

    // 2ª tentativa do job: a Sefin responde.
    $provider = nfsdobroProvider([NfseResultado::sucesso('900123', 'CHAVE-FICTICIA')]);
    $job = (new EmitirNfseJob($p))->withFakeQueueInteractions();
    $job->job->attempts = 2;
    $job->handle($provider);

    $job->assertNotFailed();
    $linhas = nfsdobroLinhas($p);
    expect($linhas)->toHaveCount(1);
    expect($provider->envios)->toBe([(string) $linha->id]);
    expect($linhas[0]->status)->toBe('emitida');
    expect($linhas[0]->numero)->toBe('900123');
});

it('chamada nova com a linha processando devolve a linha sem reenviar', function () {
    $p = nfsdobroPayload();
    $timeouts = nfsdobroProvider([new ProviderTimeoutException(), new ProviderTimeoutException(), new ProviderTimeoutException()]);
    expect(fn () => (new NfseEmissaoService($timeouts))->emitir($p))->toThrow(ProviderTimeoutException::class);

    $provider = nfsdobroProvider([NfseResultado::sucesso('1', 'X')]);
    $emissao = (new NfseEmissaoService($provider))->emitir($p);

    expect($emissao->status)->toBe('processando');
    expect($provider->envios)->toBe([]);
    expect(nfsdobroLinhas($p))->toHaveCount(1);
});

it('erro terminal falha o job sem retentar e sem criar outra linha', function () {
    $p = nfsdobroPayload();
    $provider = nfsdobroProvider([new IssInvalidoException('E501')]);

    $job = (new EmitirNfseJob($p))->withFakeQueueInteractions();
    $job->handle($provider);

    $job->assertFailedWith(IssInvalidoException::class);
    $linhas = nfsdobroLinhas($p);
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->status)->toBe('erro');
    expect($provider->envios)->toHaveCount(1);
});

it('timeout no job sobe para a fila retentar e failed() não marca erro', function () {
    $p = nfsdobroPayload();
    $provider = nfsdobroProvider([new ProviderTimeoutException(), new ProviderTimeoutException(), new ProviderTimeoutException()]);

    $job = (new EmitirNfseJob($p))->withFakeQueueInteractions();
    expect(fn () => $job->handle($provider))->toThrow(ProviderTimeoutException::class);
    $job->assertNotFailed();

    $job->failed(new ProviderTimeoutException());
    expect(nfsdobroLinhas($p)->first()->status)->toBe('processando');
});

it('DPS duplicada sem chave não grava emitida sem número', function () {
    $p = nfsdobroPayload();
    $provider = nfsdobroProvider([new RpsDuplicadoException()]);

    $emissao = (new NfseEmissaoService($provider))->emitir($p);

    expect($emissao->status)->toBe('processando');
    expect($emissao->numero)->toBeNull();
    expect($emissao->erro_mensagem)->toContain('já recebeu a DPS');
    expect($provider->consultas)->toBe([]);
});

it('DPS duplicada com chave consulta a nota e grava o número real', function () {
    $p = nfsdobroPayload();
    $timeouts = nfsdobroProvider([new ProviderTimeoutException(), new ProviderTimeoutException(), new ProviderTimeoutException()]);
    expect(fn () => (new NfseEmissaoService($timeouts))->emitir($p))->toThrow(ProviderTimeoutException::class);
    $linha = nfsdobroLinhas($p)->first();
    $linha->update(['provider_protocolo' => 'CHAVE-FICTICIA']);

    $provider = nfsdobroProvider(
        [new RpsDuplicadoException()],
        NfseResultado::sucesso('900777', 'CHAVE-FICTICIA'),
    );
    $emissao = (new NfseEmissaoService($provider))->emitir($p, retomarPendente: true);

    expect($provider->consultas)->toBe(['CHAVE-FICTICIA']);
    expect($emissao->status)->toBe('emitida');
    expect($emissao->numero)->toBe('900777');
    expect(nfsdobroLinhas($p))->toHaveCount(1);
});

it('sem config a nota fica gravada em erro com o motivo e o job falha', function () {
    NfseProviderConfig::withoutGlobalScopes()->where('business_id', NFSDOBRO_TENANT)->delete();
    $p = nfsdobroPayload();
    $provider = nfsdobroProvider([]);

    $job = (new EmitirNfseJob($p))->withFakeQueueInteractions();
    $job->handle($provider);

    $job->assertFailed();
    $linhas = nfsdobroLinhas($p);
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]->status)->toBe('erro');
    expect((string) $linhas[0]->erro_mensagem)->toContain('Configuração NFSe não encontrada');
    expect($provider->envios)->toBe([]);
});

it('reenvio de nota em erro reusa a mesma linha e o mesmo nº de DPS', function () {
    $p = nfsdobroPayload();
    $recusa = nfsdobroProvider([new IssInvalidoException('E501')]);
    expect(fn () => (new NfseEmissaoService($recusa))->emitir($p))->toThrow(IssInvalidoException::class);
    $linha = nfsdobroLinhas($p)->first();
    expect($linha->status)->toBe('erro');

    $provider = nfsdobroProvider([NfseResultado::sucesso('900888', 'CHAVE-FICTICIA')]);
    $emissao = (new NfseEmissaoService($provider))->emitir($p);

    expect($emissao->id)->toBe($linha->id);
    expect($emissao->status)->toBe('emitida');
    expect($provider->envios)->toBe([(string) $linha->id]);
    expect(nfsdobroLinhas($p))->toHaveCount(1);
});
