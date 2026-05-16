<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smoke das rotas principais do Modules/ProjectMgmt + checagem do
 * middleware auth aplicado na stack canônica UltimatePOS.
 *
 * Valida que:
 *   1. GET /project-mgmt/board (board.index) responde <500
 *   2. GET /project-mgmt/backlog (backlog.index) responde <500
 *   3. GET /project-mgmt/roadmap (roadmap.index) responde <500
 *   4. GET /project-mgmt/my-work (my-work.index) responde <500
 *   5. GET /project-mgmt/activity (activity.index) responde <500
 *   6. GET /project-mgmt/burndown (burndown.index) responde <500
 *   7. GET /project-mgmt/search (search) responde <500
 *   8. Acesso anônimo a /board é redirecionado/bloqueado por middleware 'auth'
 *
 * Stack UltimatePOS canônica: ['web','SetSessionData','auth','language',
 * 'timezone','AdminSidebarMenu','CheckUserLogin'] (Http/routes.php).
 *
 * NUNCA usar biz=4 (ROTA LIVRE produção) — ADR 0101. Tests assumem rotas
 * existem (validado em ScaffoldProjectMgmtTest) e só checam status HTTP
 * não-500 (crash de Controller/middleware).
 *
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 * @see memory/decisions/0100-projectmgmt-ui-redesign.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(Tests\TestCase::class);

// Guard SQLite: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu)
// requerem tables MySQL (business/users/permissions) — smoke real precisa
// schema completo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: middlewares UltimatePOS (SetSessionData, AdminSidebarMenu) requerem schema MySQL com tables business/users/permissions (ADR 0101)');
    }
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('business table missing — rode migrations UltimatePOS base primeiro');
    }
});

// ------------------------------------------------------------------
// Smoke routes — GET cada rota principal não deve crashar com 500
// ------------------------------------------------------------------

it('GET /project-mgmt/board (board.index) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.board.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /project-mgmt/backlog (backlog.index) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.backlog.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /project-mgmt/roadmap (roadmap.index) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.roadmap.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /project-mgmt/my-work (my-work.index) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.my-work.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /project-mgmt/activity (activity.index) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.activity.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /project-mgmt/burndown (burndown.index) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.burndown.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /project-mgmt/search (search) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.search', ['q' => 'smoke']));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('rota /project-mgmt/board bloqueia acesso anônimo via middleware auth', function () {
    // Sem login — middleware 'auth' da stack canônica UltimatePOS deve
    // interceptar e redirecionar pra /login ou retornar 401/403.
    $response = $this->get(route('project-mgmt.board.index'));

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 (auth middleware) — recebeu {$statusCode}. " .
        'Se 200, middleware auth NÃO está aplicado em /project-mgmt/board — violação stack canônica UltimatePOS.'
    );
});
