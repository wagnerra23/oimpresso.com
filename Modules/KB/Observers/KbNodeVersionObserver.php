<?php

declare(strict_types=1);

namespace Modules\KB\Observers;

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
