<?php

namespace Modules\Captacao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Recurso que entrou num projeto (captacao_aportes): dinheiro, bens, serviços ou horas de
 * voluntário (ADR 0452 D4/D8). Não se apaga: sai da lista com `removido_em` + motivo.
 */
class Aporte extends Model
{
    use HasBusinessScope;

    public const NATUREZAS = ['financeiro', 'bens', 'servicos', 'horas_voluntarias'];

    protected $table = 'captacao_aportes';

    protected $fillable = [
        'business_id', 'projeto_id', 'fonte_id', 'natureza', 'contact_id', 'descricao', 'valor', 'horas',
        'recebido_em', 'removido_em', 'removido_motivo', 'removido_por',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'horas' => 'decimal:2',
        'recebido_em' => 'date',
        'removido_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Aporte não se apaga: preencha removido_em e removido_motivo.'));
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function fonte(): BelongsTo
    {
        return $this->belongsTo(Fonte::class, 'fonte_id');
    }
}
