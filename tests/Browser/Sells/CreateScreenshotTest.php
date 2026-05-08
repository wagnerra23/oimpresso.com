<?php

declare(strict_types=1);

/**
 * Pest 4 Browser screenshot test — regressão visual Sells/create (ADR 0108).
 *
 * Camada Tier 2 de validação visual:
 *   - Tier 1: Pest structural (SellsCreatePageTest.php) — checa código-fonte
 *   - Tier 2: Pest browser (este arquivo) — checa runtime real renderizado
 *   - Tier 3 (manual): Wagner abre tela em prod via Chrome MCP
 *
 * Fluxo:
 *   1. ./vendor/bin/pest tests/Browser/Sells/
 *   2. 1ª execução: gera baseline em tests/Browser/Screenshots/__snapshots__/
 *   3. Próximas: compara contra baseline, falha se diff > threshold
 *
 * Mudança intencional após refator visual aprovado:
 *   ./vendor/bin/pest tests/Browser/Sells/ --update-snapshots
 *   git add tests/Browser/Screenshots/ && git commit -m "test(visual): update baseline Sells/create"
 *
 * Pré-requisitos local:
 *   composer install
 *   npm install
 *   npx playwright install chromium
 */

use App\Business;
use App\User;

// Mock de tempo pra evitar flakiness com defaultDatetime mudando a cada dia.
beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-05-08 12:00:00');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('Sells/Create renderiza com layout esperado (baseline)', function () {
    // Login fake biz=1 — usuário com permissão sell.create
    $user = User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('sell.create');

    // Habilita feature flag pra esta tela retornar Inertia (não Blade legacy)
    config(['feature_flags.useV2SellsCreate' => true]);

    $page = visit('/sells/create');

    // Aguarda elementos críticos renderizarem antes do screenshot
    $page->wait('text=Adicionar venda', timeout: 5000);

    // Captura + compara contra baseline (1ª vez gera, próximas comparam)
    $page->assertScreenshotMatches();
});

it('Sells/Create — empty state produtos tem CTA Buscar produto', function () {
    $user = User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('sell.create');
    config(['feature_flags.useV2SellsCreate' => true]);

    visit('/sells/create')
        ->wait('text=Nenhum produto adicionado', timeout: 5000)
        ->assertSee('Buscar produto')
        ->assertSee('Total venda');
})->skip(! class_exists(\Pest\Browser\Bootstrap::class), 'pest-plugin-browser not installed');

it('Sells/Create — sticky action bar com 3 KPIs visíveis', function () {
    $user = User::factory()->create(['business_id' => 1]);
    $user->givePermissionTo('sell.create');
    config(['feature_flags.useV2SellsCreate' => true]);

    visit('/sells/create')
        ->wait('text=Adicionar venda', timeout: 5000)
        ->assertSee('Itens')
        ->assertSee('Total venda')
        ->assertSee('Pago');
})->skip(! class_exists(\Pest\Browser\Bootstrap::class), 'pest-plugin-browser not installed');
