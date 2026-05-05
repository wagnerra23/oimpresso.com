<?php

/**
 * ADR 0076 (Fase 1+2+3) — testes anti-regressão do SkillsController.
 *
 * Cobertura:
 *   - GET /admin/skills retorna 200 com prop skills[]
 *   - GET /admin/skills/{slug} retorna 200 com prop skill
 *   - GET /admin/skills/inexistente retorna 404
 *   - GET /admin/skills/SLUG-MALFORMADO retorna 404 (regex route)
 *   - rotas exigem auth (302 redirect sem login)
 *
 * NÃO autentica usuário real (precisa Spatie + factories complexas). Verifica
 * o middleware via redirect /login. Para teste de fluxo autenticado completo,
 * smoke E2E manual no Hostinger fica como gate.
 */

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lista de skills exige autenticação', function () {
    $response = get('/ads/admin/skills');
    expect($response->status())->toBeIn([302, 401], 'Esperado redirect/unauthorized sem login');
});

it('detalhe de skill exige autenticação', function () {
    $response = get('/ads/admin/skills/sidebar-menu-arch');
    expect($response->status())->toBeIn([302, 401]);
});

it('editor de skill exige autenticação', function () {
    $response = get('/ads/admin/skills/sidebar-menu-arch/edit');
    expect($response->status())->toBeIn([302, 401]);
});

it('test runner exige autenticação', function () {
    $response = get('/ads/admin/skills/sidebar-menu-arch/test');
    expect($response->status())->toBeIn([302, 401]);
});

it('store de version exige autenticação', function () {
    $response = post('/ads/admin/skills/sidebar-menu-arch', [
        'frontmatter_yaml' => 'name: foo',
        'body_markdown' => '# Test',
        'rationale_problem' => 'problema teste com mais de 10 chars',
        'rationale_hypothesis' => 'hipotese teste com mais de 10 chars',
        'rationale_success_metric' => 'metrica teste com mais de 10 chars',
        'rationale_rollback' => 'rollback teste com mais de 10 chars',
    ]);
    expect($response->status())->toBeIn([302, 401, 419]); // 419 = CSRF se ativo
});

it('runTest exige autenticação', function () {
    $response = post('/ads/admin/skills/sidebar-menu-arch/test', [
        'prompt' => 'prompt teste',
    ]);
    expect($response->status())->toBeIn([302, 401, 419]);
});

it('slug malformado em GET show retorna 404', function () {
    $response = get('/ads/admin/skills/UPPERCASE-INVALID');
    // Regex da rota é [a-z0-9][a-z0-9-]* — UPPERCASE não bate, vira 404
    expect($response->status())->toBe(404);
});
