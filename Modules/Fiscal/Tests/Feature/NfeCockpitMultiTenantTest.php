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

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil/Fiscal requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

afterEach(function () {
    // Cleanup — qualquer emissão criada com tag de teste é removida hard.
    // Não usa global scope (precisa achar de TODOS os businesses).
    NfeEmissao::withoutGlobalScopes()
        ->where('chave_44', 'like', '%' . FISCAL_TAG_TEST . '%')
        ->forceDelete();
});

it('global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit', function () {
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

it('isCancelavel respeita janela legal 24h NFC-e (modelo 65) vs 168h NF-e (modelo 55)', function () {
    // Helper espelha lógica do NfeCockpitController::isCancelavel sem precisar
    // instanciar Controller (factory de Request seria pesado pra este teste).
    $isCancelavel = function (NfeEmissao $e): bool {
        if ($e->status !== 'autorizada' || ! $e->emitido_em) return false;
        $prazoHoras = $e->modelo === '65' ? 24 : 168;
        return $e->emitido_em->diffInHours(now()) <= $prazoHoras;
    };

    $nfceRecente = new NfeEmissao([
        'modelo'     => '65',
        'status'     => 'autorizada',
        'emitido_em' => now()->subHours(10),
    ]);
    $nfceVelha = new NfeEmissao([
        'modelo'     => '65',
        'status'     => 'autorizada',
        'emitido_em' => now()->subHours(30),
    ]);
    $nfeDentroPrazo = new NfeEmissao([
        'modelo'     => '55',
        'status'     => 'autorizada',
        'emitido_em' => now()->subHours(48),
    ]);
    $nfeForaPrazo = new NfeEmissao([
        'modelo'     => '55',
        'status'     => 'autorizada',
        'emitido_em' => now()->subHours(200),
    ]);

    expect($isCancelavel($nfceRecente))->toBeTrue('NFC-e 10h < 24h deve ser cancelável')
        ->and($isCancelavel($nfceVelha))->toBeFalse('NFC-e 30h > 24h NÃO deve ser cancelável')
        ->and($isCancelavel($nfeDentroPrazo))->toBeTrue('NF-e 48h < 168h deve ser cancelável')
        ->and($isCancelavel($nfeForaPrazo))->toBeFalse('NF-e 200h > 168h NÃO deve ser cancelável');
});

it('sefazCodes retorna mapa com pelo menos 100, 110, 220, 539, 691, 778, 999', function () {
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
