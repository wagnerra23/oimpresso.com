<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vizra_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('vizra_messages')->nullOnDelete();
            $table->string('tool_name', 64)->index();
            $table->json('input_json')->nullable();
            $table->json('output_json')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vizra_traces');
    }
};
