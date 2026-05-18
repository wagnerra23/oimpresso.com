<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Mock Cowork Mode — Wagner regra 2026-05-18: "coloque em produção ele
 * por favor. na integra todo financeiro".
 *
 * Quando `config('financeiro.mock_cowork_mode')` true (default), todas as
 * rotas GET /financeiro/* retornam o HTML mock canon do bundle Cowork
 * literal, sem layout Laravel + sem Inertia.
 *
 * Cobre:
 *   - Config registrado
 *   - Trait RendersMockCowork existe + método
 *   - Todos 10 controllers usam trait + chamam tryRenderMockCowork no index
 *   - HTML mocks existem em public/cowork-preview/
 *
 * Tier 0 multi-tenant: middleware auth + Spatie permissions continuam
 * no __construct dos controllers — só usuário logado com permission acessa.
 * Mock NÃO é caminho pra cross-tenant leak.
 */

const FIN_CONTROLLERS_MOCK = __DIR__ . '/../../Http/Controllers';
const FIN_TRAIT_MOCK = __DIR__ . '/../../Http/Controllers/Concerns/RendersMockCowork.php';
const FIN_PUBLIC_PREVIEW = __DIR__ . '/../../../../public/cowork-preview';

describe('Mock Cowork Mode — config + trait + HTMLs', function () {
    it('config/financeiro.php registra mock_cowork_mode + mapping', function () {
        $cfg = require __DIR__ . '/../../../../config/financeiro.php';
        expect($cfg)->toHaveKey('mock_cowork_mode');
        expect($cfg)->toHaveKey('mock_route_map');
        expect($cfg['mock_route_map'])->toHaveKey('financeiro.unificado.index');
        expect($cfg['mock_route_map'])->toHaveKey('financeiro.boletos.index');
    });

    it('Trait RendersMockCowork existe + método tryRenderMockCowork', function () {
        expect(file_exists(FIN_TRAIT_MOCK))->toBeTrue();
        $src = file_get_contents(FIN_TRAIT_MOCK);
        expect($src)->toContain('trait RendersMockCowork');
        expect($src)->toContain('tryRenderMockCowork');
        expect($src)->toContain("config('financeiro.mock_cowork_mode')");
        expect($src)->toContain("public_path('cowork-preview/'");
    });

    it('HTMLs mock canon existem em public/cowork-preview/', function () {
        expect(file_exists(FIN_PUBLIC_PREVIEW . '/Financeiro Unificado.html'))->toBeTrue();
        expect(file_exists(FIN_PUBLIC_PREVIEW . '/Boleto e Contas Inter.html'))->toBeTrue();
        expect(file_exists(FIN_PUBLIC_PREVIEW . '/Oimpresso ERP - Chat.html'))->toBeTrue();
    });
});

describe('Mock Cowork Mode — 10 controllers Financeiro com trait + check', function () {
    $controllers = [
        'UnificadoController',
        'FluxoController',
        'DashboardController',
        'RelatoriosController',
        'BoletoController',
        'ContaPagarController',
        'ContaReceberController',
        'CategoriaController',
        'ContaBancariaController',
        'ExtratoController',
    ];

    foreach ($controllers as $controllerName) {
        it("$controllerName usa trait + chama tryRenderMockCowork", function () use ($controllerName) {
            $src = file_get_contents(FIN_CONTROLLERS_MOCK . '/' . $controllerName . '.php');
            // 1) Import do trait
            expect($src)->toContain('use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;');
            // 2) `use RendersMockCowork;` dentro da class
            expect($src)->toMatch('/\bclass\s+' . preg_quote($controllerName) . '\s+extends\s+Controller\s*\{[^}]*use\s+RendersMockCowork;/s');
            // 3) Chamada `$this->tryRenderMockCowork()` no início do método index
            expect($src)->toContain('$this->tryRenderMockCowork()');
        });
    }
});
