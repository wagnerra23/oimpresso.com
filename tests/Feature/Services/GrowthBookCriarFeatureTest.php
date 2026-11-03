<?php

declare(strict_types=1);

/**
 * Criar feature flag desligada (GrowthBookAdminService::createBooleanFeature + flag:create)
 * e o --environment dos comandos flag:* (o --env é opção global do Laravel e derrubava
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
    config(['feature-flags.growthbook.project' => 'prj_teste']);
});

afterEach(function () {
    putenv('GROWTHBOOK_ADMIN_API_TOKEN=');
    putenv('GROWTHBOOK_ADMIN_API_HOST=');
    config(['feature-flags.growthbook.project' => null]);
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
            && $b['project'] === 'prj_teste'
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

it('flag:set aceita --environment e chega ao environment pedido', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::sequence()
            ->push(['feature' => ['id' => 'useV2Teste', 'environments' => ['staging' => ['enabled' => true, 'rules' => []]]]], 200)
            ->push(['feature' => ['id' => 'useV2Teste']], 200),
    ]);

    $rc = Artisan::call('flag:set', ['key' => 'useV2Teste', '--biz' => 1, '--enabled' => 'true', '--environment' => 'staging']);

    expect($rc)->toBe(0);
    Http::assertSent(fn ($req) => $req->method() === 'POST' && isset($req->data()['environments']['staging']));
});

it('flag:set --criar cria a feature ausente e depois põe a regra biz-1', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::sequence()
            ->push(['message' => 'Could not find a feature with that key'], 404)
            ->push(['feature' => ['id' => 'useV2Teste', 'environments' => ['production' => ['enabled' => true, 'rules' => []]]]], 200)
            ->push(['feature' => ['id' => 'useV2Teste']], 200),
        'growthbook.test.local/api/v1/features' => Http::response(['feature' => ['id' => 'useV2Teste']], 200),
    ]);

    expect(Artisan::call('flag:set', ['key' => 'useV2Teste', '--biz' => 1, '--enabled' => 'true', '--criar' => true]))->toBe(1); // sem --descricao
    expect(Artisan::call('flag:set', ['key' => 'useV2Teste', '--biz' => 1, '--enabled' => 'true', '--criar' => true, '--descricao' => 'Tela de teste']))->toBe(0);

    Http::assertSent(fn ($req) => $req->method() === 'POST' && $req->url() === 'https://growthbook.test.local/api/v1/features');
    Http::assertSent(function ($req) {
        if ($req->method() !== 'POST' || ! str_ends_with($req->url(), '/features/useV2Teste')) {
            return false;
        }
        $rule = $req->data()['environments']['production']['rules'][0] ?? [];

        return ($rule['id'] ?? null) === 'biz-1' && ($rule['value'] ?? null) === 'true'
            && ($rule['condition'] ?? null) === '{"business_id":1}';
    });
});

it('sem --criar, flag:set continua recusando feature inexistente', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::response([], 404),
    ]);

    expect(Artisan::call('flag:set', ['key' => 'useV2Teste', '--biz' => 1, '--enabled' => 'true']))->toBe(1);
    Http::assertNotSent(fn ($req) => $req->method() === 'POST');
});

it('setBizRule manda o enabled atual do environment junto com as regras (sem ele o GrowthBook dá 400)', function () {
    Http::fake([
        'growthbook.test.local/api/v1/features/useV2Teste' => Http::sequence()
            ->push(['feature' => ['id' => 'useV2Teste', 'environments' => ['production' => ['enabled' => false, 'rules' => []]]]], 200)
            ->push(['feature' => ['id' => 'useV2Teste']], 200)
            ->push(['feature' => ['id' => 'useV2Teste', 'environments' => ['production' => ['rules' => [['id' => 'biz-1', 'type' => 'force', 'value' => 'true']]]]]], 200)
            ->push(['feature' => ['id' => 'useV2Teste']], 200),
    ]);

    $svc = new GrowthBookAdminService();
    $svc->setBizRule('useV2Teste', 1, true);   // environment desligado: preserva false
    $svc->removeBizRule('useV2Teste', 1);       // feature sem enabled: padrão true

    $posts = collect(Http::recorded())->map(fn ($par) => $par[0])->filter(fn ($req) => $req->method() === 'POST')->values();
    expect($posts)->toHaveCount(2);
    expect($posts[0]->data()['environments']['production']['enabled'])->toBeFalse();
    expect($posts[1]->data()['environments']['production']['enabled'])->toBeTrue();
});
