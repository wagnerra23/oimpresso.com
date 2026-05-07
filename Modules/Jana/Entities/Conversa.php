<?php

namespace Modules\Jana\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversa extends Model
{
    use HasBusinessScope;

    protected $table = 'jana_conversas';

    protected $fillable = [
        'business_id', 'user_id', 'titulo', 'status', 'iniciada_em',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
    ];

    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class, 'conversa_id');
    }

    public function sugestoes(): HasMany
    {
        return $this->hasMany(Sugestao::class, 'conversa_id');
    }
}
