<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * UnificadoController — toggle Conferido (multi-tenant Tier 0).
 *
 * Extraído de MockOndaConferidoBridgeTest.php (US-FIN-053, Batch 3 — 2026-06-04).
 * O arquivo original misturava este sanity Tier 0 REAL com smoke estrutural do
 * asset Cowork-mock `public/cowork-preview/_oimpresso-bridge-conferido.js` (e do
 * `financeiro-curation.jsx`), apagados no #1214 — aqueles asserts ficaram stale
 * (file_get_contents em arquivo inexistente). A scaffolding Cowork-mock (trait
 * RendersMockCowork + CoworkDataMapper + config mock_*) foi removida junto.
 * Aqui sobra SÓ a cobertura multi-tenant dos endpoints reais, que NÃO depende
 * de nenhum asset Cowork.
 *
 * Cobre:
 *   - UnificadoController tem conferir() / unconferir()
 *   - Tier 0: business_id da session + findOrfail escopado por tenant
 *   - Routes/web.php registra POST/DELETE /unificado/{id}/conferir + names
 *   - Endpoints reais respondem com gate de auth sem login (HTTP smoke)
 *
 * Padrão: smoke estrutural (file_get_contents + toContain) — não boota app,
 * sobrevive DB greenfield; + HTTP smoke real no fim.
 *
 * NB: o path do source é `Routes/web.php` (maiúsculo, dir real do módulo). O
 * arquivo original usava `routes/web.php` (minúsculo), que quebra no CI Linux
 * case-sensitive — corrigido aqui.
 */

const FIN_CONFERIR_CONTROLLER = __DIR__ . '/../../Http/Controllers/UnificadoController.php';
const FIN_CONFERIR_ROUTES = __DIR__ . '/../../Routes/web.php';

describe('Conferido toggle — Backend Laravel (endpoints)', function () {
    it('UnificadoController tem conferir() + unconferir()', function () {
        $src = file_get_contents(FIN_CONFERIR_CONTROLLER);
        expect($src)->toContain('public function conferir(');
        expect($src)->toContain('public function unconferir(');
    });

    it('Tier 0: ambos os métodos escopam por business_id da session + findOrFail', function () {
        $src = file_get_contents(FIN_CONFERIR_CONTROLLER);
        expect($src)->toContain("session('user.business_id')");
        // Isolamento por tenant: Titulo escopado pelo business antes do findOrFail
        expect($src)->toContain("Titulo::where('business_id', \$businessId)->findOrFail(\$id)");
    });

    it('Routes/web.php registra POST/DELETE /unificado/{id}/conferir + names', function () {
        $src = file_get_contents(FIN_CONFERIR_ROUTES);
        expect($src)->toContain("Route::post('/unificado/{id}/conferir'");
        expect($src)->toContain("Route::delete('/unificado/{id}/conferir'");
        expect($src)->toContain("unificado.conferir");
        expect($src)->toContain("unificado.unconferir");
    });
});

describe('Conferido toggle — Endpoints funcionais (HTTP smoke)', function () {
    it('POST /conferir retorna gate (302/401/403/404/419) sem auth+CSRF', function () {
        $response = $this->post('/financeiro/unificado/1/conferir');
        expect($response->status())->toBeIn([302, 401, 403, 404, 419, 422]);
    });

    it('DELETE /conferir retorna gate (302/401/403/404/419) sem auth+CSRF', function () {
        $response = $this->delete('/financeiro/unificado/1/conferir');
        expect($response->status())->toBeIn([302, 401, 403, 404, 419, 422]);
    });
});
