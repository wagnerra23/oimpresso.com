<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Assinatura de um negócio a um pacote (backoffice SaaS).
 *
 * As anotações abaixo cobrem as colunas que o código atribui direto — sem elas o PHPStan
 * acusa `property.notFound` com razão, porque o model usa `$guarded` e não declara nada.
 *
 * @property string|null $status         approved · waiting · declined · expired · cancelled
 * @property string|null $cancel_reason  categoria do cancelamento (2026_08_19_000002)
 * @property string|null $cancel_note    texto livre do cancelamento (2026_08_19_000002)
 */
class Subscription extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'package_details' => 'array',    ];

    /**
     * Auditoria LGPD D7.b — Wave 11 Superadmin.
     * Subscriptions tocam pagamento de TODOS tenants (cross-tenant intencional
     * Wagner-only). Mudança em status/dates/payment_transaction_id == evento
     * fiscal — append-only obrigatório (CC Art. 206 prescrição 10 anos).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('superadmin.subscription');
    }

    /**
     * Scope a query to only include approved subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Get the package that belongs to the subscription.
     */
    public function package()
    {
        return $this->belongsTo('\Modules\Superadmin\Entities\Package')
            ->withTrashed();
    }

    /**
     * Returns the active subscription details for a business
     *
     * Um business PODE ter mais de uma assinatura ativa ao mesmo tempo — nada no schema
     * impede, e em produção isso acontece. Por isso a ordenação é explícita: sem ela o
     * `first()` deixa a escolha para o plano de execução do MySQL, que na prática devolve
     * a de menor `id`, ou seja **a mais antiga**.
     *
     * Medido em produção em 2026-08-26 (leitura, SSH Hostinger), antes desta linha existir:
     *
     *   biz=1    id=1    start=2021-01-12  end=2099-12-31   1 chave `*_module`
     *            id=118  start=2025-04-04  end=2030-05-13  13 chaves `*_module`
     *   -> `active_subscription(1)` devolvia a de 2021, e o gate de módulo enxergava
     *      UMA chave (`officeimpresso`). As 12 restantes, marcadas na assinatura nova,
     *      estavam mortas: uma linha de 2021 com `end_date` em 2099 vencia o `first()`.
     *
     * Isso não é detalhe de listagem — é o portão de visibilidade de módulo do sistema
     * inteiro: `ModuleUtil::hasThePermissionInSubscription` sai daqui, e ele é consumido
     * em 300 pontos de 122 arquivos. Uma assinatura antiga esquecida apagava, em silêncio,
     * todo módulo habilitado depois dela.
     *
     * `start_date` antes de `id` porque a pergunta do domínio é "qual contrato vale hoje",
     * e o desempate por `id` só existe para o caso de duas começarem no mesmo dia — aí a
     * mais recentemente criada é a que representa a última decisão de quem vendeu.
     *
     * @param $business_id int
     * @return Response
     */
    public static function active_subscription($business_id)
    {
        $date_today = \Carbon::today()->toDateString();

        $subscription = Subscription::where('business_id', $business_id)
                            ->whereDate('start_date', '<=', $date_today)
                            ->whereDate('end_date', '>=', $date_today)
                            ->approved()
                            ->orderByDesc('start_date')
                            ->orderByDesc('id')
                            ->first();

        return $subscription;
    }

    /**
     * Returns the upcoming subscription details for a business
     *
     * @param $business_id int
     * @return Response
     */
    public static function upcoming_subscriptions($business_id)
    {
        $date_today = \Carbon::today();

        $subscription = Subscription::where('business_id', $business_id)
                            ->whereDate('start_date', '>', $date_today)
                            ->approved()
                            ->get();

        return $subscription;
    }

    /**
     * Returns the subscriptions waiting for approval for superadmin
     *
     * @param $business_id int
     * @return Response
     */
    public static function waiting_approval($business_id)
    {
        $subscriptions = Subscription::where('business_id', $business_id)
                            ->whereNull('start_date')
                            ->waiting()
                            ->get();

        return $subscriptions;
    }

    public static function end_date($business_id)
    {
        $date_today = \Carbon::today();

        $subscription = Subscription::where('business_id', $business_id)
                            ->approved()
                            ->select(DB::raw('MAX(end_date) as end_date'))
                            ->first();

        if (empty($subscription->end_date)) {
            return $date_today;
        } else {
            $end_date = $subscription->end_date->addDay();
            if ($date_today->lte($end_date)) {
                return $end_date;
            } else {
                return $date_today;
            }
        }
    }

    /**
     * Returns the list of packages status
     *
     * @return array
     */
    public static function package_subscription_status()
    {
        return ['approved' => trans('superadmin::lang.approved'), 'declined' => trans('superadmin::lang.declined'), 'waiting' => trans('superadmin::lang.waiting')];
    }

    /**
     * Get the created_by.
     */
    public function created_user()
    {
        return $this->belongsTo(\App\User::class, 'created_id');
    }

    /**
     * Get the subscription business relationship.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }
}
