<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * US-NFSE-CANCEL-001 — Evento de cancelamento NFSe (espelha `nfe_eventos` mas
 * pra NFSe modelo 56).
 *
 * Diferente de NFe55/NFCe65 (evento SEFAZ tpEvento=110111 padronizado), NFSe
 * cancelamento varia por município:
 *   - ABRASF v2.04: SOAP `CancelarNfse` (lote ou unitário)
 *   - GINFES: SOAP próprio (legacy alguns municípios)
 *   - IPM/Tiplan: REST proprietário
 *   - nfse.gov.br/sefin: REST nacional (NT 2024-001, MEI obrigatório)
 *
 * Por isso `driver_key` (qual padrão usado) + `protocolo_municipal`
 * (identificador retornado pela prefeitura) + `payload_request/response`
 * (debug do round-trip).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` global scope via
 * HasBusinessScope. Cross-tenant tests biz=1 vs biz=99.
 *
 * @property int $id
 * @property int $business_id
 * @property int $nfse_emissao_id
 * @property string $driver_key
 * @property string $motivo
 * @property string $status  pendente|enviado|autorizado|rejeitado
 * @property string|null $protocolo_municipal
 * @property string|null $codigo_retorno
 * @property string|null $mensagem_retorno
 * @property \Illuminate\Support\Carbon|null $autorizado_em
 * @property array|null $payload_request
 * @property array|null $payload_response
 */
class NfseEventoCancelamento extends Model
{
    use HasBusinessScope;

    protected $table = 'nfse_eventos_cancelamento';

    protected $guarded = ['id'];

    public const STATUS_PENDENTE   = 'pendente';
    public const STATUS_ENVIADO    = 'enviado';
    public const STATUS_AUTORIZADO = 'autorizado';
    public const STATUS_REJEITADO  = 'rejeitado';

    protected $casts = [
        'payload_request'  => 'array',
        'payload_response' => 'array',
        'autorizado_em'    => 'datetime',
    ];

    public function emissao(): BelongsTo
    {
        return $this->belongsTo(NfseEmissao::class, 'nfse_emissao_id');
    }

    public function isAutorizado(): bool
    {
        return $this->status === self::STATUS_AUTORIZADO;
    }
}
