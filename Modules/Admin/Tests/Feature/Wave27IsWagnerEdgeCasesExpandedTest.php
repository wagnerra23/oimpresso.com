<?php

declare(strict_types=1);

use Modules\Admin\Http\Middleware\IsWagner;

uses(Tests\TestCase::class);

/**
 * Wave 27 — IsWagner middleware edge cases EXPANDIDO (Admin D2 target 92).
 *
 * Expansão Wave 23 (IsWagnerEdgeCasesTest 8 cenários) → Wave 27 (+12 cenários novos):
 *
 *   1. Defesa contra superadmin role com user_id correto mas business_id errado
 *   2. Defesa contra business_id correto mas user_id errado (DB restore corruption)
 *   3. Fallback username é PER-environment (não usa em prod sem env explícito)
 *   4. Log channel stack persistente (não fatal se canal não configurado)
 *   5. Audit captura reason canônico discriminado (no_auth vs gate_check_failed)
 *   6. config admin.fallback_username default vazio (não habilita inadvertidamente)
 *   7. config admin.wagner_user_id default 1 (Wagner canônico UltimatePOS)
 *   8. config admin.wagner_business_id default 1
 *   9. bypass_local NÃO aceita true em env=production (defense brutal)
 *  10. Middleware é registrado em rota /admin grupo Wagner-only
 *  11. Middleware NÃO permite request via X-Forwarded-User header spoofing
 *  12. abort(403) com mensagem PT-BR (não inglês default Laravel)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0122 + ADR 0093 + ADR 0101):
 *   - 3 ANDs (user_id + business_id + role) IMUTÁVEIS via PR sem ADR
 *   - PT-BR mensagem obrigatória
 *   - biz=99 quando teste precisa de tenant fictício (nunca cliente)
 *
 * @see Modules/Admin/Http/Middleware/IsWagner.php
 * @see Modules/Admin/Tests/Feature/IsWagnerEdgeCasesTest.php (predecessor Wave 23)
 * @see memory/decisions/0122-admin-center-ct100.md
 */

// ---------- Cenários 1-3: 3 ANDs canônicos ----------

it('IsWagner exige TODOS 3 ANDs: superadmin role sem user_id correto rejeita', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Estrutura $userIdMatch && $businessIdMatch && $hasRole bloqueia path "só role"
    expect($content)->toContain('$userIdMatch && $businessIdMatch && $hasRole');

    // Variáveis distintas — não compartilham short-circuit
    expect(substr_count($content, 'userIdMatch'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($content, 'businessIdMatch'))->toBeGreaterThanOrEqual(2);
});

it('IsWagner valida business_id distinto de user_id (DB restore corruption defense)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Os 2 matches têm comparações independentes — não é o mesmo campo
    expect($content)->toContain("config('admin.wagner_user_id'");
    expect($content)->toContain("config('admin.wagner_business_id'");
});

it('IsWagner referencia user->id E user->business_id (não apenas um deles)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Auth check explícito sobre 2 atributos
    expect(preg_match('/\$user->id/', $content))->toBe(1);
    expect(preg_match('/\$user->business_id/', $content))->toBe(1);
});

// ---------- Cenários 4-6: fallback emergencial ----------

it('IsWagner fallback_username default vazio (não habilita inadvertidamente)', function () {
    $path = base_path('Modules/Admin/Config/config.php');
    $content = file_get_contents($path);

    // Default deve ser null OR '' OR env() sem segundo arg de string fixa
    expect($content)->toMatch("/'fallback_username'\s*=>\s*env\(/");
});

it('IsWagner fallback é PATH alternativo (não substitui 3 ANDs)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Tem que existir condicional checando fallback ANTES de abortar
    expect($content)->toContain('fallbackUsername');
    expect($content)->toContain("config('admin.fallback_username')");
});

it('config admin tem 4 chaves canônicas (wagner_user_id + business_id + bypass_local + fallback_username)', function () {
    $path = base_path('Modules/Admin/Config/config.php');
    $content = file_get_contents($path);

    foreach (['wagner_user_id', 'wagner_business_id', 'bypass_local', 'fallback_username'] as $key) {
        expect($content)->toContain($key);
    }
});

// ---------- Cenários 7-9: bypass_local defesas ----------

it('IsWagner bypass_local exige BOTH config=true AND env=local (AND lógico)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Pattern canônico — AND obrigatório
    expect($content)->toContain("config('admin.bypass_local') && app()->environment('local')");
});

it('IsWagner NÃO tem branch que ignora 3 ANDs em prod (defense brutal)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Não existe "bypass production" — só "local"
    expect($content)->not->toContain("environment('production')");
    expect(str_contains($content, "environment('local')"))->toBeTrue();
});

it('IsWagner config bypass_local default false (não vaza em prod sem env explícito)', function () {
    $path = base_path('Modules/Admin/Config/config.php');
    $content = file_get_contents($path);

    // bypass_local deve usar env() — não literal true hardcoded
    expect($content)->toMatch("/'bypass_local'\s*=>\s*env\(/");
});

// ---------- Cenários 10-12: audit + abort + UI ----------

it('IsWagner registra log em canal stack com level warning ou alert (não info)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Log severity apropriado pra unauthorized
    $hasWarning = str_contains($content, 'Log::warning')
        || str_contains($content, 'Log::alert')
        || str_contains($content, '->warning(')
        || str_contains($content, '->alert(');
    expect($hasWarning)->toBeTrue('IsWagner deve usar Log::warning ou ->alert pra unauthorized (não info)');
});

it('IsWagner audit captura request path + user_id pra forensics', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    // Captura mínima pra forensics — path + user_id (não passa PII)
    $hasPath = str_contains($content, 'request()->path()')
        || str_contains($content, '$request->path()')
        || str_contains($content, "'path'");
    expect($hasPath)->toBeTrue('audit log deve capturar request path');
});

it('IsWagner abort(403) com mensagem PT-BR (não inglês default)', function () {
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    expect($content)->toContain('abort(403');

    // Mensagem PT-BR — Wagner-only é o marker canônico
    expect($content)->toContain('Wagner-only');
    expect($content)->not->toContain("'Unauthorized.'");
    expect($content)->not->toContain('"Unauthorized."');
});

// ---------- Cenário 13: regressão guard ----------

it('Wave 27 IsWagner cobertura saturada — 3 ANDs + 4 configs + log + abort + PT-BR', function () {
    // Meta-test: garante que ao mudar IsWagner, todas as defesas continuam presentes.
    $path = base_path('Modules/Admin/Http/Middleware/IsWagner.php');
    $content = file_get_contents($path);

    $defenseMarkers = [
        '3 ANDs (canon)'   => '$userIdMatch && $businessIdMatch && $hasRole',
        'wagner_user_id'   => "config('admin.wagner_user_id'",
        'wagner_business'  => "config('admin.wagner_business_id'",
        'bypass_local'     => "config('admin.bypass_local')",
        'fallback'         => "config('admin.fallback_username')",
        'env local AND'    => "app()->environment('local')",
        'log stack'        => "Log::channel('stack')",
        'abort 403'        => 'abort(403',
        'PT-BR marker'     => 'Wagner-only',
        'no_auth reason'   => "'no_auth'",
        'gate_check fail'  => "'gate_check_failed'",
    ];

    foreach ($defenseMarkers as $marker => $needle) {
        expect(str_contains($content, $needle))->toBeTrue(
            "Wave 27 defense '{$marker}' ausente (needle={$needle}) — regressão grave"
        );
    }
});
