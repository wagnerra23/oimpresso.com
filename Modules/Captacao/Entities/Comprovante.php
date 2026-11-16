<?php

namespace Modules\Captacao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Despesa executada no projeto (captacao_comprovantes), base da prestação de contas. O arquivo
 * mora no Modules/Arquivos. Não se apaga: sai da lista com `removido_em` + motivo (ADR 0452 D4).
 */
class Comprovante extends Model
{
    use HasBusinessScope;

    protected $table = 'captacao_comprovantes';

    protected $fillable = [
        'business_id', 'projeto_id', 'aporte_id', 'descricao', 'rubrica', 'valor', 'pago_em', 'arquivo_id',
        'removido_em', 'removido_motivo', 'removido_por',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'pago_em' => 'date',
        'removido_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Comprovante não se apaga: preencha removido_em e removido_motivo.'));
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }
}
