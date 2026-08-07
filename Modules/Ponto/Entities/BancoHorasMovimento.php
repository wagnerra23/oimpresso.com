<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Movimentos de banco de horas — append-only.
 *
 * Wave 18 D1 — Multi-tenant Tier 0 IRREVOGAVEL ([ADR 0093]):
 * trait HasBusinessScope aplica global scope automatico por business_id.
 * Cross-tenant leak = vazamento de saldo HE entre empresas (incidente CLT).
 *
 * Wave 26 D7 — LogsActivity Spatie pra audit trail de CRIAÇÃO de movimentos.
 * UPDATE/DELETE bloqueados (append-only override) — mas registro de "quem criou",
 * "quando", "tipo", "minutos" é fundamental pra defesa trabalhista (CLT Art. 11
 * prescrição quinquenal). LGPD Art. 37 (DPO) + retention 5 anos (retention.php).
 *
 * NÃO loga `data_referencia` ou `saldo_posterior_minutos` (derivados — re-deriváveis).
 */
class BancoHorasMovimento extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    /**
     * Wave 26 D7 — audit trail de CRIAÇÃO de movimento (rastreabilidade ledger).
     *
     * Loga apenas mudanças de:
     * - tipo (CREDITO/DEBITO/PAGAMENTO/EXPIRACAO/AJUSTE — eSocial relevância)
     * - minutos (valor do movimento — fundamental defesa trabalhista)
     * - multiplicador (HE 1.5x / 2x — Reforma Trabalhista)
     * - observacao (justificativa textual — proteção LGPD com retention 5y)
     * - usuario_id (responsável — accountability auditor fiscal)
     *
     * Como UPDATE é bloqueado (RuntimeException) o log captura na prática
     * apenas evento `created` — defesa em profundidade audit do "quem fez".
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tipo',
                'minutos',
                'multiplicador',
                'observacao',
                'usuario_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto_banco_horas_movimento');
    }

    protected $table = 'ponto_banco_horas_movimentos';

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

        // US-PONTO-011 — o override de update()/delete() abaixo NÃO cobre `save()`.
        //
        // Por quê: `Model::save()` em registro existente chama `performUpdate()`
        // DIRETO, sem passar pelo método público `update()`. Logo
        // `$mov->minutos = 999; $mov->save()` GRAVAVA, e a `Marcacao` só não sofre
        // do mesmo porque tem trigger MySQL — esta tabela não tinha (SDD §9 D-6).
        // Uma camada só, e com buraco.
        //
        // Provado pelo `UC-BHSHOW-01` (escrito em 2026-07-27, reprovou na lane em
        // 2026-08-03: "Failed asserting that true is false"). Ficou invisível
        // porque a lane não rodava havia >100 runs.
        //
        // `updating` (e não `saving`) porque é o evento que `performUpdate()`
        // dispara — `saving` também pegaria o INSERT, que é legítimo. Closure e
        // não Observer: `static::observe()` dentro de boot dispara
        // "bootIfNotBooted may not be called" (lição do hotfix #639).
        static::updating(function () {
            throw new RuntimeException(
                'Movimentos de banco de horas são append-only — nem por save(). '
                . 'Corrigir saldo é ACRESCENTAR movimento (tipo AJUSTE), nunca editar '
                . 'o anterior: o extrato é prova em reclamatória (CLT Art. 59 §5º).'
            );
        });
    }

    protected $fillable = [
        'business_id', 'colaborador_config_id', 'data_referencia',
        'tipo', 'minutos', 'multiplicador', 'saldo_posterior_minutos',
        'apuracao_dia_id', 'intercorrencia_id', 'observacao',
        'usuario_id', 'created_at',
    ];

    protected $casts = [
        'data_referencia' => 'date',
        'multiplicador'   => 'decimal:2',
        'created_at'      => 'datetime',
    ];

    public const TIPO_CREDITO   = 'CREDITO';
    public const TIPO_DEBITO    = 'DEBITO';
    public const TIPO_PAGAMENTO = 'PAGAMENTO';
    public const TIPO_EXPIRACAO = 'EXPIRACAO';
    public const TIPO_AJUSTE    = 'AJUSTE';

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_config_id');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new RuntimeException('Movimentos de banco de horas são append-only.');
    }

    public function delete()
    {
        throw new RuntimeException('Movimentos de banco de horas não podem ser deletados.');
    }
}
