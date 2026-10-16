<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0434 item 2 — pagamento sai da sequência de etapas: a ação `entregar` passa a existir também
 * em `invoiced` (Faturada → Entregue), nas empresas que JÁ têm o processo `venda_com_producao`.
 *
 * O seeder não roda no deploy (firstOrCreate); por isso a migration de dados, como a
 * 2026_10_16_130000 (retomar_producao).
 *
 * - Copia rótulo, side effect, evento e papéis da `entregar` que o MESMO processo já tem em `paid`:
 *   se a empresa mexeu nela, a nova sai igual. Processo sem `entregar` em `paid` é pulado (não
 *   inventa regra que a empresa não tem).
 * - `paid` e `marcar_pago` ficam como estão (compatibilidade).
 * - Idempotente (unique stage_id+key); o down() não apaga ação já usada no histórico (append-only).
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
                ->whereIn('key', ['invoiced', 'paid', 'delivered'])->pluck('id', 'key');
            if (! isset($etapas['invoiced'], $etapas['paid'], $etapas['delivered'])) {
                continue;
            }

            $modelo = DB::table('sale_stage_actions')->where('stage_id', $etapas['paid'])->where('key', 'entregar')->first();
            if ($modelo === null) {
                continue;
            }

            $existe = DB::table('sale_stage_actions')
                ->where('stage_id', $etapas['invoiced'])->where('key', 'entregar')->value('id');
            $actionId = $existe ?: DB::table('sale_stage_actions')->insertGetId([
                'stage_id' => $etapas['invoiced'],
                'key' => 'entregar',
                'label' => $modelo->label,
                'target_stage_id' => $etapas['delivered'],
                'event_class' => $modelo->event_class,
                'side_effect_class' => $modelo->side_effect_class,
                'side_effect_payload' => $modelo->side_effect_payload,
                'requires_confirmation' => (bool) $modelo->requires_confirmation,
                'is_critical' => (bool) $modelo->is_critical,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('sale_stage_action_roles')->where('action_id', $modelo->id)->pluck('role_name') as $papel) {
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
            ->where('p.key', 'venda_com_producao')->where('s.key', 'invoiced')->pluck('s.id');
        $actionIds = DB::table('sale_stage_actions')->whereIn('stage_id', $stageIds)
            ->where('key', 'entregar')->pluck('id');

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
