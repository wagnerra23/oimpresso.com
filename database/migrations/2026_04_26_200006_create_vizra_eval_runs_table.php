<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vizra_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('vizra_evaluations')->cascadeOnDelete();
            $table->string('agent_version', 64)->nullable();
            $table->decimal('score_avg', 5, 2)->default(0);
            $table->json('results_json');
            $table->timestamp('run_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vizra_eval_runs');
    }
};
