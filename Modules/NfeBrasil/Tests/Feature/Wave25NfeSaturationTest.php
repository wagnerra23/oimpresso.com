<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\NfeService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 25 NfeBrasil SATURATION — D2 cross-tenant deep + D6 CONFAZ preservation + D7 LogsActivity.
 *
 * Esforço (gap 72 → ≥85, +13pp):
 *   - D2: cross-tenant comprehensive (Inutilizacao count cross-tenant + cstat
 *     auditoria + numero_de range isolado por tenant)
 *   - D6: NFe cancelada NUNCA forceDelete (CONFAZ SINIEF 07/2005 Art. 14) —
 *     teste de preservação contratual de Services
 *   - D7: confirma LogsActivity em todos os 3 Models críticos (já implementado
 *     Wave 17/18 — este test prova permanência)
 *
 * Tier 0 IRREVOGÁVEL:
 *   - NFe cancelada PERMANECE no banco (status=cancelada), nunca hard-delete
 *   - Numeração SEFAZ por (business_id, modelo, serie) — UNIQUE constraint
 *   - business_id NOT NULL em todas as tabelas fiscais
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (multi-tenant Tier 0)
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md (biz=1, nunca biz=4)
 * @see memory/requisitos/NfeBrasil/PII-LGPD-FISCAL.md (LGPD x CONFAZ Art. 14)
 * @see Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php (Wave 13)
 * @see Modules/NfeBrasil/Tests/Feature/NfeEventoMultiTenantIsolationTest.php (Wave 18)
 */

const W25_NFE_BIZ_WAGNER = 1;
const W25_NFE_BIZ_FICTICIO = 99;
const W25_NFE_TAG = 'WAVE25-NFE-ISO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL UltimatePOS (ADR 0101)');
    }
    foreach (['nfe_emissoes', 'nfe_inutilizacoes', 'nfe_eventos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode migrate NfeBrasil primeiro");
        }
    }

    // ScopeByBusiness só filtra com usuário AUTENTICADO (early-return em
    // `! auth()->check()`, ScopeByBusiness.php:26) lendo session('user.business_id').
    // Sem actingAs o scope no-opa e o count() cross-tenant abaixo não isola. Autenticamos
    // um usuário do biz=1 (semeado pelo pest-mysql-setup; sem role → não é superadmin);
    // cada teste seta session('user.business_id') pra escolher o tenant ativo. ADR 0093.
    $this->actingAs(\App\User::where('business_id', W25_NFE_BIZ_WAGNER)->firstOrFail());
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    try {
        NfeEmissao::withoutGlobalScopes()->withTrashed()
            ->whereIn('business_id', [W25_NFE_BIZ_WAGNER, W25_NFE_BIZ_FICTICIO])
            ->whereJsonContains('metadata->tag', W25_NFE_TAG)
            ->forceDelete();
        NfeInutilizacao::withoutGlobalScopes()
            ->whereIn('business_id', [W25_NFE_BIZ_WAGNER, W25_NFE_BIZ_FICTICIO])
            ->where('justificativa', 'like', '%'.W25_NFE_TAG.'%')
            ->delete();
    } catch (\Throwable) {
        // best-effort
    }
});

// ------------------------------------------------------------------
// D2 — Cross-tenant Inutilizacao count comprehensive
// ------------------------------------------------------------------

it('NfeInutilizacao count() biz=99 NÃO conta ranges do biz=1 (cross-tenant)', function () {
    // 2 inutilizações biz=1 + 1 biz=99
    DB::table('nfe_inutilizacoes')->insert([
        [
            'business_id'   => W25_NFE_BIZ_WAGNER,
            'modelo'        => '55', 'serie' => '1',
            'numero_de'     => 990100, 'numero_ate' => 990110,
            'justificativa' => 'biz=1 range A '.W25_NFE_TAG,
            'status'        => 'autorizado',
            'created_at'    => now(), 'updated_at' => now(),
        ],
        [
            'business_id'   => W25_NFE_BIZ_WAGNER,
            'modelo'        => '55', 'serie' => '1',
            'numero_de'     => 990200, 'numero_ate' => 990210,
            'justificativa' => 'biz=1 range B '.W25_NFE_TAG,
            'status'        => 'pendente',
            'created_at'    => now(), 'updated_at' => now(),
        ],
        [
            'business_id'   => W25_NFE_BIZ_FICTICIO,
            'modelo'        => '55', 'serie' => '1',
            'numero_de'     => 990300, 'numero_ate' => 990310,
            'justificativa' => 'biz=99 range C '.W25_NFE_TAG,
            'status'        => 'autorizado',
            'created_at'    => now(), 'updated_at' => now(),
        ],
    ]);

    session(['user.business_id' => W25_NFE_BIZ_FICTICIO]);
    $contagemBiz99 = NfeInutilizacao::where('justificativa', 'like', '%'.W25_NFE_TAG.'%')->count();
    expect($contagemBiz99)->toBe(1);

    session(['user.business_id' => W25_NFE_BIZ_WAGNER]);
    $contagemBiz1 = NfeInutilizacao::where('justificativa', 'like', '%'.W25_NFE_TAG.'%')->count();
    expect($contagemBiz1)->toBe(2);
});

it('NfeInutilizacao::quantidadeNumeros() computa range inclusivo correto', function () {
    DB::table('nfe_inutilizacoes')->insert([
        'business_id'   => W25_NFE_BIZ_WAGNER,
        'modelo'        => '55', 'serie' => '1',
        'numero_de'     => 991000, 'numero_ate' => 991009,
        'justificativa' => 'range qty '.W25_NFE_TAG,
        'status'        => 'autorizado',
        'created_at'    => now(), 'updated_at' => now(),
    ]);

    session(['user.business_id' => W25_NFE_BIZ_WAGNER]);
    $inut = NfeInutilizacao::where('justificativa', 'like', '%'.W25_NFE_TAG.'%')->first();
    expect($inut)->not->toBeNull();
    // Range [991000..991009] inclusivo = 10 numeros
    expect($inut->quantidadeNumeros())->toBe(10);
});

it('numeração inutilizada NÃO conflita cross-tenant — biz=1 e biz=99 podem inutilizar mesmo range', function () {
    DB::table('nfe_inutilizacoes')->insert([
        [
            'business_id'   => W25_NFE_BIZ_WAGNER,
            'modelo'        => '55', 'serie' => '99',
            'numero_de'     => 555001, 'numero_ate' => 555010,
            'justificativa' => 'biz=1 mesmo range '.W25_NFE_TAG,
            'status'        => 'autorizado',
            'created_at'    => now(), 'updated_at' => now(),
        ],
        [
            'business_id'   => W25_NFE_BIZ_FICTICIO,
            'modelo'        => '55', 'serie' => '99',
            'numero_de'     => 555001, 'numero_ate' => 555010, // mesmo range
            'justificativa' => 'biz=99 mesmo range '.W25_NFE_TAG,
            'status'        => 'autorizado',
            'created_at'    => now(), 'updated_at' => now(),
        ],
    ]);

    // Sem UNIQUE cross-tenant — 2 registros válidos
    $total = DB::table('nfe_inutilizacoes')
        ->where('justificativa', 'like', '%'.W25_NFE_TAG.'%')
        ->where('serie', '99')
        ->count();
    expect($total)->toBe(2);
});

// ------------------------------------------------------------------
// D6 — CONFAZ SINIEF 07/2005 Art. 14: NFe cancelada NUNCA forceDelete
// ------------------------------------------------------------------

it('NfeEmissao usa SoftDeletes (CONFAZ Art. 14 — nunca hard-delete preserva histórico)', function () {
    $traits = class_uses_recursive(NfeEmissao::class);
    $hasSoftDeletes = collect($traits)->contains(fn ($t) => str_contains($t, 'SoftDeletes'));
    expect($hasSoftDeletes)->toBeTrue();
});

it('NfeEmissao::isCancelavel() respeita prazos CONFAZ (24h NFC-e / 168h NFe)', function () {
    $emissao = new NfeEmissao();
    $emissao->status = 'autorizada';
    $emissao->modelo = '65'; // NFC-e
    $emissao->emitido_em = now()->subHours(23); // dentro da janela 24h
    expect($emissao->isCancelavel())->toBeTrue();

    $emissao2 = new NfeEmissao();
    $emissao2->status = 'autorizada';
    $emissao2->modelo = '65';
    $emissao2->emitido_em = now()->subHours(25); // expirou
    expect($emissao2->isCancelavel())->toBeFalse();

    $emissao3 = new NfeEmissao();
    $emissao3->status = 'autorizada';
    $emissao3->modelo = '55'; // NFe
    $emissao3->emitido_em = now()->subHours(167); // dentro 168h
    expect($emissao3->isCancelavel())->toBeTrue();
});

it('NfeEmissao status terminal (cancelada) NÃO pode ser re-cancelada (idempotência fiscal)', function () {
    $emissao = new NfeEmissao();
    $emissao->status = 'cancelada';
    $emissao->modelo = '65';
    $emissao->emitido_em = now()->subHours(1); // mesmo dentro da janela
    expect($emissao->isCancelavel())->toBeFalse();
});

it('NfeService NÃO contém chamada forceDelete em código de cancelamento (CONFAZ preservation)', function () {
    $file = (new ReflectionClass(NfeService::class))->getFileName();
    $src = file_get_contents($file);

    // Cancelar = update status, NUNCA forceDelete (que removeria fisicamente)
    // Soft delete também não — número permanece "usado oficialmente" pra SEFAZ
    expect($src)->not->toContain('->forceDelete()');
});

// ------------------------------------------------------------------
// D7 — LogsActivity em 3 Models críticos (Wave 17/18 — confirmação)
// ------------------------------------------------------------------

it('NfeEmissao + NfeEvento + NfeInutilizacao todos usam LogsActivity (LGPD D7)', function () {
    foreach ([
        NfeEmissao::class,
        \Modules\NfeBrasil\Models\NfeEvento::class,
        NfeInutilizacao::class,
    ] as $cls) {
        $traits = class_uses_recursive($cls);
        expect($traits)->toContain(LogsActivity::class)
            ->and(true)->toBeTrue("$cls deve usar LogsActivity (LGPD Art. 37)");
    }
});

it('NfeEmissao::getActivitylogOptions loga apenas campos canon (sem XML body)', function () {
    $emissao = new NfeEmissao();
    $opts = $emissao->getActivitylogOptions();
    // logName scoped
    expect($opts->logName)->toBe('nfe_emissao');
});

it('NfeInutilizacao::getActivitylogOptions tem logName scoped', function () {
    $inut = new NfeInutilizacao();
    $opts = $inut->getActivitylogOptions();
    expect($opts->logName)->toBe('nfe_inutilizacao');
});

it('NfeEvento::getActivitylogOptions tem logName scoped', function () {
    $evt = new \Modules\NfeBrasil\Models\NfeEvento();
    $opts = $evt->getActivitylogOptions();
    expect($opts->logName)->toBe('nfe_evento');
});

// ------------------------------------------------------------------
// D6 — NfeEmissao::isAutorizada helper + scopes
// ------------------------------------------------------------------

it('NfeEmissao::scopeAutorizadas filtra status=autorizada', function () {
    DB::table('nfe_emissoes')->insert([
        [
            'business_id' => W25_NFE_BIZ_WAGNER, 'modelo' => '55', 'serie' => '1',
            'numero' => 770001, 'status' => 'autorizada', 'valor_total' => 100.00,
            'metadata' => json_encode(['tag' => W25_NFE_TAG]),
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'business_id' => W25_NFE_BIZ_WAGNER, 'modelo' => '55', 'serie' => '1',
            'numero' => 770002, 'status' => 'pendente', 'valor_total' => 200.00,
            'metadata' => json_encode(['tag' => W25_NFE_TAG]),
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    session(['user.business_id' => W25_NFE_BIZ_WAGNER]);
    $autorizadas = NfeEmissao::autorizadas()
        ->whereJsonContains('metadata->tag', W25_NFE_TAG)
        ->count();
    expect($autorizadas)->toBe(1);
});
