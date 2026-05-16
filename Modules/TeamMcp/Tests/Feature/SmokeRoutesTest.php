<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smoke das rotas principais do TeamMcp + checagem de middleware auth
 * aplicado na stack canonica UltimatePOS.
 *
 * Valida que:
 *   1. GET /team-mcp/team (team-mcp.team.index) responde <500 (302/200)
 *   2. GET /team-mcp/tasks (team-mcp.tasks.index — Kanban) responde <500
 *   3. GET /team-mcp/cc-sessions (team-mcp.cc.index — KB CC sessions) <500
 *   4. GET /team-mcp/install (team-mcp.install.index) responde <500
 *   5. Acesso anonimo (sem auth) e redirecionado/blocado pelo middleware
 *      'auth' da stack canonica UltimatePOS
 *
 * Per stack UltimatePOS: ['web','SetSessionData','auth','language','timezone',
 * 'AdminSidebarMenu','CheckUserLogin'] (Modules/TeamMcp/Http/routes.php).
 *
 * NUNCA usar biz=4 (ROTA LIVRE producao) — ADR 0101. Tests assumem
 * que rotas existem (validado em ScaffoldTest) e so checam status HTTP nao-500.
 *
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 * @see memory/decisions/0081-identity-mesh-mcp-actors.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(Tests\TestCase::class);

// Guard SQLite: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu) requerem
// tables MySQL como business/users/permissions; smoke real precisa schema completo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompativel: middlewares UltimatePOS (SetSessionData, '.
            'AdminSidebarMenu, CheckUserLogin) requerem schema MySQL com tables '.
            'business/users/permissions/mcp_tokens (ADR 0101)'
        );
    }
});

// ------------------------------------------------------------------
// Smoke routes
// ------------------------------------------------------------------

it('GET /team-mcp/team (team-mcp.team.index) responde com status < 500', function () {
    // Sem auth — esperado redirect 302 pra login ou similar < 500.
    // Importante: NAO deve crashar com 500 (signal de bug Controller/middleware).
    $response = $this->get(route('team-mcp.team.index'));

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /team-mcp/tasks (Kanban admin) responde com status < 500', function () {
    $response = $this->get(route('team-mcp.tasks.index'));

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /team-mcp/cc-sessions (KB Claude Code sessions) responde < 500', function () {
    $response = $this->get(route('team-mcp.cc.index'));

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /team-mcp/install responde com status < 500', function () {
    $response = $this->get(route('team-mcp.install.index'));

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rota /team-mcp/team bloqueia acesso anonimo via middleware auth', function () {
    // Sem login — middleware 'auth' da stack canonica UltimatePOS deve
    // interceptar e redirecionar pra /login ou retornar 401/403.
    // Tokens MCP do time NUNCA podem ser servidos sem auth — Tier 0 grave.
    $response = $this->get(route('team-mcp.team.index'));

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 (auth middleware) — recebeu {$statusCode}. ".
        'Se 200, middleware auth NAO esta aplicado em /team-mcp/team — '.
        'violacao GRAVE (tokens MCP do time expostos sem auth).'
    );
});

it('rota /team-mcp/cc-sessions bloqueia acesso anonimo via middleware auth', function () {
    // CC sessions contem PII Claude Code do time — auth obrigatorio.
    $response = $this->get(route('team-mcp.cc.index'));

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 (auth middleware) — recebeu {$statusCode}. ".
        'CC sessions tem PII do time, sem auth = violacao LGPD.'
    );
});
