<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCopilotoMetaPeriodosTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_meta_periodos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meta_id');
            $table->enum('tipo_periodo', ['mes', 'trim', 'ano', 'custom'])->default('mes');
            $table->date('data_ini');
            $table->date('data_fim');
            $table->decimal('valor_alvo', 15, 2);
            $table->enum('trajetoria', ['linear', 'sazonal', 'exponencial', 'manual'])->default('linear');
            $table->timestamps();

            $table->index(['meta_id', 'data_ini']);
            $table->foreign('meta_id')->references('id')->on('copiloto_metas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_meta_periodos');
    }
}
