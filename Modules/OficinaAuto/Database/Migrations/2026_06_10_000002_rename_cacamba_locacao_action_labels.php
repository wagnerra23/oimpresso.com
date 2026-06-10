<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renomeia os LABELS das transições FSM do processo legado `cacamba_locacao` pra
 * vocabulário de REPARO (ADR 0265 — espelho da 2026_06_09_000003, que renomeou os
 * stages; esta renomeia as ACTIONS que o checklist/painel FSM mostra como botão).
 *
 * Evidência [W] 2026-06-10 (OS-00004): checklist oferecia "Iniciar locação
 * (entregar caçamba)" — label de locação visível pro usuário num produto de reparo.
 *
 * KEYS FSM e side_effect_class INTOCADOS (Tier 0 — ADR 0143/0194, Martinho live):
 * só `sale_stage_actions.label` muda. UPDATE casa key + label antigo exato
 * (idempotente + reversível). Seeder OficinaAutoFsmSeeder::seedLocacaoProcess
 * atualizado em paralelo (firstOrCreate não atualiza linha existente — daí esta
 * migration pro dado em prod).
 *
 * Escopo GLOBAL (todos os business): label de UI, não dado tenant-sensível.
 *
 *   (+ sale_processes.name: "Caçamba — Locação" → "Fluxo legado — equipamento")
 *
 *   key                 label antigo (locação)              →  label novo (reparo)
 *   iniciar_locacao     Iniciar locação (entregar caçamba)  →  Iniciar execução
 *   recolher            Recolher caçamba (devolução)        →  Concluir serviço
 *   enviar_manutencao   Enviar pra manutenção               →  Enviar pra diagnóstico
 *   voltar_disponivel   Liberar pra locação                 →  Voltar pra aguardando
 *
 * @see Modules/OficinaAuto/Database/Migrations/2026_06_09_000003_rename_cacamba_locacao_stage_labels.php
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php (seedLocacaoProcess)
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> action key => [label antigo, label novo] */
    private const RENAMES = [
        'iniciar_locacao'   => ['Iniciar locação (entregar caçamba)', 'Iniciar execução'],
        'recolher'          => ['Recolher caçamba (devolução)', 'Concluir serviço'],
        'enviar_manutencao' => ['Enviar pra manutenção', 'Enviar pra diagnóstico'],
        'voltar_disponivel' => ['Liberar pra locação', 'Voltar pra aguardando'],
    ];

    public function up(): void
    {
        $this->apply(fn ($old, $new) => [$old, $new]);
    }

    public function down(): void
    {
        // Reverte label novo → antigo (key + side_effect_class intactos).
        $this->apply(fn ($old, $new) => [$new, $old]);
    }

    /**
     * @param  callable(string, string): array{0:string, 1:string}  $direction
     *         recebe [labelAntigo, labelNovo] e devolve [from, to] da direção corrente.
     */
    private function apply(callable $direction): void
    {
        if (! Schema::hasTable('sale_stage_actions')
            || ! Schema::hasTable('sale_process_stages')
            || ! Schema::hasTable('sale_processes')
        ) {
            return;
        }

        $processIds = DB::table('sale_processes')->where('key', 'cacamba_locacao')->pluck('id');
        if ($processIds->isEmpty()) {
            return;
        }

        // Nome do PROCESSO também é user-facing (telas admin FSM) — mesmo contrato
        // dos labels: casa o valor de origem exato, idempotente + reversível.
        [$nameFrom, $nameTo] = $direction('Caçamba — Locação', 'Fluxo legado — equipamento');
        DB::table('sale_processes')
            ->whereIn('id', $processIds)
            ->where('name', $nameFrom)
            ->update(['name' => $nameTo]);

        $stageIds = DB::table('sale_process_stages')->whereIn('process_id', $processIds)->pluck('id');
        if ($stageIds->isEmpty()) {
            return;
        }

        foreach (self::RENAMES as $key => [$old, $new]) {
            [$from, $to] = $direction($old, $new);
            DB::table('sale_stage_actions')
                ->whereIn('stage_id', $stageIds)
                ->where('key', $key)
                ->where('label', $from) // idempotente: só renomeia quem ainda tem o label de origem
                ->update(['label' => $to]);
        }
    }
};
