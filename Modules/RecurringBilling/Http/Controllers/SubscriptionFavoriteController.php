<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionFavorite;

/**
 * FavoriteController — toggle favorito por usuário em Subscription
 * (Onda 9 v9,75). Persistido em `rb_subscription_favorites`
 * (UNIQUE user_id + subscription_id).
 *
 * Substitui localStorage do prototipo Cowork — agora servidor lembra,
 * funciona cross-device.
 */
class SubscriptionFavoriteController extends Controller
{
    /**
     * POST /recurring-billing/{subscriptionId}/favorite — toggle.
     */
    public function toggle(int $subscriptionId): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) auth()->id();

        $sub = Subscription::query()
            ->where('business_id', $businessId)
            ->whereKey($subscriptionId)
            ->firstOrFail();

        $existing = SubscriptionFavorite::query()
            ->where('user_id', $userId)
            ->where('subscription_id', $sub->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isFavorite = false;
        } else {
            SubscriptionFavorite::create([
                'business_id'     => $businessId,
                'user_id'         => $userId,
                'subscription_id' => $sub->id,
            ]);
            $isFavorite = true;
        }

        return response()->json([
            'ok'          => true,
            'is_favorite' => $isFavorite,
        ]);
    }
}
