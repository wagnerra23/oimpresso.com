<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Essentials\Entities\ToDo;

/**
 * TodoService — Service thin extraído de ToDoController (D4.a ratio).
 *
 * Wave J 2026-05-16: dimensão D4.a (Services/Controllers) estava 0/19 — esse Service
 * é o primeiro passo do Boost Essentials 54→65. Mantém Controller stable
 * (sem breaking change) via composição: Controller injeta Service e delega
 * scopes + parsing + auth checks.
 *
 * Responsabilidades:
 *  - Build query scoped por business_id + permission do user (admin vs criador/atribuído)
 *  - Parse de datas (Y-m-d HH:ii com fallback uf_date)
 *  - Verificação 4 níveis de autorização: access / add / edit / delete
 *
 * NÃO faz (intencional, thin Service):
 *  - Render Inertia (responsabilidade Controller)
 *  - Notifications (Controller dispara)
 *  - Activity log (Controller chama Util::activityLog)
 *  - Sync de assignees (Controller fluxo Eloquent direto)
 *
 * ADR 0093: business_id sempre scopado (Tier 0 IRREVOGÁVEL multi-tenant).
 * ADR 0101: tests biz=1 (Wagner WR2) e biz=99 (fictício). NUNCA biz=4.
 *
 * @see Modules\Essentials\Http\Controllers\ToDoController
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class TodoService
{
    public function __construct(
        protected Util $commonUtil,
        protected ModuleUtil $moduleUtil,
    ) {
    }

    /**
     * Query base scoped por business_id + visibility do user (admin vê tudo,
     * não-admin só próprias OU atribuídas).
     */
    public function scopedQueryForUser(int $businessId, Model $user): Builder
    {
        $query = ToDo::where('business_id', $businessId);
        $isAdmin = $this->moduleUtil->is_admin($user, $businessId);

        if (! $isAdmin) {
            $authId = $user->id;
            $query->where(function ($q) use ($authId) {
                $q->where('created_by', $authId)
                    ->orWhereHas('users', function ($inner) use ($authId) {
                        $inner->where('user_id', $authId);
                    });
            });
        }

        return $query;
    }

    /**
     * Parse data do input (Y-m-d ou Y-m-d HH:ii) em DATETIME MySQL.
     * Tolera formato configurado no business via uf_date como fallback.
     */
    public function parseDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            try {
                return $this->commonUtil->uf_date($date, true);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    /**
     * Verifica acesso ao módulo (subscription + permissão).
     * Retorna false → Controller deve abort(403).
     */
    public function canAccess(Model $user, int $businessId): bool
    {
        return (bool) ($user->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'));
    }

    /**
     * Verifica permissão de criar — superadmin OU subscription + add_todos.
     */
    public function canAdd(Model $user, int $businessId): bool
    {
        return $this->canAccess($user, $businessId)
            && (bool) ($user->can('superadmin') || $user->can('essentials.add_todos'));
    }

    /**
     * Verifica permissão de editar — superadmin OU subscription + edit_todos.
     */
    public function canEdit(Model $user, int $businessId): bool
    {
        return $this->canAccess($user, $businessId)
            && (bool) ($user->can('superadmin') || $user->can('essentials.edit_todos'));
    }

    /**
     * Verifica permissão de deletar — superadmin OU subscription + delete_todos.
     */
    public function canDelete(Model $user, int $businessId): bool
    {
        return $this->canAccess($user, $businessId)
            && (bool) ($user->can('superadmin') || $user->can('essentials.delete_todos'));
    }

    /**
     * Garante que assignees nunca fiquem vazios — fallback pro criador.
     * Centraliza a regra que estava espalhada no Controller::store().
     */
    public function resolveAssignees(Model $user, array $requestedAssignees, int $createdBy): array
    {
        if (! $user->can('essentials.assign_todos') || empty($requestedAssignees)) {
            return [$createdBy];
        }

        return $requestedAssignees;
    }
}
