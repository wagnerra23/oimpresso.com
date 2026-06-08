<?php

namespace Modules\Essentials\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

class EssentialsUserAllowancesAndDeduction extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant via User->business_id (Wave 18 D1)

    /**
     * Resolve business_id via user (folha de pagamento por colaborador).
     */
    protected string $businessParentRelation = 'user';

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
    protected $table = 'essentials_user_allowance_and_deductions';

    /**
     * Relação parent pra resolução de tenancy via FK chain.
     */
    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    /**
     * Allowance/dedução master (cadastro).
     */
    public function allowanceDeduction()
    {
        return $this->belongsTo(\Modules\Essentials\Entities\EssentialsAllowanceAndDeduction::class, 'allowance_deduction_id');
    }
}
