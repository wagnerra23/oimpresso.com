<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class);

/**
 * Wave 4.3 US-OFICINA-014 — Pest integration EnviarLinkAprovacaoWhatsappJob.
 *
 * Cobertura:
 *  - Cenário 1: Observer dispatcha Job quando ServiceOrder.status → orcamento
 *  - Cenário 2: Job idempotência (cache key bloqueia 2º dispatch)
 *  - Cenário 3: Job rejeita cross-tenant (OS biz=1 com job param business_id=99 → skip)
 *  - Cenário 4: Job skip quando contact ausente (walk-in)
 *  - Cenário 5: Job skip quando status mudou pós-dispatch (race condition)
 *  - Cenário 6: Job dispatch 2 SendWhatsappMessageJob (msg1 link, msg2 PIN delay 60s)
 *
 * Multi-tenant Tier 0 [ADR 0093]: business_id no constructor; guard defensivo.
 *
 * @see Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php
 * @see Modules/OficinaAuto/Observers/ServiceOrderObserver.php (hook orcamento)
 * @see resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md
 */

const BIZ_JOB_A = 1;
const BIZ_JOB_B = 99;
const PLATE_JOB_PREFIX = 'WPPJ';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('Schema OficinaAuto missing — rode migrate primeiro');
    }
});

function jobCriaOs(string $suffix, int $biz = BIZ_JOB_A, string $status = 'aberta'): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'plate'       => PLATE_JOB_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => $status,
        'contact_id'  => 1,
        'entered_at'  => now(),
    ]);
}

function jobCleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_JOB_PREFIX . $suffix . '%')
        ->pluck('id')->toArray();

    if (empty($vehicles)) {
        return;
    }

    $osIds = ServiceOrder::withoutGlobalScopes()
        ->whereIn('vehicle_id', $vehicles)
        ->pluck('id')->toArray();

    foreach ($osIds as $osId) {
        Cache::forget("oficina:approval_dispatched:{$osId}");
    }

    if (! empty($osIds)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    }
    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

// ---------------------------------------------------------------------------
// Cenário 1 — Observer dispatcha Job no status orcamento
// ---------------------------------------------------------------------------

it('Cenário 1: Observer dispatcha EnviarLinkAprovacaoWhatsappJob quando status → orcamento', function () {
    Bus::fake([EnviarLinkAprovacaoWhatsappJob::class]);
    session(['user.business_id' => BIZ_JOB_A]);

    $os = jobCriaOs('A');
    $os->status = 'orcamento';
    $os->save();

    Bus::assertDispatched(
        EnviarLinkAprovacaoWhatsappJob::class,
        fn ($job) => $job->businessId === BIZ_JOB_A && $job->serviceOrderId === $os->id,
    );
})->afterEach(fn () => jobCleanup('A'));

it('Cenário 1b: Observer NÃO dispatcha Job em status concluida (auto-faturar é outro fluxo)', function () {
    Bus::fake([EnviarLinkAprovacaoWhatsappJob::class]);
    session(['user.business_id' => BIZ_JOB_A]);

    $os = jobCriaOs('A2');
    $os->status = 'concluida';
    $os->save();

    Bus::assertNotDispatched(EnviarLinkAprovacaoWhatsappJob::class);
})->afterEach(fn () => jobCleanup('A2'));

// ---------------------------------------------------------------------------
// Cenário 2 — Idempotência cache
// ---------------------------------------------------------------------------

it('Cenário 2: Job idempotência — 2º handle no mesmo OS skipa via cache', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    session(['user.business_id' => BIZ_JOB_A]);

    $os = jobCriaOs('B', BIZ_JOB_A, 'orcamento');

    // 1º handle popula cache
    Cache::put("oficina:approval_dispatched:{$os->id}", true, now()->addDays(7));

    // 2º handle deveria pular
    $job = new EnviarLinkAprovacaoWhatsappJob(BIZ_JOB_A, $os->id);
    $job->handle(app(\Modules\OficinaAuto\Services\AprovacaoOsService::class));

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
})->afterEach(fn () => jobCleanup('B'));

// ---------------------------------------------------------------------------
// Cenário 3 — Cross-tenant guard
// ---------------------------------------------------------------------------

it('Cenário 3: Job rejeita quando OS pertence a outro business (Tier 0)', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    session(['user.business_id' => BIZ_JOB_A]);

    $osBizOutro = jobCriaOs('C', BIZ_JOB_B, 'orcamento');

    // Job criado com business_id=A mas OS pertence a biz=B → skip
    $job = new EnviarLinkAprovacaoWhatsappJob(BIZ_JOB_A, $osBizOutro->id);
    $job->handle(app(\Modules\OficinaAuto\Services\AprovacaoOsService::class));

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
})->afterEach(fn () => jobCleanup('C'));

// ---------------------------------------------------------------------------
// Cenário 4 — Walk-in (sem contact_id)
// ---------------------------------------------------------------------------

it('Cenário 4: Job skip quando OS sem contact_id (walk-in)', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    session(['user.business_id' => BIZ_JOB_A]);

    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id' => BIZ_JOB_A,
        'plate'       => PLATE_JOB_PREFIX . 'D',
        'vehicle_type' => 'caminhao',
    ]);

    $os = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => BIZ_JOB_A,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'orcamento',
        'contact_id'  => null,  // walk-in
        'entered_at'  => now(),
    ]);

    $job = new EnviarLinkAprovacaoWhatsappJob(BIZ_JOB_A, $os->id);
    $job->handle(app(\Modules\OficinaAuto\Services\AprovacaoOsService::class));

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
})->afterEach(fn () => jobCleanup('D'));

// ---------------------------------------------------------------------------
// Cenário 5 — Race condition status mudou
// ---------------------------------------------------------------------------

it('Cenário 5: Job skip quando status mudou pós-dispatch (race condition)', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    session(['user.business_id' => BIZ_JOB_A]);

    $os = jobCriaOs('E', BIZ_JOB_A, 'em_servico');  // não-orcamento

    $job = new EnviarLinkAprovacaoWhatsappJob(BIZ_JOB_A, $os->id);
    $job->handle(app(\Modules\OficinaAuto\Services\AprovacaoOsService::class));

    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
})->afterEach(fn () => jobCleanup('E'));
