<?php

/**
 * Modulo: Writebot — smoke test de install + sanity check de namespace.
 *
 * Estado conhecido (preference_modulos_prioridade + ADR 0024):
 *   - Modules/Writebot/Http/routes.php aponta pro namespace Modules\Boleto
 *     (copy-paste error legado). Apos ADR 0024, InstallController foi
 *     refatorado pra extends BaseModuleInstallController. Migration namespace
 *     do route file ainda nao foi corrigido — bug conhecido.
 *
 * Esses testes documentam o estado e travam regressao do pattern install:
 *   - InstallController existe na namespace correta (Modules\Writebot\...)
 *   - Estende BaseModuleInstallController (ADR 0024)
 *   - moduleName() retorna 'Writebot' (NAO 'Boleto')
 *   - moduleSystemKey() retorna 'writebot_version' (NAO 'boleto_version')
 *
 * Sem rotas web ativas no namespace Writebot — quando corrigirem o routes.php,
 * adicionar tests de smoke de UI aqui.
 */

it('InstallController do Writebot vive em namespace Modules\\Writebot', function () {
    $class = \Modules\Writebot\Http\Controllers\InstallController::class;
    expect(class_exists($class))->toBeTrue();

    $reflection = new ReflectionClass($class);
    expect($reflection->getNamespaceName())->toBe('Modules\\Writebot\\Http\\Controllers');
});

it('Writebot InstallController estende BaseModuleInstallController (ADR 0024)', function () {
    $class = \Modules\Writebot\Http\Controllers\InstallController::class;
    expect(is_subclass_of($class, \App\Http\Controllers\BaseModuleInstallController::class))->toBeTrue();
});

it('Writebot moduleName() retorna "Writebot" — sem leakage do bug Modules\\Boleto', function () {
    $reflection = new ReflectionClass(\Modules\Writebot\Http\Controllers\InstallController::class);
    $method = $reflection->getMethod('moduleName');
    $method->setAccessible(true);
    $instance = $reflection->newInstanceWithoutConstructor();

    expect($method->invoke($instance))->toBe('Writebot');
});

it('Writebot moduleSystemKey() identifica system property writebot_version', function () {
    $reflection = new ReflectionClass(\Modules\Writebot\Http\Controllers\InstallController::class);
    $method = $reflection->getMethod('moduleSystemKey');
    $method->setAccessible(true);
    $instance = $reflection->newInstanceWithoutConstructor();

    $key = $method->invoke($instance);
    expect($key)->toBeString();
    expect(strtolower($key))->toContain('writebot');
    expect(strtolower($key))->not->toContain('boleto');
});

it('rota POST/GET /writebot/install nao existe (routes.php ainda no namespace Boleto)', function () {
    // Documenta o bug: ate hoje as rotas vivem em /boleto/* via copy-paste.
    // Se algum dia /writebot/install passar a existir, este teste FALHA — sinal pra:
    //   1. corrigir Modules/Writebot/Http/routes.php pro namespace Writebot
    //   2. atualizar este teste pra esperar status superadmin-only
    $r = $this->get('/writebot/install');
    expect($r->getStatusCode())->toBe(404);
});
