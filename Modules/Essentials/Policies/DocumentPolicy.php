<?php

declare(strict_types=1);

namespace Modules\Essentials\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Essentials\Entities\Document;
use Modules\Essentials\Entities\DocumentShare;

/**
 * D8 Security Wave 15 — Policy multi-tenant pra Documents.
 *
 * Documents tem PII alta (RG/CNH/contratos colaborador) — guard duplo:
 *   1. business_id obrigatório bater
 *   2. download/view só pra criador OU compartilhado (DocumentShare por user/role)
 *
 * Trabalha junto com método download() do Controller que já faz checagem
 * de share — Policy adiciona camada Laravel idiomática.
 */
class DocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->sameBusiness($user, (int) session()->get('user.business_id'));
    }

    public function view(User $user, Document $document): bool
    {
        if (! $this->sameBusiness($user, (int) $document->business_id)) {
            return false;
        }

        // Criador sempre vê
        if ((int) $document->user_id === (int) $user->id) {
            return true;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        // Compartilhado por user_id ou role_id
        $roleId = optional($user->roles()->first())->id;
        return DocumentShare::where('document_id', $document->id)
            ->where(function ($q) use ($user, $roleId) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('value_type', 'user')->where('value', $user->id);
                })->orWhere(function ($qq) use ($roleId) {
                    $qq->where('value_type', 'role')->where('value', $roleId);
                });
            })
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->sameBusiness($user, (int) session()->get('user.business_id'));
    }

    public function delete(User $user, Document $document): bool
    {
        if (! $this->sameBusiness($user, (int) $document->business_id)) {
            return false;
        }
        // Só criador deleta (preserva pattern existing do destroy() do Controller)
        return (int) $document->user_id === (int) $user->id;
    }

    protected function sameBusiness(User $user, int $modelBusinessId): bool
    {
        $sessionBiz = (int) (session()->get('user.business_id') ?: $user->business_id);
        return $sessionBiz === $modelBusinessId;
    }
}
