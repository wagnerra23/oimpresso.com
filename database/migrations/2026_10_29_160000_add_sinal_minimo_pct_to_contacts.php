<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-025 — sinal mínimo por cliente.
 *
 * A empresa liga "produção só libera com o sinal pago" com um percentual só
 * (`fsm_modelos_empresa.sinal_minimo_pct`, US-PCP-025). Esta coluna guarda a condição do
 * cliente: preenchida, vale no lugar da da empresa (0 = este cliente não precisa de sinal);
 * NULL = segue a empresa (todo cliente de antes desta migration). O prazo do cliente já
 * existe em `contacts.pay_term_number`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts') || Schema::hasColumn('contacts', 'sinal_minimo_pct')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('sinal_minimo_pct', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contacts', 'sinal_minimo_pct')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('sinal_minimo_pct');
        });
    }
};
