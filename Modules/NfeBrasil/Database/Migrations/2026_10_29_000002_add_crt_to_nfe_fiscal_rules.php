<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-155 · regra fiscal por regime. Na mesma empresa, o local do regime normal e o do Simples
 * tributam o mesmo NCM de jeitos diferentes (CST 10 com ICMS próprio × CSOSN 500 sem destaque).
 * É o que o Delphi do Sabor Brasil já faz com `REGRA_TRIBUTARIA.REGIME_EMPRESA` (SN/LR).
 *
 * `crt` nulo = a regra vale para qualquer emitente, exatamente como antes. Preenchido, só para o
 * local emissor com esse CRT, e ganha da regra sem regime.
 *
 * Só acrescenta (ADR 0093 G8): nenhuma linha existente muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_fiscal_rules') && ! Schema::hasColumn('nfe_fiscal_rules', 'crt')) {
            Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
                $table->unsignedTinyInteger('crt')->nullable()->after('cst')
                    ->comment('Regime do emitente a que a regra se aplica (CRT 1-4); nulo = qualquer');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_fiscal_rules') && Schema::hasColumn('nfe_fiscal_rules', 'crt')) {
            Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
                $table->dropColumn('crt');
            });
        }
    }
};
