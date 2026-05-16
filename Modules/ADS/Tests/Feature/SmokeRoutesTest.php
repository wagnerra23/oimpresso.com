<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Smoke routes ADS — garante que rotas críticas existem e estão registradas.
 *
 * Cobre Inbox decisões + páginas read-only + Skills DB + Install (ADR 0024 / 0011 / 0143).
 * Não exercita HTTP request (smoke real exige login + biz session).
 *
 * @see Modules/ADS/Routes/web.php
 * @see memory/decisions/0024-padrao-install-modulos.md
 */

it('rota ads.admin.decisoes.index existe', function () {
    expect(Route::has('ads.admin.decisoes.index'))->toBeTrue();
});

it('rota ads.admin.decisoes.show existe (com whereNumber)', function () {
    expect(Route::has('ads.admin.decisoes.show'))->toBeTrue();
});

it('rota ads.admin.decisoes.approve existe (HITL approve POST)', function () {
    expect(Route::has('ads.admin.decisoes.approve'))->toBeTrue();
});

it('rota ads.admin.decisoes.reject existe (HITL reject POST)', function () {
    expect(Route::has('ads.admin.decisoes.reject'))->toBeTrue();
});

it('rota ads.admin.policy.index existe (read-only PolicyEngine)', function () {
    expect(Route::has('ads.admin.policy.index'))->toBeTrue();
});

it('rota ads.admin.confidence.index existe (read-only ConfidenceEngine)', function () {
    expect(Route::has('ads.admin.confidence.index'))->toBeTrue();
});

it('rota ads.admin.metricas.index existe', function () {
    expect(Route::has('ads.admin.metricas.index'))->toBeTrue();
});

it('rota ads.admin.patterns.index existe (Learning Loop L1)', function () {
    expect(Route::has('ads.admin.patterns.index'))->toBeTrue();
});

it('rotas Skills ADS existem (index/show/edit/store)', function () {
    expect(Route::has('ads.admin.skills.index'))->toBeTrue();
    expect(Route::has('ads.admin.skills.show'))->toBeTrue();
    expect(Route::has('ads.admin.skills.edit'))->toBeTrue();
    expect(Route::has('ads.admin.skills.store'))->toBeTrue();
});

it('rota ads.admin.metaskills.index existe', function () {
    expect(Route::has('ads.admin.metaskills.index'))->toBeTrue();
});

it('rota /ads/install GET existe (padrão ADR 0024)', function () {
    $routes = collect(Route::getRoutes())->filter(
        fn ($r) => $r->uri() === 'ads/install' && in_array('GET', $r->methods(), true)
    );
    expect($routes)->not->toBeEmpty();
});

it('rota /ads/install/uninstall existe (padrão ADR 0024)', function () {
    $routes = collect(Route::getRoutes())->filter(
        fn ($r) => $r->uri() === 'ads/install/uninstall'
    );
    expect($routes)->not->toBeEmpty();
});

it('rota /ads/install/update existe (padrão ADR 0024)', function () {
    $routes = collect(Route::getRoutes())->filter(
        fn ($r) => $r->uri() === 'ads/install/update'
    );
    expect($routes)->not->toBeEmpty();
});
