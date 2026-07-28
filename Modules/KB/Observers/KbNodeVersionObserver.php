<?php

declare(strict_types=1);

namespace Modules\KB\Observers;

use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbNodeVersion;

/**
 * KbNodeVersionObserver — enforce append-only (Tier 0 IRREVOGÁVEL — ADR 0061).
 *
 * UPDATE e DELETE lançam DomainException. Apenas INSERT é permitido.
 *
 * Trigger MySQL ficaria V2 — V1 confia no Observer Eloquent.
 *
 * Para forense LGPD soft-delete, criar uma nova versão com `snapshot.deleted=true`
 * em vez de deletar a row antiga.
 */
class KbNodeVersionObserver
{
    /**
     * Nó bridge (is_editable=false) NÃO tem versão local — o histórico vive em
     * mcp_memory_documents_history. O KbNodeObserver já impede o snapshot pelo caminho de
     * UPDATE; aqui fecha o create DIRETO de uma versão pra nó bridge. Tier 0 — ADR 0061.
     */
    public function creating(KbNodeVersion $version): void
    {
        // SUPERADMIN: lookup do nó pra validar a invariante de bridge — node_id já é confiável
        // (FK) e o Model roda em contextos sem sessão (job/observer), então ignora o scope.
        $node = KbNode::withoutGlobalScopes()->find($version->node_id);
        if ($node !== null && $node->is_editable === false) {
            throw new \DomainException(
                "KbNodeVersion pra nó bridge #{$version->node_id} (is_editable=false) é proibida — ".
                'o histórico de bridge vive em mcp_memory_documents_history. (Tier 0 — ADR 0061)'
            );
        }
    }

    public function updating(KbNodeVersion $version): bool
    {
        throw new \DomainException(
            "KbNodeVersion #{$version->id} é append-only. ".
            'Crie nova versão em vez de editar. (Tier 0 — ADR 0061)'
        );
    }

    public function deleting(KbNodeVersion $version): bool
    {
        throw new \DomainException(
            "KbNodeVersion #{$version->id} é append-only. ".
            'Soft-delete não permitido — historico forense LGPD. (Tier 0 — ADR 0061)'
        );
    }
}
