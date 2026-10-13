<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Services\NfseEmissaoService;
use Modules\NfeBrasil\Services\Nfse\DpsXml;

uses(Tests\TestCase::class);

/**
 * Cutover fiscal por-business (Martinho biz=164, 2026-06-03).
 *
 * GAP detectado no Passo 0: o ambiente da emissão NFS-e era resolvido pelo
 * BIND GLOBAL `config('nfse.ambiente')` (env NFSE_AMBIENTE), não pelo campo
 * por-business `nfse_provider_configs.ambiente`. Ligar produção pra 1 tenant
 * afetaria TODOS (ROTA LIVRE etc) — viola o requisito "não tocar outros
 * clientes" do cutover controlado.
 *
 * Estes testes travam o comportamento correto: a EMISSÃO segue
 * `$payload->ambiente` (do tenant), independente de como o adapter foi bindado.
 *
 * DB-free (Http::fake + adapter puro) — roda em SQLite CI sem schema MySQL.
 *
 * @see Modules/NFSe/Adapters/SnNfseAdapter.php
 * @see Modules/NFSe/Services/NfseEmissaoService.php (montarPayload)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

/** PFX autoassinado: a DPS agora é assinada de verdade (R-NFSE-005), PFX falso não serve. */
function ambPfx(): string
{
    static $pfx = null;
    if ($pfx === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $x509 = openssl_csr_sign(openssl_csr_new(['commonName' => 'TESTE'], $key, ['digest_alg' => 'sha256']), null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($x509, $pfx, $key, 'senha');
    }

    return $pfx;
}

function makeNfsePayload(string $ambiente): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(
        businessId: 164,
        rpsNumero: '202606030001',
        competencia: Carbon::createFromFormat('Y-m', '2026-06'),
        tomadorNome: 'Cliente Teste',
        tomadorCnpj: '11222333000181',
        tomadorCpf: null,
        tomadorEmail: 'teste@example.com',
        descricao: 'Servico de teste cutover',
        lc116Codigo: '1.05',
        valorServicos: 10.00,
        aliquotaIss: 0.05,
        issRetido: false,
        certPfxBase64: base64_encode(ambPfx()),
        certSenha: 'senha',
        ambiente: $ambiente,
        prestadorCnpj: '11222333000181',
        regimePrestador: 'lucro_presumido',
    );
}

it('emissao usa PRODUCAO do payload (por-business) mesmo com bind global homologacao', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'P1'], 201)]);

    // Adapter bindado no GLOBAL homologacao (como o container faz hoje)...
    $adapter = new SnNfseAdapter('homologacao');
    // ...mas o tenant biz=164 está em producao.
    $adapter->emitir(makeNfsePayload('producao'));

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://sefin.nfse.gov.br')
            && str_contains(DpsXml::desempacotar((string) $request['dpsXmlGZipB64']), '<tpAmb>1</tpAmb>');
    });
});

it('emissao usa HOMOLOGACAO do payload mesmo com bind global producao (anti-vazamento)', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'P1'], 201)]);

    // Mesmo que alguém deixe o bind global em producao, um tenant em homolog
    // NÃO pode emitir nota real por arraste.
    $adapter = new SnNfseAdapter('producao');
    $adapter->emitir(makeNfsePayload('homologacao'));

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://sefin.producaorestrita.nfse.gov.br')
            && str_contains(DpsXml::desempacotar((string) $request['dpsXmlGZipB64']), '<tpAmb>2</tpAmb>');
    });
});

it('montarPayload propaga o ambiente por-business do NfseProviderConfig (wiring travado)', function () {
    $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

    // Garante que o ambiente do payload vem do $config (tenant), não hardcoded.
    expect($src)->toMatch('/ambiente:\s*\$config\?->ambiente/');
});

it('DTO NfseEmissaoPayload default ambiente e fail-safe homologacao', function () {
    $p = new NfseEmissaoPayload(
        businessId: 1,
        rpsNumero: '1',
        competencia: Carbon::createFromFormat('Y-m', '2026-06'),
        tomadorNome: 'x',
        tomadorCnpj: null,
        tomadorCpf: '12345678909',
        tomadorEmail: null,
        descricao: 'd',
        lc116Codigo: '1.05',
        valorServicos: 1.0,
        aliquotaIss: 0.05,
    );

    expect($p->ambiente)->toBe('homologacao');
});
