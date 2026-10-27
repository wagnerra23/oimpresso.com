<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-ESTOQUE-002 (parte 2) — a contagem aprovada guarda os dois movimentos que ela gerou:
 * o ajuste de estoque da falta e a entrada da sobra. Um caminho de volta auditável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_contagens', function (Blueprint $table) {
            if (! Schema::hasColumn('estoque_contagens', 'ajuste_transaction_id')) {
                $table->unsignedInteger('ajuste_transaction_id')->nullable()->after('ajustada_em');
            }
            if (! Schema::hasColumn('estoque_contagens', 'entrada_transaction_id')) {
                $table->unsignedInteger('entrada_transaction_id')->nullable()->after('ajuste_transaction_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estoque_contagens', function (Blueprint $table) {
            foreach (['entrada_transaction_id', 'ajuste_transaction_id'] as $col) {
                if (Schema::hasColumn('estoque_contagens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
