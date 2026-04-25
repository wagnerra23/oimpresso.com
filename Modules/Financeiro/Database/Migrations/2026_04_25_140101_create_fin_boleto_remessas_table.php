<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boleto emitido (gerado/enviado/pago/cancelado).
 *
 * MVP grava status='gerado_mock' — geração offline via eduardokum/laravel-boleto
 * sem chamada banco. Futuro: status='gerado' (CNAB pronto), 'enviado' (remessa
 * upada), 'pago' (retorno parsed).
 *
 * Decisão: ADR ARQ-0003 (Strategy) + ADR TECH-0003 (MVP eduardokum + mock).
 */
class CreateFinBoletoRemessasTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_boleto_remessas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id')->unsigned()->index();
            $table->bigInteger('titulo_id')->unsigned();
            $table->bigInteger('conta_bancaria_id')->unsigned();

            $table->string('nosso_numero', 30)
                  ->comment('Sequencial gerado pelo sistema; banco confirma no retorno');
            $table->string('linha_digitavel', 60)
                  ->comment('47 ou 48 digitos — formato visual com pontos/espacos');
            $table->char('codigo_barras', 44);

            $table->decimal('valor_total', 22, 4);
            $table->date('vencimento');

            $table->enum('status', [
                'gerado_mock',  // gerado offline, sem chamada banco (MVP default)
                'gerado',       // gerado e CNAB remessa pronta para envio
                'enviado',      // CNAB enviado ao banco
                'registrado',   // banco confirmou registro
                'pago',         // pagamento confirmado via retorno/webhook
                'vencido',      // passou da data de vencimento sem pagamento
                'cancelado',    // baixado sem pagamento
            ])->default('gerado_mock')->index();

            $table->string('pdf_path', 255)->nullable()
                  ->comment('Caminho relativo ao storage; gerado sob demanda');
            $table->dateTime('enviado_em')->nullable();
            $table->dateTime('pago_em')->nullable();

            $table->string('strategy', 30)
                  ->comment('cnab_direct | gateway_asaas | gateway_iugu | hybrid');
            $table->string('idempotency_key', 36)
                  ->comment('Trava re-emissao do mesmo titulo (TECH-0001)');

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('titulo_id')->references('id')->on('fin_titulos');
            $table->foreign('conta_bancaria_id')->references('id')->on('fin_contas_bancarias');
            $table->index(['business_id', 'status', 'vencimento'], 'idx_biz_status_venc');
            $table->unique(['business_id', 'titulo_id', 'idempotency_key'], 'uk_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_boleto_remessas');
    }
}
