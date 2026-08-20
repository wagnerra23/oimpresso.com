<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Services\SubscriptionLifecycleService;

uses(Tests\TestCase::class);

/**
 * SubscriptionLifecycleServiceTest — Wave 18 RETRY (Superadmin D4 boost +15).
 *
 * Valida transitions linear status Subscription:
 *   - waiting → approved (com cálculo end_date conforme package interval)
 *   - approved + end_date passado → expire() marca expired
 *   - approved → cancel() marca cancelled
 *   - Idempotência: expire() em row já expirada retorna false
 *   - findOverdueApproved() retorna rows com end_date < now
 *
 * Cross-tenant intencional (Superadmin Wagner-only).
 *
 * Schema requer MySQL UltimatePOS (subscriptions table custom UPOS).
 *
 * @see Modules\Superadmin\Services\SubscriptionLifecycleService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema subscriptions UPOS requer MySQL.');
    }
    if (! Schema::hasTable('subscriptions')) {
        $this->markTestSkipped('Tabela subscriptions ausente — rode migrations Superadmin primeiro.');
    }
});

it('SubscriptionLifecycleService é instanciável', function () {
    $service = new SubscriptionLifecycleService();
    expect($service)->toBeInstanceOf(SubscriptionLifecycleService::class);
});

it('approve() transita waiting → approved com end_date calculado (months)', function () {
    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'waiting',
        'package_details' => json_encode([
            'interval' => 'months',
            'interval_count' => 1,
        ]),
        'created_id' => 1,
    ]);

    $service = new SubscriptionLifecycleService();
    $result = $service->approve($sub);

    expect($result)->toBeTrue();

    $sub->refresh();
    expect($sub->status)->toBe('approved');
    expect($sub->start_date)->not->toBeNull();
    expect($sub->end_date)->not->toBeNull();

    // Cleanup.
    $sub->forceDelete();
});

it('approve() em sub já approved retorna false (idempotente)', function () {
    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    $service = new SubscriptionLifecycleService();
    expect($service->approve($sub))->toBeFalse();

    $sub->forceDelete();
});

it('cancel() transita approved → cancelled', function () {
    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    $service = new SubscriptionLifecycleService();
    $result = $service->cancel($sub, 'test motivo');

    expect($result)->toBeTrue();

    $sub->refresh();
    expect($sub->status)->toBe('cancelled');

    $sub->forceDelete();
});

it('expire() em sub com end_date no passado marca expired', function () {
    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now()->subDays(60),
        'end_date' => now()->subDays(1),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    $service = new SubscriptionLifecycleService();
    expect($service->expire($sub))->toBeTrue();

    $sub->refresh();
    expect($sub->status)->toBe('expired');

    $sub->forceDelete();
});

it('expire() em sub com end_date no futuro retorna false (ainda válida)', function () {
    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    $service = new SubscriptionLifecycleService();
    expect($service->expire($sub))->toBeFalse();

    $sub->refresh();
    expect($sub->status)->toBe('approved');

    $sub->forceDelete();
});

it('findOverdueApproved() retorna apenas approved com end_date < now', function () {
    $overdue = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now()->subDays(60),
        'end_date' => now()->subDays(1),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    $service = new SubscriptionLifecycleService();
    $result = $service->findOverdueApproved();

    expect($result->pluck('id')->all())->toContain($overdue->id);

    $overdue->forceDelete();
});

// ── SA-O1b · o motivo do cancelamento passa a ser PERSISTIDO ──────────────────
//
// Até 2026-08-19 o `$reason` era recebido e descartado: virava só `reason_len` num
// atributo de span. O gráfico de motivos de churn do F1 não tinha de onde sair.

it('cancel() grava a categoria quando o motivo bate com a lista', function () {
    if (! Schema::hasColumn('subscriptions', 'cancel_reason')) {
        $this->markTestSkipped('migration 2026_08_19_000002 não rodou neste ambiente.');
    }

    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    expect((new SubscriptionLifecycleService())->cancel($sub, 'preco'))->toBeTrue();

    $sub->refresh();
    expect($sub->status)->toBe('cancelled')
        ->and($sub->cancel_reason)->toBe('preco');

    $sub->forceDelete();
});

it('cancel() com texto livre vira `outro` e PRESERVA o que foi escrito', function () {
    if (! Schema::hasColumn('subscriptions', 'cancel_reason')) {
        $this->markTestSkipped('migration 2026_08_19_000002 não rodou neste ambiente.');
    }

    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    $escrito = 'mudou de cidade e fechou a filial';
    expect((new SubscriptionLifecycleService())->cancel($sub, $escrito))->toBeTrue();

    $sub->refresh();
    // Categoria vira `outro` — mas o texto NÃO se perde na tradução, que era o defeito antigo.
    expect($sub->cancel_reason)->toBe('outro')
        ->and($sub->cancel_note)->toBe($escrito);

    $sub->forceDelete();
});

it('cancel() SEM motivo deixa a categoria nula, não carimba `outro`', function () {
    if (! Schema::hasColumn('subscriptions', 'cancel_reason')) {
        $this->markTestSkipped('migration 2026_08_19_000002 não rodou neste ambiente.');
    }

    $sub = Subscription::create([
        'business_id' => 1,
        'package_id' => 1,
        'package_price' => 0,
        'status' => 'approved',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
        'created_id' => 1,
    ]);

    expect((new SubscriptionLifecycleService())->cancel($sub))->toBeTrue();

    $sub->refresh();
    // Regra R10 do F1: saída sem motivo declarado fica FORA do gráfico e é dita em texto.
    // Carimbar `outro` inventaria um dado que ninguém informou.
    expect($sub->status)->toBe('cancelled')
        ->and($sub->cancel_reason)->toBeNull()
        ->and($sub->cancel_note)->toBeNull();

    $sub->forceDelete();
});
