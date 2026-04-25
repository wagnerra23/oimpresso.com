<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de metas (KPI + alvo).
 *
 * Tenancy híbrida: business_id nullable. NULL = meta da plataforma
 * (superadmin-only). Ver adr/arq/0001-tenancy-hibrida.md.
 */
class CreateCopilotoMetasTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_metas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->nullable()->index();
            $table->string('slug', 80);
            $table->string('nome', 150);
            $table->enum('unidade', ['R$', 'qtd', '%', 'dias'])->default('R$');
            $table->enum('tipo_agregacao', ['soma', 'media', 'ultimo', 'contagem'])->default('soma');
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('criada_por_user_id')->nullable();
            $table->enum('origem', ['chat_ia', 'manual', 'seed'])->default('manual');
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('criada_por_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_metas');
    }
}
