<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Categoria extends Model
{
    use HasFactory, SoftDeletes, BusinessScope, LogsActivity;

    /**
     * Wave 17 D7 — audit trail de mudanças em categoria (LGPD Art. 16 + CTN
     * Art. 195). Categoria classifica titulos por finalidade (insumo, serviço,
     * marketing etc) — drift na categoria altera apuração tributária; audit
     * preserva trilha conforme retention.php config 'logs_audit_financeiro'.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'tipo', 'plano_conta_id', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.categoria');
    }

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
