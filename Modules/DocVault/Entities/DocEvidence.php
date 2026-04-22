<?php

namespace Modules\DocVault\Entities;

use Illuminate\Database\Eloquent\Model;

class DocEvidence extends Model
{
    protected $table = 'docs_evidences';
    protected $guarded = ['id'];

    protected $casts = [
        'extracted_by_ai' => 'boolean',
        'ai_confidence'   => 'float',
        'triaged_at'      => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(DocSource::class, 'source_id');
    }

    public function links()
    {
        return $this->hasMany(DocLink::class, 'evidence_id');
    }

    public function requirements()
    {
        return $this->belongsToMany(
            DocRequirement::class,
            'docs_links',
            'evidence_id',
            'requirement_id'
        )->withPivot('role')->withTimestamps();
    }

    public function triager()
    {
        return $this->belongsTo(\App\User::class, 'triaged_by');
    }
}
