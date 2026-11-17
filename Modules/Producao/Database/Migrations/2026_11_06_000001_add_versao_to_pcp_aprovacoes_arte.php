<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\VersaoDaArte;

/**
 * Versão da arte (UC-PCART-09, decisão [W] 2026-11-06): cada envio da prova guarda a versão do arquivo.
 * A versão só sobe quando o arquivo muda; o reenvio do mesmo arquivo mantém o número (VersaoDaArte).
 * Os envios que já existem são numerados aqui, OS a OS, na ordem em que foram feitos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcp_aprovacoes_arte') || Schema::hasColumn('pcp_aprovacoes_arte', 'versao')) {
            return;
        }
        Schema::table('pcp_aprovacoes_arte', function (Blueprint $t) {
            $t->unsignedSmallInteger('versao')->default(1)->after('arquivo')->comment('sobe só quando o arquivo muda');
        });
        VersaoDaArte::preencher();
    }

    public function down(): void
    {
        if (Schema::hasColumn('pcp_aprovacoes_arte', 'versao')) {
            Schema::table('pcp_aprovacoes_arte', fn (Blueprint $t) => $t->dropColumn('versao'));
        }
    }
};
