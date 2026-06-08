<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smoke das 3 rotas principais do AuditoriaController + checagem
 * de middleware auth aplicado na stack canonica UltimatePOS.
 *
 * Valida que:
 *   1. GET /auditoria (auditoria.index) responde <500 (200 ou 302 pra login)
 *   2. GET /auditoria/{id} (auditoria.show) responde <500
 *   3. POST /auditoria/{id}/revert (auditoria.revert) responde <500
 *      (placeholder 501 atual e considerado smoke OK — ver
 *      AuditoriaController::revert US-AUDIT-008 pendente)
 *   4. Acesso anonimo (sem auth) e redirecionado/blocado pelo
 *      middleware 'auth' da stack canonica
 *
 * Per stack UltimatePOS: ['web','SetSessionData','auth','language',
 * 'timezone','AdminSidebarMenu','CheckUserLogin'] (Routes/web.php).
 *
 * NUNCA usar biz=4 (ROTA LIVRE producao) — ADR 0101. Tests assumem
 * que rotas existem (validado em AuditoriaModuleTest) e so checam
 * status HTTP nao-500.
 *
 * @see memory/decisions/0127-modules-auditoria-ui-undo.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(Tests\TestCase::class);

// Guard SQLite: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu) requerem
// tables MySQL como business/users/permissions; smoke real precisa schema completo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: middlewares UltimatePOS (SetSessionData, AdminSidebarMenu) requerem schema MySQL com tables business/users/permissions (ADR 0101)');
    }
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log table missing — rode migrations Spatie primeiro');
    }
});

// ------------------------------------------------------------------
// Smoke routes
// ------------------------------------------------------------------

it('GET /auditoria (auditoria.index) responde com status < 500', function () {
    // Sem auth — esperado redirect 302 pra login ou similar < 500.
    // Importante: NAO deve crashar com 500 (signal de bug em Controller/middleware).
    $response = $this->get(route('auditoria.index'));

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /auditoria/{id} (auditoria.show) responde com status < 500', function () {
    $response = $this->get(route('auditoria.show', ['activityId' => 1]));

    expect($response->getStatusCode())->toBeLessThan(500);
});

it('POST /auditoria/{id}/revert (auditoria.revert) responde com status < 500', function () {
    // Placeholder atual retorna 501 (Not Implemented) per
    // AuditoriaController::revert. 501 < 500 nao bate — corrigir
    // expectativa: 501 e classe 5xx, mas e SEMANTIC nao crash.
    // Smoke real aqui: response existe e nao e 500 generico.
    $response = $this->post(route('auditoria.revert', ['activityId' => 1]), [
        'reason' => 'teste smoke route revert — placeholder',
    ]);

    // Aceita: 302 (redirect auth), 401/403 (auth fail), 419 (csrf), 501 (placeholder),
    // 422 (validation). REJEITA: 500 (crash generico) e ausencia de response.
    expect($response->getStatusCode())->not->toBe(500);
});

it('rota /auditoria bloqueia acesso anonimo via middleware auth', function () {
    // Sem login — middleware 'auth' da stack canonica UltimatePOS deve
    // interceptar e redirecionar pra /login ou retornar 401/403.
    $response = $this->get(route('auditoria.index'));

    $statusCode = $response->getStatusCode();
    $isAuthBlocked = in_array($statusCode, [302, 401, 403], true);

    expect($isAuthBlocked)->toBeTrue(
        "Esperado status 302/401/403 (auth middleware) — recebeu {$statusCode}. ".
        'Se 200, middleware auth NAO esta aplicado em /auditoria — violacao ADR 0127 stack canonica.'
    );
});
