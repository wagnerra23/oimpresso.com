<?php

declare(strict_types=1);

use App\Domain\Fsm\Support\PassosDoProcesso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passo do pedido no app guardado na própria etapa do processo (decisão [W] 2026-10-16).
 *
 * - passo / passo_rotulo / passo_ordem: em que passo da linha do tempo a etapa cai
 *   (null = fora dos passos, ex. cancelada);
 * - is_cancelled: etapa de cancelamento (filtro `cancelados` do app);
 * - is_producao: etapa que vira coluna na aba Produção do app.
 *
 * Preenche as etapas que já existem nas empresas para `venda_com_producao` e
 * `os_comunicacao_visual`, e marca como cancelada qualquer etapa `cancelled`/`cancelado`
 * de outro processo. Sem business_id: o tenancy está no processo dono da etapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_process_stages', function (Blueprint $t) {
            if (! Schema::hasColumn('sale_process_stages', 'passo')) {
                $t->string('passo', 40)->nullable()->after('color');
                $t->string('passo_rotulo', 80)->nullable()->after('passo');
                $t->unsignedSmallInteger('passo_ordem')->nullable()->after('passo_rotulo');
                $t->boolean('is_cancelled')->default(false)->after('passo_ordem');
                $t->boolean('is_producao')->default(false)->after('is_cancelled');
            }
        });

        foreach (array_keys(PassosDoProcesso::PASSOS) as $processKey) {
            PassosDoProcesso::aplicar($processKey);
        }

        DB::table('sale_process_stages')
            ->whereIn('key', PassosDoProcesso::CANCELADAS)
            ->update(['is_cancelled' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sale_process_stages', 'passo')) {
            return;
        }
        Schema::table('sale_process_stages', function (Blueprint $t) {
            $t->dropColumn(['passo', 'passo_rotulo', 'passo_ordem', 'is_cancelled', 'is_producao']);
        });
    }
};
