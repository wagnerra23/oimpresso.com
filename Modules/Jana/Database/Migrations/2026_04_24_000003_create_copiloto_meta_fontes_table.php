<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCopilotoMetaFontesTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_meta_fontes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meta_id')->unique();
            $table->enum('driver', ['sql', 'php', 'http'])->default('sql');
            $table->json('config_json');
            $table->enum('cadencia', ['diaria', 'horaria', 'manual'])->default('diaria');
            $table->timestamps();

            $table->foreign('meta_id')->references('id')->on('copiloto_metas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_meta_fontes');
    }
}
