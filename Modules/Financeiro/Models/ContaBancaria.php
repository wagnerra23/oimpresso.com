<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContaBancaria extends Model
{
    use HasFactory, SoftDeletes, BusinessScope, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'banco_codigo', 'agencia', 'conta', 'saldo_atual', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.conta_bancaria');
    }

    protected $table = 'fin_contas_bancarias';

    protected $fillable = [
        'business_id', 'nome', 'banco_codigo', 'agencia', 'conta', 'digito',
        'tipo', 'saldo_inicial', 'saldo_atual', 'saldo_data', 'ativo', 'metadata',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:4',
        'saldo_atual' => 'decimal:4',
        'saldo_data' => 'date',
        'ativo' => 'boolean',
        'metadata' => 'array',
    ];

    public function movimentos(): HasMany
    {
        return $this->hasMany(CaixaMovimento::class, 'conta_bancaria_id');
    }

    public function baixas(): HasMany
    {
        return $this->hasMany(TituloBaixa::class, 'conta_bancaria_id');
    }

    /**
     * Bloqueia hard delete se há histórico (TECH-0002).
     * Tenant deve INATIVAR (`ativo = false`), não deletar.
     */
    public function delete()
    {
        if ($this->movimentos()->exists()) {
            throw new \DomainException(
                "Conta '{$this->nome}' tem movimentos históricos e não pode ser removida. Inative em vez disso."
            );
        }

        return parent::delete();
    }
}
