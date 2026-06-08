<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 19 (2026-05-19) #49 — Conciliação OFX.
 *
 * Tabela append-only de linhas de extrato bancário importadas (OFX/CNAB).
 * Cada linha pode ser conciliada (matched) com 1 ou N Titulos via baixa.
 *
 * Workflow:
 *  1. Usuário faz upload de OFX/CSV em /financeiro/conciliacao
 *  2. Parser cria 1 row por transação no OFX
 *  3. ConciliacaoService::sugerir() faz fuzzy match (valor + data ±3 dias)
 *  4. Usuário aprova match → marca como conciliada (titulo_id setado)
 *
 * Tier 0 (R-FIN-009 multi-tenant + append-only audit).
 */
class CreateFinBankStatementLinesTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_bank_statement_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('conta_bancaria_id')->unsigned()->nullable();

            // Dados da linha do extrato (parsed do OFX/CSV).
            $table->string('fitid', 100)->nullable()->comment('OFX FITID — identificador único da transação no banco');
            $table->date('data_movimento');
            $table->string('descricao', 255);
            $table->decimal('valor', 15, 4)->comment('positivo = crédito, negativo = débito');
            $table->enum('tipo', ['credit', 'debit', 'fee', 'transfer', 'unknown'])->default('unknown');
            $table->string('memo', 500)->nullable()->comment('descrição complementar OFX MEMO');

            // Conciliação.
            $table->enum('status', ['pendente', 'sugerido', 'conciliado', 'ignorado'])->default('pendente');
            $table->integer('titulo_id')->unsigned()->nullable()->comment('FK pro Titulo quando conciliado');
            $table->integer('conciliado_by')->unsigned()->nullable()->comment('FK users.id quem aprovou match');
            $table->timestamp('conciliado_at')->nullable();
            $table->decimal('match_score', 5, 2)->nullable()->comment('0.00-1.00 confiança do match');

            // Auditoria upload.
            $table->string('source_file', 255)->nullable();
            $table->integer('uploaded_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'data_movimento']);
            $table->unique(['business_id', 'fitid'], 'unique_fitid_per_biz');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('titulo_id')->references('id')->on('fin_titulos')->onDelete('set null');
            $table->foreign('conta_bancaria_id')->references('id')->on('fin_contas_bancarias')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_bank_statement_lines');
    }
}
