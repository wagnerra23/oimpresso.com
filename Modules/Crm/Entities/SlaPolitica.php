<?php

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Prazo do chamado de garantia por tipo (categoria de acompanhamento) — US-CRM-094.
 * `followup_category_id` nulo é o prazo padrão da empresa.
 *
 * @property int $id
 * @property int $business_id
 * @property ?int $followup_category_id
 * @property int $prazo_horas
 * @property ?string $fila
 * @property ?int $responsavel_user_id
 * @property bool $ativo
 */
class SlaPolitica extends Model
{
    use HasBusinessScope; // ADR 0093

    protected $table = 'crm_sla_politicas';

    protected $guarded = ['id'];

    protected $casts = [
        'prazo_horas' => 'integer',
        'responsavel_user_id' => 'integer',
        'followup_category_id' => 'integer',
        'ativo' => 'boolean',
    ];
}
