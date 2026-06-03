<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log idempotente de eventos recebidos via webhook Inter.
 *
 * Por que idempotência: Inter pode redeliver o mesmo evento N vezes (timeout
 * de rede, retry policy deles). UNIQUE(business_id, event_hash) garante que
 * processamos cada notificação 1× só.
 *
 * event_hash = SHA-256 do JSON do item dentro do batch que o Inter manda.
 * Inter v3 não tem um event_id estável próprio — o hash do payload é o
 * melhor proxy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_inter_webhook_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('conta_bancaria_id')->index();
            $table->unsignedBigInteger('boleto_remessa_id')->nullable()->index();
            $table->unsignedBigInteger('titulo_baixa_id')->nullable()->index();

            $table->char('event_hash', 64)->comment('SHA-256 hex do JSON do item');
            $table->string('nosso_numero', 50)->nullable()->index();
            $table->string('codigo_solicitacao', 50)->nullable()->index()
                ->comment('codigoCobranca / codigoSolicitacao retornado pelo Inter v3');
            $table->string('situacao', 30)->index()
                ->comment('PAGO, A_RECEBER, CANCELADO, EXPIRADO, MARCADO_RECEBIDO, RECEBIDO');
            $table->string('origem_recebimento', 20)->nullable()
                ->comment('BOLETO ou PIX');
            $table->decimal('valor_recebido', 15, 4)->nullable();
            $table->timestamp('data_situacao')->nullable();
            $table->json('payload')->comment('JSON cru do item dentro do batch');
            $table->timestamp('processed_at')->nullable()
                ->comment('NULL = recebido mas ainda não casou com BoletoRemessa');
            $table->string('processed_status', 30)->nullable()
                ->comment('ok / boleto_nao_encontrado / erro_baixa / duplicado');
            $table->text('processed_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['business_id', 'event_hash'], 'uk_biz_event_hash');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('conta_bancaria_id')->references('id')->on('fin_contas_bancarias')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_inter_webhook_events');
    }
};
