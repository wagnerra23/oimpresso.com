<?php

declare(strict_types=1);

/**
 * Criar feature flag desligada (GrowthBookAdminService::createBooleanFeature + flag:create)
 * e o --ambiente dos comandos flag:* (o --env é opção global do Laravel e derrubava
 * flag:set/flag:get/flag:env-toggle).
 *
 * DB-less: o audit falha em silêncio sem a tabela (try/catch do serviço), então roda
 * na lane sqlite sem RefreshDatabase. Não toca rede: Http::fake.
 */

use App\Services\GrowthBookAdminService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=secret_admin_test_xxx');
    putenv('GROWTHBOOK_ADMIN_API_HOST=https://growthbook.test.local/api/v1');
});

afterEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');
    putenv('GROWTHBOOK_ADMIN_API_HOST=');
});

it('cria a feature booleana desligada, sem rule e com o environment ligado', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::response(['message' => 'Could not find a feature with that key'], 404),
        'growthbook.test.local/api/v1/features' => Http::response(['feature' => ['id' => 'useV2Teste']], 200),
    ]);

    $r = (new GrowthBookAdminService())->createBooleanFeature('useV2Teste', 'Tela de teste');

    expect($r['criada'])->toBeTrue();
    Http::assertSent(function ($req) {
        if ($req->method() !== 'POST' || $req->url() !== 'https://growthbook.test.local/api/v1/features') {
            return false;
        }
        $b = $req->data();

        return $b['id'] === 'useV2Teste'
            && $b['valueType'] === 'boolean'
            && $b['defaultValue'] === 'false'
            && $b['environments']['production'] === ['enabled' => true, 'rules' => []];
    });
});

it('não recria nem altera uma feature que já existe', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::response(['feature' => ['id' => 'useV2Teste', 'defaultValue' => 'true']], 200),
    ]);

    $r = (new GrowthBookAdminService())->createBooleanFeature('useV2Teste', 'Tela de teste');

    expect($r['criada'])->toBeFalse()
        ->and($r['feature']['defaultValue'])->toBe('true');
    Http::assertNotSent(fn ($req) => $req->method() === 'POST');
});

it('propaga erro do GrowthBook na criação', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::response([], 404),
        'growthbook.test.local/api/v1/features' => Http::response(['message' => 'sem permissão'], 403),
    ]);

    expect(fn () => (new GrowthBookAdminService())->createBooleanFeature('useV2Teste', 'x'))
        ->toThrow(RuntimeException::class, 'HTTP 403');
});

it('flag:create exige descrição e cria pelo comando', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::response([], 404),
        'growthbook.test.local/api/v1/features' => Http::response(['feature' => ['id' => 'useV2Teste']], 200),
    ]);

    expect(Artisan::call('flag:create', ['key' => 'useV2Teste']))->toBe(1);
    expect(Artisan::call('flag:create', ['key' => 'useV2Teste', '--descricao' => 'Tela de teste']))->toBe(0);
    Http::assertSent(fn ($req) => $req->method() === 'POST' && $req->url() === 'https://growthbook.test.local/api/v1/features');
});

it('flag:set aceita --ambiente e chega ao environment pedido', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::sequence()
            ->push(['feature' => ['id' => 'useV2Teste', 'environments' => ['staging' => ['enabled' => true, 'rules' => []]]]], 200)
            ->push(['feature' => ['id' => 'useV2Teste']], 200),
    ]);

    $rc = Artisan::call('flag:set', ['key' => 'useV2Teste', '--biz' => 1, '--enabled' => 'true', '--ambiente' => 'staging']);

    expect($rc)->toBe(0);
    Http::assertSent(fn ($req) => $req->method() === 'POST' && isset($req->data()['environments']['staging']));
});
