<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia os DISPLAY NAMES dos estágios do processo FSM legado `cacamba_locacao` pra
 * vocabulário de REPARO (auditoria [CC] 2026-06-09, aprovada [W]; ADR 0265 erradica locação).
 *
 * As KEYS dos estágios ficam INTOCADAS (trava Tier 0 — charter v4 / FSM ADR 0143): só o
 * label PT-BR (`sale_process_stages.name`) muda. Isso reflete nos chips FSM da Produção
 * (ProducaoOficina, data-driven por `stage.name`) sem mexer em transição, key, side-effect
 * ou histórico.
 *
 *   key          label antigo (locação)   →  label novo (reparo)
 *   disponivel   "Disponível"             →  "Aguardando"
 *   locada       "Locada (com cliente)"   →  "Em execução"
 *   recolhida    "Recolhida"              →  "Pronto p/ retirar"
 *   manutencao   "Em manutenção"          →  "Em diagnóstico"
 *
 * Escopo GLOBAL (todos os business): vocabulário de UI, não dado tenant-sensível. O UPDATE
 * casa só linhas com o label antigo exato (idempotente + reversível). O seeder
 * OficinaAutoFsmSeeder foi atualizado em paralelo pra ambientes novos nascerem corretos
 * (firstOrCreate não atualiza linhas existentes — daí esta migration pro dado em prod).
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (LOCACAO_STAGES)
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 * @see memory/sessions/2026-06-09-auditoria-lista-kanban-fechamento.md
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> key => [label antigo, label novo] */
    private const RENAMES = [
        'disponivel' => ['Disponível', 'Aguardando'],
        'locada'     => ['Locada (com cliente)', 'Em execução'],
        'recolhida'  => ['Recolhida', 'Pronto p/ retirar'],
        'manutencao' => ['Em manutenção', 'Em diagnóstico'],
    ];

    public function up(): void
    {
        $this->apply(fn ($old, $new) => [$old, $new]);
    }

    public function down(): void
    {
        // Reverte label novo → antigo (key intacta).
        $this->apply(fn ($old, $new) => [$new, $old]);
    }

    /**
     * @param  callable(string, string): array{0:string, 1:string}  $direction
     *         recebe [labelAntigo, labelNovo] e devolve [from, to] da direção corrente.
     */
    private function apply(callable $direction): void
    {
        if (! Schema::hasTable('sale_process_stages') || ! Schema::hasTable('sale_processes')) {
            return;
        }

        $processIds = DB::table('sale_processes')->where('key', 'cacamba_locacao')->pluck('id');
        if ($processIds->isEmpty()) {
            return;
        }

        foreach (self::RENAMES as $key => [$old, $new]) {
            [$from, $to] = $direction($old, $new);
            DB::table('sale_process_stages')
                ->whereIn('process_id', $processIds)
                ->where('key', $key)
                ->where('name', $from) // idempotente: só renomeia quem ainda tem o label de origem
                ->update(['name' => $to]);
        }
    }
};
