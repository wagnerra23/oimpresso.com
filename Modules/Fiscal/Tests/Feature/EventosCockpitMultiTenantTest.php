<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEvento;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave Eventos Fiscal — isolation Tier 0 + mapeamento de tipos canônicos SEFAZ.
 *
 * NfeEvento = append-only log (UPDATED_AT = null). HasBusinessScope ADR 0093.
 *
 * ⚠️ 2026-07-17 — o guard de isolamento passava VÁCUO (falso-verde). Setava
 * session sem actingAs, então ScopeByBusiness no-opava (early-return
 * `! auth()->check()`, ScopeByBusiness.php:26) e a contagem cross-tenant dava 0
 * por AUSÊNCIA de dado (nfe_eventos vazia), NÃO por filtro. Fix (espelha
 * Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest): actingAs(user
 * biz=1) + CRIA uma linha biz=99 real (bypass do scope) pra o scope ter o que
 * excluir, MAIS um controle positivo biz=1 (garante que o filtro é por-tenant, não
 * "esconde tudo"). ADR 0093 (multi-tenant Tier 0) + ADR 0101 (biz=1).
 */

const EVENTOS_BIZ_WAGNER   = 1;
const EVENTOS_BIZ_FICTICIO = 99; // nfe_eventos→nfe_emissoes (SEM FK a business) ⇒ biz fictício OK
const EVENTOS_TAG_TEST     = 'FISCAL-EVENTOS-ISO-TEST';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEvento requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_eventos') || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_eventos/nfe_emissoes table missing');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz early-return
    // em `! auth()->check()` (ScopeByBusiness.php:26) e lê a business ativa de
    // session('user.business_id') (NÃO 'business.id'). Sem actingAs o scope no-opa e o
    // guard de isolamento passa vácuo. Autenticamos um usuário do biz=1 (semeado pelo
    // pest-mysql-setup; sem role → não é superadmin). ADR 0093.
    $this->actingAs(\App\User::where('business_id', EVENTOS_BIZ_WAGNER)->firstOrFail());
});

afterEach(function () {
    // Cleanup defensivo — só os registros marcados pelo teste (tag específica),
    // em qualquer business (biz=1 do controle positivo + biz=99 cross-tenant).
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfe_eventos') || ! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    try {
        // Evento primeiro (FK ON DELETE CASCADE cobriria, mas explícito é seguro).
        DB::table('nfe_eventos')
            ->where('justificativa', 'like', '%' . EVENTOS_TAG_TEST . '%')
            ->delete();
        DB::table('nfe_emissoes')
            ->whereJsonContains('metadata->tag', EVENTOS_TAG_TEST)
            ->delete();
    } catch (\Throwable) {
        // Cleanup best-effort; falha aqui não invalida assertions.
    }
});

it('mapa de TIPOS cobre os 7 códigos SEFAZ canônicos esperados pelo cockpit', function () {
    $tipos = \Modules\Fiscal\Http\Controllers\EventosController::TIPOS;

    expect($tipos)
        ->toHaveKeys(['110110', '110111', '110140', '210200', '210210', '210220', '210240'])
        ->and($tipos['110110']['kind'])->toBe('cce')
        ->and($tipos['110111']['kind'])->toBe('cancel')
        ->and($tipos['110140']['kind'])->toBe('epec')
        ->and($tipos['210200']['kind'])->toBe('manifest');
});

it('NfeEvento HasBusinessScope esconde cross-tenant — listagem timeline scoped', function () {
    // Emissão pai + evento CROSS-TENANT (biz=99). nfe_emissoes NÃO tem FK a business ⇒
    // biz fictício OK. numero randômico evita colisão do UNIQUE (business_id,modelo,serie,numero).
    $emissaoCross = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => EVENTOS_BIZ_FICTICIO,
        'modelo'      => '55',
        'serie'       => '9',
        'numero'      => random_int(900000, 999999),
        'status'      => 'autorizada',
        'valor_total' => 10.00,
        'metadata'    => json_encode(['tag' => EVENTOS_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    $eventoCross = DB::table('nfe_eventos')->insertGetId([
        'business_id'   => EVENTOS_BIZ_FICTICIO,
        'emissao_id'    => $emissaoCross,
        'tipo'          => '110111', // cancelamento
        'justificativa' => 'cross-tenant ' . EVENTOS_TAG_TEST,
        'status'        => 'autorizado',
        'created_at'    => now(),
    ]);

    // Emissão pai + evento do PRÓPRIO tenant (biz=1) — controle positivo.
    $emissaoOwn = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => EVENTOS_BIZ_WAGNER,
        'modelo'      => '55',
        'serie'       => '9',
        'numero'      => random_int(800000, 899999),
        'status'      => 'autorizada',
        'valor_total' => 20.00,
        'metadata'    => json_encode(['tag' => EVENTOS_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    $eventoOwn = DB::table('nfe_eventos')->insertGetId([
        'business_id'   => EVENTOS_BIZ_WAGNER,
        'emissao_id'    => $emissaoOwn,
        'tipo'          => '110111',
        'justificativa' => 'own-tenant ' . EVENTOS_TAG_TEST,
        'status'        => 'autorizado',
        'created_at'    => now(),
    ]);

    session(['business.id' => EVENTOS_BIZ_WAGNER, 'user.business_id' => EVENTOS_BIZ_WAGNER]);

    // (1) A timeline scoped de biz=1 NÃO enxerga o evento biz=99.
    expect(NfeEvento::where('id', $eventoCross)->first())->toBeNull();

    // (2) Mas o registro EXISTE (bypass do scope) — sem isso o passo (1) seria vácuo
    //     (0 por ausência de dado, não por filtro).
    $real = NfeEvento::withoutGlobalScopes()->where('id', $eventoCross)->first(); // SUPERADMIN: provar existência cross-tenant
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(EVENTOS_BIZ_FICTICIO);

    // (3) Controle positivo: o evento do PRÓPRIO tenant (biz=1) É visível sob o scope —
    //     prova que o filtro é por-tenant e não "esconde tudo" (over-scoping).
    expect(NfeEvento::where('id', $eventoOwn)->first())->not->toBeNull();
});

it('NfeEvento é append-only (UPDATED_AT = null) — eventos não devem ser editados', function () {
    expect(NfeEvento::UPDATED_AT)->toBeNull();
});
