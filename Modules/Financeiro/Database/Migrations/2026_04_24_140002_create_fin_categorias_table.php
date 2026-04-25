<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias livres complementares ao plano de contas.
 * Ex: "Aluguel Loja A", "Marketing Digital Q4", "Comissão Vendedor Z".
 * Usado em filtros de relatórios + cores customizadas em UI.
 */
class CreateFinCategoriasTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_categorias', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->string('nome', 100);
            $table->string('cor', 7)->nullable()->comment('Hex: #FF6B6B');
            $table->integer('plano_conta_id')->unsigned()->nullable()->comment('Vínculo opcional ao plano contábil');
            $table->enum('tipo', ['receita', 'despesa', 'ambos'])->default('ambos');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'tipo']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('plano_conta_id')->references('id')->on('fin_planos_conta')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_categorias');
    }
}
