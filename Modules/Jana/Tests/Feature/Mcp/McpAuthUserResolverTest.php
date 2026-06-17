<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * PR-7c GUARD (ADR 0283/0081) — o McpAuthMiddleware TEM que setar o user no auth
 * MANAGER, não só no resolver do request HTTP.
 *
 * Por quê: laravel/mcp `Laravel\Mcp\Request::user()` resolve via
 * `Container::make('auth')->userResolver()` (→ guard()->user()), NÃO via o
 * `$request->setUserResolver()` do request HTTP. Sem `app('auth')->setUser($user)`,
 * `$request->user()` dentro das Tools é null e TODA mutação scopeada
 * (handoff-ack/submit/lever, tasks-claim, lgpd-esquecer) cai em "autenticação
 * ausente" no HTTP real.
 *
 * Bug PROVADO em prod 2026-06-17 (cert e2e contra o MCP vivo): ANTES do fix
 * HTTP 200 + "⛔ ...autenticação ausente"; DEPOIS HTTP 200 + pending criado.
 *
 * Os testes das tools stubam `app('auth')->resolveUsersUsing` (o manager), então
 * NUNCA exerceram o caminho real do middleware → o bug ficou invisível ("a suite
 * mente"). Este guard tokeniza a fonte (descarta comentários/docblocks, pra não
 * casar com a prosa) e exige que o handle() sete o manager. Ratchet anti-remoção —
 * espelha o guard A2 (Cache::flush) de HandoffToolsTest.
 *
 * @see Modules/Jana/Http/Middleware/McpAuthMiddleware.php
 */
it('McpAuthMiddleware seta o user no auth MANAGER (setUser), não só no request resolver', function () {
    $src = file_get_contents(dirname(__DIR__, 3) . '/Http/Middleware/McpAuthMiddleware.php');

    // Tokeniza e descarta comentários/docblocks — o guard mira o CÓDIGO, não a prosa.
    $codeOnly = '';
    foreach (token_get_all($src) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                continue;
            }
            $codeOnly .= $t[1];
        } else {
            $codeOnly .= $t;
        }
    }
    $codeOnly = preg_replace('/\s+/', '', (string) $codeOnly);

    // Os dois resolvers têm que coexistir: o do request (helpers HTTP/controllers)
    // E o do auth manager (que laravel/mcp Request::user() — e as Tools — leem).
    expect($codeOnly)->toContain('setUserResolver');
    expect($codeOnly)->toContain('->setUser($user)');
});
