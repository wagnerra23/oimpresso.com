<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCopilotoConversasTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_conversas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->nullable()->index();
            $table->unsignedInteger('user_id');
            $table->string('titulo', 200)->nullable();
            $table->enum('status', ['ativa', 'arquivada'])->default('ativa');
            $table->timestamp('iniciada_em')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_conversas');
    }
}
