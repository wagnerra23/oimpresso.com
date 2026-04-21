<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Marcação de ponto. Append-only conforme Portaria MTP 671/2021.
 * Triggers MySQL bloqueiam UPDATE/DELETE direto no banco.
 */
class Marcacao extends Model
{
    use HasFactory;
    protected $table = 'ponto_marcacoes';

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;  // só created_at, via default DB

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
        'colaborador_config_id',
        'rep_id',
        'nsr',
        'momento',
        'origem',
        'tipo',
        'marcacao_anulada_id',
        'dispositivo_id',
        'latitude',
        'longitude',
        'ip',
        'hash_anterior',
        'hash',
        'assinatura_digital',
        'usuario_criador_id',
        'created_at',
    ];

    protected $casts = [
        'momento'    => 'datetime',
        'created_at' => 'datetime',
        'latitude'   => 'decimal:7',
        'longitude'  => 'decimal:7',
    ];

    public const ORIGEM_REP_P      = 'REP_P';
    public const ORIGEM_AFD        = 'AFD';
    public const ORIGEM_AFDT       = 'AFDT';
    public const ORIGEM_MANUAL     = 'MANUAL';
    public const ORIGEM_INTEGRACAO = 'INTEGRACAO';
    public const ORIGEM_ANULACAO   = 'ANULACAO';

    public const TIPO_ENTRADA       = 'ENTRADA';
    public const TIPO_SAIDA         = 'SAIDA';
    public const TIPO_ALMOCO_INICIO = 'ALMOCO_INICIO';
    public const TIPO_ALMOCO_FIM    = 'ALMOCO_FIM';
    public const TIPO_INTERCORRENCIA = 'INTERCORRENCIA';

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_config_id');
    }

    public function rep(): BelongsTo
    {
        return $this->belongsTo(Rep::class, 'rep_id');
    }

    public function marcacaoAnulada(): BelongsTo
    {
        return $this->belongsTo(Marcacao::class, 'marcacao_anulada_id');
    }

    /**
     * Bloqueia qualquer update em nível de aplicação
     * (além da trigger de DB como defesa em profundidade).
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new RuntimeException(
            'Marcações são append-only. Para corrigir, use Marcacao::anular($motivo) e crie nova.'
        );
    }

    public function delete()
    {
        throw new RuntimeException('Marcações não podem ser deletadas (Portaria 671/2021).');
    }

    /**
     * Cria uma marcação de anulação apontando para esta, via MarcacaoService
     * (que cuida de hash encadeado + NSR + transação).
     */
    public function anular($usuarioId, $motivo)
    {
        return app(\Modules\PontoWr2\Services\MarcacaoService::class)
            ->anular($this, $usuarioId, $motivo);
    }
}
