<?php

namespace Modules\DocVault\Entities;

use Illuminate\Database\Eloquent\Model;

class DocLink extends Model
{
    protected $table = 'docs_links';
    protected $guarded = ['id'];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    public function evidence()
    {
        return $this->belongsTo(DocEvidence::class, 'evidence_id');
    }

    public function requirement()
    {
        return $this->belongsTo(DocRequirement::class, 'requirement_id');
    }
}
