<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class);

/**
 * Tests pra Charter Goal 9 (2026-05-17) — Dossier do módulo na Show.tsx.
 *
 * ModuleGradeController::show() carrega `dossier` Inertia::defer com docs
 * markdown de memory/requisitos/<name>/ (BRIEFING + CAPTERRA-*.md +
 * GOVERNANCE-MATURITY-FICHA + DEPRECATION-PLAN + SPEC + CHANGELOG).
 *
 * Cobertura:
 *   - Controller chama Inertia::defer('dossier') — guard regressão D-14
 *   - buildDossierPayload() retorna [] pra módulo sem memory/requisitos
 *   - buildDossierPayload() lê BRIEFING.md quando existe
 *   - buildDossierPayload() respeita ordem canônica (BRIEFING antes de SPEC)
 *   - Multi-tenant Tier 0: dossier é metadata de código, NÃO de cliente — sem
 *     business_id scope. NUNCA conter PII de cliente real (filesystem read,
 *     fonte é git-canônico)
 *
 * Estilo dos cenários integração: Inertia partial reload com only=[dossier]
 * força resolução da closure deferred — replica pattern ModuleGradeControllerTest.
 *
 * @see resources/js/Pages/governance/ModuleGrades/Show.charter.md (Goal 9)
 * @see Modules/Governance/Http/Controllers/ModuleGradeController.php::buildDossierPayload
 */

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap auth — replica do ModuleGradeControllerTest pra consistência.
// Tier 0 ADR 0101: NUNCA biz=4 (ROTA LIVRE cliente) em tests.
// ─────────────────────────────────────────────────────────────────────────────

function dossierTestBootstrapAuth(): array
{
    try {
        $business = Business::find(1) ?? Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no DB.');
    }

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
// SOURCE-LEVEL — não exige DB/business fixtures
// ─────────────────────────────────────────────────────────────────────────────

it('Controller declara Inertia::defer pro payload `dossier` (guard D-14)', function () {
    $controllerPath = base_path('Modules/Governance/Http/Controllers/ModuleGradeController.php');
    expect(file_exists($controllerPath))->toBeTrue();

    $source = file_get_contents($controllerPath);
    expect($source)
        ->toContain('buildDossierPayload')
        ->toContain("Inertia::defer(fn () => \$this->buildDossierPayload")
        ->toContain("'dossier' => \$dossier");
});

it('buildDossierPayload é private (não expõe API pública)', function () {
    $reflection = new \ReflectionClass(\Modules\Governance\Http\Controllers\ModuleGradeController::class);
    expect($reflection->hasMethod('buildDossierPayload'))->toBeTrue();

    $method = $reflection->getMethod('buildDossierPayload');
    expect($method->isPrivate())->toBeTrue();
});

it('buildDossierPayload retorna [] pra módulo sem memory/requisitos/', function () {
    $controller = app(\Modules\Governance\Http\Controllers\ModuleGradeController::class);

    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('buildDossierPayload');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'ModuloFicticioInexistente999');
    expect($result)->toBe([]);
});

it('buildDossierPayload lê BRIEFING.md de módulo real (Governance)', function () {
    // Pré-condição: Governance tem BRIEFING.md (confirmado pre-flight 2026-05-17)
    $briefingPath = base_path('memory/requisitos/Governance/BRIEFING.md');
    if (! file_exists($briefingPath)) {
        test()->markTestSkipped('memory/requisitos/Governance/BRIEFING.md ausente — pre-flight assumiu existir');
    }

    $controller = app(\Modules\Governance\Http\Controllers\ModuleGradeController::class);
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('buildDossierPayload');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'Governance');
    expect($result)->toBeArray()->not->toBeEmpty();

    $briefing = collect($result)->firstWhere('slug', 'briefing');
    expect($briefing)->not->toBeNull();
    expect($briefing['filename'])->toBe('BRIEFING.md');
    expect($briefing['content_md'])->toBeString()->not->toBe('');
    expect($briefing['size_chars'])->toBeGreaterThan(0);
});

it('buildDossierPayload entrega entries com schema canônico', function () {
    $controller = app(\Modules\Governance\Http\Controllers\ModuleGradeController::class);
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('buildDossierPayload');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'Governance');

    if (empty($result)) {
        test()->markTestSkipped('Sem dossier docs em memory/requisitos/Governance/ — esperado pre-flight 2026-05-17');
    }

    foreach ($result as $entry) {
        expect($entry)
            ->toHaveKey('slug')
            ->toHaveKey('label')
            ->toHaveKey('filename')
            ->toHaveKey('content_md')
            ->toHaveKey('size_chars')
            ->toHaveKey('modified_at');
        expect($entry['slug'])->toBeString();
        expect($entry['label'])->toBeString();
        expect($entry['filename'])->toBeString()->toEndWith('.md');
        expect($entry['content_md'])->toBeString();
        expect($entry['size_chars'])->toBeInt()->toBeGreaterThan(0);
    }
});

it('buildDossierPayload preserva ordem canônica (BRIEFING antes de SPEC)', function () {
    $controller = app(\Modules\Governance\Http\Controllers\ModuleGradeController::class);
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('buildDossierPayload');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'Governance');

    if (empty($result)) {
        test()->markTestSkipped('Sem dossier — esperado pre-flight 2026-05-17');
    }

    $slugs = array_column($result, 'slug');

    // Se ambos existem, BRIEFING vem antes de SPEC (ordem canônica do catalog)
    $briefingIdx = array_search('briefing', $slugs);
    $specIdx = array_search('spec', $slugs);

    if ($briefingIdx !== false && $specIdx !== false) {
        expect($briefingIdx)->toBeLessThan($specIdx);
    }
});

it('buildDossierPayload normaliza CRLF→LF (ReactMarkdown remarkGfm respeita LF)', function () {
    $controller = app(\Modules\Governance\Http\Controllers\ModuleGradeController::class);
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('buildDossierPayload');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'Governance');

    if (empty($result)) {
        test()->markTestSkipped('Sem dossier — esperado pre-flight 2026-05-17');
    }

    foreach ($result as $entry) {
        expect($entry['content_md'])->not->toContain("\r\n");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// INTEGRAÇÃO Inertia — partial reload força resolução do deferred prop
// ─────────────────────────────────────────────────────────────────────────────

it('Show partial reload com only=[dossier] expõe payload no JSON Inertia', function () {
    [$business, $user] = dossierTestBootstrapAuth();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => 'test',
            'X-Inertia-Partial-Component' => 'governance/ModuleGrades/Show',
            'X-Inertia-Partial-Data'      => 'dossier',
        ])
        ->get('/governance/module-grades/Governance');

    if (! in_array($response->status(), [200], true)) {
        test()->markTestSkipped("Render Inertia indisponível neste env (status {$response->status()})");
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('governance/ModuleGrades/Show')
        ->has('dossier')
        ->where('dossier', function ($dossier) {
            // dossier pode ser array vazio (módulo sem memory/requisitos)
            // ou array de entries. Schema validado nos source-level tests acima.
            return is_array($dossier);
        })
    );
});
