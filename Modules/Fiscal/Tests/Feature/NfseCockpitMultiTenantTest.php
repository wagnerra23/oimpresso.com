<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave NFS-e Fiscal — isolation Tier 0 (ADR 0093) + ADR 0101 biz=1.
 *
 * NfseEmissao = modelo 56 nacional NT 2024-001. HasBusinessScope global scope.
 *
 * ⚠️ 2026-07-17 — o guard de isolamento passava VÁCUO (falso-verde). Setava
 * session sem actingAs, então ScopeByBusiness no-opava (early-return
 * `! auth()->check()`, ScopeByBusiness.php:26) e a contagem cross-tenant dava 0
 * por AUSÊNCIA de dado (nfse_emissoes vazia), NÃO por filtro. Fix (espelha
 * Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest): actingAs(user
 * biz=1) + CRIA uma linha de OUTRO tenant real (bypass do scope) pra o scope ter o
 * que excluir, MAIS um controle positivo biz=1 (garante que o filtro é por-tenant,
 * não "esconde tudo"). ADR 0093 (multi-tenant Tier 0) + ADR 0101 (biz=1).
 */

const NFSE_COCKPIT_BIZ_WAGNER = 1;
// nfse_emissoes TEM FK → business(id): biz=99 não é semeado (CI seeds biz=1/biz=2;
// staging idem), então o cross-tenant "outro" é biz=2 (semeado) — mesma resolução do
// CertificadoServiceTest (nfebrasil-pest.yml ratchet 2026-06-24: FK a business ⇒ biz=99→biz=2).
const NFSE_COCKPIT_BIZ_OUTRO  = 2;
const NFSE_COCKPIT_TAG_TEST   = 'FISCAL-NFSE-ISO-TEST';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfseEmissao requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfse_emissoes')) {
        $this->markTestSkipped('nfse_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz early-return
    // em `! auth()->check()` (ScopeByBusiness.php:26) e lê a business ativa de
    // session('user.business_id') (NÃO 'business.id'). Sem actingAs o scope no-opa e o
    // guard de isolamento passa vácuo. Autenticamos um usuário do biz=1 (semeado pelo
    // pest-mysql-setup; sem role → não é superadmin). ADR 0093.
    $this->actingAs(\App\User::where('business_id', NFSE_COCKPIT_BIZ_WAGNER)->firstOrFail());
});

afterEach(function () {
    // Cleanup defensivo — só os registros marcados pelo teste (tag específica na
    // idempotency_key), em qualquer business (biz=1 do controle positivo + biz=2 cross-tenant).
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfse_emissoes')) {
        return;
    }
    try {
        DB::table('nfse_emissoes')
            ->where('idempotency_key', 'like', '%' . NFSE_COCKPIT_TAG_TEST . '%')
            ->delete();
    } catch (\Throwable) {
        // Cleanup best-effort; falha aqui não invalida assertions.
    }
});

it('NfseEmissao HasBusinessScope esconde cross-tenant da listagem do cockpit Nfse', function () {
    // idempotency_key é UNIQUE GLOBAL — sufixo randômico evita "Duplicate entry" se um
    // afterEach anterior falhou ou runs concorrem no mesmo banco (o cleanup casa por LIKE %TAG%).
    $suffix = uniqid();

    // Cross-tenant real: NFS-e de OUTRO business (biz=2 semeado — FK nfse_emissoes→business).
    $emissaoCross = DB::table('nfse_emissoes')->insertGetId([
        'business_id'     => NFSE_COCKPIT_BIZ_OUTRO,
        'competencia'     => now()->toDateString(),
        'tomador_nome'    => 'Tomador cross ' . NFSE_COCKPIT_TAG_TEST,
        'descricao'       => 'Serviço cross ' . NFSE_COCKPIT_TAG_TEST,
        'valor_servicos'  => 100.00,
        'idempotency_key' => 'iso-cross-' . NFSE_COCKPIT_TAG_TEST . '-' . $suffix,
        'status'          => 'emitida',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    // Own-tenant: NFS-e do PRÓPRIO biz=1 — controle positivo.
    $emissaoOwn = DB::table('nfse_emissoes')->insertGetId([
        'business_id'     => NFSE_COCKPIT_BIZ_WAGNER,
        'competencia'     => now()->toDateString(),
        'tomador_nome'    => 'Tomador own ' . NFSE_COCKPIT_TAG_TEST,
        'descricao'       => 'Serviço own ' . NFSE_COCKPIT_TAG_TEST,
        'valor_servicos'  => 200.00,
        'idempotency_key' => 'iso-own-' . NFSE_COCKPIT_TAG_TEST . '-' . $suffix,
        'status'          => 'emitida',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    session(['business.id' => NFSE_COCKPIT_BIZ_WAGNER, 'user.business_id' => NFSE_COCKPIT_BIZ_WAGNER]);

    // (1) A listagem scoped do cockpit de biz=1 NÃO enxerga a NFS-e do outro tenant.
    expect(NfseEmissao::where('id', $emissaoCross)->first())->toBeNull();

    // (2) Mas o registro EXISTE (bypass do scope) — sem isso o passo (1) seria vácuo
    //     (0 por ausência de dado, não por filtro).
    $real = NfseEmissao::withoutGlobalScopes()->where('id', $emissaoCross)->first(); // SUPERADMIN: provar existência cross-tenant
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(NFSE_COCKPIT_BIZ_OUTRO);

    // (3) Controle positivo: a NFS-e do PRÓPRIO tenant (biz=1) É visível sob o scope —
    //     prova que o filtro é por-tenant e não "esconde tudo" (over-scoping).
    expect(NfseEmissao::where('id', $emissaoOwn)->first())->not->toBeNull();
});

it('STATUS constants estão definidas no Model — Controller depende delas', function () {
    expect(NfseEmissao::STATUS_AUTHORIZED)->toBe('authorized')
        ->and(NfseEmissao::STATUS_REJECTED)->toBe('rejected')
        ->and(NfseEmissao::STATUS_PENDING)->toBe('pending')
        ->and(NfseEmissao::STATUS_SENT)->toBe('sent')
        ->and(NfseEmissao::STATUS_CANCELLED)->toBe('cancelled');
});
