<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Modules\Fiscal\Http\Controllers\CockpitController;
use Modules\Fiscal\Services\NotasUnifiedService;
use Modules\NfeBrasil\Models\NfeEmissao;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave Cockpit Fiscal — isolation Tier 0 + KPIs scoped + alerts determinísticos.
 *
 * Espelha pattern de NfeCockpitMultiTenantTest (ADR 0093 + ADR 0101).
 *
 * @covers-us US-FISCAL-002
 * (ADR 0273/0303 — este arquivo esta na allowlist da lane que emite JUnit,
 *  entao o "verde" declarado aqui e alcancavel; ver SDD Fiscal §8.1.)
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
    // em `! auth()->check()` (app/Scopes/ScopeByBusiness.php:26) e lê a business
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

it('UC-FCKP-05 · computeKpis scope per business: biz=99 não aparece em counts de biz=1', function () {
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

it('UC-FCKP-04 · computeAlerts não usa LLM — receitas determinísticas por estado', function () {
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

/*
 * D15 (auditoria do Fiscal, 2026-11-05) — tenant fictício 98 × vizinho 99 (ADR 0358).
 * As notas usam COCKPIT_TAG na chave, então o afterEach acima as apaga.
 */
const COCKPIT_D15_BIZ = 98;
const COCKPIT_D15_VIZINHO = 99;

/** Chama os métodos protegidos do controller (os KPIs são o que vai para o cache). */
function cockpitD15Kpis(): array
{
    $controller = new CockpitController();
    $ctx = new ReflectionMethod($controller, 'buildContexto');
    $ctx->setAccessible(true);
    $kpis = new ReflectionMethod($controller, 'computeKpis');
    $kpis->setAccessible(true);

    return $kpis->invoke($controller, $ctx->invoke($controller));
}

/** O mesmo número por OUTRO caminho: SQL cru na tabela, sem scope nem Eloquent. */
function cockpitD15KpisPorSql(int $biz): array
{
    $base = fn () => DB::table('nfe_emissoes')
        ->where('business_id', $biz)
        ->whereNull('deleted_at')
        ->where('emitido_em', '>=', now()->startOfMonth());

    return [
        'emitidas'          => $base()->count(),
        'autorizadas'       => $base()->where('status', 'autorizada')->count(),
        'rejeitadas'        => $base()->whereIn('status', ['rejeitada', 'denegada'])->count(),
        'faturamentoFiscal' => (float) $base()->where('status', 'autorizada')->sum('valor_total'),
    ];
}

function cockpitD15Semear(): void
{
    $base = ['modelo' => '55', 'serie' => '959', 'cstat' => 100, 'emitido_em' => now()];
    $notas = [
        [COCKPIT_D15_BIZ, 9_590_001, 'autorizada', 100.00],
        [COCKPIT_D15_BIZ, 9_590_002, 'autorizada', 50.00],
        [COCKPIT_D15_BIZ, 9_590_003, 'rejeitada', 30.00],
        [COCKPIT_D15_VIZINHO, 9_590_004, 'autorizada', 999.00],
    ];
    foreach ($notas as [$biz, $numero, $status, $valor]) {
        NfeEmissao::withoutGlobalScopes()->create($base + [
            'business_id' => $biz, 'numero' => $numero, 'status' => $status, 'valor_total' => $valor,
            'chave_44' => str_pad($numero . COCKPIT_TAG, 44, '0', STR_PAD_RIGHT),
        ]);
    }
}

it('UC-FCKP-16 · sem empresa na sessão o cockpit recusa e não grava cache em biz:0', function () {
    $this->actingAs(test()->usuarioComPermissoes(['fiscal.access']));
    session()->forget(['user.business_id', 'business.id']);
    Cache::forget('fiscal:cockpit:kpis:biz:0');

    $status = null;
    try {
        app(CockpitController::class)->index(app(NotasUnifiedService::class));
    } catch (HttpException $e) {
        $status = $e->getStatusCode();
    }

    expect($status)->toBe(403, 'sem empresa na sessão o cockpit deveria recusar');
    expect(Cache::has('fiscal:cockpit:kpis:biz:0'))->toBeFalse('o cockpit gravou cache na chave biz:0');
});

it('UC-FCKP-16 · os totais de uma empresa batem com o SQL cru, para usuário comum e para jana.superadmin', function () {
    $usuarios = [
        'comum'           => test()->usuarioComPermissoes(['fiscal.access']),
        'jana.superadmin' => test()->usuarioComPermissoes(['fiscal.access', 'jana.superadmin']),
    ];
    $como = function ($user) {
        $this->actingAs($user);
        session(['business.id' => COCKPIT_D15_BIZ, 'user.business_id' => COCKPIT_D15_BIZ]);
    };

    $antes = [];
    foreach ($usuarios as $papel => $user) {
        $como($user);
        $antes[$papel] = cockpitD15Kpis();
    }

    cockpitD15Semear();

    foreach ($usuarios as $papel => $user) {
        $como($user);
        $kpis = cockpitD15Kpis();
        $sql = cockpitD15KpisPorSql(COCKPIT_D15_BIZ);

        // Caminho 1 × caminho 2: o controller e o SQL cru dão o mesmo número.
        expect($kpis['emitidas'])->toBe($sql['emitidas'], "emitidas divergem do SQL ({$papel})");
        expect($kpis['autorizadas'])->toBe($sql['autorizadas'], "autorizadas divergem do SQL ({$papel})");
        expect($kpis['rejeitadas'])->toBe($sql['rejeitadas'], "rejeitadas divergem do SQL ({$papel})");
        expect($kpis['faturamentoFiscal'])->toBe($sql['faturamentoFiscal'], "faturamento diverge do SQL ({$papel})");

        // Não é vácuo: as 3 notas semeadas da 98 entraram, a do vizinho (999) não.
        expect($kpis['emitidas'] - $antes[$papel]['emitidas'])->toBe(3);
        expect($kpis['autorizadas'] - $antes[$papel]['autorizadas'])->toBe(2);
        expect($kpis['rejeitadas'] - $antes[$papel]['rejeitadas'])->toBe(1);
        expect(round($kpis['faturamentoFiscal'] - $antes[$papel]['faturamentoFiscal'], 2))->toBe(150.0);
    }
});
