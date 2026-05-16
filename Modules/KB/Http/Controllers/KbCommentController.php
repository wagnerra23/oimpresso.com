<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\KB\Entities\KbComment;
use Modules\KB\Entities\KbNode;

/**
 * KbCommentController — comments inline (ancorados em block_idx).
 *
 * Contrato: SCHEMA-DB-V1.md §11
 *
 * POST /kb/nodes/{slug}/comments — cria
 * DELETE /kb/comments/{id}       — delete próprio ou admin
 */
class KbCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'block_idx' => 'required|integer|min:0',
            'text'      => 'required|string|max:5000',
        ]);

        $node = KbNode::query()->where('slug', $slug)->firstOrFail();

        $comment = KbComment::create([
            'business_id'    => $node->business_id,
            'node_id'        => $node->id,
            'block_idx'      => $data['block_idx'],
            'text'           => $data['text'],
            'author_user_id' => Auth::id(),
        ]);

        return response()->json(['comment' => $comment], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = KbComment::query()->findOrFail($id);

        // Autor próprio OU superadmin pode deletar.
        // TODO[CL]: validar permission `kb.comment.delete_any` quando rename completar.
        if ($comment->author_user_id !== Auth::id()) {
            $user = Auth::user();
            if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('superadmin')) {
                return response()->json(['ok' => false, 'error' => 'FORBIDDEN'], 403);
            }
        }

        $comment->delete();

        return response()->json(['ok' => true]);
    }
}
