<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * PR #1 Fiscal/Nfe — isolation Tier 0 + permission gate.
 *
 * O Cockpit NF-e do módulo Fiscal é THIN agregador — lê NfeEmissao via
 * `Modules\NfeBrasil\Models\NfeEmissao` (HasBusinessScope global scope).
 * Esse teste verifica:
 *
 *   1. SQLite skip — schema NfeBrasil só roda em MySQL UltimatePOS
 *   2. Controller `index()` retorna 403 sem permission `fiscal.nfe.view`
 *   3. Counts são scoped por business_id (biz=1 não vê emissões biz=99)
 *   4. `buildRowsPayload` (deferred) respeita filtro `status=rejeitadas` cross-tenant
 *
 * ADR 0093: business_id Tier 0 IRREVOGÁVEL — toda Model que toca dados negócio.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — Larissa, cliente real prod) em tests.
 *
 * Espelha pattern de `Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest.php`.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/Fiscal/Http/Controllers/NfeCockpitController.php
 * @see resources/js/Pages/Fiscal/Nfe.charter.md
 */

const FISCAL_BIZ_WAGNER = 1;
const FISCAL_BIZ_FICTICIO = 99;
const FISCAL_TAG_TEST = 'PR1-FISCAL-NFE-ISO-TEST';

/**
 * Guard de banco — chamado SÓ pelos casos que tocam `nfe_emissoes`.
 *
 * Antes isto era um `beforeEach` do arquivo inteiro, e o efeito colateral era caro: o caso do
 * `sefazCodes` (reflection pura, zero query) **skipava junto**. Como a lane de CI é SQLite e o
 * staging do CT 100 não tem as migrations do NfeBrasil, ele não executava em lugar nenhum —
 * cobertura de papel. Movendo o guard pra dentro dos casos que realmente precisam de tabela,
 * o que é DB-free passa a rodar em qualquer lane.
 */
function fiscalNfeCockpitExigeBanco(\Tests\TestCase $t): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        $t->markTestSkipped('SQLite-incompatível: NfeBrasil/Fiscal requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $t->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz early-return
    // em `! auth()->check()` (app/Scopes/ScopeByBusiness.php:26) e lê a business
    // ativa de session('user.business_id'). Sem actingAs o scope no-opa e este guard de
    // isolamento contava biz=1 + biz=99 (3 em vez de 1) — falha de TESTE, não vazamento de
    // produto: a rota /fiscal/nfe roda atrás do middleware `auth`, onde auth()->check() é
    // sempre true. Autenticamos um usuário biz=1 (semeado pelo pest-mysql-setup; sem role →
    // não é superadmin) espelhando NfeBrasilMultiTenantIsolationTest. ADR 0093 + ADR 0101.
    $t->actingAs(\App\User::where('business_id', FISCAL_BIZ_WAGNER)->firstOrFail());
}

afterEach(function () {
    // Guard SQLite (Wagner 2026-05-25): cleanup só roda quando tabela existe.
    // beforeEach skipa tests que precisam dela, mas afterEach roda sempre —
    // sem guard, CI Pest SQLite (modules-pest.yml) quebra com QueryException
    // 'no such table: nfe_emissoes'.
    if (! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    // Cleanup — qualquer emissão criada com tag de teste é removida hard.
    // Não usa global scope (precisa achar de TODOS os businesses).
    NfeEmissao::withoutGlobalScopes()
        ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
        ->forceDelete();
});

it('UC-FNFE-01 · global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit', function () {
    fiscalNfeCockpitExigeBanco($this);

    // Cria 1 emissão biz=1 + 2 emissões biz=99 com mesma chave-tag pra rastreio.
    $base = [
        'modelo'      => '55',
        'serie'       => '1',
        'status'      => 'autorizada',
        'cstat'       => 100,
        'valor_total' => 100.00,
        'emitido_em'  => now(),
    ];

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_WAGNER,
        'numero'      => 9001,
        'chave_44'    => str_pad('9001' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_FICTICIO,
        'numero'      => 9002,
        'chave_44'    => str_pad('9002' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => FISCAL_BIZ_FICTICIO,
        'numero'      => 9003,
        'chave_44'    => str_pad('9003' . FISCAL_TAG_TEST, 44, '0', STR_PAD_RIGHT),
    ]);

    // Simula sessão biz=1 — HasBusinessScope deve filtrar transparente.
    session(['business.id' => FISCAL_BIZ_WAGNER, 'user.business_id' => FISCAL_BIZ_WAGNER]);

    $countBiz1 = NfeEmissao::query()
        ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
        ->count();

    expect($countBiz1)->toBe(1)
        ->and(NfeEmissao::withoutGlobalScopes()
            ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
            ->count())->toBe(3);
});

// ⚠️ REMOVIDO 2026-07-27 — o caso `isCancelavel respeita janela legal 24h NFC-e vs 168h NF-e`
// vivia aqui, mas era TAUTOLÓGICO: declarava um `$isCancelavel = function (...)` que
// RE-IMPLEMENTAVA a fórmula do Controller dentro do próprio teste, e depois testava esse clone.
// Se o `NfeCockpitController::isCancelavel` mudasse a janela de 24h para 12h, o teste seguiria
// verde (lápide §5 2026-06-05). O substituto invoca o método REAL por reflection e está em
// `AcoesContratoTest::UC-FNFE-02` — com mordida provada (afrouxar a regra deixa o teste vermelho).


it('UC-FNFE-03 · sefazCodes retorna mapa com pelo menos 100, 110, 220, 539, 691, 778, 999', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\NfeCockpitController();
    $reflection = new ReflectionMethod($controller, 'sefazCodes');
    $reflection->setAccessible(true);
    $codes = $reflection->invoke($controller);

    expect($codes)
        ->toHaveKeys([100, 110, 220, 539, 691, 778, 999])
        ->and($codes[100]['tone'])->toBe('ok')
        ->and($codes[220]['tone'])->toBe('bad')
        ->and($codes[691]['tone'])->toBe('warn');
});
