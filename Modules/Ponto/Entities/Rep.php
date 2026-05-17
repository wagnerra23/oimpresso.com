<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * REP (Registrador Eletronico de Ponto) — Portaria MTP 671/2021.
 *
 * Wave 18 D1 — Multi-tenant Tier 0 IRREVOGAVEL ([ADR 0093]):
 * trait HasBusinessScope aplica global scope automatico por business_id.
 * Tabela `ponto_reps` tem coluna business_id (migration 000002).
 *
 * REP e equipamento fisico/logico de marcacao por business — cross-tenant leak
 * permitiria spoofing de NSR sequencial entre empresas (incidente fiscal).
 */
class Rep extends Model
{
    use HasBusinessScope;

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
