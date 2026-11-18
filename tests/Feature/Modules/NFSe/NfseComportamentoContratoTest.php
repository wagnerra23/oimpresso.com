<?php

declare(strict_types=1);

// @covers-us US-NFSE-004
// @covers-us US-NFSE-006
// Comportamentos fiscais que precisam CAIR se sumirem (auditoria NFSe 2026-11-05, lote 1
// PR 1.2). Antes disso só havia teste de PRESENÇA (Wave25..28: `class_uses` tem SoftDeletes,
// string `isCancelada()` no fonte) — nada exercitava o comportamento.
//
//  1. Nota cancelada fica no banco: não há rota que a apague, e o delete() do model só
//     marca deleted_at (Show.charter.md:73 — "cancelada permanece no banco").
//  2. Certificado vencido bloqueia a emissão antes de qualquer HTTP; a nota fica `erro` com o
//     motivo (lote 2 PR 2.2, #11025 — antes ela sumia).
//  3. Senha errada do A1 bloqueia a emissão: nada sai para a Sefin e a nota fica em `erro`.
//     Controle positivo: o MESMO PFX com a senha certa emite.
//  4. A 98 nunca emite com o certificado da 99, mesmo com a config apontando para ele
//     (desde 2026-11-18 a NFS-e usa o certificado ATIVO da empresa, não o cert_id).
//
// Empresa = 98 (fictícia canônica, ADR 0358); adversário = 99. Nunca biz=4 nem biz=1.
// DatabaseTransactions: o rollback desfaz só o que o teste gravou.
//
// O forceDelete() de nota cancelada está em NfseCanceladaNaoSomeDoBancoTest.php (arquivo
// próprio porque hoje nada impede o forceDelete — ver o cabeçalho de lá).

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;

// `Tests\TestCase` vem do tests/Pest.php — repetir aqui derruba a lane.
uses(DatabaseTransactions::class);

const NFSECOMP_TENANT = 98;
const NFSECOMP_ADVERSARIO = 99;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes e nfe_certificados (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSECOMP_TENANT) || ! \App\Business::find(NFSECOMP_ADVERSARIO)) {
        $this->markTestSkipped('Lane sem os tenants 98/99 semeados (ADR 0358).');
    }

    $this->user = $this->usuarioComPermissoes(['nfse.view', 'nfse.emit', 'nfse.cancel'], \App\Business::find(NFSECOMP_TENANT));
    $this->actingAs($this->user);
    session(['user.business_id' => NFSECOMP_TENANT, 'business.id' => NFSECOMP_TENANT]);
});

/** PFX autoassinado com senha conhecida (a DPS é assinada de verdade, PFX falso não serve). */
function nfsecompPfx(string $senha): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'TESTE NFSE FICTICIO'], $key, ['digest_alg' => 'sha256']);
    $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
    openssl_pkcs12_export($x509, $pfx, $key, $senha);

    return $pfx;
}

function nfsecompCert(int $businessId, bool $vencido = false): int
{
    return (int) DB::table('nfe_certificados')->insertGetId([
        'business_id'        => $businessId,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000098', // CNPJ fictício
        'valido_ate'         => $vencido ? now()->subDay()->toDateString() : now()->addYear()->toDateString(),
        'encrypted_password' => Crypt::encryptString('senha-ficticia'),
        'ativo'              => 1,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
}

function nfsecompConfig(int $businessId, ?int $certId): NfseProviderConfig
{
    return NfseProviderConfig::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste
        'business_id'           => $businessId,
        'provider'              => 'sn_nfse_federal',
        'municipio_codigo_ibge' => '4218707',
        'serie_default'         => 'RPS',
        'cnae'                  => '6201-5/00',
        'lc116_codigo_default'  => '1.05',
        'aliquota_iss'          => 0.02,
        'ambiente'              => 'homologacao',
        'cert_id'               => $certId,
    ]);
}

function nfsecompPayload(?string $pfx, ?string $senha): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(
        businessId: NFSECOMP_TENANT,
        rpsNumero: '1',
        competencia: Carbon::create(2026, 10, 1),
        tomadorNome: 'TOMADOR FICTICIO LTDA',
        tomadorCnpj: '11222333000181', // CNPJ fictício
        tomadorCpf: null,
        tomadorEmail: 'tomador@ficticio.example',
        descricao: 'Servico de teste ' . uniqid(),
        lc116Codigo: '1.05',
        valorServicos: 100.00,
        aliquotaIss: 0.02,
        certPfxBase64: $pfx === null ? null : base64_encode($pfx),
        certSenha: $senha,
        ambiente: 'homologacao',
        prestadorCnpj: '11222333000181',
        regimePrestador: 'lucro_presumido',
    );
}

/**
 * Roda a emissão e devolve a CertificadoInvalidoException lançada. A mensagem dessa exceção é FIXA
 * ("Certificado A1 inválido ou expirado…"); o motivo de verdade vai em `->detalhe`, e é ele que
 * separa vencido × senha errada × certificado ausente. Medido no 1º run da lane (PR #11031).
 */
function nfsecompRecusa(callable $emitir): CertificadoInvalidoException
{
    try {
        $emitir();
    } catch (CertificadoInvalidoException $e) {
        return $e;
    }

    throw new \RuntimeException('A emissão deveria ter sido recusada por certificado e passou.');
}

/**
 * A recusa deixa UMA linha `erro` da 98 para este payload. A linha guarda a mensagem fixa da
 * CertificadoInvalidoException ("Certificado A1 inválido ou expirado…"); o motivo específico
 * (vencido × ausente) fica em ->detalhe, que cada teste confere na exceção. Medido no run do #11069.
 */
function nfsecompNotaEmErro(NfseEmissaoPayload $payload): void
{
    $motivo = 'Certificado A1 inválido ou expirado';
    // SUPERADMIN: teste confere a linha da própria empresa 98 sem depender da sessão.
    $notas = NfseEmissao::withoutGlobalScopes()
        ->where('business_id', NFSECOMP_TENANT)
        ->where('idempotency_key', $payload->idempotencyKey())
        ->get();
    expect($notas)->toHaveCount(1);
    expect($notas[0]->status)->toBe('erro');
    expect((string) $notas[0]->erro_mensagem)->toContain($motivo);
    expect($notas[0]->numero)->toBeNull();
}

function nfsecompNotaCancelada(): int
{
    return (int) DB::table('nfse_emissoes')->insertGetId([
        'business_id'     => NFSECOMP_TENANT,
        'serie'           => 'RPS',
        'rps_numero'      => 'COMP-CANC',
        'numero'          => '555',
        'competencia'     => '2026-10-01',
        'tomador_nome'    => 'TOMADOR FICTICIO LTDA',
        'descricao'       => 'Servico de teste',
        'valor_servicos'  => 100,
        'valor_iss'       => 2,
        'status'          => 'cancelada',
        'idempotency_key' => 'ct-nfsecomp-' . uniqid('', true),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

// ── 1. Nota cancelada fica no banco ──────────────────────────────────────────

it('[T0] US-NFSE-006 · nao ha rota que apague nota: DELETE no detalhe nao e aceito e a cancelada fica', function () {
    $id = nfsecompNotaCancelada();

    // Controle positivo: a nota existe e o detalhe dela abre para a empresa dona.
    $this->get("/nfse/{$id}")->assertOk();

    $this->delete("/nfse/{$id}")->assertStatus(405);

    $linha = DB::table('nfse_emissoes')->where('id', $id)->first();
    expect($linha)->not->toBeNull('A nota cancelada sumiu do banco depois de um DELETE.');
    expect($linha->status)->toBe('cancelada');
    expect($linha->deleted_at)->toBeNull();
});

it('US-NFSE-006 · delete() do model so marca deleted_at: a linha da cancelada segue no banco', function () {
    $id = nfsecompNotaCancelada();

    try {
        NfseEmissao::findOrFail($id)->delete();
    } catch (\Throwable) {
        // Recusar o delete também cumpre o contrato: o que importa é a linha ficar.
    }

    $linha = DB::table('nfse_emissoes')->where('id', $id)->first();
    expect($linha)->not->toBeNull('delete() removeu fisicamente a nota cancelada.');
    expect($linha->status)->toBe('cancelada');
});

// ── 2. Certificado vencido ───────────────────────────────────────────────────

it('US-NFSE-004 · certificado vencido bloqueia a emissao antes de chamar a Sefin e a nota fica em erro', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'NAO-DEVIA'], 201)]);
    nfsecompConfig(NFSECOMP_TENANT, nfsecompCert(NFSECOMP_TENANT, vencido: true));

    $service = new NfseEmissaoService(new SnNfseAdapter('homologacao'));
    $payload = nfsecompPayload(nfsecompPfx('senha'), 'senha');

    $recusa = nfsecompRecusa(fn () => $service->emitir($payload));
    expect((string) $recusa->detalhe)->toContain('expirado em');

    Http::assertNothingSent();
    // Desde o lote 2 PR 2.2 (#11025) a nota é gravada antes da validação e fica `erro`,
    // para aparecer em /nfse. Antes sumia sem rastro.
    nfsecompNotaEmErro($payload);
});

// ── 3. Senha errada ──────────────────────────────────────────────────────────

it('US-NFSE-004 · senha errada do A1 bloqueia a emissao: nada vai para a Sefin e a nota fica em erro', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'NAO-DEVIA'], 201)]);
    nfsecompConfig(NFSECOMP_TENANT, nfsecompCert(NFSECOMP_TENANT));

    $service = new NfseEmissaoService(new SnNfseAdapter('homologacao'));
    $payload = nfsecompPayload(nfsecompPfx('senha-certa'), 'senha-errada');

    $recusa = nfsecompRecusa(fn () => $service->emitir($payload));
    expect((string) $recusa->detalhe)->toContain('assinar a DPS');

    Http::assertNothingSent();
    $nota = NfseEmissao::where('idempotency_key', $payload->idempotencyKey())->first();
    expect($nota)->not->toBeNull();
    expect($nota->status)->toBe('erro');
});

it('US-NFSE-004 · controle positivo: o mesmo PFX com a senha certa assina e emite', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE-FICTICIA'], 201)]);
    nfsecompConfig(NFSECOMP_TENANT, nfsecompCert(NFSECOMP_TENANT));

    $service = new NfseEmissaoService(new SnNfseAdapter('homologacao'));
    $emissao = $service->emitir(nfsecompPayload(nfsecompPfx('senha-certa'), 'senha-certa'));

    Http::assertSentCount(1);
    expect($emissao->status)->toBe('emitida');
    expect($emissao->provider_protocolo)->toBe('CHAVE-FICTICIA');
});

// ── 4. Certificado de outra empresa ──────────────────────────────────────────

it('[T0] US-NFSE-004 · config da 98 apontando para certificado da 99 nao usa o certificado alheio', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'NAO-DEVIA'], 201)]);
    // A 98 parte sem certificado ativo (a transação desfaz); a 99 tem um ativo e vigente.
    DB::table('nfe_certificados')->where('business_id', NFSECOMP_TENANT)->update(['ativo' => 0]);
    $certAlheio = nfsecompCert(NFSECOMP_ADVERSARIO);
    nfsecompConfig(NFSECOMP_TENANT, $certAlheio);

    $certificados = app(\Modules\NfeBrasil\Services\CertificadoService::class);
    expect($certificados->certificadoAtivo(NFSECOMP_TENANT))->toBeNull();

    $service = new NfseEmissaoService(new SnNfseAdapter('homologacao'));
    $payload = nfsecompPayload(nfsecompPfx('senha'), 'senha');
    $recusa = nfsecompRecusa(fn () => $service->emitir($payload));
    expect((string) $recusa->detalhe)->toContain('Nenhum certificado');
    Http::assertNothingSent();
    // A nota da 98 fica `erro` (lote 2 PR 2.2); nada foi assinado com o A1 da 99.
    nfsecompNotaEmErro($payload);

    // Controle positivo: com certificado ativo da própria empresa, é ele que resolve.
    $proprio = nfsecompCert(NFSECOMP_TENANT);
    expect($certificados->certificadoAtivo(NFSECOMP_TENANT)?->id)->toBe($proprio);
});
