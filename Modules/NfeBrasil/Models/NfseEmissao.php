<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * US-NFE-060 · Emissão NFSe modelo 56 nacional (NT 2024-001).
 *
 * Padrão único `nfse.gov.br/sefin` — substitui emissores municipais legacy
 * (Tinus, Issnet, Ginfes). Obrigatório MEI desde 09/2023; demais regimes
 * em fases 2025-2026.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` global scope via trait
 * HasBusinessScope (cross-tenant tests biz=1 vs biz=99 garantem isolamento).
 *
 * Status canônicos:
 *   pending     — registro criado, ainda não enviado
 *   sent        — XML enviado, aguarda autorização
 *   authorized  — autorizada (numero_nfse + codigo_verificacao populados)
 *   rejected    — rejeitada (ver error_msg)
 *   cancelled   — cancelada via evento posterior
 *
 * @see Modules\NfeBrasil\Jobs\EmitirNFSeJob
 * @see memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md
 */
class NfseEmissao extends Model
{
    use HasBusinessScope;

    protected $table = 'nfse_emissoes';

    protected $guarded = ['id'];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_SENT       = 'sent';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $casts = [
        'value_servico' => 'decimal:4',
        'value_iss'     => 'decimal:4',
        'aliquota_iss'  => 'decimal:4',
        'emitted_at'    => 'datetime',
    ];

    public function isAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }
}
