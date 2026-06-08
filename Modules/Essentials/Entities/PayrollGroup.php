<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PayrollGroup extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION — folha)
    use LogsActivity;     // D7 LGPD audit Wave 27 — grupo de folha = referência fiscal

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
    protected $table = 'essentials_payroll_groups';

    /**
     * Auditoria LGPD (D7 Wave 27) — operações em grupos de folha.
     *
     * CLT Art. 11 (5 anos prescricional) + RFB Art. 195 (fiscal 5 anos).
     * Loga business_id + nome + location_id + payroll_for (período) + status.
     *
     * @see Modules\Essentials\Config\retention.php (payroll_group: 1825 dias)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_id', 'name', 'location_id', 'payroll_for', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.payroll_group');
    }

    /**
     * Get the transactions for the payroll group.
     */
    public function payrollGroupTransactions()
    {
        return $this->belongsToMany(\App\Transaction::class, 'essentials_payroll_group_transactions', 'payroll_group_id', 'transaction_id');
    }

    /**
     * Get the location that owns the payroll group.
     */
    public function businessLocation()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    /**
     * Get the business that owns the payroll group.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }
}
