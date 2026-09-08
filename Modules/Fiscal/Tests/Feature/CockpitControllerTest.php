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
        expect($url)->toBeIn($uris, "o destino `{$id}` aponta para {$url}, que não é rota registrada");
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

/**
 * UC-FCKP-10 — o certificado A1 JÁ VENCIDO entra na fila de alertas.
 *
 * `diasAteVencimento()` devolve um número NEGATIVO quando o cert venceu. Até
 * 2026-09-04 `computeAlerts()` guardava o bloco com `$dias <= 60 && $dias > 0`,
 * e o `> 0` descartava exatamente esse caso: no pior estado possível o cockpit
 * não emitia alerta nenhum. Em produção (biz=1, 2026-09-03) isso aparecia como
 * uma contradição na MESMA tela — a sidebar acusava "Certificado vencido há 28
 * dias" (GUARD US-NFE-001) e o miolo do cabeçalho dizia "0 requerem ação",
 * porque o contador soma `alerts` de nível `crit` (Cockpit.tsx `totalRej`).
 *
 * O contrato é o dos outros 5 consumidores de `diasAteVencimento()`, que
 * classificam por `$dias < 0` e põem o `0` na banda de aviso, nunca num vão:
 * CertHealthCheckCommand:187, ConfigController:61, NfeHealthCommand:213,
 * HandleInertiaRequests:384 e PaymentGatewaysController:97 (`$dias >= 0`).
 *
 * Os casos INJETAM o certificado no `$contexto` em vez de semeá-lo no banco.
 * Duas razões, e as duas importam:
 *
 *   1. sem injeção o teste passaria por VACUIDADE — num tenant sem certificado o
 *      bloco não roda e a suíte fica verde sem ter medido nada (a mesma armadilha
 *      que o docblock do UC-FCKP-08 acima descreve para `props.alerts`);
 *   2. o modelo não é persistido, então nenhum fixture é fabricado num tenant que
 *      o teste trata como real.
 *
 * Bite-test (2026-09-04): com a guarda antiga `$dias > 0`, os casos "vencido" e
 * "vence hoje" FALHAM (a fila volta sem nenhum item `shield`); os casos "vencendo
 * em 47d" e "válido por 90d" passam nas duas versões — são guardas de regressão.
 */
function fckpAlertasComCert(?\Illuminate\Support\Carbon $validoAte): array
{
    $controller = new \Modules\Fiscal\Http\Controllers\CockpitController();

    $cert = new \Modules\NfeBrasil\Models\NfeCertificado(['valido_ate' => $validoAte]);

    $m = new ReflectionMethod($controller, 'computeAlerts');
    $m->setAccessible(true);

    $alerts = $m->invoke($controller, ['cert' => $cert, 'dfeCount' => 0]);

    // Só os alertas de certificado — rejeições/DF-e do tenant são ruído aqui.
    return array_values(array_filter($alerts, fn ($a) => $a['icon'] === 'shield'));
}

it('UC-FCKP-10 · cert vencido há 28 dias vira alerta crit (antes: fila muda)', function () {
    $this->actingAs(\App\User::where('business_id', 1)->firstOrFail());

    $shield = fckpAlertasComCert(now()->subDays(28));

    expect($shield)->toHaveCount(1, 'cert vencido há 28d não gerou alerta — o vão do `$dias > 0` voltou');
    expect($shield[0]['level'])->toBe('crit');
    expect($shield[0]['title'])->toBe('Certificado A1 vencido há 28 dias');
    expect($shield[0]['goto'])->toBe('fiscal_config');
});

it('UC-FCKP-10 · cert que vence HOJE não cai no vão entre vencido e vencendo', function () {
    $this->actingAs(\App\User::where('business_id', 1)->firstOrFail());

    $shield = fckpAlertasComCert(now());

    expect($shield)->toHaveCount(1, '`$dias === 0` ficou sem banda — os outros 5 consumidores põem o 0 no aviso');
    expect($shield[0]['level'])->toBe('crit');
    expect($shield[0]['title'])->toBe('Certificado A1 vence hoje');
});

it('UC-FCKP-10 · cert vencendo em 47d segue warn (guarda de regressão da banda antiga)', function () {
    $this->actingAs(\App\User::where('business_id', 1)->firstOrFail());

    $shield = fckpAlertasComCert(now()->addDays(47));

    expect($shield)->toHaveCount(1);
    expect($shield[0]['level'])->toBe('warn');
    expect($shield[0]['title'])->toBe('Certificado A1 vence em 47 dias');
});

it('UC-FCKP-10 · cert válido por 90d não polui a fila', function () {
    $this->actingAs(\App\User::where('business_id', 1)->firstOrFail());

    expect(fckpAlertasComCert(now()->addDays(90)))->toBeEmpty();
    expect(fckpAlertasComCert(null))->toBeEmpty();
});
