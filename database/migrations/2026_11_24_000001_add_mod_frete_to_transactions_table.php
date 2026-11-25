<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Frete por conta" da venda (campo `modFrete` da NF-e: 0, 1, 2, 3, 4 ou 9), gravado pela Nova
 * venda V3 (UC-V409). Nullable e no fim da tabela: venda antiga e venda do Blade ficam null, e o
 * ALTER é instantâneo no MySQL 8 (sem `after`). As demais colunas de transporte já existem.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'mod_frete')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedTinyInteger('mod_frete')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'mod_frete')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('mod_frete');
            });
        }
    }
};
