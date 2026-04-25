<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Copiloto\Scopes\ScopeByBusiness;

class Conversa extends Model
{
    protected $table = 'copiloto_conversas';

    protected $fillable = [
        'business_id', 'user_id', 'titulo', 'status', 'iniciada_em',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ScopeByBusiness);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class, 'conversa_id');
    }

    public function sugestoes(): HasMany
    {
        return $this->hasMany(Sugestao::class, 'conversa_id');
    }
}
