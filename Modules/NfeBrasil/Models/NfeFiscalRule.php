<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;

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
    use HasBusinessScope;

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

    /**
     * Eloquent boot — dispatch dos events que o Listener `SyncFiscalRuleToTaxRate`
     * (ADR ARQ-0005) consome pra manter `tax_rates` core sincronizada.
     *
     * `static::created()` em vez de `creating()` pra ter $rule->id disponível.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(function (self $rule) {
            FiscalRuleCreated::dispatch($rule);
        });

        static::updated(function (self $rule) {
            FiscalRuleUpdated::dispatch($rule);
        });

        static::deleted(function (self $rule) {
            FiscalRuleDeleted::dispatch($rule->id, (int) $rule->business_id);
        });
    }
}
