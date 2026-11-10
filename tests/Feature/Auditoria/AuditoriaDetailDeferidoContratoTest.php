<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Carga deferida da tela Auditoria/Detail, pelo HTTP e com os headers do cliente Inertia.
 *
 * UCs: UC-AUDD-10 e UC-AUDD-11 (Detail.casos.md). Âncora: SDD-auditoria-v1.0 §6 (CU-AUD-02) e
 * RUNBOOK-auditoria-detail §3. O render do esqueleto × não encontrada está em
 * tests/js/auditoria-detail-deferido.test.tsx; aqui é o contrato do que o servidor entrega.
 *
 * Tenants fictícios 98 × 99 (ADR 0358), nunca biz=4. Transação revertida: activity_log é
 * append-only e o banco do CT 100 persiste entre runs.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: activity_log com business_id requer o schema MySQL.');
    }
    if (! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('activity_log sem business_id — rode as migrations.');
    }

    // Empresa com o módulo e usuário com a permissão de leitura: o teste mede a carga da tela,
    // não quem pode abri-la.
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);

    $this->biz = $this->seededTenant();
    $this->usuario = $this->usuarioComPermissoes(['auditoria.view'], $this->biz);
});

function audDetailActivity(int $biz): Activity
{
    return Activity::create([
        'log_name'    => 'auditoria-detail-deferido-test',
        'description' => 'contrato carga deferida',
        'event'       => 'updated',
        'business_id' => $biz,
        'properties'  => ['old' => ['status' => 'a'], 'attributes' => ['status' => 'b']],
    ]);
}

/** Primeira visita: o HTML com o `page` do Inertia. */
function audDetailVisita(object $teste, int $activityId)
{
    $teste->flushHeaders();

    return $teste->actingAs($teste->usuario)
        ->withSession([
            'user.business_id' => (int) $teste->biz->id,
            'user' => ['business_id' => (int) $teste->biz->id, 'id' => $teste->usuario->id],
        ])
        ->get('/auditoria/'.$activityId);
}

/** O pedido que o cliente Inertia faz depois do primeiro render, para buscar a prop deferida. */
function audDetailParcial(object $teste, int $activityId)
{
    $versao = (string) data_get(audDetailVisita($teste, $activityId)->viewData('page'), 'version');

    return $teste->actingAs($teste->usuario)
        ->withSession([
            'user.business_id' => (int) $teste->biz->id,
            'user' => ['business_id' => (int) $teste->biz->id, 'id' => $teste->usuario->id],
        ])
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $versao,
            'X-Inertia-Partial-Component' => 'Auditoria/Detail',
            'X-Inertia-Partial-Data' => 'activity',
        ])
        ->get('/auditoria/'.$activityId);
}

it('UC-AUDD-10 · a primeira visita não traz a prop e a declara deferida; o pedido parcial traz o registro', function () {
    $act = audDetailActivity((int) $this->biz->id);

    $inicial = audDetailVisita($this, $act->id);
    $inicial->assertStatus(200);
    $page = $inicial->viewData('page');

    expect($page['component'])->toBe('Auditoria/Detail');
    expect(array_key_exists('activity', $page['props']))->toBeFalse();
    expect(collect($page['deferredProps'] ?? [])->flatten()->all())->toContain('activity');

    $parcial = audDetailParcial($this, $act->id);
    $parcial->assertStatus(200);

    expect($parcial->json('props.activity.id'))->toBe($act->id);
    expect($parcial->json('props.activity.properties.old.status'))->toBe('a');
});

it('UC-AUDD-11 · registro inexistente responde a prop como null, não 404', function () {
    $idInexistente = ((int) Activity::query()->max('id')) + 1000;

    $parcial = audDetailParcial($this, $idInexistente);

    $parcial->assertStatus(200);
    // A chave tem de existir: sem ela o cliente veria `undefined` e ficaria no esqueleto.
    expect(array_key_exists('activity', $parcial->json('props')))->toBeTrue();
    expect($parcial->json('props.activity'))->toBeNull();
});

it('UC-AUDD-11 · registro de outra empresa responde igual a inexistente, sem dado nenhum', function () {
    $alheia = audDetailActivity((int) $this->biz->id + 1);
    $propria = audDetailActivity((int) $this->biz->id);

    // Controle: o mesmo caminho devolve o registro da própria empresa.
    expect(audDetailParcial($this, $propria->id)->json('props.activity.id'))->toBe($propria->id);

    $parcial = audDetailParcial($this, $alheia->id);

    $parcial->assertStatus(200);
    expect(array_key_exists('activity', $parcial->json('props')))->toBeTrue();
    expect($parcial->json('props.activity'))->toBeNull();
    expect($parcial->getContent())->not->toContain('contrato carga deferida');
});
