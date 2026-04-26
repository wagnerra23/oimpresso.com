<?php

declare(strict_types=1);

namespace App\Models\Evolution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $table = 'vizra_evaluations';

    protected $guarded = [];

    protected $casts = [
        'golden_set_json' => 'array',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(EvalRun::class, 'evaluation_id');
    }
}
