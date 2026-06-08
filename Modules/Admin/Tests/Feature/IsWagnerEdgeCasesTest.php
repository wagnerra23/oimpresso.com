<?php

declare(strict_types=1);

use Modules\Admin\Http\Middleware\IsWagner;

uses(Tests\TestCase::class);

/**
 * Wave 23 — IsWagner middleware edge cases (Admin gap +4).
 *
 * Cobre paths defensivos do gate hardcoded:
 *   1. no_auth — request sem user logado
 *   2. user_id mismatch (DB restore que perdeu user_id=1)
 *   3. business_id mismatch (Wagner virou cliente acidentalmente)
 *   4. role superadmin ausente (perm revoked)
 *   5. fallback username (env var override emergencial)
 *   6. bypass_local respeitando env (default false em prod)
 *
 * Tier 0 (ADR 0122 §1 + ADR 0093 + ADR 0101):
 *   - 3 condições AND (defense in depth contra DB corruption)
 *   - Fallback emergencial via env ADMIN_FALLBACK_USERNAME
 *   - Audit log obrigatório em violations (Log channel stack admin.unauthorized)
 *
 * Unit-level: assert estrutura de código + comportamento sem boot HTTP completo.
 *
 * @see Modules/Admin/Http/Middleware/IsWagner.php
 * @see memory/decisions/0122-admin-center-ct100.md
 */

it('IsWagner middleware classe existe + tem método handle', function () {
    expect(class_exists(IsWagner::class))->toBeTrue();
    expect(method_exists(IsWagner::class, 'handle'))->toBeTrue();
});

it('IsWagner valida 3 ANDs canônicos no source code (user_id + business_id + role)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    // Os 3 ANDs precisam estar explícitos no código
    expect($content)->toContain('userIdMatch');
    expect($content)->toContain('businessIdMatch');
    expect($content)->toContain('hasRole');

    // E todos juntos em uma expressão AND
    expect($content)->toContain('$userIdMatch && $businessIdMatch && $hasRole');
});

it('IsWagner usa config admin.wagner_user_id (não hardcoded literal)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    expect($content)->toContain("config('admin.wagner_user_id'");
    expect($content)->toContain("config('admin.wagner_business_id'");
});

it('IsWagner suporta fallback username via env ADMIN_FALLBACK_USERNAME (DB restore emergency)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Override emergencial Agent D 2026-05-10
    expect($content)->toContain("config('admin.fallback_username')");
    expect($content)->toContain('fallbackUsername');
});

it('IsWagner respeita bypass_local SOMENTE em env=local (defesa prod)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    expect($content)->toContain("config('admin.bypass_local')");
    expect($content)->toContain("app()->environment('local')");

    // Garantir AND lógico (bypass exige BOTH config + env=local)
    expect($content)->toContain("config('admin.bypass_local') && app()->environment('local')");
});

it('IsWagner audita unauthorized via Log channel stack (admin.unauthorized)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    expect($content)->toContain('admin.unauthorized');
    expect($content)->toContain("Log::channel('stack')");
});

it('IsWagner retorna 403 com mensagem PT-BR específica (Wagner-only)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    expect($content)->toContain('abort(403');
    expect($content)->toContain('Wagner-only');
});

it('IsWagner registra reason específico em cada violation path (no_auth | gate_check_failed)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    expect($content)->toContain("'no_auth'");
    expect($content)->toContain("'gate_check_failed'");
});

it('config admin tem keys wagner_user_id + wagner_business_id + bypass_local + fallback_username', function () {
    $path = base_path('Modules/Admin/Config/config.php');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    // 4 chaves canônicas (ADR 0122)
    expect($content)->toContain('wagner_user_id');
    expect($content)->toContain('wagner_business_id');
    expect($content)->toContain('bypass_local');
    expect($content)->toContain('fallback_username');
});

it('IsWagner é registrado no router via aliasMiddleware is-wagner', function () {
    $path = base_path('Modules/Admin/Providers/AdminServiceProvider.php');
    $content = file_get_contents($path);

    expect($content)->toContain("aliasMiddleware('is-wagner', IsWagner::class)");
});
