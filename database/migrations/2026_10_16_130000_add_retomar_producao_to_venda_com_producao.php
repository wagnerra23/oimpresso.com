<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0434 — leva a ação `retomar_producao` (Em espera → Em produção) às empresas que JÁ têm o
 * processo `venda_com_producao`.
 *
 * O FsmProcessoVendaComProducaoSeeder não roda no deploy e usa firstOrCreate: a ação nova só nasce
 * em processo semeado depois. Para o dado em produção o caminho do repo é migration de dados (mesmo
 * padrão da Modules/OficinaAuto 2026_06_10_000002).
 *
 * - Só processos que têm as duas etapas (on_hold e in_production); quem não tem fica como está.
 * - Papéis: copiados da `iniciar_producao` do MESMO processo (o seed usa `producao.iniciar`, com ou
 *   sem o sufixo `#{business_id}`). Copiar evita adivinhar o nome e respeita quem ajustou o papel.
 * - Sem side effect, não crítica. Idempotente (unique stage_id+key) e reversível.
 * - Sem consulta cruzada entre empresas: cada linha nova pertence ao processo do próprio business.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->temTabelas()) {
            return;
        }

        foreach (DB::table('sale_processes')->where('key', 'venda_com_producao')->pluck('id') as $processId) {
            $etapas = DB::table('sale_process_stages')->where('process_id', $processId)
                ->whereIn('key', ['on_hold', 'in_production', 'quote_approved'])->pluck('id', 'key');
            if (! isset($etapas['on_hold'], $etapas['in_production'])) {
                continue;
            }

            $existe = DB::table('sale_stage_actions')
                ->where('stage_id', $etapas['on_hold'])->where('key', 'retomar_producao')->value('id');
            $actionId = $existe ?: DB::table('sale_stage_actions')->insertGetId([
                'stage_id' => $etapas['on_hold'],
                'key' => 'retomar_producao',
                'label' => 'Retomar produção',
                'target_stage_id' => $etapas['in_production'],
                'side_effect_class' => null,
                'requires_confirmation' => false,
                'is_critical' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $papeis = isset($etapas['quote_approved'])
                ? DB::table('sale_stage_action_roles as r')
                    ->join('sale_stage_actions as a', 'a.id', '=', 'r.action_id')
                    ->where('a.stage_id', $etapas['quote_approved'])->where('a.key', 'iniciar_producao')
                    ->pluck('r.role_name')
                : collect();

            foreach ($papeis as $papel) {
                DB::table('sale_stage_action_roles')->insertOrIgnore([
                    'action_id' => $actionId,
                    'role_name' => $papel,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->temTabelas()) {
            return;
        }

        $stageIds = DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.key', 'venda_com_producao')->where('s.key', 'on_hold')->pluck('s.id');
        $actionIds = DB::table('sale_stage_actions')->whereIn('stage_id', $stageIds)
            ->where('key', 'retomar_producao')->pluck('id');

        // Histórico (sale_stage_history) é append-only: se a ação já foi usada, não apaga.
        $usadas = Schema::hasTable('sale_stage_history')
            ? DB::table('sale_stage_history')->whereIn('action_id', $actionIds)->distinct()->pluck('action_id')
            : collect();
        $apagar = $actionIds->diff($usadas);

        DB::table('sale_stage_action_roles')->whereIn('action_id', $apagar)->delete();
        DB::table('sale_stage_actions')->whereIn('id', $apagar)->delete();
    }

    private function temTabelas(): bool
    {
        foreach (['sale_processes', 'sale_process_stages', 'sale_stage_actions', 'sale_stage_action_roles'] as $t) {
            if (! Schema::hasTable($t)) {
                return false;
            }
        }

        return Schema::hasColumn('sale_stage_actions', 'is_critical');
    }
};
