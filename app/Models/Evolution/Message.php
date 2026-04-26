<?php

declare(strict_types=1);

namespace App\Models\Evolution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $table = 'vizra_messages';

    protected $guarded = [];

    protected $casts = [
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function traces(): HasMany
    {
        return $this->hasMany(Trace::class, 'message_id');
    }
}
