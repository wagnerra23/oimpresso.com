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

it('UC-FCKP-01 · GET /fiscal aborta 403 sem permission superadmin nem fiscal.access', function () {
    $user = \App\User::factory()->create(['business_id' => 1]);
    $this->actingAs($user);

    $response = $this->get('/fiscal');
    $response->assertStatus(403);
});

it('UC-FCKP-02 · GET /fiscal renderiza Inertia component Fiscal/Cockpit com props canon', function () {
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

it('UC-FCKP-03 · props.kpis tem shape canon (6 chaves obrigatorias)', function () {
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

it('UC-FCKP-04 · props.alerts é array de items deterministicos (sem campos LLM tipo thought/reasoning)', function () {
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

/**
 * UC-FCKP-08 — a fila de alertas é DESENHADA, e cada item leva a algum lugar.
 *
 * Até 2026-09-03 a prop `alerts` chegava à tela e alimentava só a contagem do
 * miolo do cabeçalho ("N requerem ação"); a lista nunca era renderizada. Ao
 * desenhá-la, dois contratos cross-language passaram a valer — e nenhum dos dois
 * dá erro quando quebra, os dois somem em silêncio:
 *
 *   1. `goto` NÃO é um caminho: é o `id` de uma sub-página do Fiscal, o mesmo
 *      vocabulário do `_lib/paginas-fiscais.tsx`. Um id fora do mapa não explode
 *      — o botão do alerta simplesmente não é desenhado.
 *   2. `icon` fala o vocabulário do protótipo (`audit`/`shield`/`receipt`), não o
 *      do Lucide. Nome fora do mapa de `_lib/icones-alerta.ts` renderiza sem ícone.
 *
 * Estes dois casos asseram o VOCABULÁRIO que o controller emite, não os alertas de
 * um tenant — de propósito. Um teste que percorresse `props.alerts` passaria por
 * vacuidade num business sem rejeição, sem certificado vencendo e sem DF-e: o
 * `foreach` não roda e a suíte fica verde sem ter medido nada.
 */
it('UC-FCKP-08 · todo `goto` de alerta é um id de navegação que a tela sabe resolver', function () {
    $php = file_get_contents(base_path('Modules/Fiscal/Http/Controllers/CockpitController.php'));
    $tsx = file_get_contents(base_path('resources/js/Pages/Fiscal/_lib/paginas-fiscais.tsx'));

    preg_match_all("/'goto'\s*=>\s*'([a-z_]+)'/", $php, $mGoto);
    preg_match_all("/id:\s*'([a-z_]+)'/", $tsx, $mIds);

    $gotos = array_unique($mGoto[1]);
    $ids = $mIds[1];

    expect($gotos)->not->toBeEmpty('computeAlerts deixou de emitir `goto` — o regex ou o contrato mudou');
    expect($ids)->toHaveCount(7, 'paginas-fiscais.tsx deve manter as 7 sub-páginas do Fiscal');

    foreach ($gotos as $goto) {
        expect($ids)->toContain($goto);
    }
});

it('UC-FCKP-08 · a url de cada destino de alerta é uma rota registrada do Fiscal', function () {
    // O runtime é o oráculo de "esta rota existe" — não a leitura do Routes/web.php.
    $tsx = file_get_contents(base_path('resources/js/Pages/Fiscal/_lib/paginas-fiscais.tsx'));
    preg_match_all("/id:\s*'([a-z_]+)'.*?url:\s*'([^']+)'/s", $tsx, $m, PREG_SET_ORDER);

    expect($m)->toHaveCount(7);

    $uris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
        ->all();

    foreach ($m as [, $id, $url]) {
        expect($uris)->toContain($url, "o destino `{$id}` aponta para {$url}, que não é rota registrada");
    }
});

it('UC-FCKP-08 · todo `icon` de alerta tem glifo no mapa da tela', function () {
    $php = file_get_contents(base_path('Modules/Fiscal/Http/Controllers/CockpitController.php'));
    $ts = file_get_contents(base_path('resources/js/Pages/Fiscal/_lib/icones-alerta.ts'));

    preg_match_all("/'icon'\s*=>\s*'([a-z_]+)'/", $php, $mIcon);
    // A união de tipos é a declaração estável do vocabulário coberto; o objeto
    // `ICONES` abaixo dela é a implementação, e o TypeScript já exige que os dois
    // concordem (`Record<IconeAlerta, LucideIcon>`).
    preg_match("/export type IconeAlerta = ([^;]+);/", $ts, $mTipo);
    preg_match_all("/'([a-z_]+)'/", $mTipo[1] ?? '', $mMap);

    $emitidos = array_unique($mIcon[1]);
    $mapeados = $mMap[1];

    expect($emitidos)->not->toBeEmpty('computeAlerts deixou de emitir `icon`');
    expect($mapeados)->toEqualCanonicalizing(['audit', 'shield', 'receipt']);

    foreach ($emitidos as $icon) {
        expect($mapeados)->toContain($icon);
    }
});
