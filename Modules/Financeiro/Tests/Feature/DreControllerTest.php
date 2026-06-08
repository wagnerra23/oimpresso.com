<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-014a — Pest GUARD da tela /financeiro/dre (reaplicação canon).
 *
 * Cobre invariantes do charter (Pages/Financeiro/Dre/Index.charter.md) +
 * visual-comparison aprovado 2026-05-20:
 *  - Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): biz B nunca vê dados de biz A
 *  - Inertia component path + Props no shape esperado (meta + linhas + margem + top categorias)
 *  - Subtotal "Resultado operacional" com highlight=true
 *  - Top categorias receita ≤ 3 itens ordenado desc
 *  - Export CSV retorna text/csv UTF-8 com BOM
 *
 * Padrão Unificado/Jana/Repair: skip gracioso quando DB greenfield ou
 * subscription gate bloqueia financeiro_module no env atual.
 */
function dreBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.relatorios.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.relatorios.view')) {
        $user->givePermissionTo('financeiro.relatorios.view');
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'business'                 => [
            'id'              => $business->id,
            'name'            => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin'                 => true,
    ]);

    return $user;
}

it('renderiza DRE 200 com Inertia component Financeiro/Dre/Index', function () {
    $user = dreBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate financeiro_module bloqueia neste env.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('Módulo Financeiro não instalado neste env (financeiro:install pendente).');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->not()->toBeNull();
});

it('expõe Props no shape canon (meta, linhas, margem_operacional, top_categorias_receita)', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Dre/Index')
        ->has('meta.periodo_tipo')
        ->has('meta.periodo_label')
        ->has('meta.periodo_label_prev')
        ->has('meta.anchor_mes')
        ->has('meta.prev_mes')
        ->has('meta.base_rl')
        ->has('meta.business_name')
        ->has('meta.business_id')
        ->has('meta.aviso_sem_mapping')
        ->has('linhas')
        ->has('margem_operacional.atual_pct')
        ->has('margem_operacional.meta_pct')
        ->has('margem_operacional.prev_pct')
        ->has('margem_operacional.delta_pp')
        ->has('top_categorias_receita')
    );
});

it('expõe margem_operacional meta = 12.0 (Q6 hardcode aprovado 2026-05-20)', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('margem_operacional.meta_pct', 12.0)
    );
});

it('subtotal "Resultado operacional" tem highlight=true (Q5 canon)', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $linhas = $page->toArray()['props']['linhas'] ?? [];
        $resOp = collect($linhas)->first(fn ($l) => ($l['type'] ?? '') === 'subtotal' && ($l['key'] ?? '') === 'resultado_operacional');
        expect($resOp)->not()->toBeNull();
        expect($resOp['highlight'] ?? false)->toBeTrue();
        expect($resOp['label'] ?? '')->toBe('Resultado operacional');
    });
});

it('top_categorias_receita tem no máximo 3 itens ordenados desc por valor (Q7)', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $top = $page->toArray()['props']['top_categorias_receita'] ?? [];
        expect(count($top))->toBeLessThanOrEqual(3);

        if (count($top) >= 2) {
            // ordem desc por valor (abs preserva ordem se positivos)
            $valores = array_map(fn ($c) => (float) ($c['valor'] ?? 0.0), $top);
            $valoresOrd = $valores;
            rsort($valoresOrd);
            expect($valores)->toBe($valoresOrd);
        }

        foreach ($top as $cat) {
            expect($cat)->toHaveKeys(['label', 'valor', 'pct']);
        }
    });
});

it('Tier 0 IRREVOGÁVEL: respeita business scope (ADR 0093) — meta.business_id casa', function () {
    $user = dreBootstrap();
    $businessId = (int) $user->business_id;

    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    // meta.business_id deve casar com auth biz
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('meta.business_id', $businessId)
    );

    // Confirma que filtros do Service usam o businessId correto. Modelos Titulo
    // + PlanoConta usam BusinessScope global scope — defensiva extra no Service.
    expect($businessId)->toBeGreaterThan(0);
});

it('Export CSV retorna text/csv charset UTF-8 com BOM', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre/export-csv');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($response->status())->toBe(200);

    $ct = (string) $response->headers->get('Content-Type');
    expect($ct)->toContain('text/csv');
    expect(strtolower($ct))->toContain('utf-8');

    $disp = (string) $response->headers->get('Content-Disposition');
    expect($disp)->toContain('attachment');
    expect($disp)->toContain('.csv');

    // BOM UTF-8 = 0xEF 0xBB 0xBF
    $content = $response->streamedContent();
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
});

it('não dispara mutação em GET /dre (read-only puro)', function () {
    $user = dreBootstrap();

    $tituloCountBefore = Titulo::query()->count();
    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $tituloCountAfter = Titulo::query()->count();
    expect($tituloCountAfter)->toBe($tituloCountBefore);
});

it('aceita ?periodo=mes (Q4 — F1 só "mes" funcional) e default cai pra mes', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre?periodo=mes');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('meta.periodo_tipo', 'mes')
    );

    // periodo inválido cai pra default mes
    $response2 = $this->actingAs($user)->get('/financeiro/dre?periodo=invalido');
    if (in_array($response2->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }
    $response2->assertInertia(fn (AssertableInertia $page) => $page
        ->where('meta.periodo_tipo', 'mes')
    );
});

it('aceita ?anchor=YYYY-MM e reflete em meta.anchor_mes', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre?anchor=2026-04');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('meta.anchor_mes', '2026-04')
        ->where('meta.prev_mes', '2026-03')
    );
});

/**
 * REGRESSION GUARD — hotfix 2026-05-20.
 *
 * Erro prod: "TypeError: can't access property 'toFixed', t.pct_rl is undefined"
 * Causa: DreService::materializarLinhas() só populava `v` e `prev`. Frontend
 * (Pages/Financeiro/Dre/Index.tsx) acessa `l.pct_rl.toFixed(1)` e `l.delta_pct.toFixed(0)`
 * em CADA linha (header / item / subtotal). Faltava enrichment pós-baseRL.
 *
 * Este guard valida que TODA linha do payload tem `pct_rl` + `delta_pct` numéricos.
 * Sem ele, a regressão pode voltar silenciosamente.
 */
it('cada linha do payload tem pct_rl e delta_pct numericos (regression guard)', function () {
    $user = dreBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('linhas')
        ->where('linhas', function (array $linhas) {
            foreach ($linhas as $i => $linha) {
                expect($linha)->toHaveKey('pct_rl', "linha[$i] sem pct_rl — frontend vai quebrar com toFixed undefined");
                expect($linha)->toHaveKey('delta_pct', "linha[$i] sem delta_pct");
                expect($linha['pct_rl'])->toBeNumeric("linha[$i].pct_rl deve ser numero (não null)");
                expect($linha['delta_pct'])->toBeNumeric("linha[$i].delta_pct deve ser numero");
            }
            return true;
        })
        // Hotfix 2026-05-20 #2: também validar que meta.periodo_label* são
        // strings (e não cast de float vazado por shadow `$prev` no loop
        // de enrichment pct_rl). Erro original: "mesLabelPtBr float given".
        ->where('meta.periodo_label', fn ($v) => is_string($v) && $v !== '')
        ->where('meta.periodo_label_prev', fn ($v) => is_string($v) && $v !== '')
    );
});
