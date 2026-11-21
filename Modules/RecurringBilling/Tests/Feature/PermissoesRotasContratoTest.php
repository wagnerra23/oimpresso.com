<?php

declare(strict_types=1);

use App\User;
use Illuminate\Http\Request;
use Modules\RecurringBilling\Http\Controllers\ConfiguracoesController;
use Modules\RecurringBilling\Http\Controllers\InvoiceController;
use Modules\RecurringBilling\Http\Controllers\PlanController;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionEventController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionFavoriteController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionNoteController;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Policies\SubscriptionPolicy;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

/**
 * Auditoria RecurringBilling d1 (2026-11-19) — permissão por rota.
 *
 * Antes: lista, /faturas, /configuracoes, notas, pin, favoritos, timeline e reenviar-nfe só
 * exigiam login; a SubscriptionPolicy aceitava `recurringbilling.access` sozinha para criar,
 * editar, pausar, reativar e cancelar; planos (preço) também. 6 das 10 permissões declaradas
 * em DataController::user_permissions nunca eram lidas.
 *
 * Cada rota é chamada duas vezes com um usuário dublê:
 *  - SEM a permissão dela (só as outras do módulo) → 403;
 *  - COM a permissão → passa do gate (o que vier depois — banco ausente, 404 — não é 403).
 * O 2º passo é o controle positivo: sem ele, um 403 em tudo também deixaria o 1º verde.
 *
 * Sem banco: o gate roda antes de qualquer consulta. Tenant 98 na sessão (ADR 0358).
 */
const RB_PERMISSOES = [
    'recurringbilling.access', 'recurringbilling.subscriptions.manage', 'recurringbilling.subscriptions.cancel',
    'recurringbilling.plans.manage', 'recurringbilling.invoices.view', 'recurringbilling.invoices.charge',
    'recurringbilling.invoices.cancel', 'recurringbilling.notes.write', 'recurringbilling.favorites.write',
    'recurringbilling.configuracoes.manage',
];

function rbUsuarioCom(array $permissoes): User
{
    $u = Mockery::mock(User::class)->makePartial();
    $u->business_id = 98;
    $u->shouldReceive('can')->andReturnUsing(fn ($a) => in_array($a, $permissoes, true));

    return $u;
}

/** Status HTTP da chamada: 403 se o gate barrou; 0 se passou do gate (qualquer outro desfecho). */
function rbStatusDoGate(callable $chamada): int
{
    try {
        $chamada();
    } catch (HttpException $e) {
        return $e->getStatusCode() === 403 ? 403 : 0;
    } catch (Throwable) {
        // Passou do gate e quebrou adiante (sem banco neste teste) — não é o que se mede aqui.
    }

    return 0;
}

beforeEach(function () {
    session(['user.business_id' => 98, 'business.id' => 98, 'user' => ['business_id' => 98]]);
});

afterEach(function () {
    session()->flush();
});

dataset('rotas', [
    'GET /recurring-billing (lista)' => ['recurringbilling.access',
        fn () => app(RecurringBillingController::class)->index(Request::create('/recurring-billing'))],
    'POST /{id}/reenviar-nfe' => ['recurringbilling.access',
        fn () => app(RecurringBillingController::class)->reenviarNfe(Request::create('/x', 'POST'), 1)],
    'GET /faturas' => ['recurringbilling.invoices.view',
        fn () => app(InvoiceController::class)->index(Request::create('/recurring-billing/faturas'))],
    'GET /configuracoes' => ['recurringbilling.configuracoes.manage',
        fn () => app(ConfiguracoesController::class)->index(Request::create('/recurring-billing/configuracoes'))],
    'POST /{id}/notes' => ['recurringbilling.notes.write',
        fn () => (new SubscriptionNoteController())->store(Request::create('/x', 'POST', ['body' => 'n']), 1)],
    'DELETE /{id}/notes/{noteId}' => ['recurringbilling.notes.write',
        fn () => (new SubscriptionNoteController())->destroy(1, 1)],
    'POST /{id}/notes/{noteId}/pin' => ['recurringbilling.notes.write',
        fn () => (new SubscriptionNoteController())->togglePin(1, 1)],
    'POST /{id}/favorite' => ['recurringbilling.favorites.write',
        fn () => (new SubscriptionFavoriteController())->toggle(1)],
    'GET /{id}/events' => ['recurringbilling.access',
        fn () => (new SubscriptionEventController())->index(1)],
    'POST /{id}/events' => ['recurringbilling.notes.write',
        fn () => (new SubscriptionEventController())->store(Request::create('/x', 'POST', ['body' => 'n']), 1)],
    'GET /planos' => ['recurringbilling.access',
        fn () => app(PlanController::class)->index(Request::create('/recurring-billing/planos'))],
    'GET /planos/novo' => ['recurringbilling.plans.manage',
        fn () => app(PlanController::class)->create()],
    'GET /planos/{id}/editar' => ['recurringbilling.plans.manage',
        fn () => app(PlanController::class)->edit(1)],
    'DELETE /planos/{id}' => ['recurringbilling.plans.manage',
        fn () => app(PlanController::class)->destroy(1)],
]);

it('d1 · sem a permissão da rota responde 403; com ela, passa do gate', function (string $permissao, Closure $chamada) {
    $outras = array_values(array_diff(RB_PERMISSOES, [$permissao]));

    $this->actingAs(rbUsuarioCom($outras));
    expect(rbStatusDoGate($chamada))->toBe(403);

    $this->actingAs(rbUsuarioCom([$permissao]));
    expect(rbStatusDoGate($chamada))->toBe(0);
})->with('rotas');

it('d1 · sem login nenhuma dessas rotas passa do gate', function () {
    // Nenhum actingAs neste caso: o guard está sem usuário.
    expect(rbStatusDoGate(fn () => app(InvoiceController::class)->index(Request::create('/x'))))->toBe(403)
        ->and(rbStatusDoGate(fn () => (new SubscriptionFavoriteController())->toggle(1)))->toBe(403);
});

it('d1 · Policy: criar, editar, pausar e reativar pedem subscriptions.manage; cancelar pede subscriptions.cancel', function () {
    $policy = new SubscriptionPolicy();
    $sub = new Subscription();
    $sub->business_id = 98;
    $sub->status = 'active';
    $pausada = new Subscription();
    $pausada->business_id = 98;
    $pausada->status = 'paused';

    $soAcesso = rbUsuarioCom(['recurringbilling.access']);
    expect($policy->create($soAcesso))->toBeFalse()
        ->and($policy->update($soAcesso, $sub))->toBeFalse()
        ->and($policy->pause($soAcesso, $sub))->toBeFalse()
        ->and($policy->resume($soAcesso, $pausada))->toBeFalse()
        ->and($policy->cancel($soAcesso, $sub))->toBeFalse();

    $gestor = rbUsuarioCom(['recurringbilling.subscriptions.manage']);
    expect($policy->create($gestor))->toBeTrue()
        ->and($policy->update($gestor, $sub))->toBeTrue()
        ->and($policy->pause($gestor, $sub))->toBeTrue()
        ->and($policy->resume($gestor, $pausada))->toBeTrue()
        ->and($policy->cancel($gestor, $sub))->toBeFalse();

    expect($policy->cancel(rbUsuarioCom(['recurringbilling.subscriptions.cancel']), $sub))->toBeTrue();
});
