<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * REP (Registrador Eletronico de Ponto) — Portaria MTP 671/2021.
 *
 * Wave 18 D1 — Multi-tenant Tier 0 IRREVOGAVEL ([ADR 0093]):
 * trait HasBusinessScope aplica global scope automatico por business_id.
 * Tabela `ponto_reps` tem coluna business_id (migration 000002).
 *
 * REP e equipamento fisico/logico de marcacao por business — cross-tenant leak
 * permitiria spoofing de NSR sequencial entre empresas (incidente fiscal).
 *
 * Wave 26 D7 — LogsActivity Spatie pra audit trail CADASTRAL do REP.
 * REP é cadastro mutável (descrição, local, certificado renovado, ativação/desativação)
 * — diferente de Marcação append-only. Audit trail necessário pra fiscalização Auditor
 * Fiscal do Trabalho (Portaria 671 Art. 85 — rastreabilidade do equipamento).
 *
 * NÃO loga `ultimo_nsr` (volume alto, irrelevante operacional) nem `certificado_info`
 * inteiro (PII estrutural certificado A1 — guarda só evento de troca via `ativo` toggle).
 */
class Rep extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    /**
     * Wave 26 D7 — audit trail cadastral REP (fiscalização Portaria 671 Art. 85).
     *
     * Loga apenas mudanças de:
     * - tipo (REP_P/REP_C/REP_A — mudança rara, eSocial relevância)
     * - identificador (mudança = swap fisico do equipamento)
     * - descricao / local (movimentação fisica do REP)
     * - cnpj (rara — mudança CNPJ filial)
     * - ativo (ativação/desativação — evento auditável crítico)
     *
     * NÃO loga: ultimo_nsr (alta frequência), certificado_info (PII certificado A1).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tipo',
                'identificador',
                'descricao',
                'local',
                'cnpj',
                'ativo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto_rep');
    }

    protected $table = 'ponto_reps';

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'business_id',
        'tipo',
        'identificador',
        'descricao',
        'local',
        'cnpj',
        'ultimo_nsr',
        'certificado_info',
        'ativo',
    ];

    protected $casts = [
        'certificado_info' => 'array',
        'ativo'            => 'boolean',
    ];

    public const TIPO_REP_P = 'REP_P';
    public const TIPO_REP_C = 'REP_C';
    public const TIPO_REP_A = 'REP_A';

    public function marcacoes(): HasMany
    {
        return $this->hasMany(Marcacao::class, 'rep_id');
    }

    public function proximoNsr(): int
    {
        return ++$this->ultimo_nsr;
    }
}
