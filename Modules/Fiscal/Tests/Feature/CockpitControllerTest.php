<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * MWART Gate compliance — CockpitController (Fiscal cockpit unificado).
 *
 * Complementa CockpitMultiTenantTest (que cobre apenas protected computeKpis
 * + computeAlerts via reflection). Este foca o entrypoint público GET /fiscal
 * — Inertia component + props shape + permission gate.
 *
 * Pattern alinhado com CockpitMultiTenantTest (ADR 0093 + ADR 0101).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil/NfseEmissao requerem schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

it('GET /fiscal aborta 403 sem permission superadmin nem fiscal.access', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $this->actingAs($user);

    $response = $this->get('/fiscal');
    $response->assertStatus(403);
});

it('GET /fiscal renderiza Inertia component Fiscal/Cockpit com props canon', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);

    session(['business.id' => 1, 'user.business_id' => 1]);

    $response = $this->get('/fiscal');
    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('Fiscal/Cockpit')
            ->has('kpis')
            ->has('sparklines')
            ->has('alerts')
    );
});

it('props.kpis tem shape canon (6 chaves obrigatorias)', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);

    session(['business.id' => 1, 'user.business_id' => 1]);

    $response = $this->get('/fiscal');
    $response->assertInertia(
        fn ($page) => $page
            ->where(
                'kpis',
                fn ($kpis) => collect(['emitidas', 'autorizadas', 'autorizadasPct', 'rejeitadas',
                    'faturamentoFiscal', 'dfeAguardando', 'certificadoValidadeDias'])
                    ->every(fn ($k) => array_key_exists($k, $kpis))
            )
    );
});

it('props.alerts é array de items deterministicos (sem campos LLM tipo thought/reasoning)', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('superadmin');
    $this->actingAs($user);

    session(['business.id' => 1, 'user.business_id' => 1]);

    $response = $this->get('/fiscal');
    $response->assertInertia(
        fn ($page) => $page->where('alerts', function ($alerts) {
            expect($alerts)->toBeArray();
            foreach ($alerts as $a) {
                expect($a)
                    ->toHaveKeys(['level', 'icon', 'title', 'sub', 'action', 'goto'])
                    ->and($a)->not->toHaveKey('thought')
                    ->and($a)->not->toHaveKey('reasoning');
            }
            return true;
        })
    );
});
