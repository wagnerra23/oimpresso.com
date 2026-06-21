<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbNodeVersion;

/**
 * KbVersionController — versões de um artigo editável.
 *
 * Contrato: SCHEMA-DB-V1.md §11
 *
 * - GET  /kb/nodes/{slug}/versions          lista snapshots
 * - POST /kb/nodes/{slug}/restore-version   restaura snapshot escolhido
 *
 * Tier 0 IRREVOGÁVEL: restore NUNCA apaga versão existente — cria uma NOVA
 * versão com o snapshot escolhido aplicado (append-only ADR 0061).
 */
class KbVersionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request, string $slug): JsonResponse
    {
        $node = KbNode::query()->where('slug', $slug)->firstOrFail();

        if (! $node->is_editable) {
            return response()->json([
                'ok'    => false,
                'error' => 'NODE_NOT_EDITABLE',
                'message' => 'Bridge canon — histórico vive em mcp_memory_documents_history. Use /kb/{slug}/history.',
            ], 422);
        }

        $versions = $node->versions()
            ->limit(50)
            ->get(['id', 'version_at', 'author_user_id', 'change_reason']);

        return response()->json([
            'slug'     => $slug,
            'versions' => $versions,
        ]);
    }

    public function restoreVersion(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'version_id' => 'required|integer|exists:kb_node_versions,id',
            'change_reason' => 'nullable|string|max:255',
        ]);

        $node = KbNode::query()->where('slug', $slug)->firstOrFail();

        if (! $node->is_editable) {
            return response()->json([
                'ok' => false,
                'error' => 'NODE_NOT_EDITABLE',
            ], 422);
        }

        // SUPERADMIN: lookup limitado por node_id (já validado ao tenant via firstOrFail acima) + version_id — bypass evita depender do scope de business_id em kb_node_versions
        $version = KbNodeVersion::withoutGlobalScopes()
            ->where('id', $data['version_id'])
            ->where('node_id', $node->id)
            ->firstOrFail();

        $snapshot = (array) $version->snapshot;

        // Updating no node dispara Observer que cria SNAPSHOT da versão atual
        // ANTES de aplicar o restore — historico fica completo (estado-atual → versão-restaurada).
        $node->fill([
            'title'          => $snapshot['title']          ?? $node->title,
            'excerpt'        => $snapshot['excerpt']        ?? null,
            'body_blocks'    => $snapshot['body_blocks']    ?? null,
            'tags'           => $snapshot['tags']           ?? null,
            'status'         => $snapshot['status']         ?? 'ok',
            'category_id'    => $snapshot['category_id']    ?? null,
            'subcategory_id' => $snapshot['subcategory_id'] ?? null,
            'nivel'          => $snapshot['nivel']          ?? null,
            'equip'          => $snapshot['equip']          ?? null,
        ]);

        // Forward `change_reason` pro Observer pegar via request().
        $request->merge(['change_reason' => $data['change_reason'] ?? "Restore from version #{$version->id}"]);

        $node->save();

        return response()->json([
            'ok'   => true,
            'node' => $node->fresh(),
            'message' => "Restaurado snapshot #{$version->id} de ".
                $version->version_at?->format('Y-m-d H:i'),
        ]);
    }
}
