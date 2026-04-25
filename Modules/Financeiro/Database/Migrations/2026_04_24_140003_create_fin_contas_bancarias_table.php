<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas bancárias do business.
 * Soft delete bloqueado se houver fin_caixa_movimentos vinculados (TECH-0002).
 * Saldo inicial é gravado como movimento tipo='ajuste' na criação.
 */
class CreateFinContasBancariasTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_contas_bancarias', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->string('nome', 100);
            $table->char('banco_codigo', 3)->nullable()->comment('FEBRABAN — null para "Caixa Físico"');
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->char('digito', 2)->nullable();
            $table->enum('tipo', ['cc', 'poup', 'inv', 'caixa'])->default('cc');
            $table->decimal('saldo_inicial', 22, 4)->default(0);
            $table->decimal('saldo_atual', 22, 4)->default(0)->comment('Atualizado por observer em movimento');
            $table->date('saldo_data');
            $table->boolean('ativo')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'ativo']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_contas_bancarias');
    }
}
