<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Services\AlertaService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Aba Alertas da Jana (`/ia/alertas`) — CONTRATO. Fecha os UC do `Alertas.casos.md`.
 *
 * Cada `it()` CITA o UC que defende (casos-gate G-2). As asserções vêm de fora do
 * `.tsx`: charter (§Goals/§Anti-hooks), `jana-alertas.contract.json` (copy + ordem)
 * e a âncora `jana-telas-novas.jsx` §JmAlertas (colunas, régua de severidade).
 *
 * Tenant: `seededTenant()` (trait WithSeededTenant). Skip acionável se o seed não
 * rodou — ⚠️ skip sai exit 0: leia ASSERTIONS, não "0 failed" (LC-13).
 */
const ALERTAS_TSX      = 'resources/js/Pages/Jana/Alertas.tsx';
const ALERTAS_CONTRATO = 'prototipo-ui/contrato/jana-alertas.contract.json';

function alertasBootstrap(): array
{
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped("Sem user em business_id={$business->id}.");
    }

    // Mesma concessão do PainelContratoTest: o grupo /ia exige `jana.access` (e isso é
    // correto — o JanaAccessGateTest prova o 403 sem ela). Damos ao usuário de teste a
    // permissão que o usuário real tem; o gate não é afrouxado.
    try {
        Permission::findOrCreate('jana.access', 'web');
        $user->givePermissionTo('jana.access');
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível garantir a permission jana.access: '.$e->getMessage());
    }

    test()->actingAs($user);
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business'         => ['id' => $business->id, 'name' => $business->name],
    ]);

    return [$business, $user];
}

/**
 * A entry da Jana no `shell.menu` é gated pela ASSINATURA (Camada 1 — `jana_module` no
 * `package_details`, `ModuleUtil::hasThePermissionInSubscription`), não só pela permission.
 * O tenant do seed pode não ter assinatura: garantimos uma (transação, rollback no fim) —
 * é o mesmo eixo que o `JanaPlanoTierTest` escreve. Sem isto o UC-00 media o pacote, não a aba.
 */
function alertasGaranteAssinaturaJana(Business $business, User $user): void
{
    // `AdminSidebarMenu` só invoca o `modifyAdminMenu` de módulo INSTALADO — `ModuleUtil::isModuleInstalled`
    // lê `system.jana_version`, que só o InstallController grava. O CI migra do zero e nunca o roda: sem a
    // property, nenhuma entry da Jana nasce, com ou sem assinatura. Gravada na transação (rollback no fim).
    if (! \App\System::getProperty('jana_version')) {
        \App\System::addProperty('jana_version', (string) config('copiloto.module_version', '0.1'));
    }

    if (! class_exists(\Modules\Superadmin\Entities\Subscription::class)) {
        return; // sem Superadmin, `hasThePermissionInSubscription` devolve true sozinho
    }

    $sub = \Modules\Superadmin\Entities\Subscription::active_subscription($business->id);

    if (! $sub) {
        $pkg = \Modules\Superadmin\Entities\Package::query()->first()
            ?: \Modules\Superadmin\Entities\Package::query()->forceCreate([
                'name' => 'Pacote de teste (Jana)', 'description' => 'fixture', 'location_count' => 0,
                'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0, 'interval' => 'months',
                'interval_count' => 1, 'trial_days' => 0, 'price' => 0, 'custom_permissions' => [],
                'created_by' => $user->id, 'is_active' => 1,
            ]);
        $sub = \Modules\Superadmin\Entities\Subscription::query()->forceCreate([
            'business_id' => $business->id, 'package_id' => $pkg->id,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
            'package_price' => 0, 'package_details' => [], 'created_id' => $user->id, 'status' => 'approved',
        ]);
    }

    $detalhes = (array) ($sub->package_details ?? []);
    $detalhes['jana_module'] = 1;
    $sub->package_details = $detalhes;
    $sub->save();
}

/** Meta ativa + período vigente (hoje no meio) + apuração — o mínimo pra existir alerta. */
function alertasMetaComDesvio(?int $businessId, string $slug, float $alvo, float $realizado): Meta
{
    $meta = Meta::create([
        'business_id'    => $businessId,
        'slug'           => $slug,
        'nome'           => 'Meta '.$slug,
        'unidade'        => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);
    MetaPeriodo::create([
        'meta_id'      => $meta->id,
        'tipo_periodo' => 'mensal',
        'data_ini'     => Carbon::today()->subDays(15)->toDateString(),
        'data_fim'     => Carbon::today()->addDays(15)->toDateString(),
        'valor_alvo'   => $alvo,
    ]);
    MetaApuracao::create([
        'meta_id'         => $meta->id,
        'data_ref'        => Carbon::today()->toDateString(),
        'valor_realizado' => $realizado,
        'calculado_em'    => now(),
    ]);

    // `load`, não `fresh()`: `fresh()` passa pelo ScopeByBusiness e devolve null pra meta
    // de OUTRO business (é o caso do UC-02, onde só o id importa).
    return $meta->load(['periodoAtual', 'ultimaApuracao']);
}

/** Fixture em memória pro `calcular()` — sem banco, como o FarolServerSideTest. */
function alertasMetaMemoria(float $alvo, float $realizado, bool $comPeriodo = true, bool $comApuracao = true): Meta
{
    $meta = new Meta(['nome' => 'x', 'unidade' => 'R$']);
    $meta->setRelation('periodoAtual', $comPeriodo ? new MetaPeriodo([
        'data_ini' => '2026-01-01', 'data_fim' => '2026-01-31', 'valor_alvo' => $alvo,
    ]) : null);
    $meta->setRelation('ultimaApuracao', $comApuracao ? new MetaApuracao([
        'valor_realizado' => $realizado, 'data_ref' => '2026-01-16',
    ]) : null);

    return $meta;
}

// ── RUNTIME ──────────────────────────────────────────────────────────────────

it('UC-ALERTA-00: a aba Alertas existe na barra da área, na 3ª posição, e leva a /ia/alertas', function () {
    [$business, $user] = alertasBootstrap();
    alertasGaranteAssinaturaJana($business, $user);

    $resposta = $this->get('/ia/alertas')->assertStatus(200);
    $resposta->assertInertia(fn ($page) => $page->component('Jana/Alertas')->has('shell.menu'));

    $menu = $resposta->inertiaPage()['props']['shell']['menu'];
    $jana = collect($menu)->first(fn ($m) => ($m['group'] ?? null) === 'ia' || strtolower($m['label'] ?? '') === 'jana');

    expect($jana)->not->toBeNull('a entry da Jana não está no shell.menu');

    $keys = array_column($jana['ghosts'] ?? [], 'key');
    // Ordem da âncora (`JmTabs`): Painel · Conversa · Alertas · Ações · Memória (Plataforma é PR próprio).
    expect(array_slice($keys, 0, 5))->toBe(['dashboard', 'copiloto', 'alertas', 'acoes', 'memorias']);

    $ghost = collect($jana['ghosts'])->firstWhere('key', 'alertas');
    expect($ghost['label'])->toBe('Alertas')
        ->and($ghost['href'])->toBe('/ia/alertas');
});

it('UC-ALERTA-01: a lista traz o desvio que o SERVIDOR calculou, e só quem tem base entra', function () {
    [$business] = alertasBootstrap();

    // -30% com o período no meio: alvo 100 → projetado 50 → realizado 35.
    $meta = alertasMetaComDesvio($business->id, 'uc-alerta-01-'.uniqid(), 100, 35);
    // Sem apuração: NÃO vira linha (não existe alerta sem com o que comparar).
    $semBase = Meta::create([
        'business_id' => $business->id, 'slug' => 'uc-alerta-01-sem-base-'.uniqid(),
        'nome' => 'Sem base', 'unidade' => 'R$', 'tipo_agregacao' => 'soma', 'ativo' => true, 'origem' => 'manual',
    ]);

    $esperado = app(AlertaService::class)->calcular($meta);
    expect($esperado)->not->toBeNull();

    $props = $this->get('/ia/alertas')->assertStatus(200)->inertiaPage()['props'];

    expect((float) $props['corte'])->toBe((float) config('copiloto.alertas.desvio_threshold_default', 10));

    $linha = collect($props['alertas'])->firstWhere('id', $meta->id);
    expect($linha)->not->toBeNull('a meta com desvio não chegou na prop `alertas`')
        ->and($linha['projetado'])->toEqual(round($esperado['projetado'], 2))
        ->and($linha['realizado'])->toEqual(round($esperado['realizado'], 2))
        ->and($linha['desvio_pct'])->toEqual(round($esperado['desvio_pct'], 1))
        ->and($linha['severidade'])->toBe($esperado['severidade'])
        ->and($linha['dispara'])->toBeTrue()
        ->and($linha['status'])->toBe('novo')
        ->and(array_keys($linha))->toContain('meta', 'slug', 'unidade', 'data_ref');

    expect(collect($props['alertas'])->firstWhere('id', $semBase->id))->toBeNull();
});

it('UC-ALERTA-02: meta de OUTRO business nunca entra na lista (Tier 0)', function () {
    [$business] = alertasBootstrap();

    $outro = Business::where('id', '!=', $business->id)->first();
    if (! $outro) {
        test()->markTestSkipped('Só um business no seed — sem como provar o isolamento.');
    }

    $alheia = alertasMetaComDesvio($outro->id, 'uc-alerta-02-'.uniqid(), 100, 35);
    $minha  = alertasMetaComDesvio($business->id, 'uc-alerta-02-'.uniqid(), 100, 35);

    $ids = collect($this->get('/ia/alertas')->inertiaPage()['props']['alertas'])->pluck('id')->all();

    expect($ids)->toContain($minha->id)
        ->and($ids)->not->toContain($alheia->id);
});

// ── ARQUIVO (copy e ordem vivem no .tsx, não no payload) ────────────────────

it('UC-ALERTA-03: toda âncora e toda copy do contrato estão no alvo, na ordem declarada', function () {
    $c    = json_decode(file_get_contents(base_path(ALERTAS_CONTRATO)), true);
    $blob = implode("\n", array_map(fn ($f) => file_get_contents(base_path($f)), $c['alvo']));

    preg_match_all('/data-contract\s*=\s*["\']([^"\']+)["\']/u', $blob, $m);
    $seq = $m[1];

    foreach ($c['secoes'] as $s) {
        expect($seq)->toContain($s['id']);
        foreach ($s['copy'] as $copy) {
            expect($blob)->toContain($copy);
        }
    }

    // `ordem` é subsequência da sequência de âncoras no fonte.
    $pos = array_map(fn ($id) => array_search($id, $seq, true), $c['ordem']);
    expect($pos)->toBe(array_values(array_filter($pos, fn ($p) => $p !== false)));
    $sorted = $pos; sort($sorted);
    expect($pos)->toBe($sorted);
});

// ── UNIDADE — a régua de severidade ─────────────────────────────────────────

it('UC-ALERTA-04: severidade é múltiplo do corte (1× baixa · 1,5× média · 3× alta) e sem base é null', function () {
    config(['copiloto.alertas.desvio_threshold_default' => 10]);
    $svc  = app(AlertaService::class);
    $hoje = Carbon::parse('2026-01-16'); // 15 de 30 dias → projetado = alvo × 0,5

    $c = $svc->calcular(alertasMetaMemoria(100, 44), $hoje);   // −12%
    expect($c['severidade'])->toBe('baixa')->and($c['dispara'])->toBeTrue()
        ->and(round($c['desvio_pct'], 1))->toEqual(-12.0)->and($c['projetado'])->toEqual(50.0);

    expect($svc->calcular(alertasMetaMemoria(100, 42.5), $hoje)['severidade'])->toBe('media');   // −15%
    expect($svc->calcular(alertasMetaMemoria(100, 35), $hoje)['severidade'])->toBe('alta');      // −30%
    expect($svc->calcular(alertasMetaMemoria(100, 95), $hoje)['severidade'])->toBe('alta');      // +90% — superar também alerta

    $abaixo = $svc->calcular(alertasMetaMemoria(100, 46), $hoje);                                // −8%
    expect($abaixo['dispara'])->toBeFalse();

    expect($svc->calcular(alertasMetaMemoria(100, 40, comPeriodo: false), $hoje))->toBeNull()
        ->and($svc->calcular(alertasMetaMemoria(100, 40, comApuracao: false), $hoje))->toBeNull();
});
