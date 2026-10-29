<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-155 · regime próprio do local emissor (Sabor Brasil: um CNPJ no regime normal e outro no
 * Simples, na mesma empresa — decisão [W] 2026-10-29).
 *
 * `crt` nulo = o local emite com o regime da empresa (`business.regime`), exatamente como antes.
 * Preenchido, vale para o CRT do XML, a escolha CST × CSOSN do item e o ST.
 *
 * Só acrescenta (ADR 0093 G8): nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_locais_emissores') && ! Schema::hasColumn('nfe_locais_emissores', 'crt')) {
            Schema::table('nfe_locais_emissores', function (Blueprint $table) {
                $table->unsignedTinyInteger('crt')->nullable()->after('serie_nfce')
                    ->comment('CRT do local: 1 Simples, 2 Simples excesso, 3 regime normal, 4 MEI; nulo = o da empresa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_locais_emissores') && Schema::hasColumn('nfe_locais_emissores', 'crt')) {
            Schema::table('nfe_locais_emissores', function (Blueprint $table) {
                $table->dropColumn('crt');
            });
        }
    }
};
