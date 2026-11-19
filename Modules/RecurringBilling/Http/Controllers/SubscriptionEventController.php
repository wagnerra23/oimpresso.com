<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use Modules\RecurringBilling\Http\Controllers\Concerns\ExigePermissaoRb;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;

/**
 * Onda 16 v9,75 — Timeline append-only events em Subscription.
 *
 * Multi-tenant Tier 0: business_id da session SEMPRE; cross-tenant guard via
 * Subscription::where('business_id') + firstOrFail (404 limpo).
 */
class SubscriptionEventController extends Controller
{
    use ExigePermissaoRb;

    /**
     * GET /recurring-billing/{subscriptionId}/events — timeline DESC.
     */
    public function index(int $subscriptionId): JsonResponse
    {
        $this->exigirPermissao('recurringbilling.access');
        $businessId = (int) session('user.business_id');
        $sub = Subscription::query()
            ->where('business_id', $businessId)
            ->whereKey($subscriptionId)
            ->firstOrFail();

        $events = SubscriptionEvent::query()
            ->where('business_id', $businessId)
            ->where('subscription_id', $sub->id)
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get(['id', 'kind', 'by_actor', 'body', 'occurred_at']);

        return response()->json([
            'events' => $events->map(fn ($e) => [
                'id'          => $e->id,
                'kind'        => $e->kind,
                'by_actor'    => $e->by_actor,
                'body'        => $e->body,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }

    /**
     * POST /recurring-billing/{subscriptionId}/events — cria nota manual.
     *
     * Só nota humana (`kind=note`). Os kinds de sistema (cobrança, NF, status...) nascem no
     * código que faz o ato; aceitar aqui deixava qualquer usuário forjar "cobrança disparada"
     * ou "NF emitida" na timeline (auditoria RB d2, 2026-11-19).
     */
    public function store(Request $request, int $subscriptionId): JsonResponse
    {
        $this->exigirPermissao('recurringbilling.notes.write');
        $businessId = (int) session('user.business_id');
        $sub = Subscription::query()
            ->where('business_id', $businessId)
            ->whereKey($subscriptionId)
            ->firstOrFail();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'kind' => ['nullable', 'string', 'in:'.SubscriptionEvent::KIND_NOTE],
        ]);

        $user = auth()->user();
        $actor = $user?->first_name ?: ($user?->name ?: 'sistema');

        $event = SubscriptionEvent::create([
            'business_id'     => $businessId,
            'subscription_id' => $sub->id,
            'kind'            => SubscriptionEvent::KIND_NOTE,
            'by_actor'        => $actor,
            'body'            => $data['body'],
            'occurred_at'     => now(),
        ]);

        return response()->json([
            'ok'    => true,
            'event' => [
                'id'          => $event->id,
                'kind'        => $event->kind,
                'by_actor'    => $event->by_actor,
                'body'        => $event->body,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ],
        ], 201);
    }
}
