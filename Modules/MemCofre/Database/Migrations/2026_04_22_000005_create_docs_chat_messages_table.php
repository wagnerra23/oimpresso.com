<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('docs_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('session_id', 64)->index();      // agrupa mensagens de uma conversa
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->string('module_context', 64)->nullable(); // módulo selecionado quando perguntou
            $table->json('sources')->nullable();               // refs aos arquivos/adrs citados
            $table->enum('mode', ['offline', 'ai'])->default('offline'); // como foi gerada
            $table->unsignedInteger('tokens_used')->nullable(); // quando mode=ai
            $table->timestamps();

            $table->index(['business_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_chat_messages');
    }
};
