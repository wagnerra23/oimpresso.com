<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Acesso anônimo às rotas do AuditoriaController.
 *
 * Stack UltimatePOS: ['web','SetSessionData','auth','language','timezone',
 * 'AdminSidebarMenu','CheckUserLogin'] (Routes/web.php). O contrato aqui é exato: quem
 * não está logado vai para o login e não recebe dado nem altera nada. "Qualquer status
 * abaixo de 500" não provava isso (aceitava 200, 404 e até 501).
 *
 * Tenant fictício via seededTenant() (ADR 0358), nunca biz=4. Transação revertida.
 *
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

uses(Tests\TestCase::class, DatabaseTransactions::class);

// Guard SQLite: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu) requerem
// tables MySQL como business/users/permissions; smoke real precisa schema completo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: middlewares UltimatePOS (SetSessionData, AdminSidebarMenu) requerem schema MySQL com tables business/users/permissions');
    }
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log table missing — rode migrations Spatie primeiro');
    }

    $this->marcador = 'smoke-auditoria-'.uniqid();
    $this->activity = Activity::create([
        'log_name'    => 'auditoria-smoke-test',
        'description' => $this->marcador,
        'event'       => 'updated',
        'business_id' => $this->seededTenant()->id,
        'properties'  => ['old' => ['nome' => 'A'], 'attributes' => ['nome' => 'B']],
    ]);
});

it('GET /auditoria anônimo vai para o login', function () {
    $this->get(route('auditoria.index'))->assertRedirect(route('login'));
});

it('GET /auditoria/{id} anônimo vai para o login', function () {
    $this->get(route('auditoria.show', ['activityId' => $this->activity->id]))
        ->assertRedirect(route('login'));
});

it('POST /auditoria/{id}/revert anônimo vai para o login e não marca reversão', function () {
    $this->post(route('auditoria.revert', ['activityId' => $this->activity->id]), [
        'revert_reason' => 'teste de acesso anonimo ao revert',
    ])->assertRedirect(route('login'));

    expect($this->activity->fresh()->reverted_at)->toBeNull();
});

it('resposta anônima do detalhe não carrega o conteúdo da Activity', function () {
    $response = $this->get(route('auditoria.show', ['activityId' => $this->activity->id]));

    $response->assertRedirect(route('login'));
    expect($response->getContent())->not->toContain($this->marcador);
});
