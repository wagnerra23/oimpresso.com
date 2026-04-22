<?php

namespace Modules\DocVault\Entities;

use Illuminate\Database\Eloquent\Model;

class DocSource extends Model
{
    protected $table = 'docs_sources';
    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function evidences()
    {
        return $this->hasMany(DocEvidence::class, 'source_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }
}
