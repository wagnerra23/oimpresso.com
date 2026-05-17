<?php

namespace Modules\Essentials\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

class PayrollGroupTransaction extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant via PayrollGroup->business_id (Wave 18 D1)

    /**
     * Resolve business_id via payroll group.
     */
    protected string $businessParentRelation = 'payrollGroup';

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
    protected $table = 'essentials_payroll_group_transactions';

    /**
     * Payroll group parent (tenancy).
     */
    public function payrollGroup()
    {
        return $this->belongsTo(\Modules\Essentials\Entities\PayrollGroup::class, 'payroll_group_id');
    }
}
