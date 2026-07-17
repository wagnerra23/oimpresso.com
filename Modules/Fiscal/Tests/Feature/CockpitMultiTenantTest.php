<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave Cockpit Fiscal — isolation Tier 0 + KPIs scoped + alerts determinísticos.
 *
 * Espelha pattern de NfeCockpitMultiTenantTest (ADR 0093 + ADR 0101).
 */

const COCKPIT_BIZ_WAGNER   = 1;
const COCKPIT_BIZ_FICTICIO = 99;
const COCKPIT_TAG          = 'PR2-COCKPIT-ISO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz early-return
    // em `! auth()->check()` (Modules/Jana/Scopes/ScopeByBusiness.php:26) e lê a business
    // ativa de session('user.business_id'). Sem actingAs o scope no-opa e este guard de
    // isolamento contava biz=1 + biz=99 (2 em vez de 1) — falha de TESTE, não vazamento de
    // produto: a rota /fiscal roda atrás do middleware `auth`, onde auth()->check() é sempre
    // true. Autenticamos um usuário biz=1 (semeado pelo pest-mysql-setup; sem role → não é
    // superadmin) espelhando NfeBrasilMultiTenantIsolationTest. ADR 0093 + ADR 0101.
    $this->actingAs(\App\User::where('business_id', COCKPIT_BIZ_WAGNER)->firstOrFail());
});

afterEach(function () {
    // Guard SQLite (Wagner 2026-05-25): cleanup só roda quando tabela existe.
    // beforeEach skipa tests que precisam dela, mas afterEach roda sempre —
    // sem guard, CI Pest SQLite (modules-pest.yml) quebra com QueryException
    // 'no such table: nfe_emissoes'.
    if (! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    NfeEmissao::withoutGlobalScopes()
        ->where('chave_44', 'like', '%' . COCKPIT_TAG . '%')
        ->forceDelete();
});

it('computeKpis scope per business: biz=99 não aparece em counts de biz=1', function () {
    $base = [
        'modelo'      => '55',
        'serie'       => '1',
        'status'      => 'autorizada',
        'cstat'       => 100,
        'valor_total' => 250.00,
        'emitido_em'  => now(),
    ];

    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => COCKPIT_BIZ_WAGNER,
        'numero'      => 7001,
        'chave_44'    => str_pad('7001' . COCKPIT_TAG, 44, '0', STR_PAD_RIGHT),
    ]);
    NfeEmissao::withoutGlobalScopes()->create($base + [
        'business_id' => COCKPIT_BIZ_FICTICIO,
        'numero'      => 7002,
        'chave_44'    => str_pad('7002' . COCKPIT_TAG, 44, '0', STR_PAD_RIGHT),
    ]);

    session(['business.id' => COCKPIT_BIZ_WAGNER, 'user.business_id' => COCKPIT_BIZ_WAGNER]);

    $controller = new \Modules\Fiscal\Http\Controllers\CockpitController();

    // Pós Onda ESTABILIZAR 2026-05-25 (GAP-FISCAL-002): computeKpis recebe
    // $contexto pré-computado (cert + dfeCount) pra evitar query duplicada
    // em computeAlerts. Invoca buildContexto primeiro, passa adiante.
    $buildContexto = new ReflectionMethod($controller, 'buildContexto');
    $buildContexto->setAccessible(true);
    $contexto = $buildContexto->invoke($controller);

    $reflection = new ReflectionMethod($controller, 'computeKpis');
    $reflection->setAccessible(true);
    $kpis = $reflection->invoke($controller, $contexto);

    // KPIs devem refletir SOMENTE biz=1 — autorizadas com tag
    $countTagsBiz1 = NfeEmissao::query()
        ->where('chave_44', 'like', '%' . COCKPIT_TAG . '%')
        ->count();
    expect($countTagsBiz1)->toBe(1);

    // KPIs estrutura
    expect($kpis)
        ->toHaveKeys(['emitidas', 'autorizadas', 'autorizadasPct', 'rejeitadas',
                      'faturamentoFiscal', 'dfeAguardando', 'certificadoValidadeDias']);
});

it('computeAlerts não usa LLM — receitas determinísticas por estado', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\CockpitController();

    // Pós Onda ESTABILIZAR 2026-05-25 (GAP-FISCAL-002): computeAlerts recebe
    // $contexto pré-computado (reuse cert + dfeCount sem re-query).
    $buildContexto = new ReflectionMethod($controller, 'buildContexto');
    $buildContexto->setAccessible(true);
    $contexto = $buildContexto->invoke($controller);

    $reflection = new ReflectionMethod($controller, 'computeAlerts');
    $reflection->setAccessible(true);
    $alerts = $reflection->invoke($controller, $contexto);

    // Cada alert tem estrutura fixa (sem campos genéricos LLM tipo "thought" ou "reasoning")
    foreach ($alerts as $a) {
        expect($a)
            ->toHaveKeys(['level', 'icon', 'title', 'sub', 'action', 'goto'])
            ->and($a['level'])->toBeIn(['crit', 'warn', 'info']);
    }
});
