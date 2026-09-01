<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Config fiscal 1:1 do business — alimenta cascade Nível 4 do
 * MotorTributarioService (ADR ARQ-0006).
 *
 * `tributacao_default` JSON contém:
 *   {
 *     "csosn"?:        "102",
 *     "cst"?:          "000",
 *     "cfop":          "5102",
 *     "aliquota_icms": 0.0,
 *     "aliquota_pis":  0.0,
 *     "aliquota_cofins": 0.0,
 *     "aliquota_ipi":  0.0
 *   }
 *
 * Pré-populado pelo wizard de onboarding por regime (MEI/Simples/Presumido/Real).
 */
class NfeBusinessConfig extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_business_configs';

    protected $fillable = [
        'business_id', 'regime', 'auto_emission_enabled', 'tributacao_default',
        // US-FISCAL-021 (PR-C): flag Reforma Tributária. legacy (default) | hybrid_2026 | full.
        'reforma_tributaria_modo',
        // US-NFE-006 / ADR TECH-0002: contingência é decisão POR TENANT (a ADR rejeitou
        // auto-ativação). A observação de que a SEFAZ caiu é global, em nfe_sefaz_status.
        'em_contingencia', 'contingencia_ativada_em', 'contingencia_motivo',
    ];

    protected $casts = [
        'auto_emission_enabled' => 'boolean',
        'tributacao_default' => 'array',
        'em_contingencia' => 'boolean',
        'contingencia_ativada_em' => 'datetime',
    ];

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }
}
