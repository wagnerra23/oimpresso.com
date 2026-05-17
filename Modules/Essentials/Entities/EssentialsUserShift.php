<?php

namespace Modules\Essentials\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

class EssentialsUserShift extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant via User->business_id (Wave 18 D1)

    /**
     * Resolve business_id via user (App\User tem business_id direto).
     */
    protected string $businessParentRelation = 'user';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }
}
