<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de fluxo da empresa (ADR 0435).
 *
 * - `fsm_modelos_empresa`: porte, atividade e modelo escolhido por business (tabela própria —
 *   a `business` do UltimatePOS não recebe coluna nova).
 * - `sale_processes.dominio`: venda | producao | compra | entrega | financeiro (null = não
 *   classificado). `sale_processes.modelo`: de que modelo o processo veio (`legado` = antes do
 *   catálogo).
 * - `sale_process_stages.regras_ao_entrar`: regras "ao entrar nesta etapa → efeito" do modelo,
 *   como dado (UC, efeito, estado). Só a classe na ação executa; regra planejada não roda.
 *
 * Backfill: processos existentes viram `modelo = legado`; `venda_*` → domínio venda, `os_*` →
 * produção (a OS da Comunicação Visual e a OS de reparo); o resto fica sem domínio. Toda empresa
 * que já tem processo ganha a linha `legado`. Nenhum documento é tocado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fsm_modelos_empresa')) {
            Schema::create('fsm_modelos_empresa', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('business_id');
                $t->string('porte', 20)->nullable();
                $t->string('atividade', 20)->nullable();
                $t->string('modelo', 40)->default('legado');
                $t->timestamp('aplicado_em')->nullable();
                $t->unsignedInteger('aplicado_por')->nullable();
                $t->timestamps();

                $t->unique('business_id', 'fsm_modelos_empresa_biz_uq');
                $t->foreign('business_id', 'fsm_modelos_empresa_biz_fk')
                    ->references('id')->on('business')->onDelete('cascade');
            });
        }

        Schema::table('sale_processes', function (Blueprint $t) {
            if (! Schema::hasColumn('sale_processes', 'dominio')) {
                $t->string('dominio', 20)->nullable()->after('key');
                $t->string('modelo', 40)->nullable()->after('dominio');
                $t->index(['business_id', 'dominio'], 'sale_processes_biz_dominio_idx');
            }
        });

        Schema::table('sale_process_stages', function (Blueprint $t) {
            if (! Schema::hasColumn('sale_process_stages', 'regras_ao_entrar')) {
                $t->json('regras_ao_entrar')->nullable()->after('is_producao');
            }
        });

        DB::table('sale_processes')->whereNull('modelo')->update(['modelo' => 'legado']);
        DB::table('sale_processes')->whereNull('dominio')->where('key', 'like', 'venda%')->update(['dominio' => 'venda']);
        DB::table('sale_processes')->whereNull('dominio')->where('key', 'like', 'os%')->update(['dominio' => 'producao']);

        $comProcesso = DB::table('sale_processes')->distinct()->pluck('business_id');
        $jaTem = DB::table('fsm_modelos_empresa')->pluck('business_id')->all();
        $agora = now();
        foreach ($comProcesso as $biz) {
            if (! in_array($biz, $jaTem) && DB::table('business')->where('id', $biz)->exists()) {
                DB::table('fsm_modelos_empresa')->insert([
                    'business_id' => $biz, 'modelo' => 'legado', 'created_at' => $agora, 'updated_at' => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_process_stages', 'regras_ao_entrar')) {
            Schema::table('sale_process_stages', fn (Blueprint $t) => $t->dropColumn('regras_ao_entrar'));
        }
        if (Schema::hasColumn('sale_processes', 'dominio')) {
            Schema::table('sale_processes', function (Blueprint $t) {
                $t->dropIndex('sale_processes_biz_dominio_idx');
                $t->dropColumn(['dominio', 'modelo']);
            });
        }
        Schema::dropIfExists('fsm_modelos_empresa');
    }
};
