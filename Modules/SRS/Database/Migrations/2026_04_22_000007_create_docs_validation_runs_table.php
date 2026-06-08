<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('docs_validation_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('run_at')->index();
            $table->string('module', 64)->nullable()->index(); // null = global
            $table->unsignedInteger('issues_total')->default(0);
            $table->unsignedInteger('issues_critical')->default(0);
            $table->json('issues')->nullable();                // [{type, level, ref, message}]
            $table->unsignedTinyInteger('health_score')->default(0); // 0-100
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_validation_runs');
    }
};
