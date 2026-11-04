<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila do Receita Saúde (US-FISIO-023, etapa F3 §3.6). Guarda só a MARCA de que o recibo do
 * pagamento foi enviado: o pagamento, o valor e a data são do `transaction_payments` do núcleo
 * e não são copiados (regra do lote 4: nenhum financeiro paralelo). Sem linha = pendente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_health_receipts')) {
            Schema::create('fisio_health_receipts', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('payment_id')->comment('transaction_payments.id do núcleo');
                $t->enum('marca', ['enviado', 'retificar']);
                $t->string('receipt_number', 40)->nullable();
                $t->unsignedInteger('marked_by');
                $t->timestamp('marked_at');
                $t->timestamps();
                $t->unique(['business_id', 'payment_id'], 'fisio_health_receipts_biz_pay_uq');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_health_receipts');
    }
};
