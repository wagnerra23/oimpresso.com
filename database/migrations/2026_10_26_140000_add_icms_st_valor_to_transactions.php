<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-078 — ICMS-ST cobrado na venda ([W] 2026-10-26: entra no fechamento da venda).
 *
 * O `final_total` da venda passa a incluir o ST que a NF-e (55) vai reter; esta coluna guarda
 * quanto dele é ST, para a nota partir da base sem o ST (vNF = base + vST = final_total).
 * 0 = venda sem ST (toda venda de hoje).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'icms_st_valor')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('icms_st_valor', 22, 4)->default(0)->after('final_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'icms_st_valor')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('icms_st_valor');
            });
        }
    }
};
