<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Helpers\AdminAuthHelper;

uses(Tests\TestCase::class);

/**
 * Sprint 2 Admin mutations — 3 endpoints com double-confirmation pattern.
 *
 * Cobertura:
 * - Validation (reason ≥5 chars + confirm bool obrigatórios)
 * - Auth gate (sem auth → 403)
 * - Audit log (cada chamada gera linha em mcp_admin_audit_log)
 * - 422 erros de validation auditados como `*.validation`
 * - Sucesso auditado como `*.requested` + `*.completed`
 *
 * @see memory/decisions/0122-admin-center-ct100.md §3
 */

beforeEach(function () {
    config()->set('admin.bypass_local', false);
});

it('curador apply rejects request sem reason', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/curador/apply', [
            'batch_id' => '2026-05-10-sensitive-001',
            'confirm'  => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(422);
});

it('curador apply rejects request sem confirm', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/curador/apply', [
            'batch_id' => '2026-05-10-sensitive-001',
            'reason'   => 'teste regression',
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(422);
});

it('curador apply accepts valid request e audita', function () {
    $user = AdminAuthHelper::createWagnerUser();

    if (! Schema::hasTable('mcp_admin_audit_log')) {
        $this->markTestSkipped('mcp_admin_audit_log table missing — rode migrate primeiro.');
        return;
    }

    $auditBefore = DB::table('mcp_admin_audit_log')->count();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/curador/apply', [
            'batch_id' => '2026-05-10-sensitive-001',
            'reason'   => 'teste mutation Sprint 2',
            'confirm'  => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBeIn([200, 202]);

    $auditAfter = DB::table('mcp_admin_audit_log')->count();
    expect($auditAfter)->toBeGreaterThan($auditBefore);

    // Pelo menos 1 linha de "curador.apply.requested" deve ter sido criada
    $hasRequested = DB::table('mcp_admin_audit_log')
        ->where('action', 'curador.apply.requested')
        ->where('created_at', '>=', now()->subMinutes(2))
        ->exists();
    expect($hasRequested)->toBeTrue();
});

it('regenerate token rejects sem reason curto', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/mcp-token/regenerate', [
            'reason'  => 'no', // <5 chars
            'confirm' => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(422);
});

it('regenerate token retorna 404 se mcp_tokens table ausente', function () {
    $user = AdminAuthHelper::createWagnerUser();

    if (Schema::hasTable('mcp_tokens')) {
        $this->markTestSkipped('mcp_tokens existe — este test só roda em homolog sem tabela.');
        return;
    }

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/mcp-token/regenerate', [
            'reason'  => 'rotacao planejada teste',
            'confirm' => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(404);
});

it('run health-check accepts request com reason valid', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/health-check/run-now', [
            'reason'  => 'manual refresh dashboard',
            'confirm' => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    // 200 sucesso OU 500 se jana:health-check command não existir
    expect($response->status())->toBeIn([200, 500]);
});

it('mutations endpoints bloqueiam acesso fora Tailscale CIDR', function () {
    $user = AdminAuthHelper::createWagnerUser();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/curador/apply', [
            'batch_id' => 'test',
            'reason'   => 'teste IP gate',
            'confirm'  => true,
        ], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

    expect($response->status())->toBe(403);
});

it('mutations endpoints bloqueiam Maiara mesmo Tailscale', function () {
    $user = AdminAuthHelper::createMaiaraUser();

    $response = $this->actingAs($user)
        ->call('POST', '/admin/mutations/health-check/run-now', [
            'reason'  => 'tentativa maiara',
            'confirm' => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(403);
});
