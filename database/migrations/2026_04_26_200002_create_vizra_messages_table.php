<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vizra_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('vizra_agents')->cascadeOnDelete();
            $table->string('conversation_id', 64)->index();
            $table->enum('role', ['system', 'user', 'assistant', 'tool']);
            $table->mediumText('content');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vizra_messages');
    }
};
