<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use App\Business;
use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BusinessAuditService — Wave 23 D4 + D9 SATURATION.
 *
 * Encapsula consultas auditoriais cross-tenant que hoje vivem espalhadas em
 * `BusinessController` (filterTransactionDate, last login, soft-delete count)
 * — Service injetável testável + spans OTel pra observabilidade SRE.
 *
 * **Cross-tenant intencional Tier 0** (ADR 0093 §exceções Superadmin):
 *   - Queries operam sobre TODOS businesses por design
 *   - Wagner-only via Controller permission gate
 *
 * Casos de uso:
 *   - Identificar businesses inativos (sem transação há N dias) → notificação Asaas
 *   - Self-destroy guard: contagem de businesses preserva biz=1 (Wagner)
 *   - Audit trail de mudanças de subscription cross-business
 *   - Aging de tokens de acesso por business (preview pra D7 LGPD futura)
 *
 * D9 Obs: spans per query agregada pra dashboard SRE + alerta drift.
 *
 * @see Modules\Superadmin\Http\Controllers\BusinessController
 * @see Modules\Superadmin\Services\SuperadminDashboardService (sibling)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class BusinessAuditService
{
    /**
     * Lista businesses SEM transação desde data de corte (inativos).
     *
     * Cross-tenant intencional.
     *
     * @return array<int, array{id: int, name: string, last_tx_date: ?string}>
     */
    public function findInactiveSince(Carbon $cutoff): array
    {
        return OtelHelper::spanBiz('superadmin.business_audit.inactive_since', function () use ($cutoff): array {
            // SUPERADMIN: leitura cross-tenant intencional (governance dashboard).
            if (! \Illuminate\Support\Facades\Schema::hasTable('transactions')) {
                return [];
            }

            $rows = DB::table('business')
                ->leftJoin('transactions as t', 'business.id', '=', 't.business_id')
                ->select(
                    'business.id',
                    'business.name',
                    DB::raw('MAX(t.transaction_date) as last_tx_date'),
                )
                ->where('business.is_active', 1)
                ->groupBy('business.id', 'business.name')
                ->havingRaw('(MAX(t.transaction_date) IS NULL OR MAX(t.transaction_date) < ?)', [$cutoff->toDateString()])
                ->limit(500)
                ->get();

            return $rows->map(fn ($r) => [
                'id'           => (int) $r->id,
                'name'         => (string) $r->name,
                'last_tx_date' => $r->last_tx_date ? (string) $r->last_tx_date : null,
            ])->toArray();
        }, ['module' => 'Superadmin', 'service' => self::class, 'cutoff' => $cutoff->toDateString()]);
    }

    /**
     * Self-destroy guard helper: garante que destruir um business preserva
     * o biz=1 (Wagner) e existe ao menos 1 outro business.
     *
     * @return array{can_destroy: bool, reason: string}
     */
    public function canDestroy(int $businessId, int $currentSessionBizId): array
    {
        return OtelHelper::spanBiz('superadmin.business_audit.can_destroy', function () use ($businessId, $currentSessionBizId): array {
            if ($businessId === 1) {
                return [
                    'can_destroy' => false,
                    'reason'      => 'Business #1 (Wagner) é protegido — nunca pode ser deletado.',
                ];
            }

            if ($businessId === $currentSessionBizId) {
                return [
                    'can_destroy' => false,
                    'reason'      => 'Self-destroy guard — superadmin não pode deletar próprio business da session.',
                ];
            }

            $total = (int) Business::count();
            if ($total <= 1) {
                return [
                    'can_destroy' => false,
                    'reason'      => 'Não é possível deletar o último business existente.',
                ];
            }

            return [
                'can_destroy' => true,
                'reason'      => 'Pré-condições ok pra destroy.',
            ];
        }, ['module' => 'Superadmin', 'service' => self::class, 'target_biz' => $businessId, 'session_biz' => $currentSessionBizId]);
    }

    /**
     * Aging summary de subscriptions por status (cron sweep input).
     *
     * @return array<string, int>  Mapa status => count
     */
    public function subscriptionAgingSummary(): array
    {
        return OtelHelper::spanBiz('superadmin.business_audit.sub_aging', function (): array {
            if (! \Illuminate\Support\Facades\Schema::hasTable('subscriptions')) {
                return ['waiting' => 0, 'approved' => 0, 'expired' => 0, 'cancelled' => 0];
            }

            $rows = DB::table('subscriptions')
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->get();

            $summary = ['waiting' => 0, 'approved' => 0, 'expired' => 0, 'cancelled' => 0];
            foreach ($rows as $r) {
                $key = (string) ($r->status ?? 'unknown');
                $summary[$key] = (int) $r->c;
            }

            return $summary;
        }, ['module' => 'Superadmin', 'service' => self::class]);
    }
}
