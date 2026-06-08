<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Util\OtelHelper;
use App\Utils\Util;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Utils\CrmUtil;

/**
 * ScheduleService — orquestrador thin de follow-ups (crm_schedules).
 *
 * Service thin extraído de `ScheduleController` (Wave J D4.a boost — 2026-05-16).
 * Encapsula a normalização do payload (uf_date) + roteamento entre os 3 modos
 * de follow-up já implementados em `CrmUtil` (addFollowUp / addRecursiveFollowUp /
 * addAdvanceFollowUp). Controller fica responsável por auth/permissão + response
 * shape (zero regressão JSON/Inertia).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * caller obrigatoriamente passa `$businessId` resolvido de `session()->get('user.business_id')`
 * — Service NUNCA toca em session diretamente (back-pressure pra Job assíncrono).
 *
 * Padrão thin: zero side-effect além das chamadas Util/Repository já existentes.
 * Compatível 100% com response shape legacy.
 *
 * @see Modules\Crm\Http\Controllers\ScheduleController
 * @see Modules\Crm\Utils\CrmUtil::addFollowUp
 * @see Modules\Crm\Utils\CrmUtil::addRecursiveFollowUp
 * @see Modules\Crm\Utils\CrmUtil::addAdvanceFollowUp
 * @see Modules\Crm\Utils\CrmUtil::updateFollowUp
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0011-alinhamento-padrao-jana.md
 */
class ScheduleService
{
    public function __construct(
        private readonly Util $commonUtil,
        private readonly CrmUtil $crmUtil,
    ) {
    }

    /**
     * Cria follow-up novo roteando pelo modo correto (simples / recursivo / advanced).
     *
     * Caller (Controller) já filtrou `$input` via `$request->except([...])` e validou
     * autorização. Service apenas normaliza datas + delega pra Util correto.
     *
     * Multi-tenant: `$input` precisa carregar `business_id` (Controller setou via
     * `request()->session()->get('user.business_id')` previamente OU passou explícito).
     *
     * @param  array<string, mixed>  $input  Payload sem `_token`/`schedule_for`/`contact_ids`
     * @param  \App\User             $user   Usuário autenticado (controller passa `\Auth::user()`)
     */
    public function createFollowUp(array $input, $user): void
    {
        OtelHelper::spanBiz('crm.schedule.create', function () use (&$input, $user) {
            if (empty($input['is_recursive'])) {
                $input['start_datetime'] = $this->commonUtil->uf_date($input['start_datetime'], true);
                $input['end_datetime']   = $this->commonUtil->uf_date($input['end_datetime'], true);
            }

            DB::beginTransaction();
            try {
                if (empty($input['follow_ups']) && empty($input['is_recursive'])) {
                    $this->crmUtil->addFollowUp($input, $user);
                } elseif (! empty($input['is_recursive'])) {
                    $this->crmUtil->addRecursiveFollowUp($input, $user);
                } else {
                    $this->crmUtil->addAdvanceFollowUp($input, $user);
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }, [
            'is_recursive' => ! empty($input['is_recursive']),
            'has_follow_ups' => ! empty($input['follow_ups']),
        ]);
    }

    /**
     * Atualiza follow-up existente — normaliza datas + delega pra `CrmUtil::updateFollowUp`.
     *
     * Caller (Controller) garante que `$id` pertence a `$businessId` antes de chamar
     * (query `where('business_id', $businessId)->findOrFail($id)` no update path original).
     *
     * @param  int                    $id       ID do Schedule
     * @param  array<string, mixed>   $payload  Payload sem `_method`/`_token`/`schedule_for`
     * @param  \App\User              $user     Usuário autenticado
     */
    public function updateFollowUp(int $id, array $payload, $user): void
    {
        $payload['start_datetime'] = $this->commonUtil->uf_date($payload['start_datetime'], true);
        $payload['end_datetime']   = $this->commonUtil->uf_date($payload['end_datetime'], true);

        $this->crmUtil->updateFollowUp($id, $payload, $user);
    }

    /**
     * Resolve query base de Schedule por business + permissão.
     *
     * Centraliza o filtro `where business_id + access_own_schedule` repetido em
     * edit/destroy. Fonte única evita drift entre handlers (bug histórico antes
     * da extração — ver `ScheduleController` legacy edit/destroy/update).
     *
     * @param  int   $businessId
     * @param  int   $authUserId
     * @param  bool  $canAccessAll
     * @param  bool  $canAccessOwn
     * @return \Illuminate\Database\Eloquent\Builder<Schedule>
     */
    public function scopedQuery(int $businessId, int $authUserId, bool $canAccessAll, bool $canAccessOwn)
    {
        $query = Schedule::where('business_id', $businessId);

        if (! $canAccessAll && $canAccessOwn) {
            $query->where(function ($qry) use ($authUserId) {
                $qry->whereHas('users', function ($q) use ($authUserId) {
                    $q->where('user_id', $authUserId);
                })->orWhere('created_by', $authUserId);
            });
        }

        return $query;
    }
}
