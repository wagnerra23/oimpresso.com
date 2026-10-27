<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Boleto DDA emitido contra o CNPJ da empresa — sugestão de conta a pagar (US-FIN-078).
 */
class DdaBoleto extends Model
{
    use BusinessScope;

    public const STATUS_SUGERIDO = 'sugerido';
    public const STATUS_ACEITO = 'aceito';
    public const STATUS_DESCARTADO = 'descartado';

    protected $table = 'fin_dda_boletos';

    protected $fillable = [
        'business_id', 'fonte', 'codigo_barras', 'beneficiario_documento', 'beneficiario_nome',
        'valor', 'vencimento', 'contact_id', 'status', 'titulo_id', 'decidido_por', 'decidido_em',
        'raw_payload',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'vencimento' => 'date',
        'decidido_em' => 'datetime',
        'raw_payload' => 'array',
    ];
}
