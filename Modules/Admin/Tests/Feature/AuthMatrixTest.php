<?php

declare(strict_types=1);

use Tests\Helpers\AdminAuthHelper;

uses(Tests\TestCase::class);

/**
 * US-ADM-009 — Matriz 6 cenários auth gate Modules/Admin (Sprint 1 dia 5).
 *
 * Middleware stack ordem: tailscale-only -> auth -> is-wagner.
 *
 * 6 cenários cobertos:
 *   1. Wagner Tailscale + role        → 200
 *   2. Wagner Tailscale SEM role      → 403 (DB corruption)
 *   3. Maiara Tailscale + role        → 403 (gate user_id mismatch)
 *   4. Wagner externo + role          → 403 (gate IP fora CIDR)
 *   5. sem auth Tailscale             → 302/403 (auth middleware)
 *   6. sem auth externo               → 403 (Tailscale primeiro, zero-cost)
 *
 * Usa REMOTE_ADDR via $this->call() — Laravel hidrata $request->ip() a partir.
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */

beforeEach(function () {
    // Resetar config admin_bypass_local pra garantir middleware ATIVO em todos cenários
    config()->set('admin.bypass_local', false);
});

it('cenario 1: Wagner Tailscale + role retorna 200', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(200);
});

it('cenario 2: Wagner Tailscale SEM role retorna 403', function () {
    $user = AdminAuthHelper::createWagnerWithoutRole();

    $response = $this->actingAs($user)
        ->call('GET', '/admin', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(403);
});

it('cenario 3: Maiara Tailscale + role retorna 403 (gate user_id)', function () {
    $user = AdminAuthHelper::createMaiaraUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin', [], [], [], [
            'REMOTE_ADDR' => '100.99.5.10',
        ]);

    expect($response->status())->toBe(403);
});

it('cenario 4: Wagner externo (IP fora CIDR) retorna 403 (gate IP)', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('GET', '/admin', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
        ]);

    expect($response->status())->toBe(403);
});

it('cenario 5: sem auth Tailscale retorna 302 ou 403', function () {
    $response = $this->call('GET', '/admin', [], [], [], [
        'REMOTE_ADDR' => '100.99.5.10',
    ]);

    // Auth middleware redireciona pra login (302) OU 403 direto dependendo do guard
    expect($response->status())->toBeIn([302, 403]);
});

it('cenario 6: sem auth externo retorna 403 (Tailscale gate primeiro)', function () {
    $response = $this->call('GET', '/admin', [], [], [], [
        'REMOTE_ADDR' => '8.8.8.8',
    ]);

    // TailscaleOnly bloqueia ANTES de auth — zero-cost IP check
    expect($response->status())->toBe(403);
});

it('bypass_local em ambiente local permite acesso direto', function () {
    config()->set('admin.bypass_local', true);
    app()->detectEnvironment(fn () => 'local');

    $response = $this->call('GET', '/admin', [], [], [], [
        'REMOTE_ADDR' => '8.8.8.8', // mesmo IP externo passa em dev local
    ]);

    expect($response->status())->toBe(200);
});
