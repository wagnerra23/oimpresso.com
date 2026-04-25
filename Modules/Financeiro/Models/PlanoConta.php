<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;

class PlanoConta extends Model
{
    use HasFactory, SoftDeletes, BusinessScope;

    protected $table = 'fin_planos_conta';

    protected $fillable = [
        'business_id', 'codigo', 'nome', 'tipo', 'nivel',
        'parent_id', 'natureza', 'aceita_lancamento', 'protegido', 'ativo',
    ];

    protected $casts = [
        'aceita_lancamento' => 'boolean',
        'protegido' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PlanoConta::class, 'parent_id');
    }

    public function titulos(): HasMany
    {
        return $this->hasMany(Titulo::class, 'plano_conta_id');
    }

    /**
     * Bloqueia delete em conta protegida (TECH-0002).
     */
    public function delete()
    {
        if ($this->protegido) {
            throw new \DomainException(
                "Conta '{$this->codigo} {$this->nome}' é protegida e não pode ser removida. Inative em vez disso."
            );
        }

        if ($this->titulos()->exists()) {
            throw new \DomainException(
                "Conta '{$this->codigo}' tem títulos vinculados. Inative em vez disso."
            );
        }

        return parent::delete();
    }
}
