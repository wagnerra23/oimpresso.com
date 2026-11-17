<?php

declare(strict_types=1);

// @covers-us US-NFSE-005
// Certificado fora da fila + PII (auditoria NFSe 2026-11-05, lote 2, PR 2.3):
//  (a) o EmitirNfseJob vai para a fila SEM o PFX e a senha decifrados — antes eles iam no
//      payload e ficavam em `jobs`/`failed_jobs` quando a fila é banco;
//  (b) o envio resolve o certificado pelo business_id do payload;
//  (c) `failed()` do job e o log de cancelamento passam a mensagem/motivo pelo PiiRedactor.
// Tenant 98 (ADR 0358). Nunca biz=4.

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\NFSe\Contracts\NfseCancelaComCertificado;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Jobs\EmitirNfseJob;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Models\NfeCertificado;

uses(DatabaseTransactions::class);

const NFSFILA_TENANT = 98;
const NFSFILA_PFX = 'PFX-FICTICIO-FILA-98';
const NFSFILA_SENHA = 'senha-ficticia-fila';
const NFSFILA_EMAIL = 'tomador@exemplo.test';

function nfsfilaPayload(?string $pfx = null, ?string $senha = null): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(
        businessId: NFSFILA_TENANT,
        rpsNumero: '1',
        competencia: Carbon::create(2026, 10, 1),
        tomadorNome: 'Tomador Ficticio Ltda',
        tomadorCnpj: null,
        tomadorCpf: null,
        tomadorEmail: null,
        descricao: 'Servico de teste ' . Str::uuid(),
        lc116Codigo: '1.05',
        valorServicos: 100.00,
        aliquotaIss: 0.02,
        certPfxBase64: $pfx,
        certSenha: $senha,
        ambiente: 'homologacao',
    );
}

/** Provedor-dublê que guarda o payload recebido e confirma cancelamento. */
function nfsfilaProvider(): object
{
    return new class implements NfseProviderInterface, NfseCancelaComCertificado {
        public ?NfseEmissaoPayload $recebido = null;

        public function emitir(NfseEmissaoPayload $payload): NfseResultado
        {
            $this->recebido = $payload;

            return NfseResultado::sucesso('900555', 'CHAVE-FICTICIA');
        }

        public function consultar(string $protocolo): NfseResultado
        {
            throw new RuntimeException('consultar não é exercitado aqui');
        }

        public function cancelar(string $numero, string $motivo): bool
        {
            throw new RuntimeException('o caminho sem ambiente/certificado não pode ser usado');
        }

        public function cancelarNoAmbiente(string $numero, string $motivo, string $ambiente, string $certPfxBase64, string $certSenha): bool
        {
            return true;
        }
    };
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes e nfe_certificados (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSFILA_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    Storage::fake('nfe_certs');
    $cert = NfeCertificado::withoutGlobalScopes()->create([
        'business_id'        => NFSFILA_TENANT,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000191',
        'valido_ate'         => now()->addYear(),
        'encrypted_password' => Crypt::encryptString(NFSFILA_SENHA),
        'ativo'              => true,
    ]);
    Storage::disk('nfe_certs')->put(
        sprintf('%d/cert/%s.pfx.enc', NFSFILA_TENANT, $cert->uuid),
        Crypt::encrypt(NFSFILA_PFX),
    );
    $existentes = NfseProviderConfig::withoutGlobalScopes()->where('business_id', NFSFILA_TENANT);
    if ((clone $existentes)->exists()) {
        $existentes->update(['ambiente' => 'homologacao', 'cert_id' => $cert->id]);
    } else {
        NfseProviderConfig::withoutGlobalScopes()->create([
            'business_id'           => NFSFILA_TENANT,
            'provider'              => 'sn_nfse_federal',
            'municipio_codigo_ibge' => '4218707',
            'ambiente'              => 'homologacao',
            'cert_id'               => $cert->id,
        ]);
    }

    $retencao = Mockery::spy(RetencaoNfseTituloService::class);
    $retencao->allows('aplicar')->andReturn('desligado');
    $retencao->allows('estornar')->andReturn('nada_a_estornar');
    app()->instance(RetencaoNfseTituloService::class, $retencao);
});

it('o job vai para a fila sem o PFX e a senha decifrados', function () {
    Queue::fake();

    (new NfseEmissaoService(nfsfilaProvider()))
        ->despacharEmissaoAsync(nfsfilaPayload(base64_encode(NFSFILA_PFX), NFSFILA_SENHA));

    Queue::assertPushed(EmitirNfseJob::class, function (EmitirNfseJob $job) {
        $serializado = serialize($job);

        return $job->payload->certPfxBase64 === null
            && $job->payload->certSenha === null
            && $job->payload->businessId === NFSFILA_TENANT
            && ! str_contains($serializado, NFSFILA_SENHA)
            && ! str_contains($serializado, base64_encode(NFSFILA_PFX));
    });
});

it('o job resolve o certificado pelo business_id na hora de enviar', function () {
    $provider = nfsfilaProvider();
    $job = (new EmitirNfseJob(nfsfilaPayload()))->withFakeQueueInteractions();

    $job->handle($provider);

    $job->assertNotFailed();
    expect($provider->recebido?->certPfxBase64)->toBe(base64_encode(NFSFILA_PFX));
    expect($provider->recebido?->certSenha)->toBe(NFSFILA_SENHA);
});

it('failed() do job grava a mensagem redigida', function () {
    $p = nfsfilaPayload();
    $nota = NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => NFSFILA_TENANT,
        'status'          => 'processando',
        'rps_numero'      => '1',
        'serie'           => '1',
        'competencia'     => '2026-10-01',
        'tomador_nome'    => 'Tomador Ficticio Ltda',
        'descricao'       => $p->descricao,
        'valor_servicos'  => 100,
        'idempotency_key' => $p->idempotencyKey(),
    ]);

    (new EmitirNfseJob($p))->failed(new NfseException('Tomador ' . NFSFILA_EMAIL . ' recusado pela Sefin.'));

    $mensagem = (string) $nota->fresh()->erro_mensagem;
    expect($nota->fresh()->status)->toBe('erro');
    expect($mensagem)->not->toContain(NFSFILA_EMAIL);
    expect($mensagem)->toContain('[REDACTED:EMAIL]');
});

it('o log de cancelamento grava o motivo redigido', function () {
    $nota = NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => NFSFILA_TENANT,
        'status'          => 'emitida',
        'numero'          => '900556',
        'rps_numero'      => '1',
        'serie'           => '1',
        'competencia'     => '2026-10-01',
        'tomador_nome'    => 'Tomador Ficticio Ltda',
        'descricao'       => 'Servico de teste',
        'valor_servicos'  => 100,
        'idempotency_key' => 'ct-nfsfila-' . uniqid('', true),
    ]);
    // Spy no facade: qualquer outro log passa; o canal `nfse` é o que se inspeciona.
    $canal = Mockery::spy();
    Log::spy();
    Log::shouldReceive('channel')->andReturn($canal);

    (new NfseEmissaoService(nfsfilaProvider()))
        ->cancelar($nota, 'Pedido do tomador pelo e-mail ' . NFSFILA_EMAIL);

    expect($nota->fresh()->status)->toBe('cancelada');
    $canal->shouldHaveReceived('info')->withArgs(function ($mensagem, $contexto) {
        return $mensagem === 'NFSe cancelada'
            && ! str_contains((string) $contexto['motivo'], NFSFILA_EMAIL)
            && str_contains((string) $contexto['motivo'], '[REDACTED:EMAIL]');
    })->once();
});
