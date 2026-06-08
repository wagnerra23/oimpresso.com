<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento SEFAZ aplicado a DFe recebido — append-only log de manifestações.
 *
 * Distinto de NfeEvento (eventos de NF-e EMITIDA) — SoC brutal por domínio.
 *
 * Tipos canônicos NT 2014.002:
 *   210210 — Ciência da Operação
 *   210200 — Confirmação da Operação
 *   210220 — Desconhecimento da Operação
 *   210240 — Operação não Realizada
 */
class NfeDfeEvento extends Model
{
    use HasBusinessScope;

    public const UPDATED_AT = null; // append-only

    public const TIPO_CIENCIA       = '210210';
    public const TIPO_CONFIRMACAO   = '210200';
    public const TIPO_DESCONHECIMENTO = '210220';
    public const TIPO_NAO_REALIZADA = '210240';

    public const TIPOS = [
        self::TIPO_CIENCIA,
        self::TIPO_CONFIRMACAO,
        self::TIPO_DESCONHECIMENTO,
        self::TIPO_NAO_REALIZADA,
    ];

    protected $table = 'nfe_dfe_eventos';

    protected $fillable = [
        'business_id',
        'dfe_recebido_id',
        'tipo',
        'justificativa',
        'status',
        'cstat_evento',
        'payload_json',
        'nseq_evento',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'nseq_evento'  => 'integer',
    ];

    public function dfeRecebido(): BelongsTo
    {
        return $this->belongsTo(NfeDfeRecebido::class, 'dfe_recebido_id');
    }

    public function isAutorizado(): bool
    {
        return $this->status === 'autorizado';
    }

    public function exigeJustificativa(): bool
    {
        return in_array($this->tipo, [self::TIPO_DESCONHECIMENTO, self::TIPO_NAO_REALIZADA], true);
    }
}
