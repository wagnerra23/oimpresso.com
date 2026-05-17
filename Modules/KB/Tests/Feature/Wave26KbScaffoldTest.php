<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

// `uses(Tests\TestCase::class)` aplicado globalmente em tests/Pest.php pra
// Modules/KB/Tests/Feature. Não redeclarar aqui — viola check Pest 3.x.

/**
 * Wave 26 KB Scaffold — D2.b canonical pattern (ModuleGradeService rubrica).
 *
 * Smoke test do scaffold Modules/KB — Knowledge Base unificada (ADR 0150).
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas Install (ADR 0024) foram registradas
 *   4. Rotas canônicas KB browser declaradas
 *   5. Topnav declarativo existe (UltimatePOS AdminSidebarMenu)
 *   6. Permissions declaration completa
 *   7. Multi-tenant Tier 0 trait disponível
 *
 * Refs: ADR 0150 KB unificado, ADR 0024 receita criar módulo,
 *       ADR 0093 multi-tenant Tier 0 (BusinessScope/BelongsToBusinessTrait),
 *       skill criar-modulo.
 */

it('cenario 1: modulo KB aparece registrado em nWidart', function () {
    $module = Module::find('KB');
    expect($module)->not->toBeNull();
    expect($module->getName())->toBe('KB');
});

it('cenario 2: KBServiceProvider esta carregado (status enabled)', function () {
    $module = Module::find('KB');
    expect($module)->not->toBeNull();
    // Se o provider falhou no boot, app não chega aqui — basta o módulo existir e estar enabled.
    // nWidart 10.x: isStatus(bool) recebe boolean (true = enabled).
    expect($module->isStatus(true))->toBeTrue();
});

it('cenario 3: rotas Install ADR 0024 estao registradas', function () {
    // Rotas /kb/install, /kb/install/uninstall, /kb/install/update
    // existem mas não são nomeadas — checa via routes collection.
    $routes = collect(\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn ($uri) => str_starts_with($uri, 'kb/install'));

    expect($routes->contains('kb/install'))->toBeTrue();
});

it('cenario 4: rota canonica kb.index do browser KB existe', function () {
    expect(\Route::has('kb.index'))->toBeTrue();
});

it('cenario 5: topnav declarativo carrega sem erro', function () {
    $topnavPath = base_path('Modules/KB/Resources/menus/topnav.php');
    expect(file_exists($topnavPath))->toBeTrue();

    $topnav = require $topnavPath;
    expect($topnav)->toBeArray();
});

it('cenario 6: permissions.php declaration existe e nao-vazia', function () {
    $permsPath = base_path('Modules/KB/Resources/permissions.php');
    expect(file_exists($permsPath))->toBeTrue();

    $perms = require $permsPath;
    expect($perms)->toBeArray()->and($perms)->not->toBeEmpty();
});

it('cenario 7: DataController user_permissions retorna kb.view', function () {
    $controller = new \Modules\KB\Http\Controllers\DataController;
    $perms = $controller->user_permissions();

    $values = array_column($perms, 'value');
    // toContain Pest 3.x — primeiro arg é item, sem mensagem como 2º.
    expect($values)->toContain('kb.view');
});

it('cenario 8: BelongsToBusinessTrait (BusinessScope) trait existe e usavel', function () {
    // Multi-tenant Tier 0 (ADR 0093) — BusinessScope global addGlobalScope business_id
    // via trait module-local em Concerns/. Marker D1.a.
    $traitClass = \Modules\KB\Entities\Concerns\BelongsToBusinessTrait::class;
    expect(trait_exists($traitClass))->toBeTrue();

    // Confirma método boot canônico declarado
    $reflection = new \ReflectionClass($traitClass);
    expect($reflection->hasMethod('bootBelongsToBusinessTrait'))->toBeTrue();
});

it('cenario 9: Entities canonicos KB existem e usam trait BusinessScope', function () {
    $entities = [
        \Modules\KB\Entities\KbNode::class,
        \Modules\KB\Entities\KbEdge::class,
        \Modules\KB\Entities\KbCategory::class,
        \Modules\KB\Entities\KbPath::class,
        \Modules\KB\Entities\KbDecisionTree::class,
    ];

    foreach ($entities as $class) {
        expect(class_exists($class))->toBeTrue();

        // class_uses_recursive() retorna ['Modules\KB\...\TraitName' => 'Modules\KB\...\TraitName']
        // — chave = nome qualificado da trait. toHaveKey verifica presença da key.
        $traits = class_uses_recursive($class);
        expect($traits)->toHaveKey(\Modules\KB\Entities\Concerns\BelongsToBusinessTrait::class);
    }
});
