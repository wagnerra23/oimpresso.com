<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalaTurno extends Model
{
    use HasFactory;
    protected $table = 'ponto_escala_turnos';

    protected $fillable = [
        'escala_id',
        'dia_semana',
        'hora_entrada',
        'hora_almoco_inicio',
        'hora_almoco_fim',
        'hora_saida',
    ];

    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class, 'escala_id');
    }
}
