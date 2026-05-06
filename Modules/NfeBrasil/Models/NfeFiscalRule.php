<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Regra tributária por (business, ncm, uf_origem, uf_destino?).
 *
 * Usada pelo cascade do MotorTributarioService (ADR ARQ-0006):
 *   Nível 2: regra exata — uf_destino NOT NULL
 *   Nível 3: regra padrão NCM — uf_destino IS NULL
 *
 * Multi-tenant: queries DEVEM escopear por business_id.
 */
class NfeFiscalRule extends Model
{
    use SoftDeletes;

    protected $table = 'nfe_fiscal_rules';

    protected $fillable = [
        'business_id',
        'ncm', 'uf_origem', 'uf_destino',
        'cfop', 'csosn', 'cst',
        'aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi',
        'mva', 'fcp',
        'metadata',
    ];

    protected $casts = [
        'aliquota_icms'   => 'float',
        'aliquota_pis'    => 'float',
        'aliquota_cofins' => 'float',
        'aliquota_ipi'    => 'float',
        'mva'             => 'float',
        'fcp'             => 'float',
        'metadata'        => 'array',
    ];

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }
}
