<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ARQ-0003 — mutex por arquivo para evitar race conditions entre agentes
class CreateMcpFileLocksTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_file_locks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('file_path', 500)->unique();
            $table->string('locked_by', 50);   // 'brain_a' | 'brain_b' | 'claude_code'
            $table->unsignedBigInteger('decision_id')->nullable(); // FK para dual_brain_decisions
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamp('expires_at');   // locked_at + ads.file_lock_ttl_seconds

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_file_locks');
    }
}
