<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escala extends Model
{
    use HasFactory;
    protected $table = 'ponto_escalas';

    protected $fillable = [
        'business_id',
        'nome',
        'codigo',
        'tipo',
        'carga_diaria_minutos',
        'carga_semanal_minutos',
        'permite_banco_horas',
        'dias_semana',
        'horarios_padrao',
        'ativo',
    ];

    protected $casts = [
        'dias_semana'         => 'array',
        'horarios_padrao'     => 'array',
        'permite_banco_horas' => 'boolean',
        'ativo'               => 'boolean',
    ];

    public const TIPO_FIXA         = 'FIXA';
    public const TIPO_FLEXIVEL     = 'FLEXIVEL';
    public const TIPO_ESCALA_12X36 = 'ESCALA_12X36';
    public const TIPO_ESCALA_6X1   = 'ESCALA_6X1';
    public const TIPO_ESCALA_5X2   = 'ESCALA_5X2';

    public function turnos(): HasMany
    {
        return $this->hasMany(EscalaTurno::class, 'escala_id');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'escala_atual_id');
    }
}
