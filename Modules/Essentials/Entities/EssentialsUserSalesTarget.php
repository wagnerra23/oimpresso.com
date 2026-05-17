<?php

namespace Modules\Essentials\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

class EssentialsUserSalesTarget extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant via User->business_id (Wave 18 D1)

    /**
     * Resolve business_id via user (meta de vendas por colaborador).
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
    protected $table = 'essentials_user_sales_targets';

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }
}
