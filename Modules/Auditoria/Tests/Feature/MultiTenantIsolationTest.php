<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\AuditEntryService;
use Modules\Auditoria\Services\RevertService;
use Spatie\Activitylog\Models\Activity;

/**
 * Isolamento multi-tenant Tier 0 do Modules/Auditoria.
 *
 * Auditoria não tem Entity própria para a trilha: lê `activity_log` (Spatie), que não
 * usa global scope. A fronteira é o `where business_id` do AuditEntryService, então é
 * esse serviço que estes testes exercitam, e não uma query reescrita aqui.
 *
 * Cada caso cria dado nos DOIS tenants: o resultado vazio não pode vir de banco vazio.
 *
 * Tenants fictícios via seededTenant() e seededSupportClientTenant() (ADR 0358). Nunca
 * biz=1 nem biz=4. Tudo em transação revertida: no CT 100 o banco persiste entre runs.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */

uses(Tests\TestCase::class, DatabaseTransactions::class);

// Guard SQLite: activity_log com business_id + causer_kind requer schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: activity_log com business_id + causer_kind requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log table missing — rode migrations Spatie + 2021_03_16_add_business_id primeiro');
    }
    if (! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('coluna business_id ausente em activity_log — rode migration 2021_03_16_add_business_id_to_activity_log_table');
    }

    $this->biz = (int) $this->seededTenant()->id;
    $this->outroBiz = (int) $this->seededSupportClientTenant()->id;
    $this->entries = app(AuditEntryService::class);
});

function auditIsoActivity(int $biz, string $event = 'created'): Activity
{
    return Activity::create([
        'log_name'    => 'auditoria-multitenant-test',
        'description' => 'registro de teste do isolamento',
        'event'       => $event,
        'business_id' => $biz,
        'properties'  => ['old' => [], 'attributes' => ['nome' => 'X']],
    ]);
}

function auditIsoIds(LengthAwarePaginator $page): array
{
    return collect($page->items())->pluck('id')->all();
}

it('listagem de um tenant não traz Activity do outro', function () {
    $doTenant = auditIsoActivity($this->biz);
    $doOutro = auditIsoActivity($this->outroBiz);

    $ids = auditIsoIds($this->entries->list($this->outroBiz, [], 200));

    // Âncora positiva: a lista do outro tenant não está vazia por acaso.
    expect(in_array($doOutro->id, $ids, true))->toBeTrue();
    expect(in_array($doTenant->id, $ids, true))->toBeFalse();
});

it('listagem traz as Activities do próprio tenant e só dele', function () {
    $activity = auditIsoActivity($this->biz, 'updated');
    auditIsoActivity($this->outroBiz, 'updated');

    $page = $this->entries->list($this->biz, ['event' => 'updated'], 200);

    expect(in_array($activity->id, auditIsoIds($page), true))->toBeTrue();
    foreach ($page->items() as $item) {
        expect((int) $item->business_id)->toBe($this->biz);
    }
});

it('total paginado conta só o próprio tenant', function () {
    $event = 'iso-'.uniqid();
    foreach (range(1, 3) as $i) {
        auditIsoActivity($this->biz, $event);
    }
    auditIsoActivity($this->outroBiz, $event);

    // total() vem da query de count, que é outra query além da de linhas.
    expect($this->entries->list($this->biz, ['event' => $event])->total())->toBe(3);
    expect($this->entries->list($this->outroBiz, ['event' => $event])->total())->toBe(1);
});

it('canRevert nega Activity de outro tenant antes de olhar o subject', function () {
    $activity = auditIsoActivity($this->biz, 'updated');

    $deOutroTenant = new \App\User();
    $deOutroTenant->id = 999999;
    $deOutroTenant->business_id = $this->outroBiz;

    $doMesmoTenant = new \App\User();
    $doMesmoTenant->id = 999998;
    $doMesmoTenant->business_id = $this->biz;

    $service = new RevertService();
    $negado = $service->canRevert($activity, $deOutroTenant);
    $mesmoTenant = $service->canRevert($activity, $doMesmoTenant);

    expect($negado->allowed)->toBeFalse();
    expect($negado->reason)->toContain('Tier 0');
    // Contraprova: a mesma Activity no tenant certo cai em outra regra (não tem subject).
    expect($mesmoTenant->reason)->not->toContain('Tier 0');
});
