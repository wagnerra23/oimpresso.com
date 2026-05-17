<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\RecurringBilling\Http\Presenters\SubscriptionIndexPresenter;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;

uses(Tests\TestCase::class);

/**
 * Wave 4 — Pest cobrindo SubscriptionIndexPresenter (Pages/RecurringBilling/Index.tsx
 * payload). Unit tests puros — stateless presenter, sem touch DB.
 *
 * Cenários:
 *  1. deriveVisualStatus mapeia 5 estados DB → 5 estados Cowork
 *  2. toListRow retorna campos canônicos esperados pela Page
 *  3. computeKpis MRR convertendo trimestral→mensal corretamente
 *  4. computeKpis churn_rate calcula com base no total ever active
 *  5. translateCycle PT-BR
 */

/**
 * Helper — cria Subscription em-memory (sem persistir) com Plan eager.
 */
function makeSub(string $status, array $extra = []): Subscription
{
    $plan = new Plan(['name' => 'Plano X', 'valor' => 480.0, 'ciclo' => 'monthly', 'ativo' => true]);
    $plan->id = 1;

    $sub = new Subscription(array_merge([
        'business_id'          => 1,
        'plan_id'              => 1,
        'contact_id'           => 1,
        'status'               => $status,
        'start_date'           => '2025-01-01',
        'next_due_date'        => '2026-06-10',
        'billing_anchor_date'  => '2025-01-01',
        'payment_method'       => 'pix',
        'total_paid_cached'    => 0,
        'failed_count_cached'  => 0,
        'total_revenue_cached' => 0.0,
    ], $extra));
    $sub->id = $extra['id'] ?? 1;
    $sub->setRelation('plan', $plan);
    $sub->setRelation('contact', null);
    $sub->setRelation('lastInvoice', null);
    $sub->setRelation('pinnedNote', null);

    return $sub;
}

it('R-RB-WAVE4-1 — deriveVisualStatus mapeia 5 estados DB → 5 estados Cowork', function () {
    expect(SubscriptionIndexPresenter::deriveVisualStatus(makeSub('active')))->toBe('em_dia');
    expect(SubscriptionIndexPresenter::deriveVisualStatus(makeSub('trialing')))->toBe('em_dia');
    expect(SubscriptionIndexPresenter::deriveVisualStatus(makeSub('paused')))->toBe('pausada');
    expect(SubscriptionIndexPresenter::deriveVisualStatus(makeSub('canceled')))->toBe('cancelada');
    // past_due sem lastInvoice → retentando (lastAttemptCount=0 < 3)
    expect(SubscriptionIndexPresenter::deriveVisualStatus(makeSub('past_due')))->toBe('retentando');
});

it('R-RB-WAVE4-2 — toListRow retorna campos canônicos pra Page Inertia', function () {
    $sub = makeSub('active', [
        'payment_method'       => 'boleto',
        'next_due_date'        => '2026-06-10',
        'total_paid_cached'    => 12,
        'failed_count_cached'  => 1,
        'total_revenue_cached' => 5760.0,
    ]);

    $row = SubscriptionIndexPresenter::toListRow($sub);

    expect($row)
        ->toHaveKeys([
            'id', 'client', 'cnpj', 'plan_id', 'plan_name', 'plan_cycle',
            'since', 'method', 'status', 'retry', 'retry_max',
            'next_at', 'next_value', 'os', 'is_pinned', 'paid', 'missed', 'ltv',
        ])
        ->and($row['status'])->toBe('em_dia')
        ->and($row['method'])->toBe('boleto')
        ->and($row['plan_cycle'])->toBe('mensal')
        ->and($row['paid'])->toBe(12)
        ->and($row['missed'])->toBe(1)
        ->and($row['ltv'])->toBe(5760.0)
        ->and($row['retry'])->toBeNull();
});

it('R-RB-WAVE4-3 — computeKpis converte trimestral pra mensal equivalente no MRR', function () {
    $planTri = new Plan(['name' => 'Trimestral', 'valor' => 900.0, 'ciclo' => 'quarterly', 'ativo' => true]);
    $planTri->id = 2;

    $sub1 = makeSub('active'); // mensal 480 → MRR contrib 480
    $sub2 = new Subscription([
        'business_id'         => 1,
        'plan_id'             => 2,
        'status'              => 'active',
        'next_due_date'       => '2026-06-10',
        'total_revenue_cached' => 0,
    ]);
    $sub2->setRelation('plan', $planTri);
    $sub2->setRelation('contact', null);
    $sub2->setRelation('lastInvoice', null);
    $sub2->setRelation('pinnedNote', null);

    $kpis = SubscriptionIndexPresenter::computeKpis(new Collection([$sub1, $sub2]));

    // MRR = 480 (sub1 mensal) + 900/3=300 (sub2 trimestral) = 780
    expect($kpis['mrr'])->toBe(780.0);
    expect($kpis['active_count'])->toBe(2);
});

it('R-RB-WAVE4-4 — computeKpis churn_rate calcula sobre total non-trialing', function () {
    Carbon::setTestNow('2026-05-17 12:00:00');
    $subCanceledNow = makeSub('canceled', ['canceled_at' => Carbon::parse('2026-05-10 10:00:00')]);
    $subActive1 = makeSub('active', ['id' => 2]);
    $subActive2 = makeSub('active', ['id' => 3]);

    $kpis = SubscriptionIndexPresenter::computeKpis(new Collection([
        $subCanceledNow, $subActive1, $subActive2,
    ]));

    expect($kpis['churn_count'])->toBe(1);
    // total non-trialing = 3 (active+active+canceled); churn = 1/3 = 33.3
    expect($kpis['churn_rate'])->toBe(33.3);

    Carbon::setTestNow();
});

it('R-RB-WAVE4-5 — toDrawerPayload inclui contact + note + fiscal blocks', function () {
    $sub = makeSub('em_dia');

    $payload = SubscriptionIndexPresenter::toDrawerPayload($sub);

    expect($payload)
        ->toHaveKeys(['contact', 'note', 'fiscal', 'churn_reason', 'paused_until', 'canceled_at'])
        ->and($payload['contact'])->toHaveKeys(['name', 'phone', 'email'])
        ->and($payload['fiscal'])->toHaveKeys(['type', 'channels', 'last_nf'])
        ->and($payload['note'])->toBeNull(); // pinnedNote=null no makeSub helper
});
