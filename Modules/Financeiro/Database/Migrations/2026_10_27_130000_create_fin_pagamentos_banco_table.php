<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento de título a pagar pelo banco — boleto ou Pix (US-FIN-079).
 *
 * Uma linha por PEDIDO ao banco, e ela é a trilha de auditoria: quem pediu, quando, quanto,
 * para onde, a conta debitada e o que o banco respondeu. A baixa no título só acontece quando
 * o banco confirma (`status = pago`); o pedido em si não mexe no título.
 * `idempotency_key` (UUID) segue até a `fin_titulo_baixas`, então a confirmação repetida não
 * baixa duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_pagamentos_banco')) {
            return;
        }

        Schema::create('fin_pagamentos_banco', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('titulo_id');
            $t->unsignedInteger('conta_bancaria_id');
            $t->string('meio', 10);
            $t->string('destino', 512);
            $t->decimal('valor', 22, 4);
            $t->char('idempotency_key', 36);
            $t->string('status', 20);
            $t->string('codigo_transacao', 80)->nullable();
            $t->string('motivo', 191)->nullable();
            $t->unsignedInteger('solicitado_por');
            $t->dateTime('solicitado_em');
            $t->dateTime('confirmado_em')->nullable();
            $t->unsignedBigInteger('baixa_id')->nullable();
            $t->json('resposta_banco')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'idempotency_key'], 'fin_pag_banco_biz_idem_uq');
            $t->index(['business_id', 'titulo_id']);
            $t->index(['business_id', 'status']);
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_pagamentos_banco');
    }
};
