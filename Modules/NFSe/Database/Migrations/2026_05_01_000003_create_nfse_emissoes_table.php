<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNfseEmissoesTable extends Migration
{
    public function up(): void
    {
        Schema::create('nfse_emissoes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();

            // Identificação da nota
            $table->string('numero', 20)->nullable()->comment('Número atribuído pela prefeitura após emissão');
            $table->string('serie', 10)->default('RPS');
            $table->string('rps_numero', 20)->nullable()->comment('Número do RPS gerado pelo prestador');
            $table->date('competencia')->comment('Mês/ano de prestação do serviço');

            // Tomador do serviço
            $table->string('tomador_cnpj', 18)->nullable();
            $table->string('tomador_cpf', 14)->nullable();
            $table->string('tomador_nome', 150);
            $table->string('tomador_email', 150)->nullable();
            $table->string('tomador_municipio_ibge', 7)->nullable();

            // Serviço
            $table->string('lc116_codigo', 5)->nullable()->comment('Ex: 1.05');
            $table->string('cnae', 10)->nullable();
            $table->text('descricao');

            // Valores
            $table->decimal('valor_servicos', 15, 2);
            $table->decimal('valor_deducoes', 15, 2)->default(0);
            $table->decimal('valor_base_calculo', 15, 2)->default(0);
            $table->decimal('aliquota_iss', 5, 4)->nullable();
            $table->decimal('valor_iss', 15, 2)->default(0);
            $table->boolean('iss_retido')->default(false);

            // Resultado / integração provider
            $table->enum('status', ['rascunho', 'processando', 'emitida', 'cancelada', 'erro'])
                ->default('rascunho')->index();
            $table->string('provider_protocolo', 100)->nullable();
            $table->string('provider_codigo_verificacao', 100)->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->longText('xml_envio')->nullable();
            $table->longText('xml_retorno')->nullable();
            $table->text('erro_mensagem')->nullable();

            // Idempotência (evita dupla emissão)
            $table->string('idempotency_key', 64)->unique();

            // Vínculo com recurring invoice UPOS (US-NFSE-007)
            $table->integer('recurring_invoice_id')->unsigned()->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'competencia']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfse_emissoes');
    }
}
