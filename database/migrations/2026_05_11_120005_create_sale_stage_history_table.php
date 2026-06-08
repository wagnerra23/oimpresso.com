<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-011 — Audit log de transições FSM executadas (ADR 0129 §Schema).
 *
 * Trilha completa: quem moveu O QUE, DE qual stage PRA qual stage, QUANDO,
 * com qual ACTION e payload-snapshot. Atende compliance LGPD/fiscal.
 *
 * `from_stage_id` null = stage inicial atribuído (sem transição prévia).
 * `to_stage_id` null = ação que NÃO transitou (ex: re-emitir 2ª via).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_stage_history', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('transaction_id');
            $t->unsignedBigInteger('action_id');
            $t->unsignedBigInteger('from_stage_id')->nullable();
            $t->unsignedBigInteger('to_stage_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->json('payload_snapshot')->nullable();
            $t->timestamp('executed_at')->useCurrent();

            $t->index(['business_id', 'transaction_id'], 'sale_history_biz_tx_idx');
            $t->index(['business_id', 'executed_at'], 'sale_history_biz_when_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_stage_history');
    }
};
