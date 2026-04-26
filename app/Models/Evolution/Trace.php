<?php

declare(strict_types=1);

namespace App\Models\Evolution;

use Illuminate\Database\Eloquent\Model;

class Trace extends Model
{
    protected $table = 'vizra_traces';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'input_json' => 'array',
        'output_json' => 'array',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
