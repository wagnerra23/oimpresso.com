<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

class EssentialsHoliday extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION)

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }
}
