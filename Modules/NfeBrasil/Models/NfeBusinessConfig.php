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
 *
 * US-NFE-006 / ADR TECH-0002 — estado de contingência POR TENANT. As anotações
 * abaixo existem porque Larastan não infere atributo dinâmico de Eloquent a partir
 * da migration: sem elas, todo acesso vira "undefined property" no ratchet do PHPStan.
 *
 * @property bool $em_contingencia
 * @property \Illuminate\Support\Carbon|null $contingencia_ativada_em
 * @property string|null $contingencia_motivo
 * @property string|null $contingencia_modo  US-NFE-071: epec | svc-an | svc-rs (nulo = epec)
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
        // US-NFE-071: epec | svc-an | svc-rs (Services\ModoContingencia). Nulo = epec.
        'contingencia_modo',
        // Envio automático do DANFE por empresa (2026-11-19). Nulo = segue a chave do servidor.
        'email_danfe_nfe', 'email_danfe_nfce',
    ];

    protected $casts = [
        'auto_emission_enabled' => 'boolean',
        'tributacao_default' => 'array',
        'em_contingencia' => 'boolean',
        'contingencia_ativada_em' => 'datetime',
        'email_danfe_nfe' => 'boolean',
        'email_danfe_nfce' => 'boolean',
    ];

    /**
     * A empresa manda o DANFE por e-mail ao autorizar a nota deste modelo?
     *
     * Escolha da empresa (`email_danfe_nfe` / `email_danfe_nfce`) quando gravada; senão a
     * chave do servidor, como era antes. Os listeners rodam em fila, sem sessão: o
     * `business_id` vem da própria nota.
     */
    public static function envioDanfeAtivo(int $businessId, string $modelo): bool
    {
        $nfce = $modelo === '65';
        $padrao = $nfce
            ? (bool) config('nfebrasil.email_danfe_nfce_on_autorizada', false)
            : (bool) config('nfebrasil.email_danfe_on_autorizada', true);

        try {
            // SUPERADMIN: listener em fila não tem sessão; filtra pelo business_id da nota.
            $escolha = static::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->value($nfce ? 'email_danfe_nfce' : 'email_danfe_nfe');
        } catch (\Illuminate\Database\QueryException) {
            return $padrao; // coluna ainda não migrada neste ambiente
        }

        return $escolha === null ? $padrao : (bool) $escolha;
    }

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }
}
