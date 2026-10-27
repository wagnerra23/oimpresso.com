<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pedido de exportação dos dados de uma empresa (US-BKP-001).
 * Sem global scope: toda leitura passa por `daEmpresa()` com o business_id explícito
 * (o job roda sem sessão). Tier 0, ADR 0093.
 */
class ExportacaoEmpresa extends Model
{
    protected $table = 'exportacoes_empresa';

    protected $guarded = ['id'];

    protected $casts = [
        'manifesto' => 'array',
        'expira_em' => 'datetime',
        'baixado_em' => 'datetime',
    ];

    public function scopeDaEmpresa($q, int $businessId)
    {
        return $q->where('business_id', $businessId);
    }
}
