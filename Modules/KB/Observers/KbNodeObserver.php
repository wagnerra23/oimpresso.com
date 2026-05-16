<?php

declare(strict_types=1);

namespace Modules\KB\Observers;

use Illuminate\Support\Facades\Auth;
use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbNodeVersion;

/**
 * KbNodeObserver — enforce invariantes Tier 0 (ADR 0093 + ADR 0061).
 *
 * Invariantes:
 *   1. is_editable=false ⇒ body_blocks IS NULL
 *      (conteúdo de bridges canon vem do JOIN com mcp_memory_documents)
 *   2. Versionamento local SÓ pra is_editable=true
 *      (bridges já têm versionamento via mcp_memory_documents_history)
 *
 * O método saving() bloqueia save antes do INSERT/UPDATE.
 * O método updating() cria snapshot ANTES de aplicar mudanças
 * (só pra is_editable=true).
 */
class KbNodeObserver
{
    /**
     * Bloqueia save se invariante is_editable=false ⇒ body_blocks IS NULL é violada.
     *
     * @throws \DomainException quando bridge canon tenta gravar body_blocks
     */
    public function saving(KbNode $node): void
    {
        if ($node->is_editable === false && ! empty($node->body_blocks)) {
            throw new \DomainException(
                "KbNode #{$node->id} (slug='{$node->slug}', type='{$node->type}'): ".
                'is_editable=false bloqueia body_blocks (deve vir do JOIN com mcp_memory_documents). '.
                'Tier 0 IRREVOGÁVEL (ADR 0061).'
            );
        }
    }

    /**
     * Snapshot pre-update pra artigos editáveis (kb_node_versions).
     *
     * Bridges canon NÃO geram snapshot — historico vive em mcp_memory_documents_history.
     */
    public function updating(KbNode $node): void
    {
        if ($node->is_editable !== true) {
            return;
        }

        // Carrega os atributos ORIGINAIS pra capturar o estado pre-update.
        $original = $node->getOriginal();

        // Só snapshot se houve mudança em campos relevantes.
        $tracked = ['title', 'excerpt', 'body_blocks', 'tags', 'status',
                    'category_id', 'subcategory_id', 'nivel', 'equip'];

        $hasRelevantChange = false;
        foreach ($tracked as $field) {
            if ($node->isDirty($field)) {
                $hasRelevantChange = true;
                break;
            }
        }

        if (! $hasRelevantChange) {
            return;
        }

        KbNodeVersion::query()->create([
            'business_id'    => $node->business_id,
            'node_id'        => $node->id,
            'version_at'     => now(),
            'author_user_id' => Auth::id(),
            'snapshot'       => [
                'title'          => $original['title']        ?? $node->title,
                'excerpt'        => $original['excerpt']      ?? null,
                'body_blocks'    => isset($original['body_blocks'])
                    ? (is_string($original['body_blocks'])
                        ? json_decode($original['body_blocks'], true)
                        : $original['body_blocks'])
                    : null,
                'tags'           => isset($original['tags'])
                    ? (is_string($original['tags'])
                        ? json_decode($original['tags'], true)
                        : $original['tags'])
                    : null,
                'status'         => $original['status']         ?? 'ok',
                'category_id'    => $original['category_id']    ?? null,
                'subcategory_id' => $original['subcategory_id'] ?? null,
                'nivel'          => $original['nivel']          ?? null,
                'equip'          => $original['equip']          ?? null,
            ],
            'change_reason' => request()?->input('change_reason'),
        ]);
    }
}
