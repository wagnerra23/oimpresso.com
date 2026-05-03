<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ARQ-0007 — padrões aprendidos pelo Learning Loop L2/L3; candidatos a regra hardcoded
class CreateMcpDecisionPatternsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_decision_patterns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('domain', 50);
            $table->string('event_type', 80);

            $table->char('pattern_hash', 64)->unique();
            $table->text('description');
            $table->json('example_decision_ids')->nullable();

            $table->unsignedSmallInteger('success_count')->default(0);
            $table->unsignedSmallInteger('total_count')->default(0);

            // success_rate calculado na aplicação (evita coluna GENERATED que cria problema em testes)
            $table->decimal('success_rate', 4, 3)->default(0.000);

            $table->boolean('is_hardcoded')->default(false);        // promovido a PolicyEngine.php
            $table->boolean('approved_by_wagner')->default(false);
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['domain', 'event_type'], 'idx_dp_domain_type');
            $table->index('success_rate', 'idx_dp_rate');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_decision_patterns');
    }
}
