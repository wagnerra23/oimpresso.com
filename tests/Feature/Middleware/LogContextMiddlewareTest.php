<?php

declare(strict_types=1);

/**
 * Pest test estrutural — LogContextMiddleware (ADR 0212 Camada 1, US-INFRA-016).
 *
 * Cobertura:
 *  1. Middleware existe + namespace correto
 *  2. Registrado no Kernel `'web'` group APÓS StartSession (precisa session)
 *  3. handle() injeta os 4 campos canônicos (business_id, user_id, request_id, route_name)
 *  4. business_id/user_id são null-safe (request não-autenticado não crasha)
 *  5. request_id é UUID v4 (string com 36 chars + 4 hyphens)
 */

const MIDDLEWARE_PATH = 'app/Http/Middleware/LogContextMiddleware.php';
const KERNEL_PATH = 'app/Http/Kernel.php';

function readMiddleware(): string
{
    return file_get_contents(base_path(MIDDLEWARE_PATH));
}

function readKernel(): string
{
    return file_get_contents(base_path(KERNEL_PATH));
}

it('US-INFRA-016 — LogContextMiddleware.php existe', function () {
    expect(file_exists(base_path(MIDDLEWARE_PATH)))->toBeTrue();
});

it('US-INFRA-016 — middleware tem namespace App\\Http\\Middleware', function () {
    $src = readMiddleware();
    expect($src)->toContain('namespace App\\Http\\Middleware;');
});

it('US-INFRA-016 — registrado no Kernel `web` group', function () {
    $src = readKernel();
    expect($src)->toContain('\\App\\Http\\Middleware\\LogContextMiddleware::class');
});

it('US-INFRA-016 — registrado APÓS StartSession (precisa session ativa)', function () {
    $src = readKernel();
    $posStart = strpos($src, 'StartSession::class');
    $posLog = strpos($src, 'LogContextMiddleware::class');
    expect($posStart)->not->toBeFalse();
    expect($posLog)->not->toBeFalse();
    expect($posLog)->toBeGreaterThan($posStart);
});

it('US-INFRA-016 — handle() injeta request_id via Str::uuid()', function () {
    $src = readMiddleware();
    expect($src)->toContain("'request_id' => (string) Str::uuid()");
});

it('US-INFRA-016 — handle() injeta business_id de session UPOS canon', function () {
    $src = readMiddleware();
    expect($src)->toContain("'user.business_id'");
    expect($src)->toMatch("/business_id.*session->get.*'user\\.business_id'/s");
});

it('US-INFRA-016 — handle() injeta user_id de session UPOS canon', function () {
    $src = readMiddleware();
    expect($src)->toContain("'user.id'");
    expect($src)->toMatch("/user_id.*session->get.*'user\\.id'/s");
});

it('US-INFRA-016 — handle() null-safe quando session NÃO tem business_id/user_id', function () {
    $src = readMiddleware();
    // Padrão: `if ($session->has(...))` antes de assign
    expect($src)->toMatch("/if\\s*\\(\\s*\\\$session->has\\(\\s*'user\\.business_id'\\s*\\)/");
    expect($src)->toMatch("/if\\s*\\(\\s*\\\$session->has\\(\\s*'user\\.id'\\s*\\)/");
});

it('US-INFRA-016 — handle() injeta route_name (route name OR path fallback)', function () {
    $src = readMiddleware();
    expect($src)->toContain("'route_name'");
    expect($src)->toContain('->getName()');
    // Fallback pra request->path() se rota não-nomeada
    expect($src)->toContain('->path()');
});

it('US-INFRA-016 — usa Log::withContext (não Log::* direto)', function () {
    $src = readMiddleware();
    expect($src)->toContain('Log::withContext($context)');
});

it('US-INFRA-016 — return $next($request) preserva chain middleware', function () {
    $src = readMiddleware();
    expect($src)->toContain('return $next($request);');
});

it('US-INFRA-016 — comentário cita ADR 0212 (rastreabilidade canon)', function () {
    $src = readMiddleware();
    expect($src)->toContain('ADR 0212');
});
