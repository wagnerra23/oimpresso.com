<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-NFE-025b · CST de PIS e de COFINS na regra fiscal. Até aqui o XML levava `07` (isenta)
 * fixo, e a regra só guardava as alíquotas. O Sabor Brasil emite CST 01 (0,65% e 3%) no
 * tempero e 06 no alho; com 07 fixo a nota saía como isenta.
 *
 * Nulo = a regra não diz o CST, e a emissão segue como antes (07, com log). Só acrescenta
 * (ADR 0093 G8): nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_fiscal_rules')) {
            return;
        }
        Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_fiscal_rules', 'cst_pis')) {
                $table->char('cst_pis', 2)->nullable()->after('aliquota_cofins')
                    ->comment('CST do PIS (tabela 4.3.3); nulo = 07, como antes');
            }
            if (! Schema::hasColumn('nfe_fiscal_rules', 'cst_cofins')) {
                $table->char('cst_cofins', 2)->nullable()->after('cst_pis')
                    ->comment('CST da COFINS (tabela 4.3.4); nulo = 07, como antes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_fiscal_rules')) {
            return;
        }
        Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
            foreach (['cst_cofins', 'cst_pis'] as $coluna) {
                if (Schema::hasColumn('nfe_fiscal_rules', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
