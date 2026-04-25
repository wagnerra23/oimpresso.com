<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;

class Categoria extends Model
{
    use HasFactory, SoftDeletes, BusinessScope;

    protected $table = 'fin_categorias';

    protected $fillable = [
        'business_id', 'nome', 'cor', 'plano_conta_id', 'tipo', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'plano_conta_id');
    }

    public function titulos(): HasMany
    {
        return $this->hasMany(Titulo::class, 'categoria_id');
    }
}
