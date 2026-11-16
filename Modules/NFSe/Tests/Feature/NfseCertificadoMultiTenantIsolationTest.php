<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\NFSe\Models\NfseCertificado;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Isolamento multi-tenant Tier 0 do alias `NfseCertificado`.
 *
 * `NfseCertificado` é alias de `Modules\NfeBrasil\Models\NfeCertificado` (mesma tabela
 * `nfe_certificados`, com CNPJ titular e senha cifrada do A1). Vazar entre empresas
 * expõe credencial fiscal de outro CNPJ. Este arquivo confirma que o alias herda o
 * escopo do pai (`HasBusinessScope` → `ScopeByBusiness`).
 *
 * Empresa da sessão = 98 (ADR 0358); adversário = 99. Nunca biz=4 nem biz=1.
 * Rollback por DatabaseTransactions — não apaga nada que o teste não criou.
 *
 * O que estava errado na versão anterior (run 43865896173, ficou em quarentena):
 *  - gravava colunas que não existem mais em nfe_certificados (nome_titular,
 *    validade_inicio, validade_fim, tipo) → QueryException 1054;
 *  - setava `business.id` sem login: em modo `aberto` o ScopeByBusiness só filtra com
 *    `auth()->check()` e `user.business_id`, então o "não vaza" nunca foi exercitado;
 *  - `SHOW COLUMNS ... LIKE ?` com binding → erro 1064.
 *
 * @see Modules/NFSe/Models/NfseCertificado.php
 * @see app/Scopes/ScopeByBusiness.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const NFSE_CERT_TENANT = 98;
const NFSE_CERT_ADVERSARIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requer MySQL (lane nfse-pest).');
    }
    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('nfe_certificados ausente.');
    }
    if (! \App\Business::find(NFSE_CERT_TENANT) || ! \App\Business::find(NFSE_CERT_ADVERSARIO)) {
        $this->markTestSkipped('Lane sem os tenants 98/99 semeados (ADR 0358).');
    }

    // Usuário novo da 98, sem papel (sem jana.superadmin, que abriria o escopo).
    $this->actingAs($this->usuarioComPermissoes([], \App\Business::find(NFSE_CERT_TENANT)));
    session(['user.business_id' => NFSE_CERT_TENANT, 'business.id' => NFSE_CERT_TENANT]);
});

function nfseCertCriar(int $businessId, bool $vencido = false): int
{
    return (int) DB::table('nfe_certificados')->insertGetId([
        'business_id'        => $businessId,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000099', // CNPJ fictício
        'valido_ate'         => $vencido ? now()->subDays(10)->toDateString() : now()->addYear()->toDateString(),
        'encrypted_password' => Crypt::encryptString('senha-ficticia'),
        'ativo'              => 1,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
}

it('[T0] NfseCertificado da empresa 99 nao aparece com sessao 98 (escopo herdado)', function () {
    $alheio = nfseCertCriar(NFSE_CERT_ADVERSARIO);

    expect(NfseCertificado::where('id', $alheio)->first())->toBeNull();

    // A linha existe: o null acima é o escopo, não ausência do dado.
    $real = NfseCertificado::withoutGlobalScopes()->whereKey($alheio)->first(); // SUPERADMIN: prova de existência
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(NFSE_CERT_ADVERSARIO);
});

it('[T0] NfseCertificado da empresa 98 aparece com sessao 98 (controle positivo)', function () {
    $proprio = nfseCertCriar(NFSE_CERT_TENANT);

    $cert = NfseCertificado::where('id', $proprio)->first();
    expect($cert)->not->toBeNull();
    expect((int) $cert->business_id)->toBe(NFSE_CERT_TENANT);
});

it('NfseCertificado isExpirado() acompanha isVencido() do pai', function () {
    $vencido = NfseCertificado::find(nfseCertCriar(NFSE_CERT_TENANT, vencido: true));
    $valido = NfseCertificado::find(nfseCertCriar(NFSE_CERT_TENANT));

    expect($vencido->isExpirado())->toBeTrue();
    expect($vencido->isExpirado())->toBe($vencido->isVencido());
    expect($valido->isExpirado())->toBeFalse();
});

it('nfe_certificados.business_id e NOT NULL', function () {
    // Alias explícito: no MySQL 8 o information_schema devolve os nomes de coluna em
    // maiúsculas, e ->value('is_nullable') leria null.
    $linha = DB::selectOne(
        'SELECT IS_NULLABLE AS nulavel FROM information_schema.columns
          WHERE table_schema = ? AND table_name = ? AND column_name = ?',
        [DB::getDatabaseName(), 'nfe_certificados', 'business_id'],
    );

    expect($linha)->not->toBeNull();
    expect($linha->nulavel)->toBe('NO');
});
