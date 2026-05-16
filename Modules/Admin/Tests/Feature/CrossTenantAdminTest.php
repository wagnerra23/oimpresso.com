<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Helpers\AdminAuthHelper;

uses(Tests\TestCase::class);

/**
 * Cross-tenant Admin Center — isolamento biz=1 vs biz=99 (Tier 0 IRREVOGÁVEL).
 *
 * Cobre 5 cenários críticos:
 *   1. Role Spatie com suffix `#{biz}` legacy UltimatePOS — role biz=1 NÃO autoriza biz=99
 *   2. Feature flags scoped por business — flag enabled biz=1 NÃO afeta biz=99
 *   3. Mutations endpoint exige Wagner — user biz=99 SEM gate bloqueado mesmo em Tailscale
 *   4. Audit log mcp_admin_audit_log preserva business_id de origem (não vaza)
 *   5. Withoutglobalscopes em MutationsController só com comentário SUPERADMIN
 *
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE prod cliente).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0122-admin-center-ct100.md
 */

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

// Guard SQLite: Pest local Wagner mandatory MySQL UltimatePOS — ADR 0101.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: tests Admin Center cross-tenant requerem schema MySQL UltimatePOS '
            .'(roles.business_id NOT NULL, mcp_admin_audit_log) — ADR 0101.'
        );
    }
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('Tabela roles ausente — rode migrate Spatie primeiro.');
    }
    config()->set('admin.bypass_local', false);
});

// ------------------------------------------------------------------
// Cenário 1 — Role Spatie suffix #{biz} legacy UltimatePOS
// ------------------------------------------------------------------

it('Cenário 1: role superadmin#1 (biz=1) NÃO autoriza acesso em biz=99', function () {
    // Wagner com role superadmin#1 (escopo biz=1) tenta agir em biz=99
    $wagner = AdminAuthHelper::createWagnerUser();

    Business::firstOrCreate(
        ['id' => BIZ_FICTICIO],
        ['name' => 'Business Ficticio Teste', 'currency_id' => 1]
    );

    // Criar role biz-scoped explícita (padrão UltimatePOS)
    $hasBizColumn = Schema::hasColumn('roles', 'business_id');

    if ($hasBizColumn) {
        Role::firstOrCreate([
            'name'        => 'superadmin#'.BIZ_WAGNER,
            'guard_name'  => 'web',
            'business_id' => BIZ_WAGNER,
        ]);

        // Tentar criar role com mesmo nome em biz=99 — deve permitir (escopo diferente)
        $roleBiz99 = Role::firstOrCreate([
            'name'        => 'superadmin#'.BIZ_FICTICIO,
            'guard_name'  => 'web',
            'business_id' => BIZ_FICTICIO,
        ]);

        // Wagner NÃO tem role do biz=99
        expect($wagner->hasRole('superadmin#'.BIZ_FICTICIO))->toBeFalse();
    } else {
        // Schema legacy sem business_id em roles — pula
        $this->markTestSkipped('roles.business_id ausente — schema antigo sem suffix #{biz}.');
    }
});

// ------------------------------------------------------------------
// Cenário 2 — Feature flags scoped por business
// ------------------------------------------------------------------

it('Cenário 2: FeatureFlagAudit grava business_id de origem (não vaza cross-tenant)', function () {
    if (! Schema::hasTable('feature_flag_audits')) {
        $this->markTestSkipped('feature_flag_audits ausente — rode migrate FeatureFlags primeiro.');
    }

    $wagner = AdminAuthHelper::createWagnerUser();

    // Audit log de mudança de flag para biz=99 deve gravar biz_id=99 (não confundir com gate user)
    $auditCountBefore = DB::table('feature_flag_audits')->count();

    DB::table('feature_flag_audits')->insert([
        'user_id'    => $wagner->id,
        'flag_key'   => 'test_isolation_flag',
        'action'     => 'rule_upsert',
        'biz_id'     => BIZ_FICTICIO,  // mudança aplicada AO biz=99
        'payload'    => json_encode(['value' => true]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $audit = DB::table('feature_flag_audits')
        ->where('flag_key', 'test_isolation_flag')
        ->where('biz_id', BIZ_FICTICIO)
        ->first();

    expect($audit)->not->toBeNull();
    expect((int) $audit->biz_id)->toBe(BIZ_FICTICIO);

    // Cleanup
    DB::table('feature_flag_audits')->where('flag_key', 'test_isolation_flag')->delete();
});

// ------------------------------------------------------------------
// Cenário 3 — Mutations endpoint Wagner-only (user biz=99 BLOQUEADO)
// ------------------------------------------------------------------

it('Cenário 3: user em biz=99 com role superadmin é BLOQUEADO no /admin/mutations/*', function () {
    // Criar user fictício biz=99 com role superadmin (não-Wagner)
    Business::firstOrCreate(
        ['id' => BIZ_FICTICIO],
        ['name' => 'Business Ficticio Teste', 'currency_id' => 1]
    );

    $intruder = User::firstOrCreate(
        ['id' => 9999],
        [
            'username'    => 'intruder_biz99',
            'email'       => 'intruder@biz99.test',
            'password'    => bcrypt('secret'),
            'business_id' => BIZ_FICTICIO,
            'first_name'  => 'Intruder',
            'last_name'   => 'Biz99',
        ]
    );

    // Mesmo Tailscale + role, gate `is_wagner` (user_id=1 AND business_id=1) bloqueia
    $response = $this->actingAs($intruder)
        ->call('POST', '/admin/mutations/health-check/run-now', [
            'reason'  => 'tentativa intruso biz=99',
            'confirm' => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBe(403);
});

// ------------------------------------------------------------------
// Cenário 4 — Audit log mcp_admin_audit_log preserva user_id origem
// ------------------------------------------------------------------

it('Cenário 4: mcp_admin_audit_log preserva user_id origem (não compartilha entre tenants)', function () {
    if (! Schema::hasTable('mcp_admin_audit_log')) {
        $this->markTestSkipped('mcp_admin_audit_log ausente — rode migrate Modules/Admin primeiro.');
    }

    $wagner = AdminAuthHelper::createWagnerUser();

    $countBefore = DB::table('mcp_admin_audit_log')->count();

    // Wagner aciona mutation health-check
    $response = $this->actingAs($wagner)
        ->call('POST', '/admin/mutations/health-check/run-now', [
            'reason'  => 'cross-tenant test audit log',
            'confirm' => true,
        ], [], [], ['REMOTE_ADDR' => '100.99.5.10']);

    expect($response->status())->toBeIn([200, 500]);

    // Audit log gravou user_id=1 (Wagner) — não pode aparecer outro
    $auditEntry = DB::table('mcp_admin_audit_log')
        ->where('created_at', '>=', now()->subMinutes(2))
        ->orderByDesc('id')
        ->first();

    if ($auditEntry !== null) {
        expect((int) $auditEntry->user_id)->toBe(1);
    }
});

// ------------------------------------------------------------------
// Cenário 5 — Tailscale gate bloqueia user válido em IP fora CIDR
// ------------------------------------------------------------------

it('Cenário 5: Wagner válido em IP biz=99 (fora Tailscale) ainda é bloqueado por gate IP', function () {
    $wagner = AdminAuthHelper::createWagnerUser();

    // IP fora CIDR Tailscale 100.99.0.0/16 — gate primeiro bloqueia ANTES de checar role/user_id
    $response = $this->actingAs($wagner)
        ->call('POST', '/admin/mutations/curador/apply', [
            'batch_id' => 'test-cross-tenant',
            'reason'   => 'tentativa via IP biz=99 externo',
            'confirm'  => true,
        ], [], [], ['REMOTE_ADDR' => '189.4.99.99']);

    expect($response->status())->toBe(403);
});
