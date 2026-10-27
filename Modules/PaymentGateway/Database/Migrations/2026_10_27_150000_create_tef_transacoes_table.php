<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-075 — cada cobrança enviada à maquininha a partir do caixa.
 *
 * Uma linha por pedido de cobrança: o caixa manda o valor, o driver TEF
 * (independente da adquirente) devolve aprovação, bandeira, NSU e autorização.
 * A venda ainda não existe quando a máquina aprova, por isso `transaction_id`
 * nasce nulo e é preenchido depois.
 *
 * Idempotência: (business_id, idempotency_key) UNIQUE — o mesmo clique
 * reenviado não cobra duas vezes.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NOT NULL + índice; global scope
 * no model via HasBusinessScope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tef_transacoes')) {
            return;
        }

        Schema::create('tef_transacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('transaction_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();

            $table->string('driver', 40);
            $table->string('terminal_id', 80)->nullable();
            $table->unsignedInteger('valor_centavos');
            $table->string('modalidade', 10); // credito | debito | pix
            $table->unsignedTinyInteger('parcelas')->default(1);

            $table->string('status', 12)->default('pendente'); // pendente | aprovada | negada | cancelada | erro
            $table->string('bandeira', 30)->nullable();
            $table->string('nsu', 40)->nullable();
            $table->string('autorizacao', 40)->nullable();
            $table->string('externo_id', 80)->nullable();
            $table->string('mensagem', 255)->nullable();

            $table->string('idempotency_key', 64);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'tef_transacoes_biz_idem_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tef_transacoes');
    }
};
