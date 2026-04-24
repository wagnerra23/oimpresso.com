<?php

namespace Modules\MemCofre\Entities;

use Illuminate\Database\Eloquent\Model;

class DocChatMessage extends Model
{
    protected $table = 'docs_chat_messages';

    protected $fillable = [
        'business_id',
        'user_id',
        'session_id',
        'role',
        'content',
        'module_context',
        'sources',
        'mode',
        'tokens_used',
    ];

    protected $casts = [
        'sources' => 'array',
    ];
}
