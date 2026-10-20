<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data prometida na OS (UC-FLX-06 · US-PCP-028). Decisão [W] 2026-10-20: a data prometida fica na
 * venda (`transactions.due_date`, PROJETO_DT_FIM) e vai para a OS quando ela nasce na aprovação.
 * Os dias úteis vêm do RH (feriados e expediente do Essentials) — sem tabela de feriados aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_ordens') && ! Schema::hasColumn('pcp_ordens', 'prazo_prometido')) {
            Schema::table('pcp_ordens', function (Blueprint $t) {
                $t->date('prazo_prometido')->nullable()->after('quantidade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pcp_ordens') && Schema::hasColumn('pcp_ordens', 'prazo_prometido')) {
            Schema::table('pcp_ordens', fn (Blueprint $t) => $t->dropColumn('prazo_prometido'));
        }
    }
};
