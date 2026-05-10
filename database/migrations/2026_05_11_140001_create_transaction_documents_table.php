<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-014 — Multi-documento por venda (ADR 0129 §Schema + caso prático Comunicação Visual).
 *
 * 1 transaction (UltimatePOS) → N documentos fiscais (NFe55, NFCe65, NFSe56,
 * NFCom62, MDFe58, CTe57). Caso prático real: OS R$ [redacted Tier 0] = NFe55 R$ [redacted Tier 0] (banner)
 * + NFSe56 R$ [redacted Tier 0] (instalação) — ver memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md.
 *
 * Polimórfica: `doc_class` (FQCN) + `doc_id` permitem apontar pra qualquer
 * Model concreto (Modules\NfeBrasil\Models\NfeEmissao,
 * Modules\NfeBrasil\Models\NfseEmissao, etc) sem coluna nullable por tipo.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) via business_id indexado.
 *
 * Idempotência: UNIQUE(transaction_id, doc_type, doc_id) impede duplicação
 * acidental quando listener emite o mesmo documento 2x (race condition).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_documents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('transaction_id');
            $t->enum('doc_type', ['nfe55', 'nfce65', 'nfse56', 'nfcom62', 'mdfe58', 'cte57']);
            $t->string('doc_class', 255);
            $t->unsignedBigInteger('doc_id');
            $t->decimal('value_total', 22, 4);
            $t->timestamp('emitted_at')->nullable();
            $t->enum('status', ['pending', 'authorized', 'rejected', 'cancelled'])->default('pending');
            $t->timestamps();

            $t->unique(['transaction_id', 'doc_type', 'doc_id'], 'tx_docs_tx_type_doc_uq');
            $t->index(['business_id', 'transaction_id'], 'tx_docs_biz_tx_idx');
            $t->index(['business_id', 'doc_type', 'status'], 'tx_docs_biz_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_documents');
    }
};
