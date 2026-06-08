<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use App\Utils\Util;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EssentialsAllowanceAndDeduction extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION — folha/RH)
    use LogsActivity;     // D7 LGPD audit Wave 27 — folha de pagamento PII forte

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'essentials_allowances_and_deductions';

    /**
     * Auditoria LGPD (D7 Wave 27) — cadastro de proventos/descontos folha.
     *
     * CLT Art. 11 + RFB 5 anos. Loga metadata fiscal (type, amount, amount_type)
     * + business_id. `description` pode ser livre — loga só hash via metadata se
     * necessário no futuro.
     *
     * @see Modules\Essentials\Config\retention.php (essentials_allowance_deduction: 1825 dias)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_id', 'description', 'type', 'amount', 'amount_type', 'applicable_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.allowance_deduction');
    }

    public function employees()
    {
        return $this->belongsToMany(\App\User::class, 'essentials_user_allowance_and_deductions', 'allowance_deduction_id', 'user_id');
    }

    public static function forDropdown($business_id)
    {
        $ads = EssentialsAllowanceAndDeduction::whereNull('applicable_date')
                    ->where('business_id', $business_id)
                    ->select('id', 'description', 'type', 'amount', 'amount_type')
                    ->get();

        $util = new Util();
        $pay_components = [];
        foreach ($ads as $ad) {
            if ($ad->amount_type != 'percent') {
                $amount = $util->num_f($ad->amount, true);
            } else {
                $amount = $util->num_f($ad->amount);
                $amount .= '%';
            }

            $pay_components[$ad->id] = $ad->description.' ('.$amount.' '.__('essentials::lang.'.$ad->type).')';
        }

        return $pay_components;
    }
}
