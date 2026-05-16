<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Util\OtelHelper;
use App\Utils\ModuleUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Essentials\Entities\EssentialsLeave;

/**
 * LeaveRequestService — Service thin extraído de EssentialsLeaveController (D4.a ratio).
 *
 * Wave J 2026-05-16: dimensão D4.a (Services/Controllers) estava 0/19 — junto
 * com TodoService dá os 2 primeiros pontos do Boost Essentials 54→65.
 * Mantém Controller stable (sem breaking change) via composição: Controller
 * injeta Service via DI e delega scope + status mapping + auth checks.
 *
 * Responsabilidades:
 *  - Build query base scoped por business_id + visibility do user
 *    (crud_all_leave vê tudo, crud_own_leave só do próprio user_id)
 *  - Mapa canônico de status (pending/approved/cancelled) — antes hardcoded no constructor
 *  - Verificação 3 níveis de autorização: access (módulo) / crud all / crud own
 *  - Validação de status novos (apenas pending/approved/cancelled)
 *
 * NÃO faz (intencional, thin Service):
 *  - Render view/Inertia (responsabilidade Controller)
 *  - Notifications (Controller dispara LeaveStatusNotification, NewLeaveNotification)
 *  - Activity log (Spatie Activitylog em Entity)
 *  - Geração de ref_no (helper privado __addLeave segue no Controller — fluxo session)
 *
 * ADR 0093: business_id sempre scopado (Tier 0 IRREVOGÁVEL multi-tenant).
 * ADR 0101: tests biz=1 (Wagner WR2) e biz=99 (fictício). NUNCA biz=4.
 *
 * @see Modules\Essentials\Http\Controllers\EssentialsLeaveController
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class LeaveRequestService
{
    /**
     * Status canônicos — Controller usava hardcoded inline no constructor.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        protected ModuleUtil $moduleUtil,
    ) {
    }

    /**
     * Mapa de status com label i18n + classe Bootstrap (mantém compatibilidade
     * com `view('essentials::leave.index')` e DataTables editColumn('status')).
     */
    public function statusMap(): array
    {
        return [
            self::STATUS_PENDING => [
                'name'  => __('lang_v1.pending'),
                'class' => 'bg-yellow',
            ],
            self::STATUS_APPROVED => [
                'name'  => __('essentials::lang.approved'),
                'class' => 'bg-green',
            ],
            self::STATUS_CANCELLED => [
                'name'  => __('essentials::lang.cancelled'),
                'class' => 'bg-red',
            ],
        ];
    }

    /**
     * Query base scoped por business_id + permissão do user.
     *
     * Controller filtra `essentials_leaves.user_id` quando user só tem
     * crud_own_leave — este Service replica a regra.
     */
    public function scopedQueryForUser(int $businessId, Model $user): Builder
    {
        $query = EssentialsLeave::where('business_id', $businessId);

        $canAll = (bool) $user->can('essentials.crud_all_leave');
        $canOwn = (bool) $user->can('essentials.crud_own_leave');

        if (! $canAll && $canOwn) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * Verifica acesso ao módulo (subscription + permissão geral).
     * Retorna false → Controller deve abort(403).
     */
    public function canAccess(Model $user, int $businessId): bool
    {
        return (bool) ($user->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'));
    }

    /**
     * Permissão CRUD geral — qualquer leave do business (admin RH).
     */
    public function canCrudAll(Model $user, int $businessId): bool
    {
        return $this->canAccess($user, $businessId)
            && (bool) ($user->can('superadmin') || $user->can('essentials.crud_all_leave'));
    }

    /**
     * Permissão CRUD próprio — usuário gere apenas suas solicitações.
     */
    public function canCrudOwn(Model $user, int $businessId): bool
    {
        return $this->canAccess($user, $businessId)
            && (bool) ($user->can('essentials.crud_own_leave') || $user->can('essentials.crud_all_leave'));
    }

    /**
     * Permissão de aprovação — admin RH ou approve_leave.
     */
    public function canApprove(Model $user, int $businessId): bool
    {
        return $this->canAccess($user, $businessId)
            && (bool) ($user->can('superadmin') || $user->can('essentials.approve_leave'));
    }

    /**
     * Valida transição de status — só aceita os 3 canônicos.
     * Defesa contra POST mal-formado em changeStatus().
     */
    public function isValidStatus(?string $status): bool
    {
        return in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Carrega leave scoped (business_id + permissão own).
     * Retorna null se não encontrado ou usuário não tem permissão de ver.
     *
     * Wave 12 D9 OTel — instrumented com `spanBiz` (zero-cost se OTel off).
     */
    public function findScopedOrNull(int $id, int $businessId, Model $user): ?EssentialsLeave
    {
        return OtelHelper::spanBiz('essentials.leave.find_scoped', function () use ($id, $businessId, $user) {
            return $this->scopedQueryForUser($businessId, $user)
                ->where('id', $id)
                ->first();
        }, [
            'leave_id' => $id,
            'user_id'  => $user->id,
        ]);
    }
}
