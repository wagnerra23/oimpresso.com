<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class ClientRelationship extends Model
{
    protected $table = 'client_relationships';
    public $timestamps = false;
    protected $fillable = [];
}
