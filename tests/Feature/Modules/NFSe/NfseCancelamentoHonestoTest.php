<?php

declare(strict_types=1);

// @covers-us US-NFSE-006
// Cancelamento honesto (auditoria NFSe 2026-11-05, lote 0, PR 0.3):
//  (a) `cancelada` só com a confirmação do provedor — senão exceção e a nota fica `emitida`;
//  (b) cancela no ambiente da config da EMPRESA da nota, com o certificado dela (mTLS);
//  (c) só nota `emitida` cancela.
// A troca de status para `cancelada` estorna a retenção no Financeiro
// (NfseEmissaoRetencaoObserver) — o caso (a) prova também que o estorno NÃO roda sem
// confirmação. Tenant 98 (ADR 0358). Nunca biz=4.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\NFSe\Contracts\NfseCancelaComCertificado;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Models\NfeCertificado;

uses(DatabaseTransactions::class);

const NFSCANC_TENANT = 98;
const NFSCANC_PFX = 'PFX-FICTICIO-98';

function nfscancNota(string $status = 'emitida', ?string $numero = '900001'): NfseEmissao
{
    return NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => NFSCANC_TENANT,
        'status'          => $status,
        'numero'          => $numero,
        'rps_numero'      => '000001',
        'serie'           => '1',
        'competencia'     => '2026-10-01',
        'tomador_nome'    => 'Tomador Ficticio Ltda',
        'descricao'       => 'Servico de teste',
        'valor_servicos'  => 100,
        'idempotency_key' => 'ct-nfscanc-' . uniqid('', true),
    ]);
}

/** Provedor-dublê que implementa as duas interfaces e registra a chamada de cancelamento. */
function nfscancProvider(bool $confirma): object
{
    return new class($confirma) implements NfseProviderInterface, NfseCancelaComCertificado {
        public array $chamadas = [];

        public function __construct(private readonly bool $confirma) {}

        public function emitir(\Modules\NFSe\DTO\NfseEmissaoPayload $payload): \Modules\NFSe\DTO\NfseResultado
        {
            throw new RuntimeException('emitir não é exercitado aqui');
        }

        public function consultar(string $protocolo): \Modules\NFSe\DTO\NfseResultado
        {
            throw new RuntimeException('consultar não é exercitado aqui');
        }

        public function cancelar(string $numero, string $motivo): bool
        {
            throw new RuntimeException('o caminho sem ambiente/certificado não pode ser usado');
        }

        public function cancelarNoAmbiente(string $numero, string $motivo, string $ambiente, string $certPfxBase64, string $certSenha): bool
        {
            $this->chamadas[] = compact('numero', 'motivo', 'ambiente', 'certPfxBase64', 'certSenha');

            return $this->confirma;
        }
    };
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes e nfe_certificados (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSCANC_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    // Certificado da empresa 98: arquivo cifrado no disco do dono (NfeBrasil) + senha cifrada.
    Storage::fake('nfe_certs');
    $cert = NfeCertificado::withoutGlobalScopes()->create([
        'business_id'        => NFSCANC_TENANT,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000191',
        'valido_ate'         => now()->addYear(),
        'encrypted_password' => Crypt::encryptString('senha-ficticia'),
        'ativo'              => true,
    ]);
    Storage::disk('nfe_certs')->put(
        sprintf('%d/cert/%s.pfx.enc', NFSCANC_TENANT, $cert->uuid),
        Crypt::encrypt(NFSCANC_PFX),
    );

    // Config da empresa 98 em PRODUÇÃO — diferente do default global (homologação), para
    // provar que o ambiente vem da empresa da nota.
    config(['nfse.ambiente' => 'homologacao']);
    $existentes = NfseProviderConfig::withoutGlobalScopes()->where('business_id', NFSCANC_TENANT);
    if ((clone $existentes)->exists()) {
        $existentes->update(['ambiente' => 'producao', 'cert_id' => $cert->id]);
    } else {
        NfseProviderConfig::withoutGlobalScopes()->create([
            'business_id'           => NFSCANC_TENANT,
            'provider'              => 'sn_nfse_federal',
            'municipio_codigo_ibge' => '4218707',
            'ambiente'              => 'producao',
            'cert_id'               => $cert->id,
        ]);
    }

    // Espião do estorno de retenção do Financeiro (chamado pelo observer na troca de status).
    $this->retencao = Mockery::spy(RetencaoNfseTituloService::class);
    // Os métodos têm retorno `string`: o espião precisa devolver um valor do vocabulário.
    $this->retencao->allows('estornar')->andReturn('nada_a_estornar');
    $this->retencao->allows('aplicar')->andReturn('desligado');
    app()->instance(RetencaoNfseTituloService::class, $this->retencao);
});

it('cancela no ambiente e com o certificado da empresa da nota quando o provedor confirma', function () {
    $nota = nfscancNota();
    $provider = nfscancProvider(true);

    (new NfseEmissaoService($provider))->cancelar($nota, 'Cancelamento solicitado pelo tomador');

    expect($nota->fresh()->status)->toBe('cancelada');
    expect($provider->chamadas)->toHaveCount(1);
    expect($provider->chamadas[0]['ambiente'])->toBe('producao');
    expect($provider->chamadas[0]['certPfxBase64'])->toBe(base64_encode(NFSCANC_PFX));
    expect($provider->chamadas[0]['certSenha'])->toBe('senha-ficticia');
    expect($provider->chamadas[0]['numero'])->toBe('900001');
    // Controle positivo do espião: a transição real dispara o estorno.
    $this->retencao->shouldHaveReceived('estornar')->once();
});

it('nao grava cancelada nem estorna a retencao quando o provedor nao confirma', function () {
    $nota = nfscancNota();
    $provider = nfscancProvider(false);

    expect(fn () => (new NfseEmissaoService($provider))->cancelar($nota, 'Cancelamento solicitado pelo tomador'))
        ->toThrow(NfseException::class, 'não confirmou');

    expect($nota->fresh()->status)->toBe('emitida');
    expect($provider->chamadas)->toHaveCount(1);
    $this->retencao->shouldNotHaveReceived('estornar');
});

it('recusa cancelar nota que nao esta emitida e nao chama o provedor', function () {
    $nota = nfscancNota('erro', null);
    $provider = nfscancProvider(true);

    expect(fn () => (new NfseEmissaoService($provider))->cancelar($nota, 'Cancelamento solicitado pelo tomador'))
        ->toThrow(NfseException::class, 'Só nota emitida');

    expect($nota->fresh()->status)->toBe('erro');
    expect($provider->chamadas)->toHaveCount(0);
});
