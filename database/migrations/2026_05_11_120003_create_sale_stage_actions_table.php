<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-011 — Transições disponíveis em cada estado (ADR 0129 §Schema).
 *
 * `target_stage_id` null = ação que NÃO transita (ex: emitir 2ª via DANFE).
 * `event_class` = FQCN do Event Laravel a disparar.
 * `side_effect_class` = FQCN de App\Domain\Fsm\Contracts\SideEffectInterface.
 * `side_effect_payload` = parâmetros JSON passados pro side-effect.
 * `requires_confirmation` = UI deve pedir confirmação humana antes de invocar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_stage_actions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('stage_id');
            $t->string('key', 80);
            $t->string('label', 150);
            $t->unsignedBigInteger('target_stage_id')->nullable();
            $t->string('event_class', 255)->nullable();
            $t->string('side_effect_class', 255)->nullable();
            $t->json('side_effect_payload')->nullable();
            $t->boolean('requires_confirmation')->default(false);
            $t->timestamps();

            $t->unique(['stage_id', 'key'], 'sale_actions_stage_key_uq');
            $t->index('target_stage_id', 'sale_actions_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_stage_actions');
    }
};
