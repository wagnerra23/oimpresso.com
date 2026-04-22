<?php

namespace Modules\DocVault\Entities;

use Illuminate\Database\Eloquent\Model;

class DocRequirement extends Model
{
    protected $table = 'docs_requirements';
    protected $guarded = ['id'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function evidences()
    {
        return $this->belongsToMany(
            DocEvidence::class,
            'docs_links',
            'requirement_id',
            'evidence_id'
        )->withPivot('role')->withTimestamps();
    }
}
