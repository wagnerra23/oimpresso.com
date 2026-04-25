<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Boleto emitido por um Título. Estado independente do título: o título pode
 * estar "aberto" enquanto o boleto está "gerado", "pago", "cancelado", etc.
 */
class BoletoRemessa extends Model
{
    use HasFactory, SoftDeletes, BusinessScope;

    public const STATUS_GERADO_MOCK = 'gerado_mock';
    public const STATUS_GERADO = 'gerado';
    public const STATUS_ENVIADO = 'enviado';
    public const STATUS_REGISTRADO = 'registrado';
    public const STATUS_PAGO = 'pago';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';

    public const STRATEGY_CNAB_DIRECT = 'cnab_direct';
    public const STRATEGY_GATEWAY = 'gateway';
    public const STRATEGY_HYBRID = 'hybrid';

    protected $table = 'fin_boleto_remessas';

    protected $fillable = [
        'business_id', 'titulo_id', 'conta_bancaria_id',
        'nosso_numero', 'linha_digitavel', 'codigo_barras',
        'valor_total', 'vencimento',
        'status', 'pdf_path', 'enviado_em', 'pago_em',
        'strategy', 'idempotency_key', 'metadata',
    ];

    protected $casts = [
        'valor_total' => 'decimal:4',
        'vencimento' => 'date',
        'enviado_em' => 'datetime',
        'pago_em' => 'datetime',
        'metadata' => 'array',
    ];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }
}
