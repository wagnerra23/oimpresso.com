<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $guarded = [];

    protected $visible  = ['id', 'name', 'source_type'];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', '=', 1);
    }

    protected $casts = [
        'source_other_info' => 'json',
    ];
}
