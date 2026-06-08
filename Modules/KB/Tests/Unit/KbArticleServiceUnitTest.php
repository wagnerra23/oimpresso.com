<?php

declare(strict_types=1);

use Modules\KB\Services\KbArticleService;

/**
 * KbArticleServiceUnitTest — Wave 18 RETRY (KB D4 boost +9).
 *
 * Smoke tests de instanciação + assinatura pública do Service. Tests
 * de query real estão em KbRagServiceMultiTenantTest (que usa kbBootstrapSchema).
 *
 * Aqui só validamos contrato canônico do Service:
 *   - Service é instanciável sem DI dep externa
 *   - Métodos públicos buildListQuery/paginate existem com assinatura correta
 *
 * Mantém Pest baseline saudável mesmo sem schema MySQL carregado.
 *
 * @see Modules\KB\Services\KbArticleService
 * @see Modules\KB\Http\Controllers\KbNodeController (consumidor)
 */

it('KbArticleService é instanciável (Service stub canônico)', function () {
    $service = new KbArticleService();
    expect($service)->toBeInstanceOf(KbArticleService::class);
});

it('KbArticleService expõe buildListQuery(Request)', function () {
    $reflection = new ReflectionClass(KbArticleService::class);
    expect($reflection->hasMethod('buildListQuery'))->toBeTrue();

    $method = $reflection->getMethod('buildListQuery');
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(1);
});

it('KbArticleService expõe paginate(Request)', function () {
    $reflection = new ReflectionClass(KbArticleService::class);
    expect($reflection->hasMethod('paginate'))->toBeTrue();

    $method = $reflection->getMethod('paginate');
    expect($method->isPublic())->toBeTrue();
});

it('KbArticleService é injectável via container Laravel', function () {
    $instance = app(KbArticleService::class);
    expect($instance)->toBeInstanceOf(KbArticleService::class);
});
