<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class WorkDetails extends Model
{
    protected $fillable = [
        'contact_id',
        'employer',
        'official_designation',
        'pf_number',
        'employment_terms',
        'basic_salary',
        'gross_salary',
        'net_salary',
        'date_of_employment',
        'date_of_contract_expiry',
    ];
}
