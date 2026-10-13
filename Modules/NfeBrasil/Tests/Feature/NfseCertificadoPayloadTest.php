<?php

declare(strict_types=1);

// @covers-us R-NFSE-004 — a NFS-e lê o certificado da config pelo CertificadoService (pfxDecriptado/senhaDecriptada não existem desde 4e772bcb46).

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * R-NFSE-004. Antes deste teste, `montarPayload` chamava `pfxDecriptado()` e
 * `senhaDecriptada()` no certificado — métodos removidos na unificação do schema
 * (4e772bcb46, 2026-05-07). Empresa com certificado configurado quebrava ao montar a nota.
 * Tenant 99 com certificado × 98 sem config — ADR 0358.
 */
const NFSC_BIZ = 98;
const NFSC_BIZ_OUTRO = 99;
const NFSC_PFX = "\x30\x82\x01\x00PFX-BINARIO-FALSO\x00\xff";
const NFSC_SENHA = 'senha-teste-r-nfse-004';

function nfscDados(): array
{
    return [
        'competencia'    => '2026-10',
        'tomador_nome'   => 'Tomador Teste',
        'tomador_cnpj'   => '11222333000181',
        'descricao'      => 'Serviço — teste R-NFSE-004',
        'lc116_codigo'   => '1.05',
        'valor_servicos' => '100.00',
        'aliquota_iss'   => '0.05',
    ];
}

beforeEach(function () {
    foreach (['nfse_provider_configs', 'nfe_certificados'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("{$t} ausente — rode as migrations do NfeBrasil/NFSe.");
        }
    }
    $this->seededSupportClientTenant(); // FKs → business 99
    Storage::fake('nfe_certs');

    // Mesmo formato do CertificadoService::salvar: .pfx Crypt::encrypt em {biz}/cert/{uuid}.pfx.enc
    $this->uuid = (string) Str::uuid();
    Storage::disk('nfe_certs')->put(sprintf('%d/cert/%s.pfx.enc', NFSC_BIZ_OUTRO, $this->uuid), Crypt::encrypt(NFSC_PFX));
    $this->certId = DB::table('nfe_certificados')->insertGetId([
        'business_id' => NFSC_BIZ_OUTRO, 'uuid' => $this->uuid, 'cnpj_titular' => '11222333000181',
        'valido_ate' => now()->addYear()->toDateString(), 'encrypted_password' => Crypt::encryptString(NFSC_SENHA),
        'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('nfse_provider_configs')->whereIn('business_id', [NFSC_BIZ, NFSC_BIZ_OUTRO])->delete();
    $this->configId = DB::table('nfse_provider_configs')->insertGetId([
        'business_id' => NFSC_BIZ_OUTRO, 'municipio_codigo_ibge' => '4218707', 'serie_default' => 'RPS',
        'ambiente' => 'homologacao', 'cert_id' => $this->certId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    DB::table('nfse_provider_configs')->where('id', $this->configId ?? 0)->delete();
    DB::table('nfe_certificados')->where('id', $this->certId ?? 0)->delete();
});

it('R-NFSE-004 montarPayload lê o certificado da config: PFX binário em base64 uma vez e a senha decriptada', function () {
    $p = (new NfseEmissaoService(new SnNfseAdapter()))->montarPayload(nfscDados(), NFSC_BIZ_OUTRO);

    expect($p->certPfxBase64)->toBe(base64_encode(NFSC_PFX));
    // o adapter decodifica uma vez e precisa chegar no binário, não em texto base64
    expect(base64_decode($p->certPfxBase64))->toBe(NFSC_PFX);
    expect($p->certSenha)->toBe(NFSC_SENHA);
});

it('R-NFSE-004 empresa sem config (98) monta sem certificado e não lê o do 99', function () {
    $p = (new NfseEmissaoService(new SnNfseAdapter()))->montarPayload(nfscDados(), NFSC_BIZ);

    expect($p->certPfxBase64)->toBeNull();
    expect($p->certSenha)->toBeNull();
});

it('R-NFSE-004 carregarCertificado procura o arquivo no business DO certificado e falha visível se ele sumiu', function () {
    $cert = NfeCertificado::withoutGlobalScopes()->findOrFail($this->certId);
    $lido = app(CertificadoService::class)->carregarCertificado($cert);
    expect($lido['pfx_binary'])->toBe(NFSC_PFX);
    expect($lido['senha'])->toBe(NFSC_SENHA);

    Storage::disk('nfe_certs')->delete(sprintf('%d/cert/%s.pfx.enc', NFSC_BIZ_OUTRO, $this->uuid));
    expect(fn () => app(CertificadoService::class)->carregarCertificado($cert))
        ->toThrow(RuntimeException::class, 'Arquivo do certificado ausente em disco');
});

it('R-NFSE-004 carregarParaSefaz segue lendo o certificado ativo igual a antes (mesma implementação)', function () {
    $ativos = DB::table('nfe_certificados')->where('business_id', NFSC_BIZ_OUTRO)->where('ativo', 1)->whereNull('deleted_at')->count();
    if ($ativos !== 1) {
        $this->markTestSkipped("biz 99 tem {$ativos} certificados ativos de outros testes; carregarParaSefaz escolheria um deles.");
    }
    $lido = app(CertificadoService::class)->carregarParaSefaz(NFSC_BIZ_OUTRO);

    expect($lido['pfx_binary'])->toBe(NFSC_PFX);
    expect($lido['senha'])->toBe(NFSC_SENHA);
    expect($lido['source'])->toBe('nfe_brasil');
});
