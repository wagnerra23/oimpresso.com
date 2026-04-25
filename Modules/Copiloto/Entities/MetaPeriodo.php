<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

class MetaPeriodo extends Model
{
    protected $table = 'copiloto_meta_periodos';

    protected $fillable = [
        'meta_id', 'tipo_periodo', 'data_ini', 'data_fim', 'valor_alvo', 'trajetoria',
    ];

    protected $casts = [
        'data_ini' => 'date',
        'data_fim' => 'date',
        'valor_alvo' => 'decimal:2',
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
