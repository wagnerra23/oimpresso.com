<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Boleto DDA emitido contra o CNPJ da empresa — sugestão de conta a pagar (US-FIN-078).
 *
 * @property int $id
 * @property int $business_id
 * @property string $fonte
 * @property string $codigo_barras
 * @property string|null $beneficiario_documento
 * @property string|null $beneficiario_nome
 * @property string $valor
 * @property \Illuminate\Support\Carbon $vencimento
 * @property int|null $contact_id
 * @property string $status
 * @property int|null $titulo_id
 * @property int|null $decidido_por
 * @property \Illuminate\Support\Carbon|null $decidido_em
 * @property array<string, mixed>|null $raw_payload
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
