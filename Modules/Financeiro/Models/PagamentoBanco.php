<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Pedido de pagamento de título a pagar pelo banco (US-FIN-079). Trilha de auditoria.
 *
 * @property int $id
 * @property int $business_id
 * @property int $titulo_id
 * @property int $conta_bancaria_id
 * @property string $meio
 * @property string $destino
 * @property string $valor
 * @property string $idempotency_key
 * @property string $status
 * @property string|null $codigo_transacao
 * @property string|null $motivo
 * @property int $solicitado_por
 * @property \Illuminate\Support\Carbon $solicitado_em
 * @property \Illuminate\Support\Carbon|null $confirmado_em
 * @property int|null $baixa_id
 * @property array<string, mixed>|null $resposta_banco
 */
class PagamentoBanco extends Model
{
    use BusinessScope, LogsActivity;

    public const MEIOS = ['boleto', 'pix'];

    public const STATUS_ENVIANDO = 'enviando';
    public const STATUS_AGUARDANDO = 'aguardando_banco';
    public const STATUS_PAGO = 'pago';
    public const STATUS_RECUSADO = 'recusado';
    public const STATUS_ERRO = 'erro';

    /** Status que ainda podem virar pagamento — bloqueiam um segundo pedido no mesmo título. */
    public const STATUS_EM_ABERTO = [self::STATUS_ENVIANDO, self::STATUS_AGUARDANDO];

    protected $table = 'fin_pagamentos_banco';

    protected $fillable = [
        'business_id', 'titulo_id', 'conta_bancaria_id', 'meio', 'destino', 'valor',
        'idempotency_key', 'status', 'codigo_transacao', 'motivo', 'solicitado_por',
        'solicitado_em', 'confirmado_em', 'baixa_id', 'resposta_banco',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'solicitado_em' => 'datetime',
        'confirmado_em' => 'datetime',
        'resposta_banco' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'valor', 'meio', 'codigo_transacao', 'motivo', 'baixa_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.pagamento_banco');
    }
}
