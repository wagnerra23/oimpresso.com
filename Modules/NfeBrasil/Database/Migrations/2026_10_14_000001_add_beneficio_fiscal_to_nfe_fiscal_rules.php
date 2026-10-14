<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 25 · benefício fiscal na regra (R-NFE-030 · UC-TRB-26).
 *
 * Só ACRESCENTA 3 colunas nulas em `nfe_fiscal_rules`. Nenhuma linha existente muda: regra com
 * `reducao_base` NULL calcula exatamente como antes (append-only, ADR 0093 G8).
 *
 * - `reducao_base`: fração da base do ICMS que o benefício tira (0.3333 = 33,33%), no mesmo
 *   formato decimal das alíquotas da tabela.
 * - `c_benef`: código do benefício na tabela da UF, informado pelo contador; vai no `<cBenef>`
 *   do item.
 * - `beneficio_fundamento`: o ato legal da UF que concede o benefício, citado literal pelo
 *   contador (lei 4 do módulo: sem número fiscal sem lei citada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_fiscal_rules')) {
            return;
        }

        Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_fiscal_rules', 'reducao_base')) {
                $table->decimal('reducao_base', 7, 4)->nullable()->after('fcp')
                    ->comment('Fração da base do ICMS reduzida pelo benefício (0.3333 = 33,33%)');
            }
            if (! Schema::hasColumn('nfe_fiscal_rules', 'c_benef')) {
                $table->string('c_benef', 10)->nullable()->after('reducao_base')
                    ->comment('Código de benefício fiscal da UF (cBenef do item)');
            }
            if (! Schema::hasColumn('nfe_fiscal_rules', 'beneficio_fundamento')) {
                $table->string('beneficio_fundamento', 255)->nullable()->after('c_benef')
                    ->comment('Ato legal da UF que concede o benefício, citado literal');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nfe_fiscal_rules')) {
            return;
        }
        foreach (['beneficio_fundamento', 'c_benef', 'reducao_base'] as $coluna) {
            if (Schema::hasColumn('nfe_fiscal_rules', $coluna)) {
                Schema::table('nfe_fiscal_rules', fn (Blueprint $table) => $table->dropColumn($coluna));
            }
        }
    }
};
