<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCopilotoSugestoesTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_sugestoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversa_id');
            $table->unsignedBigInteger('meta_id')->nullable(); // preenche ao escolher
            $table->json('payload_json');
            $table->timestamp('escolhida_em')->nullable();
            $table->timestamp('rejeitada_em')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'escolhida_em']);
            $table->foreign('conversa_id')->references('id')->on('copiloto_conversas')->onDelete('cascade');
            $table->foreign('meta_id')->references('id')->on('copiloto_metas')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_sugestoes');
    }
}
