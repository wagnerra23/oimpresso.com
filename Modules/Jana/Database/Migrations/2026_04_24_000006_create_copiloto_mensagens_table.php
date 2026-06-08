<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensagens da conversa — append-only (sem updated_at).
 */
class CreateCopilotoMensagensTable extends Migration
{
    public function up(): void
    {
        Schema::create('copiloto_mensagens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversa_id');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversa_id', 'created_at']);
            $table->foreign('conversa_id')->references('id')->on('copiloto_conversas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copiloto_mensagens');
    }
}
