<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Documento fiscal emitido a partir de uma transaction (ADR 0129 §Schema).
 *
 * 1 transaction → N TransactionDocument (poly N:1). Caso prático canônico:
 * OS R$ 550 = NFe55 (banner R$ 350) + NFSe56 (instalação R$ 200) — ver
 * memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) via HasBusinessScope.
 *
 * Polimorfismo: `document()` resolve pra Model concreto via doc_class+doc_id
 * (ex: Modules\NfeBrasil\Models\NfeEmissao, NfseEmissao, NfcomEmissao etc).
 *
 * Status individual por documento — cancelar NFe55 não afeta NFSe56 da mesma
 * transação. Cada documento tem seu próprio ciclo de vida fiscal.
 */
class TransactionDocument extends Model
{
    use HasBusinessScope;

    protected $table = 'transaction_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'value_total' => 'decimal:4',
        'emitted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const DOC_NFE55 = 'nfe55';

    public const DOC_NFCE65 = 'nfce65';

    public const DOC_NFSE56 = 'nfse56';

    public const DOC_NFCOM62 = 'nfcom62';

    public const DOC_MDFE58 = 'mdfe58';

    public const DOC_CTE57 = 'cte57';

    /**
     * Relacionamento polimórfico — resolve doc_class+doc_id pro Model concreto.
     *
     * Ex.: $td->document retorna Modules\NfeBrasil\Models\NfeEmissao instance.
     */
    public function document(): MorphTo
    {
        return $this->morphTo('document', 'doc_class', 'doc_id');
    }

    /**
     * Scope: documentos de uma transaction específica.
     *
     * Uso: TransactionDocument::forTransaction(123)->get();
     */
    public function scopeForTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }
}
