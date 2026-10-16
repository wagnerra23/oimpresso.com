<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Porte, atividade e modelo de fluxo escolhido por empresa (ADR 0435).
 *
 * `modelo = legado` = a empresa usa os processos de antes do catálogo (venda_com_producao e cia.).
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 */
class ModeloEmpresa extends Model
{
    use HasBusinessScope;

    protected $table = 'fsm_modelos_empresa';

    protected $guarded = ['id'];

    protected $casts = [
        'aplicado_em' => 'datetime',
    ];
}
