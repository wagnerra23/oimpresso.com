<?php

namespace Modules\DocVault\Entities;

use Illuminate\Database\Eloquent\Model;

class DocValidationRun extends Model
{
    protected $table = 'docs_validation_runs';

    protected $fillable = [
        'run_at',
        'module',
        'issues_total',
        'issues_critical',
        'issues',
        'health_score',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'issues' => 'array',
    ];
}
