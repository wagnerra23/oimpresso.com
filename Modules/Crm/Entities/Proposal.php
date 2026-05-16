<?php

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (defesa-em-profundidade; soma ao where('business_id') explícito dos Controllers)

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_proposals';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }
}
