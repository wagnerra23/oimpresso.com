<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de contas BR (47 entries seedadas via FinanceiroPlanoContasSeeder).
 * Estrutura hierárquica padrão Receita Federal/DCASP.
 * Contas protegidas (Caixa, Receita Bruta) não podem ser deletadas — TECH-0002.
 */
class CreateFinPlanosContaTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_planos_conta', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->string('codigo', 20)->comment('Ex: 1.1.01.001');
            $table->string('nome', 100);
            $table->enum('tipo', ['ativo', 'passivo', 'patrimonio', 'receita', 'despesa', 'custo']);
            $table->tinyInteger('nivel')->unsigned()->comment('1 = sintética raiz; folhas tipicamente 4');
            $table->integer('parent_id')->unsigned()->nullable();
            $table->enum('natureza', ['debito', 'credito']);
            $table->boolean('aceita_lancamento')->default(true)->comment('false em contas sintéticas');
            $table->boolean('protegido')->default(false)->comment('Não pode ser deletado (Caixa, Receita Bruta...)');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'codigo']);
            $table->index(['business_id', 'tipo']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('fin_planos_conta')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_planos_conta');
    }
}
