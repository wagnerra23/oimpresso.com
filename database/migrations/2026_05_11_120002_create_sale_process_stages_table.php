<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-011 — Estados (etapas) de cada processo (ADR 0129 §Schema).
 *
 * Ex: rascunho → orcamento → faturada → paga → emitida → enviada.
 * `is_initial` marca o estado de criação; `is_terminal` bloqueia novas transições.
 * `color` é hint visual pra UI (Cockpit lista).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_process_stages', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('process_id');
            $t->string('key', 80);
            $t->string('name', 150);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_initial')->default(false);
            $t->boolean('is_terminal')->default(false);
            $t->string('color', 20)->nullable();
            $t->timestamps();

            $t->unique(['process_id', 'key'], 'sale_stages_proc_key_uq');
            $t->index(['process_id', 'sort_order'], 'sale_stages_proc_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_process_stages');
    }
};
