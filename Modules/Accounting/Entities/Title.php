<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    protected $table = 'titles';
    public $timestamps = false;
    protected $fillable = [];
}
