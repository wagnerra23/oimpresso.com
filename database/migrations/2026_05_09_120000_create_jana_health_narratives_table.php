<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COPI-099 — Brain A narrador horário do Cockpit Saúde.
 *
 * Cada linha é uma narrativa gerada pelo Brain A (gpt-4o-mini) a partir do
 * snapshot agregado por HealthSnapshotService. Sem business_id — plataforma
 * toda, leitura superadmin (ADR 0094 §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jana_health_narratives', function (Blueprint $t) {
            $t->id();
            $t->timestamp('generated_at')->index();
            $t->enum('severity', ['info', 'warning', 'critical'])->default('info')->index();
            $t->text('narrative');
            $t->string('snapshot_hash', 64)->index();
            $t->string('model', 50)->default('gpt-4o-mini');
            $t->unsignedInteger('tokens_in')->nullable();
            $t->unsignedInteger('tokens_out')->nullable();
            $t->decimal('custo_brl', 10, 6)->nullable();
            $t->json('payload_summary')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jana_health_narratives');
    }
};
