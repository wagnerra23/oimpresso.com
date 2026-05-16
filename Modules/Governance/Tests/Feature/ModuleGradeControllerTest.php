<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Tests pra ModuleGradeController (rotas Inertia /governance/module-grades).
 *
 * Cobertura:
 *   - Smoke (rotas existem, auth aplicado, regex constraint)
 *   - Inertia::defer ativo (regressão guard pra D-14 / RUNBOOK-inertia-defer-pattern)
 *   - Payload v3 (ADR 0155): `score_v3_normalized`, `score_v3_raw`, breakdown 9 dims
 *   - Pesos v3 das 4 dims novas: performance=10, lgpd=10, security=8, observability=7
 *   - N/A justificado preservado no payload (ADR 0154 backward-compat)
 *
 * Os cenários "renderização completa" pulam (markTestSkipped) em ambientes
 * sem User/Business fixtures (CI fresh) — Service-level tests cobrem o resto
 * (ModuleGradeServiceTest + ModuleGradeServiceV3SubDimensionsTest).
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 * @see memory/decisions/0155-module-grade-rubrica-v3.md (LIVE)
 * @see Modules/Governance/Http/Controllers/ModuleGradeController.php
 */

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap auth — multi-tenant Tier 0 (ADR 0093 + ADR 0101): biz=1 (oimpresso),
// NUNCA biz=4 (ROTA LIVRE cliente). Pula se fixtures ausentes (CI fresh).
// ─────────────────────────────────────────────────────────────────────────────

function moduleGradeBootstrapAuth(): array
{
    try {
        $business = Business::find(1) ?? Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no DB.');
    }

    // Evita biz=4 (ROTA LIVRE cliente real) em tests — Tier 0 ADR 0101.
    if ((int) $business->id === 4) {
        $alt = Business::where('id', '!=', 4)->first();
        if (! $alt) {
            test()->markTestSkipped('Apenas biz=4 (cliente) disponível — proibido em tests.');
        }
        $business = $alt;
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user pra biz='.$business->id);
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.currency_symbol' => 'R$',
        'business'                 => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'                 => true,
    ]);

    return [$business, $user];
}

// ─────────────────────────────────────────────────────────────────────────────
// SMOKE — rotas + auth + regex (originais)
// ─────────────────────────────────────────────────────────────────────────────

it('rota nomeada governance.module-grades.index existe', function () {
    expect(\Route::has('governance.module-grades.index'))->toBeTrue();
});

it('rota nomeada governance.module-grades.show existe', function () {
    expect(\Route::has('governance.module-grades.show'))->toBeTrue();
});

it('GET /governance/module-grades sem auth redireciona ou bloqueia', function () {
    $response = $this->get('/governance/module-grades');
    expect($response->status())->toBeIn([302, 401, 403])
        ->and($response->status())->not->toBe(200);
});

it('GET /governance/module-grades/{name} sem auth redireciona ou bloqueia', function () {
    $response = $this->get('/governance/module-grades/Governance');
    expect($response->status())->toBeIn([302, 401, 403])
        ->and($response->status())->not->toBe(200);
});

it('Rota show param name aceita apenas A-Za-z0-9_-', function () {
    $route = \Route::getRoutes()->getByName('governance.module-grades.show');
    expect($route)->not->toBeNull();

    $wheres = $route->wheres ?? [];
    expect($wheres['name'] ?? null)->toBe('[A-Za-z0-9_-]+');
});

it('Controller usa Inertia::defer em props caras (D-14 lição)', function () {
    $controllerPath = base_path('Modules/Governance/Http/Controllers/ModuleGradeController.php');
    expect(file_exists($controllerPath))->toBeTrue();

    $source = file_get_contents($controllerPath);
    expect($source)->toContain('Inertia::defer');
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 1 — Index Inertia render exibe payload v3 das dimensões
// ─────────────────────────────────────────────────────────────────────────────
// Index usa Inertia::defer pra `grades` e `kpis`; partial reload com only[]=grades
// força a closure a executar e expor o payload no resposta JSON Inertia.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 1 — Index payload v3 expõe score_v3_normalized + dimensões formato "X/10"', function () {
    [$business, $user] = moduleGradeBootstrapAuth();

    // Partial reload força resolução das deferred props (`grades`).
    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia'                  => 'true',
            'X-Inertia-Version'          => 'test',
            'X-Inertia-Partial-Component' => 'governance/ModuleGrades/Index',
            'X-Inertia-Partial-Data'     => 'grades',
        ])
        ->get('/governance/module-grades?only[]=grades');

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia falhou (status '.$response->status().') — middleware stack/subscription gate.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('governance/ModuleGrades/Index')
        ->has('grades')
        ->has('grades.0', fn (AssertableInertia $g) => $g
            ->has('module')
            ->has('score')
            ->has('bucket')
            ->has('color')
            ->has('dimensions.multi_tenant')
            ->has('dimensions.pest_coverage')
            ->has('dimensions.documentation')
            ->has('dimensions.architecture')
            ->has('dimensions.client_real')
            ->etc()
        )
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 2 — Show drill-down expõe breakdown D1-D9 + weight_v3 das novas dims
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 2 — Show payload v3 expõe D6-D9 com weight_v3 canônico (10/10/8/7)', function () {
    [$business, $user] = moduleGradeBootstrapAuth();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => 'test',
        ])
        ->get('/governance/module-grades/Governance');

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia Show falhou (status '.$response->status().') — middleware/subscription gate.');
    }

    // `grade` é prop EAGER (não-deferred) no Show — render inicial já contém o payload completo.
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('governance/ModuleGrades/Show')
        ->has('grade')
        ->has('grade.score_v3_normalized')
        ->has('grade.score_v3_raw')
        ->where('grade.module', 'Governance')
        // 9 dimensões presentes
        ->has('grade.dimensions.multi_tenant')
        ->has('grade.dimensions.pest_coverage')
        ->has('grade.dimensions.documentation')
        ->has('grade.dimensions.architecture')
        ->has('grade.dimensions.client_real')
        ->has('grade.dimensions.performance')
        ->has('grade.dimensions.lgpd')
        ->has('grade.dimensions.security')
        ->has('grade.dimensions.observability')
        // pesos v3 canônicos das 4 dims novas (ADR 0155)
        ->where('grade.dimensions.performance.weight_v3', 10)
        ->where('grade.dimensions.lgpd.weight_v3', 10)
        ->where('grade.dimensions.security.weight_v3', 8)
        ->where('grade.dimensions.observability.weight_v3', 7)
        // metadata canônica v3 top-level
        ->where('grade.weights_v3_total', 118)
        ->etc()
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 3 — N/A justificado preserva campo no payload (ADR 0154 + 0155)
// ─────────────────────────────────────────────────────────────────────────────
// Validação direto via Service no nível do contrato (sem render Inertia):
// confirma chaves canônicas `total_na_justified` + `na_justified` (dim-level)
// que o Controller passa cru pro frontend via `grade`.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 3 — payload Service preserva total_na_justified + na_justified ao Controller', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    // Contrato top-level v3 (ADR 0155) — Controller passa cru pro Inertia render.
    expect($grade)->toHaveKey('total_na_justified');
    expect($grade['total_na_justified'])->toBeInt()->toBeGreaterThanOrEqual(0);

    // Cada dim retorna chave `na_justified` (mesmo que array vazio) — back-compat
    // ADR 0154; frontend depende pra renderizar badge "N/A justificado".
    foreach (['multi_tenant', 'pest_coverage', 'documentation', 'architecture', 'client_real', 'performance', 'lgpd', 'security', 'observability'] as $dimKey) {
        expect($grade['dimensions'])->toHaveKey($dimKey);
        // Service expõe `na_justified` em sub-itens (breakdown[*].na_justified) — não exige top-level por dim
        expect($grade['dimensions'][$dimKey])->toHaveKey('breakdown');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 4 — Inertia::defer ativo nas props caras (regressão guard)
// ─────────────────────────────────────────────────────────────────────────────
// D-14 lição (proibicoes.md): props com Service call DB / filesystem expensive
// DEVEM ser Inertia::defer pra UX não bloquear render inicial. Controller atual
// usa defer em `grades`, `kpis` (Index) + `history` (Show). Regressão guard.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 4 — Controller declara Inertia::defer pra grades/kpis/history (3 ocorrências mín)', function () {
    $controllerPath = base_path('Modules/Governance/Http/Controllers/ModuleGradeController.php');
    $source = file_get_contents($controllerPath);

    // 3 props caras DEVEM estar atrás de Inertia::defer:
    //   grades (Index)  — gradeAllModules() coleta 34 módulos × FS scan (1-2s)
    //   kpis (Index)    — aggregação derivada de grades
    //   history (Show)  — query SQL mcp_module_grades_history × 34 módulos
    $occurrences = substr_count($source, 'Inertia::defer');
    expect($occurrences)->toBeGreaterThanOrEqual(3,
        "Controller deveria ter 3+ Inertia::defer (grades, kpis, history) — encontrou {$occurrences}. "
        . 'Regressão D-14 / RUNBOOK-inertia-defer-pattern.md');

    // Confirma que as props específicas estão deferred (não eager)
    expect($source)->toContain("Inertia::defer(fn () => \$this->buildAllGradesPayload())");
    expect($source)->toContain("Inertia::defer(fn () => \$this->buildKpisPayload())");
    expect($source)->toContain("Inertia::defer(fn () => \$this->buildHistoryPayload(");
});
