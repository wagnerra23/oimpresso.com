<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * MetaFonte — driver de cálculo (sql/php/http).
 *
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md pras regras de segurança.
 */
class MetaFonte extends Model
{
    protected $table = 'copiloto_meta_fontes';

    protected $fillable = [
        'meta_id', 'driver', 'config_json', 'cadencia',
    ];

    protected $casts = [
        'config_json' => 'array',
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
