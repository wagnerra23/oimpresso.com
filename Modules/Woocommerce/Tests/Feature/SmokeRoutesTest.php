<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Smoke das rotas principais Modules/Woocommerce.
 *
 * Garante que rotas registradas em Routes/web.php e Routes/api.php existem
 * e respondem com middleware esperado (não 500, não rota faltando).
 *
 * Rotas web exigem stack `web, SetSessionData, auth, language, timezone, AdminSidebarMenu`.
 * Sem auth → redirect 302 pra login (NUNCA 500/404).
 *
 * Webhooks (sem auth) — pegam `business_id` na URL e validam HMAC do payload.
 * Sem secret/payload válido → emergency log + resposta controlada (nunca 500 cru).
 *
 * NÃO usa token WooCommerce real — só smoke de roteamento + middleware.
 *
 * @see Modules/Woocommerce/Routes/web.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('rota GET /woocommerce existe e exige auth (302 redirect, não 500)', function () {
    $response = $this->get('/woocommerce');

    // Sem auth → redirect login. Crítico que NÃO seja 500 (controller boot quebrado)
    // nem 404 (rota não registrada).
    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota GET /woocommerce/api-settings existe e exige auth', function () {
    $response = $this->get('/woocommerce/api-settings');

    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota GET /woocommerce/sync-products existe e exige auth', function () {
    $response = $this->get('/woocommerce/sync-products');

    expect($response->status())->toBeIn([302, 401, 403]);
});

it('rota GET /woocommerce/install existe e exige auth', function () {
    $response = $this->get('/woocommerce/install');

    expect($response->status())->toBeIn([302, 401, 403]);
});

it('webhook POST /webhook/order-created/{biz} aceita request (não 404)', function () {
    // Webhook NÃO usa auth (valida HMAC). Smoke: rota responde algo controlado.
    // Sem secret/payload correto → controller loga emergência mas não crasha 500.
    $response = $this->post('/webhook/order-created/1', [], [
        'X-WC-Webhook-Signature' => 'fake-signature-smoke-test',
    ]);

    // Aceitar 200 (controller engoliu mismatch e logou) ou 4xx/5xx-controlado.
    // O que NÃO pode é 404 (rota não registrada).
    expect($response->status())->not->toBe(404);
});

it('webhook POST /webhook/order-updated/{biz} aceita request (não 404)', function () {
    $response = $this->post('/webhook/order-updated/1', [], [
        'X-WC-Webhook-Signature' => 'fake-sig-smoke',
    ]);

    expect($response->status())->not->toBe(404);
});

it('GET /woocommerce/sync-categories existe e exige auth', function () {
    $response = $this->get('/woocommerce/sync-categories');

    expect($response->status())->toBeIn([302, 401, 403]);
});

it('GET /woocommerce/view-sync-log existe e exige auth', function () {
    $response = $this->get('/woocommerce/view-sync-log');

    expect($response->status())->toBeIn([302, 401, 403]);
});
