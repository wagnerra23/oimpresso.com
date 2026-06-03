<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Append-only log de eventos recebidos via webhook Inter.
 * Idempotência via UNIQUE (business_id, event_hash).
 *
 * Não use Eloquent::delete — use soft mark via processed_status='descartado'
 * se precisar invalidar. Não tem updated_at — auditoria preserva o original.
 */
class InterWebhookEvent extends Model
{
    use BusinessScope;

    public const STATUS_OK = 'ok';
    public const STATUS_BOLETO_NAO_ENCONTRADO = 'boleto_nao_encontrado';
    public const STATUS_ERRO_BAIXA = 'erro_baixa';
    public const STATUS_DUPLICADO = 'duplicado';
    public const STATUS_IGNORADO = 'ignorado';

    public const SITUACAO_PAGO = 'PAGO';
    public const SITUACAO_RECEBIDO = 'RECEBIDO';
    public const SITUACAO_MARCADO_RECEBIDO = 'MARCADO_RECEBIDO';
    public const SITUACAO_A_RECEBER = 'A_RECEBER';
    public const SITUACAO_CANCELADO = 'CANCELADO';
    public const SITUACAO_EXPIRADO = 'EXPIRADO';

    protected $table = 'fin_inter_webhook_events';

    public $timestamps = false;

    protected $fillable = [
        'business_id', 'conta_bancaria_id', 'boleto_remessa_id', 'titulo_baixa_id',
        'event_hash', 'nosso_numero', 'codigo_solicitacao',
        'situacao', 'origem_recebimento', 'valor_recebido', 'data_situacao',
        'payload', 'processed_at', 'processed_status', 'processed_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'valor_recebido' => 'decimal:4',
        'data_situacao' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function boletoRemessa(): BelongsTo
    {
        return $this->belongsTo(BoletoRemessa::class, 'boleto_remessa_id');
    }

    public function tituloBaixa(): BelongsTo
    {
        return $this->belongsTo(TituloBaixa::class, 'titulo_baixa_id');
    }

    public function delete()
    {
        throw new \DomainException('fin_inter_webhook_events é append-only.');
    }

    public static function ehSituacaoPaga(string $situacao): bool
    {
        return in_array($situacao, [
            self::SITUACAO_PAGO,
            self::SITUACAO_RECEBIDO,
            self::SITUACAO_MARCADO_RECEBIDO,
        ], true);
    }
}
