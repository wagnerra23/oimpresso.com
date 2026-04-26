<?php

declare(strict_types=1);

namespace App\Models\Evolution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $table = 'vizra_agents';

    protected $guarded = [];

    protected $casts = [
        'config' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'agent_id');
    }
}
