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
 * OS R$ [redacted Tier 0] = NFe55 (banner R$ [redacted Tier 0]) + NFSe56 (instalação R$ [redacted Tier 0]) — ver
 * memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) via HasBusinessScope.
 *
 * Polimorfismo: `document()` resolve pra Model concreto via doc_class+doc_id
 * (ex: Modules\NfeBrasil\Models\NfeEmissao, NfseEmissao, NfcomEmissao etc).
 *
 * Status individual por documento — cancelar NFe55 não afeta NFSe56 da mesma
 * transação. Cada documento tem seu próprio ciclo de vida fiscal.
 *
 * Doc types fiscais (NF-e/NFCe/NFSe/NFCom/MDFe/CTe) representam documentos
 * fiscais stricto sensu — emissão SEFAZ/prefeitura, XML autorizado, etc.
 *
 * Doc types de cobrança financeira (boleto_asaas, boleto_inter) representam
 * cargas de gateway de pagamento — NÃO são documento fiscal. São registrados
 * aqui pra unificar o ciclo de cancelamento (CancelarVendaCascade lê todos
 * os documentos da transaction e despacha estorno por tipo). Adicionados em
 * 2026_05_12_020001_alter_transaction_documents_add_boleto_types.php
 * (US-CASCADE-BOLETO-001 foundational).
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
     * Doc types de cobrança financeira via gateway de pagamento.
     *
     * Não são documentos fiscais stricto sensu — são charges Asaas / Inter PJ
     * registradas aqui pra unificar o ciclo de cancelamento via
     * CancelarVendaCascade + EstornarBoletoJob (US-CASCADE-BOLETO-001+).
     */
    public const DOC_BOLETO_ASAAS = 'boleto_asaas';

    public const DOC_BOLETO_INTER = 'boleto_inter';

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
