<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionNote;

/**
 * NotesController — CRUD de anotações livres em Subscription (Onda 9 v9,75).
 *
 * Usado pelo Drawer Sells/Cobrança Recorrente — card amarelo "Nota pinada"
 * e timeline de anotações + eventos.
 *
 * Multi-tenant Tier 0: Subscription tem HasBusinessScope automático + guard
 * explícito por business_id da sessão.
 */
class SubscriptionNoteController extends Controller
{
    /**
     * POST /recurring-billing/{subscriptionId}/notes — criar nota.
     */
    public function store(Request $request, int $subscriptionId): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $sub = Subscription::query()
            ->where('business_id', $businessId)
            ->whereKey($subscriptionId)
            ->firstOrFail();

        $data = $request->validate([
            'body'      => ['required', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        // Se nova nota for pinned, despin outras notas do sub.
        if (! empty($data['is_pinned'])) {
            SubscriptionNote::query()
                ->where('subscription_id', $sub->id)
                ->where('business_id', $businessId)
                ->update(['is_pinned' => false]);
        }

        $note = SubscriptionNote::create([
            'business_id'     => $businessId,
            'subscription_id' => $sub->id,
            'user_id'         => (int) auth()->id(),
            'body'            => $data['body'],
            'is_pinned'       => (bool) ($data['is_pinned'] ?? false),
        ]);

        return response()->json([
            'ok'   => true,
            'note' => [
                'id'        => $note->id,
                'body'      => $note->body,
                'is_pinned' => $note->is_pinned,
                'created_at' => $note->created_at?->toIso8601String(),
                'by'        => optional(auth()->user())->first_name ?? 'sistema',
            ],
        ], 201);
    }

    /**
     * DELETE /recurring-billing/{subscriptionId}/notes/{noteId} — soft delete.
     */
    public function destroy(int $subscriptionId, int $noteId): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        $note = SubscriptionNote::query()
            ->where('business_id', $businessId)
            ->where('subscription_id', $subscriptionId)
            ->whereKey($noteId)
            ->firstOrFail();

        $note->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /recurring-billing/{subscriptionId}/notes/{noteId}/pin — pinar/despinar.
     */
    public function togglePin(int $subscriptionId, int $noteId): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        $note = SubscriptionNote::query()
            ->where('business_id', $businessId)
            ->where('subscription_id', $subscriptionId)
            ->whereKey($noteId)
            ->firstOrFail();

        $willPin = ! $note->is_pinned;

        if ($willPin) {
            SubscriptionNote::query()
                ->where('subscription_id', $subscriptionId)
                ->where('business_id', $businessId)
                ->where('id', '!=', $noteId)
                ->update(['is_pinned' => false]);
        }

        $note->update(['is_pinned' => $willPin]);

        return response()->json([
            'ok'        => true,
            'is_pinned' => $note->is_pinned,
        ]);
    }
}
