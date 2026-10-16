<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras configuráveis por empresa (ADR 0435, aba Fluxos da empresa).
 *
 * - `fsm_modelos_empresa.regras_compra`: o que a empresa mudou nas regras das etapas da Compra
 *   (`App\Domain\Fsm\Modelos\RegrasCompra`), por id de regra: `{ativo, params}`. null = padrão.
 *
 * Ligar e desligar as regras "ao entrar" dos outros processos não precisa de coluna: a chave
 * `ligada` vai na própria regra, em `sale_process_stages.regras_ao_entrar` (a configuração mora
 * na etapa). Nenhum documento é tocado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fsm_modelos_empresa') && ! Schema::hasColumn('fsm_modelos_empresa', 'regras_compra')) {
            Schema::table('fsm_modelos_empresa', function (Blueprint $t) {
                $t->json('regras_compra')->nullable()->after('modelo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fsm_modelos_empresa', 'regras_compra')) {
            Schema::table('fsm_modelos_empresa', fn (Blueprint $t) => $t->dropColumn('regras_compra'));
        }
    }
};
