<?php

namespace Modules\MemCofre\Entities;

use Illuminate\Database\Eloquent\Model;

class DocPage extends Model
{
    protected $table = 'docs_pages';

    protected $fillable = [
        'path',
        'component',
        'module',
        'status',
        'stories',
        'rules',
        'adrs',
        'tests',
        'file_path',
        'last_synced_at',
    ];

    protected $casts = [
        'stories'        => 'array',
        'rules'          => 'array',
        'adrs'           => 'array',
        'tests'          => 'array',
        'last_synced_at' => 'datetime',
    ];
}
