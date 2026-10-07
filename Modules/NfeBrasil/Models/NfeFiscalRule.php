<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
 *
 * Colunas da thread 07 (migração 2026_10_07_000001) — declaradas para a análise estática, que lê o
 * schema sem elas:
 * @property int|null $operacao_id
 * @property \Illuminate\Support\Carbon|null $valida_de
 * @property \Illuminate\Support\Carbon|null $valida_ate
 * @property int|null $versao_origem_id
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
        // GAP-FISCAL-004 / US-FISCAL-021 — Reforma Tributária NT 2025.002
        // Migration 2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules
        'c_class_trib', 'cst_ibs', 'cst_cbs',
        'aliquota_ibs', 'aliquota_cbs',
        // Playbook Fiscal thread 07 · operação + vigência (R-NFE-018..020d)
        'operacao_id', 'valida_de', 'valida_ate', 'versao_origem_id',
    ];

    protected $casts = [
        'aliquota_icms'   => 'float',
        'aliquota_pis'    => 'float',
        'aliquota_cofins' => 'float',
        'aliquota_ipi'    => 'float',
        'aliquota_ibs'    => 'float',
        'aliquota_cbs'    => 'float',
        'mva'             => 'float',
        'fcp'             => 'float',
        'metadata'        => 'array',
        'valida_de'       => 'date:Y-m-d',
        'valida_ate'      => 'date:Y-m-d',
    ];

    /** @var array<string,bool> cache por conexão — a migração da thread 07 rodou? */
    private static array $versionamento = [];

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }

    /**
     * A tabela tem as colunas de operação/vigência (migração 2026_10_07_000001)? Sem elas — schema
     * montado à mão em teste SQLite — o motor e a edição se comportam como antes da thread 07.
     */
    public static function temVersionamento(): bool
    {
        $conn = (new static())->getConnectionName() ?? config('database.default');

        return self::$versionamento[$conn] ??= Schema::connection($conn)->hasColumn('nfe_fiscal_rules', 'valida_de');
    }

    public static function esquecerVersionamento(): void
    {
        self::$versionamento = [];
    }

    /** Versões vigentes na data (Y-m-d): NULL nas pontas = sem limite. */
    public function scopeVigenteEm(Builder $q, string $data): Builder
    {
        return $q->where(fn (Builder $w) => $w->whereNull('valida_de')->orWhere('valida_de', '<=', $data))
            ->where(fn (Builder $w) => $w->whereNull('valida_ate')->orWhere('valida_ate', '>=', $data));
    }

    public function estaVigenteEm(string $data): bool
    {
        $de  = $this->valida_de?->format('Y-m-d');
        $ate = $this->valida_ate?->format('Y-m-d');

        return ($de === null || $de <= $data) && ($ate === null || $ate >= $data);
    }

    /**
     * R-NFE-019 · editar gera versão nova; a antiga continua lendo igual.
     *
     * A antiga só ganha `valida_ate` = ontem — nenhuma outra coluna dela muda. A nova copia tudo,
     * aplica `$dados`, nasce com `valida_de` = hoje e aponta `versao_origem_id` para a 1ª versão da
     * cadeia (o override por produto segue achando a vigente).
     *
     * O vínculo com `tax_rates` (ARQ-0005) PASSA para a versão nova em vez de o listener criar um
     * `tax_rate` por versão — senão o cadastro de impostos do UltimatePOS ganharia uma alíquota
     * repetida a cada edição. Os eventos de modelo ficam calados durante a troca e o `Updated` da
     * versão nova sincroniza os valores no `tax_rate` herdado.
     */
    public function novaVersao(array $dados, ?string $hoje = null): self
    {
        $hoje  ??= now()->toDateString();
        $ontem = date('Y-m-d', strtotime($hoje . ' -1 day'));

        return DB::transaction(function () use ($dados, $hoje, $ontem): self {
            $nova = $this->replicate(['created_at', 'updated_at', 'deleted_at']);
            $nova->fill($dados);
            $nova->business_id      = $this->business_id;
            $nova->setAttribute('valida_de', $hoje);
            $nova->setAttribute('valida_ate', null);
            $nova->versao_origem_id = $this->versao_origem_id ?? $this->id;

            $this->setAttribute('valida_ate', $ontem);
            $this->saveQuietly();
            $nova->saveQuietly();

            if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
                DB::table('nfe_fiscal_rule_tax_rate_links')
                    ->where('business_id', $this->business_id)
                    ->where('fiscal_rule_id', $this->id)
                    ->update(['fiscal_rule_id' => $nova->id, 'updated_at' => now()]);
            }

            FiscalRuleUpdated::dispatch($nova);

            return $nova;
        });
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
