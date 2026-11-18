<?php

declare(strict_types=1);

// @covers-us US-NFSE-004
// @covers-us US-NFSE-006
// A NFS-e usa o certificado A1 ATIVO da empresa em `nfe_certificados` — o mesmo da NF-e,
// trocado na tela /fiscal/config?tab=cert — e não mais `nfse_provider_configs.cert_id`.
//
// Antes (até 2026-11-18): a emissão e o cancelamento liam o `cert_id` da config, ponteiro
// que só o `nfse:importar-cert` atualizava. Em produção a empresa 1 seguia apontando um
// certificado vencido depois de trocar o certificado na tela.
//
// Cenário de cada teste: a config da 98 aponta (cert_id) para um certificado ANTIGO da 98,
// já desativado; a 98 tem outro certificado ativo; a 99 tem o seu ativo. A emissão, o envio
// pela fila e o cancelamento têm de usar o ativo da 98 — nunca o antigo, nunca o da 99.
//
// Empresa = 98 (fictícia canônica, ADR 0358); adversário = 99. Nunca biz=4 nem biz=1.

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\NFSe\Contracts\NfseCancelaComCertificado;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Models\NfeCertificado;

// `Tests\TestCase` vem do tests/Pest.php — repetir aqui derruba a lane.
uses(DatabaseTransactions::class);

const NFSATIVO_TENANT = 98;
const NFSATIVO_ADVERSARIO = 99;
const NFSATIVO_PFX_ATIVO = 'PFX-98-ATIVO';
const NFSATIVO_PFX_ANTIGO = 'PFX-98-ANTIGO';
const NFSATIVO_PFX_ALHEIO = 'PFX-99-ATIVO';

/** Certificado com o arquivo cifrado no disco do dono (NfeBrasil), como o CertificadoService::salvar grava. */
function nfsativoCert(int $businessId, string $pfx, string $senha, bool $ativo): NfeCertificado
{
    // SUPERADMIN: setup de teste grava certificado de empresa explícita, sem sessão.
    $cert = NfeCertificado::withoutGlobalScopes()->create([
        'business_id'        => $businessId,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000098', // CNPJ fictício
        'valido_ate'         => now()->addYear(),
        'encrypted_password' => Crypt::encryptString($senha),
        'ativo'              => $ativo,
    ]);
    Storage::disk('nfe_certs')->put(sprintf('%d/cert/%s.pfx.enc', $businessId, $cert->uuid), Crypt::encrypt($pfx));

    return $cert;
}

function nfsativoPayload(): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(
        businessId: NFSATIVO_TENANT,
        rpsNumero: '1',
        competencia: Carbon::create(2026, 11, 1),
        tomadorNome: 'TOMADOR FICTICIO LTDA',
        tomadorCnpj: '11222333000181', // CNPJ fictício
        tomadorCpf: null,
        tomadorEmail: null,
        descricao: 'Servico de teste ' . uniqid(),
        lc116Codigo: '1.05',
        valorServicos: 100.00,
        aliquotaIss: 0.02,
        ambiente: 'homologacao',
    );
}

/** Provedor-dublê que registra o certificado recebido na emissão e no cancelamento. */
function nfsativoProvider(): object
{
    return new class implements NfseProviderInterface, NfseCancelaComCertificado {
        public array $emitidos = [];

        public array $cancelados = [];

        public function emitir(NfseEmissaoPayload $payload): NfseResultado
        {
            $this->emitidos[] = ['pfx' => $payload->certPfxBase64, 'senha' => $payload->certSenha];

            return NfseResultado::sucesso('700001', 'PROT-ATIVO');
        }

        public function consultar(string $protocolo): NfseResultado
        {
            throw new RuntimeException('consultar não é exercitado aqui');
        }

        public function cancelar(string $numero, string $motivo): bool
        {
            throw new RuntimeException('o caminho sem ambiente/certificado não pode ser usado');
        }

        // $prestadorCnpj opcional: o #11132 (evento e101101) acrescenta esse parâmetro ao
        // contrato. Opcional aqui, o dublê satisfaz a interface antes e depois dele.
        public function cancelarNoAmbiente(string $numero, string $motivo, string $ambiente, string $certPfxBase64, string $certSenha, string $prestadorCnpj = ''): bool
        {
            $this->cancelados[] = ['pfx' => $certPfxBase64, 'senha' => $certSenha];

            return true;
        }
    };
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes e nfe_certificados (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSATIVO_TENANT) || ! \App\Business::find(NFSATIVO_ADVERSARIO)) {
        $this->markTestSkipped('Lane sem os tenants 98/99 semeados (ADR 0358).');
    }

    Storage::fake('nfe_certs');

    // Ponto de partida conhecido (a transação desfaz): nenhuma das duas com certificado ativo.
    DB::table('nfe_certificados')->whereIn('business_id', [NFSATIVO_TENANT, NFSATIVO_ADVERSARIO])->update(['ativo' => 0]);

    $this->antigo = nfsativoCert(NFSATIVO_TENANT, NFSATIVO_PFX_ANTIGO, 'senha-antiga', ativo: false);
    $this->alheio = nfsativoCert(NFSATIVO_ADVERSARIO, NFSATIVO_PFX_ALHEIO, 'senha-99', ativo: true);

    // SUPERADMIN: setup de teste da config da 98, sem sessão.
    $existentes = NfseProviderConfig::withoutGlobalScopes()->where('business_id', NFSATIVO_TENANT);
    if ((clone $existentes)->exists()) {
        $existentes->update(['ambiente' => 'homologacao', 'cert_id' => $this->antigo->id, 'deleted_at' => null]);
    } else {
        NfseProviderConfig::withoutGlobalScopes()->create([
            'business_id'           => NFSATIVO_TENANT,
            'provider'              => 'sn_nfse_federal',
            'municipio_codigo_ibge' => '4218707',
            'ambiente'              => 'homologacao',
            'cert_id'               => $this->antigo->id,
        ]);
    }

    // Espião da retenção do Financeiro (o observer roda na troca de status da nota).
    $retencao = Mockery::spy(RetencaoNfseTituloService::class);
    $retencao->allows('estornar')->andReturn('nada_a_estornar');
    $retencao->allows('aplicar')->andReturn('desligado');
    app()->instance(RetencaoNfseTituloService::class, $retencao);
});

it('[T0] montarPayload da 98 leva o certificado ativo da 98, não o do cert_id nem o da 99', function () {
    nfsativoCert(NFSATIVO_TENANT, NFSATIVO_PFX_ATIVO, 'senha-ativa', ativo: true);

    $p = (new NfseEmissaoService(nfsativoProvider()))->montarPayload([
        'competencia'    => '2026-11',
        'tomador_nome'   => 'TOMADOR FICTICIO LTDA',
        'tomador_cnpj'   => '11222333000181', // CNPJ fictício
        'descricao'      => 'Servico de teste',
        'lc116_codigo'   => '1.05',
        'valor_servicos' => '100.00',
        'aliquota_iss'   => '0.02',
    ], NFSATIVO_TENANT);

    expect(base64_decode((string) $p->certPfxBase64))->toBe(NFSATIVO_PFX_ATIVO);
    expect($p->certSenha)->toBe('senha-ativa');
});

it('[T0] a config da 98 apontando para o certificado da 99 não muda nada: vale o ativo da 98', function () {
    nfsativoCert(NFSATIVO_TENANT, NFSATIVO_PFX_ATIVO, 'senha-ativa', ativo: true);
    // SUPERADMIN: o teste força o ponteiro cruzado na config da 98.
    NfseProviderConfig::withoutGlobalScopes()->where('business_id', NFSATIVO_TENANT)->update(['cert_id' => $this->alheio->id]);

    $provider = nfsativoProvider();
    (new NfseEmissaoService($provider))->emitir(nfsativoPayload());

    expect($provider->emitidos)->toHaveCount(1);
    expect(base64_decode((string) $provider->emitidos[0]['pfx']))->toBe(NFSATIVO_PFX_ATIVO);
    expect(base64_decode((string) $provider->emitidos[0]['pfx']))->not->toBe(NFSATIVO_PFX_ALHEIO);
});

it('[T0] envio pela fila (payload sem certificado) assina com o certificado ativo da 98', function () {
    nfsativoCert(NFSATIVO_TENANT, NFSATIVO_PFX_ATIVO, 'senha-ativa', ativo: true);

    $provider = nfsativoProvider();
    $emissao = (new NfseEmissaoService($provider))->emitir(nfsativoPayload());

    expect($emissao->status)->toBe('emitida');
    expect($provider->emitidos)->toHaveCount(1);
    expect(base64_decode((string) $provider->emitidos[0]['pfx']))->toBe(NFSATIVO_PFX_ATIVO);
    expect($provider->emitidos[0]['senha'])->toBe('senha-ativa');
});

it('[T0] cancelamento usa o certificado ativo da 98', function () {
    nfsativoCert(NFSATIVO_TENANT, NFSATIVO_PFX_ATIVO, 'senha-ativa', ativo: true);
    // SUPERADMIN: nota da própria 98 criada sem sessão.
    $nota = NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => NFSATIVO_TENANT,
        'status'          => 'emitida',
        'numero'          => '700002',
        'rps_numero'      => '000001',
        'serie'           => '1',
        'competencia'     => '2026-11-01',
        'tomador_nome'    => 'Tomador Ficticio Ltda',
        'descricao'       => 'Servico de teste',
        'valor_servicos'  => 100,
        'idempotency_key' => 'ct-nfsativo-' . uniqid('', true),
    ]);

    $provider = nfsativoProvider();
    (new NfseEmissaoService($provider))->cancelar($nota, 'Cancelamento solicitado pelo tomador');

    expect($nota->fresh()->status)->toBe('cancelada');
    expect($provider->cancelados)->toHaveCount(1);
    expect(base64_decode($provider->cancelados[0]['pfx']))->toBe(NFSATIVO_PFX_ATIVO);
    expect($provider->cancelados[0]['senha'])->toBe('senha-ativa');
});

it('[T0] 98 sem certificado ativo recusa, mesmo com a 99 tendo um ativo e o cert_id apontando para o antigo', function () {
    $provider = nfsativoProvider();
    $payload = nfsativoPayload();

    try {
        (new NfseEmissaoService($provider))->emitir($payload);
        $this->fail('A emissão sem certificado ativo deveria ter sido recusada.');
    } catch (CertificadoInvalidoException $e) {
        expect((string) $e->detalhe)->toContain('Nenhum certificado A1');
    }

    expect($provider->emitidos)->toBe([]);
});
