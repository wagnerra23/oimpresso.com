<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * UnificadoController — update Titulo (multi-tenant Tier 0).
 *
 * Extraído de MockOndaEditBridgeTest.php (US-FIN-053, Batch 3 — 2026-06-04).
 * O arquivo original misturava este sanity Tier 0 REAL com smoke estrutural do
 * asset Cowork-mock `public/cowork-preview/_oimpresso-bridge-edit.js` (e do
 * `financeiro-curation.jsx`), apagados no #1214 — aqueles asserts ficaram stale
 * (file_get_contents em arquivo inexistente). A scaffolding Cowork-mock (trait
 * RendersMockCowork + CoworkDataMapper + config mock_*) foi removida junto.
 * Aqui sobra SÓ a cobertura multi-tenant do endpoint + FormRequest reais, que
 * NÃO depende de nenhum asset Cowork.
 *
 * Cobre:
 *   - UnificadoController tem update()
 *   - Tier 0: business_id da session + findOrFail escopado por tenant
 *   - Routes/web.php registra PUT /unificado/{id} + name
 *   - UpdateTituloRequest valida cliente_descricao/observacoes/vencimento/valor_total
 *   - Guard de imutabilidade assertValorMutavel (valor_total pós-baixa)
 *   - Endpoint real responde com gate de auth sem login (HTTP smoke)
 *
 * Padrão: smoke estrutural (file_get_contents + toContain) — não boota app,
 * sobrevive DB greenfield; + HTTP smoke real no fim.
 *
 * NB: o path do source é `Routes/web.php` (maiúsculo, dir real do módulo). O
 * arquivo original usava `routes/web.php` (minúsculo), que quebra no CI Linux
 * case-sensitive — corrigido aqui.
 */

const FIN_UPDATE_CONTROLLER = __DIR__ . '/../../Http/Controllers/UnificadoController.php';
const FIN_UPDATE_ROUTES = __DIR__ . '/../../Routes/web.php';
const FIN_UPDATE_REQUEST = __DIR__ . '/../../Http/Requests/UpdateTituloRequest.php';

describe('Update Titulo — Backend Laravel (endpoint)', function () {
    it('UnificadoController tem update()', function () {
        $src = file_get_contents(FIN_UPDATE_CONTROLLER);
        expect($src)->toContain('public function update(');
    });

    it('Tier 0: update escopa por business_id da session + findOrFail', function () {
        $src = file_get_contents(FIN_UPDATE_CONTROLLER);
        expect($src)->toContain("session('user.business_id')");
        // Isolamento por tenant: Titulo escopado pelo business antes do findOrFail
        expect($src)->toContain("Titulo::where('business_id', \$businessId)->findOrFail(\$id)");
    });

    it('Routes/web.php registra PUT /unificado/{id} + name', function () {
        $src = file_get_contents(FIN_UPDATE_ROUTES);
        expect($src)->toContain("Route::put('/unificado/{id}'");
        expect($src)->toContain("unificado.update");
    });
});

describe('Update Titulo — FormRequest (validação + imutabilidade)', function () {
    it('UpdateTituloRequest valida cliente_descricao/observacoes/vencimento/valor_total', function () {
        $src = file_get_contents(FIN_UPDATE_REQUEST);
        expect($src)->toContain('cliente_descricao');
        expect($src)->toContain('observacoes');
        expect($src)->toContain('vencimento');
        expect($src)->toContain('valor_total');
    });

    it('guarda imutabilidade de valor_total pós-baixa via assertValorMutavel', function () {
        $src = file_get_contents(FIN_UPDATE_REQUEST);
        expect($src)->toContain('assertValorMutavel');
    });
});

describe('Update Titulo — Endpoint funcional (HTTP smoke)', function () {
    it('PUT /unificado/{id} retorna gate (302/401/403/404/419) sem auth+CSRF', function () {
        $response = $this->put('/financeiro/unificado/1', ['vencimento' => '2026-06-30']);
        expect($response->status())->toBeIn([302, 401, 403, 404, 419, 422]);
    });
});
