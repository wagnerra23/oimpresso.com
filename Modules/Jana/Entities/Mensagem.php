<?php

namespace Modules\Jana\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Mensagem — append-only (ver GLOSSARY).
 */
class Mensagem extends Model
{
    protected $table = 'jana_mensagens';

    protected $fillable = [
        'conversa_id', 'role', 'content', 'tokens_in', 'tokens_out',
    ];

    public const UPDATED_AT = null;

    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
