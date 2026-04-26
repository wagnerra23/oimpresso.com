<?php

declare(strict_types=1);

namespace App\Models\Evolution;

use Illuminate\Database\Eloquent\Model;

class EvalRun extends Model
{
    protected $table = 'vizra_eval_runs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'results_json' => 'array',
        'score_avg' => 'float',
        'run_at' => 'datetime',
    ];
}
