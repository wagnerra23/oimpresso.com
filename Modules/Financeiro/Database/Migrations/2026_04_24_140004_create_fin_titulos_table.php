<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Títulos financeiros (a receber + a pagar na mesma tabela — UI-0002 dashboard unificado).
 * Idempotência: UNIQUE (business_id, origem, origem_id, parcela_numero) protege
 * de auto-criação duplicada via TransactionObserver.
 *
 * cliente_id é FK para `contacts` core (lazy — não constraint pra evitar quebra
 * em delete de contact).
 */
class CreateFinTitulosTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_titulos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->string('numero', 20)->comment('Sequencial business-isolado; lockForUpdate em geração');
            $table->enum('tipo', ['receber', 'pagar']);
            $table->enum('status', ['aberto', 'parcial', 'quitado', 'cancelado'])->default('aberto');

            $table->integer('cliente_id')->unsigned()->nullable()->comment('FK soft -> contacts.id');
            $table->string('cliente_descricao', 255)->nullable()->comment('Fallback se cliente não cadastrado');

            $table->decimal('valor_total', 22, 4);
            $table->decimal('valor_aberto', 22, 4)->comment('valor_total - sum(baixas.valor); auto via observer');
            $table->char('moeda', 3)->default('BRL');

            $table->date('emissao');
            $table->date('vencimento');
            $table->char('competencia_mes', 7)->comment('YYYY-MM regime competência');

            $table->enum('origem', ['manual', 'venda', 'compra', 'despesa', 'recurring', 'folha']);
            $table->integer('origem_id')->unsigned()->nullable()->comment('transaction.id, recurring_invoice.id, etc.');
            $table->tinyInteger('parcela_numero')->unsigned()->nullable();
            $table->tinyInteger('parcela_total')->unsigned()->nullable();
            $table->integer('titulo_pai_id')->unsigned()->nullable()->comment('Self-FK para parcelas');

            $table->integer('plano_conta_id')->unsigned()->nullable();
            $table->integer('categoria_id')->unsigned()->nullable();

            $table->text('observacoes')->nullable();
            $table->json('metadata')->nullable()->comment('Shape específico por origem (ex: nfe_chave)');

            $table->integer('created_by')->unsigned();
            $table->integer('updated_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Idempotência da auto-criação (Observer + Listener)
            $table->unique(
                ['business_id', 'origem', 'origem_id', 'parcela_numero'],
                'uk_titulo_origem'
            );
            $table->index(['business_id', 'status', 'vencimento'], 'idx_business_status_venc');
            $table->index(['business_id', 'tipo', 'status'], 'idx_business_tipo_status');
            $table->index(['business_id', 'cliente_id'], 'idx_business_cliente');

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('plano_conta_id')->references('id')->on('fin_planos_conta')->onDelete('set null');
            $table->foreign('categoria_id')->references('id')->on('fin_categorias')->onDelete('set null');
            $table->foreign('titulo_pai_id')->references('id')->on('fin_titulos')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulos');
    }
}
