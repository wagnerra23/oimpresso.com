<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

class EssentialsAttendance extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION)

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function employee()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(\Modules\Essentials\Entities\Shift::class, 'essentials_shift_id');
    }
}
