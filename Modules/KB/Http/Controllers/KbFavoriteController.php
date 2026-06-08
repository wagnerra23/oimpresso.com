<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\KB\Entities\KbFavorite;
use Modules\KB\Entities\KbNode;

/**
 * KbFavoriteController — toggle bookmark per user.
 *
 * Contrato: SCHEMA-DB-V1.md §11
 *
 * POST /kb/nodes/{slug}/favorite — toggle (cria se não existe, deleta se existe).
 *
 * UNIQUE (user_id, node_id) garante idempotência.
 */
class KbFavoriteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function toggle(Request $request, string $slug): JsonResponse
    {
        $node = KbNode::query()->where('slug', $slug)->firstOrFail();
        $userId = Auth::id();

        $fav = KbFavorite::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('node_id', $node->id)
            ->first();

        if ($fav) {
            $fav->delete();
            return response()->json(['ok' => true, 'favorited' => false]);
        }

        KbFavorite::create([
            'business_id' => $node->business_id,
            'user_id'     => $userId,
            'node_id'     => $node->id,
        ]);

        return response()->json(['ok' => true, 'favorited' => true]);
    }
}
