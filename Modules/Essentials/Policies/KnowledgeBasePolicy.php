<?php

declare(strict_types=1);

namespace Modules\Essentials\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Essentials\Entities\KnowledgeBase;

/**
 * D8 Security Wave 15 — Policy multi-tenant pra KnowledgeBase.
 *
 * KB tem visibility model (public vs only_with whitelist).
 * Policy enforça business_id + visibility — usado em Controller via $this->authorize().
 */
class KnowledgeBasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->sameBusiness($user, (int) session()->get('user.business_id'));
    }

    public function view(User $user, KnowledgeBase $kb): bool
    {
        if (! $this->sameBusiness($user, (int) $kb->business_id)) {
            return false;
        }

        if ((int) $kb->created_by === (int) $user->id) {
            return true;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        // share_with NULL ou 'public' = todo mundo do tenant vê
        if ($kb->share_with === null || $kb->share_with === 'public') {
            return true;
        }

        // only_with: precisa estar na whitelist via pivot
        return $kb->users()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->sameBusiness($user, (int) session()->get('user.business_id'));
    }

    public function update(User $user, KnowledgeBase $kb): bool
    {
        if (! $this->sameBusiness($user, (int) $kb->business_id)) {
            return false;
        }
        return (int) $kb->created_by === (int) $user->id || (bool) $user->can('superadmin');
    }

    public function delete(User $user, KnowledgeBase $kb): bool
    {
        if (! $this->sameBusiness($user, (int) $kb->business_id)) {
            return false;
        }
        return (int) $kb->created_by === (int) $user->id || (bool) $user->can('superadmin');
    }

    protected function sameBusiness(User $user, int $modelBusinessId): bool
    {
        $sessionBiz = (int) (session()->get('user.business_id') ?: $user->business_id);
        return $sessionBiz === $modelBusinessId;
    }
}
