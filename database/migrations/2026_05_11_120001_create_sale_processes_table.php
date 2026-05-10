<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-011 — Catálogo de processos por business (ADR 0129 §Schema).
 *
 * Cada business tem N processos próprios (ex: "Venda Sem Nota", "Venda Com Nota
 * Manual", "Venda Com Nota Automática"). Resolução automática por Contact type
 * via `default_for_contact_type` (cf|pf|pj|any).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_processes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->string('key', 80);
            $t->string('name', 150);
            $t->string('description', 255)->nullable();
            $t->enum('default_for_contact_type', ['cf', 'pf', 'pj', 'any'])->default('any');
            $t->boolean('active')->default(true);
            $t->timestamps();

            $t->unique(['business_id', 'key'], 'sale_processes_biz_key_uq');
            $t->index(['business_id', 'active'], 'sale_processes_biz_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_processes');
    }
};
