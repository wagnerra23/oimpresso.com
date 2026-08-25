<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Subscription;

/**
 * SubscriptionLifecycleService — encapsula transições de status Subscription.
 *
 * Wave 18 RETRY — D4 boost. Subscription tem status linear simples
 * (waiting_approval → approved → expired) — não justifica FSM Pipeline ADR 0143
 * (declarado `fsm_n_a: true` em module.json), mas merece Service dedicado pra:
 *
 *   - Centralizar regra "quando approve cria audit trail automático"
 *   - Encapsular cálculo de end_date a partir de package.interval
 *   - Permitir mock em Pest sem precisar dispatch Asaas/PesaPal stub
 *
 * Wave 25 SATURATION — D9 boost: spans OTel canônicos por transição.
 * Zero-cost se `otel.enabled=false`. Em CT 100 com OTel collector ativo,
 * exporta tracing pra dashboard SRE — slice por `lifecycle.action` permite
 * spotting de regressão de approve/expire/cancel.
 *
 * Cross-tenant intencional (Superadmin Wagner-only).
 *
 * Spatie LogsActivity em Subscription model já registra os deltas — Service
 * apenas orquestra UPDATEs com escrita coerente (DB::transaction).
 *
 * @see Modules\Superadmin\Entities\Subscription
 * @see app\Util\OtelHelper
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (cross-tenant Superadmin)
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D9.a
 */
class SubscriptionLifecycleService
{
    /**
     * Aprova subscription pendente. Calcula end_date conforme package + gera audit.
     *
     * @return bool  true se transição efetuada; false se status incompatível.
     */
    public function approve(Subscription $subscription, ?Carbon $startDate = null): bool
    {
        return OtelHelper::spanBiz('superadmin.subscription.approve', function () use ($subscription, $startDate): bool {
            if ($subscription->status !== 'waiting' && $subscription->status !== 'waiting_approval') {
                return false;
            }

            $startDate = $startDate ?? now();

            return DB::transaction(function () use ($subscription, $startDate) {
                $packageDetails = (array) ($subscription->package_details ?? []);
                $intervalType = $packageDetails['interval'] ?? 'months';
                $intervalCount = (int) ($packageDetails['interval_count'] ?? 1);

                $endDate = match ($intervalType) {
                    'days'   => $startDate->copy()->addDays($intervalCount),
                    'months' => $startDate->copy()->addMonths($intervalCount),
                    'years'  => $startDate->copy()->addYears($intervalCount),
                    default  => $startDate->copy()->addMonth(),
                };

                $subscription->status = 'approved';
                $subscription->start_date = $startDate;
                $subscription->end_date = $endDate;
                $subscription->save();

                return true;
            });
        }, [
            'module' => 'Superadmin',
            'service' => self::class,
            'subscription_id' => $subscription->id ?? 0,
            'target_biz' => $subscription->business_id ?? 0,
            'lifecycle.action' => 'approve',
        ]);
    }

    /**
     * Marca subscription como expirada (cron diário ou manual).
     */
    public function expire(Subscription $subscription): bool
    {
        return OtelHelper::spanBiz('superadmin.subscription.expire', function () use ($subscription): bool {
            if ($subscription->status === 'expired') {
                return false;  // idempotente
            }

            if ($subscription->end_date && $subscription->end_date->isFuture()) {
                return false;  // ainda válida
            }

            $subscription->status = 'expired';
            $subscription->save();

            return true;
        }, [
            'module' => 'Superadmin',
            'service' => self::class,
            'subscription_id' => $subscription->id ?? 0,
            'target_biz' => $subscription->business_id ?? 0,
            'lifecycle.action' => 'expire',
        ]);
    }

    /**
     * Categorias aceitas em `subscriptions.cancel_reason` (2026_08_19_000002).
     *
     * Espelha o enum da migration. Motivo fora desta lista cai em `outro` e o texto original
     * é preservado em `cancel_note` — perder o que a pessoa escreveu é pior que categorizar
     * errado, e é o que acontecia até aqui: o motivo só virava `reason_len` num span.
     */
    public const MOTIVOS_CANCELAMENTO = ['preco', 'sem_uso', 'trocou_sistema', 'fechou', 'inadimplencia', 'outro'];

    /**
     * Cancela subscription (admin force; mantém audit + soft-delete).
     *
     * @param  string  $reason  categoria (uma de MOTIVOS_CANCELAMENTO) ou texto livre.
     *                          Texto que não bate com a lista vira `outro` + `cancel_note`.
     * @param  string  $nota    observação livre, sempre preservada como escrita.
     */
    public function cancel(Subscription $subscription, string $reason = '', string $nota = ''): bool
    {
        return OtelHelper::spanBiz('superadmin.subscription.cancel', function () use ($subscription, $reason, $nota): bool {
            if (in_array($subscription->status, ['cancelled', 'expired'], true)) {
                return false;
            }

            return DB::transaction(function () use ($subscription, $reason, $nota) {
                $subscription->status = 'cancelled';

                // O motivo passa a ser PERSISTIDO (SA-O1b, decisão [W] 2026-08-19). Antes ele
                // era recebido e descartado — o gráfico de motivos do F1 não tinha de onde sair.
                // Guarda de coluna: a migration pode não ter rodado no ambiente (lane reduzida),
                // e o cancelamento não pode falhar por causa da anotação.
                if (Schema::hasColumn('subscriptions', 'cancel_reason')) {
                    $categoria = in_array($reason, self::MOTIVOS_CANCELAMENTO, true) ? $reason : null;

                    // Sem motivo declarado fica NULL, não `outro`: a regra R10 do F1 manda deixar
                    // a saída sem motivo FORA do gráfico e dita em texto. Carimbar `outro` num
                    // cancelamento silencioso inventaria um dado que ninguém informou.
                    if ($categoria === null && $reason !== '') {
                        $categoria = 'outro';
                    }

                    $subscription->cancel_reason = $categoria;

                    // A nota preserva o texto original mesmo quando ele virou categoria — se
                    // alguém mandar texto livre, ele não se perde na tradução.
                    $livre = trim($nota !== '' ? $nota : ($categoria === 'outro' ? $reason : ''));
                    $subscription->cancel_note = $livre !== '' ? $livre : null;
                }

                $subscription->save();

                return true;
            });
        }, [
            'module' => 'Superadmin',
            'service' => self::class,
            'subscription_id' => $subscription->id ?? 0,
            'target_biz' => $subscription->business_id ?? 0,
            'lifecycle.action' => 'cancel',
            'reason_len' => strlen($reason),
        ]);
    }

    /**
     * Subscriptions com end_date no passado e status ainda approved (cron sweep).
     */
    public function findOverdueApproved(): \Illuminate\Database\Eloquent\Collection
    {
        return OtelHelper::spanBiz('superadmin.subscription.find_overdue', function (): \Illuminate\Database\Eloquent\Collection {
            // SUPERADMIN: cross-tenant intencional (cron sweep global).
            return Subscription::query()
                ->where('status', 'approved')
                ->whereDate('end_date', '<', now())
                ->get();
        }, ['module' => 'Superadmin', 'service' => self::class, 'lifecycle.action' => 'find_overdue']);
    }
}
