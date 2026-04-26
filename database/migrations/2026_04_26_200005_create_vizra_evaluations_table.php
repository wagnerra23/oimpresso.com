<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vizra_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('golden_set_json');
            $table->string('judge_model', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vizra_evaluations');
    }
};
