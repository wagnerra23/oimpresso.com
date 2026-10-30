<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-026 — a venda guarda o sinal mínimo combinado.
 *
 * A tela de venda traz o sinal do cliente (`contacts.sinal_minimo_pct`, P-025) e o vendedor
 * pode trocar. Preenchido, vale para as OS desta venda no lugar do sinal do cliente e da
 * empresa (0 = esta venda não precisa de sinal); NULL = segue o cliente, depois a empresa
 * (toda venda de antes desta migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'sinal_minimo_pct')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('sinal_minimo_pct', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('transactions', 'sinal_minimo_pct')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('sinal_minimo_pct');
        });
    }
};
