<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apurações — append-only. Idempotência garantida por unique
 * (meta_id, data_ref, fonte_query_hash) — mesma execução substitui valor
 * ao invés de duplicar linha. Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 */
class CreateCopilotoMetaApuracoesTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_meta_apuracoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meta_id');
            $table->date('data_ref');
            $table->decimal('valor_realizado', 15, 2);
            $table->timestamp('calculado_em');
            $table->string('fonte_query_hash', 64);
            $table->timestamps();

            $table->unique(['meta_id', 'data_ref', 'fonte_query_hash'], 'copiloto_apur_unico');
            $table->index(['meta_id', 'data_ref']);
            $table->foreign('meta_id')->references('id')->on('copiloto_metas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_meta_apuracoes');
    }
}
