<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rb_plans')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('rb_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('name', 150);
            $table->string('slug', 80);
            $table->text('description')->nullable();
            $table->decimal('valor', 15, 2);
            $table->enum('ciclo', ['monthly', 'quarterly', 'semiannual', 'yearly', 'custom']);
            $table->unsignedSmallInteger('ciclo_dias')->nullable()
                ->comment('Apenas quando ciclo=custom');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('ativo')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug'], 'rb_plans_biz_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rb_plans');
    }
};
