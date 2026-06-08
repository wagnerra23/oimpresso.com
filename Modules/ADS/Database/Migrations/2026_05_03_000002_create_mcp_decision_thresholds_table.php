<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ARQ-0003 — thresholds do Decision Router, calibráveis pelo Learning Loop L3 com aprovação Wagner
class CreateMcpDecisionThresholdsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_decision_thresholds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 50)->default('*');      // '*' = global
            $table->string('event_type', 80)->default('*');

            $table->decimal('brain_a_risk_max', 4, 3)->default(0.300);
            $table->decimal('brain_a_conf_min', 4, 3)->default(0.700);
            $table->decimal('brain_b_risk_max', 4, 3)->default(0.700);

            $table->string('approved_by', 50)->default('system');
            $table->timestamp('approved_at')->useCurrent();
            $table->text('reason')->nullable();

            $table->unique(['domain', 'event_type'], 'uk_threshold_domain_type');
        });

        // Seed: threshold global padrão (ARQ-0004)
        DB::table('mcp_decision_thresholds')->insert([
            'domain'         => '*',
            'event_type'     => '*',
            'brain_a_risk_max' => 0.300,
            'brain_a_conf_min' => 0.700,
            'brain_b_risk_max' => 0.700,
            'approved_by'    => 'wagner',
            'reason'         => 'Threshold inicial ARQ-0003/ARQ-0004 — prior conservador semana 1',
            'approved_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_decision_thresholds');
    }
}
