<?php

declare(strict_types=1);

namespace Modules\Essentials\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Essentials\Entities\ToDo;

/**
 * D8 Security Wave 15 — Policy multi-tenant (ADR 0093 IRREVOGÁVEL).
 *
 * Defesa-em-profundidade além do HasBusinessScope global:
 *   - business_id do model DEVE bater com session do user
 *   - permissões Spatie governam grão fino (add/edit/delete)
 *   - autor pode sempre ler própria tarefa; admin pode ler/editar tudo
 *
 * Uso no Controller: `$this->authorize('update', $todo)` ou `@can('update', $todo)` em Blade/React.
 */
class ToDoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->sameBusiness($user, (int) session()->get('user.business_id'));
    }

    public function view(User $user, ToDo $todo): bool
    {
        if (! $this->sameBusiness($user, (int) $todo->business_id)) {
            return false;
        }
        // Autor sempre vê; usuários atribuídos veem; admin/permission view_all vê
        if ((int) $todo->created_by === (int) $user->id) {
            return true;
        }
        if ($user->can('superadmin') || $user->can('essentials.assign_todos')) {
            return true;
        }
        return $todo->users()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->sameBusiness($user, (int) session()->get('user.business_id'))
            && (bool) $user->can('essentials.add_todos');
    }

    public function update(User $user, ToDo $todo): bool
    {
        if (! $this->sameBusiness($user, (int) $todo->business_id)) {
            return false;
        }
        return (bool) $user->can('essentials.edit_todos');
    }

    public function delete(User $user, ToDo $todo): bool
    {
        if (! $this->sameBusiness($user, (int) $todo->business_id)) {
            return false;
        }
        return (bool) $user->can('essentials.delete_todos');
    }

    protected function sameBusiness(User $user, int $modelBusinessId): bool
    {
        // user.business_id é o tenant ativo na sessão atual (multi-business support).
        $sessionBiz = (int) (session()->get('user.business_id') ?: $user->business_id);
        return $sessionBiz === $modelBusinessId;
    }
}
