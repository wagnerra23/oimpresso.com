<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Re-aponta OS órfãs presas em pipeline FSM legado de locação (ADR 0265 — fio usável).
 *
 * Causa raiz (evidência [W] 2026-06-10, OS-00004): start-pipeline manual caía no
 * processo resolvido pelo order_type da ÉPOCA ('locacao' → cacamba_locacao). Depois
 * que a 2026_06_09_000002 backfillou order_type → 'mecanica', essas OS ficaram com
 * current_stage_id num stage de `cacamba_locacao` enquanto o painel FSM resolve
 * `oficina_mecanica_os` — resultado: "Nenhuma transição disponível", OS morta no quadro.
 *
 * Critério de órfã (conservador):
 *  1. current_stage_id aponta pra stage cujo processo DIVERGE do processo correto —
 *     stage em `cacamba_locacao` é SEMPRE órfã (locação erradicada, ADR 0265: o
 *     correto é `oficina_mecanica_os`); nos demais, correto = resolvido pelo
 *     order_type atual (mapa canônico ServiceOrderPipelineStarter); E
 *  2. NÃO há transição FSM real no histórico — só entrada(s) `pipeline_started`
 *     (sale_stage_history com action_id IS NULL). OS com transição real (action_id
 *     NOT NULL em processo cacamba_*) são PRESERVADAS (histórico operacional vivo)
 *     e logadas pra auditoria manual.
 *
 * Ação: re-aponta pro stage INICIAL do processo correto do MESMO business
 * (oficina_mecanica_os → recepcao · cacamba_manutencao → aberta). Órfã de locação
 * também recebe order_type='mecanica' (em prod a 2026_06_09_000001 já tinha virado
 * essas linhas pra 'manutencao' ANTES do backfill 000002 mirar 'locacao' — sem o
 * flip aqui, a OS re-apontada pra `recepcao` resolveria process cacamba_manutencao
 * e voltaria pro beco; evidência: 3 órfãs em prod 2026-06-10, todas 'manutencao' em
 * stage de locação). Se o processo correto não existir no business →
 * current_stage_id = NULL (auto-start do store() ou botão manual resolvem depois;
 * a migration irmã 2026_06_10_000000 seeda o processo antes desta rodar).
 * Trilha append-only: insere entrada `pipeline_repointed` em sale_stage_history
 * com from/to + order_type_before (auditável, ADR 0143).
 *
 * Multi-tenant Tier 0 (ADR 0093): re-aponta SEMPRE dentro do mesmo business_id.
 * Idempotente: re-rodar não acha mais órfãs (mismatch zerado no 1º run).
 * down(): no-op documentado — o from_stage_id fica preservado no payload_snapshot
 * da trilha (reversão manual possível caso a caso; rollback cego reintroduziria
 * exatamente o beco que esta migration conserta).
 *
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 * @see Modules/OficinaAuto/Services/ServiceOrderPipelineStarter.php (mapa canônico)
 */
return new class extends Migration
{
    /** @var array<string, string> order_type → process_key correto (espelha ServiceOrderPipelineStarter) */
    private const ORDER_TYPE_TO_PROCESS = [
        'manutencao' => 'cacamba_manutencao',
        'mecanica'   => 'oficina_mecanica_os',
    ];

    /** @var list<string> processos FSM da Oficina (universo do conserto) */
    private const OFICINA_PROCESS_KEYS = ['cacamba_locacao', 'cacamba_manutencao', 'oficina_mecanica_os'];

    public function up(): void
    {
        if (! Schema::hasTable('service_orders')
            || ! Schema::hasColumn('service_orders', 'current_stage_id')
            || ! Schema::hasColumn('service_orders', 'order_type')
            || ! Schema::hasTable('sale_processes')
            || ! Schema::hasTable('sale_process_stages')
            || ! Schema::hasTable('sale_stage_history')
        ) {
            return;
        }

        // Mapa stage_id → [process_key, business_id] dos processos da Oficina.
        $processes = DB::table('sale_processes')
            ->whereIn('key', self::OFICINA_PROCESS_KEYS)
            ->get(['id', 'key', 'business_id']);

        if ($processes->isEmpty()) {
            return;
        }

        $processById = $processes->keyBy('id');
        $stages = DB::table('sale_process_stages')
            ->whereIn('process_id', $processes->pluck('id'))
            ->get(['id', 'process_id', 'key', 'is_initial']);

        $stageInfo = [];      // stage_id → ['process_key' =>, 'business_id' =>]
        $initialStage = [];   // "{business_id}:{process_key}" → stage_id inicial
        foreach ($stages as $stage) {
            $proc = $processById[$stage->process_id] ?? null;
            if ($proc === null) {
                continue;
            }
            $stageInfo[$stage->id] = ['process_key' => $proc->key, 'business_id' => (int) $proc->business_id];
            if ($stage->is_initial) {
                $initialStage["{$proc->business_id}:{$proc->key}"] = $stage->id;
            }
        }

        $candidates = DB::table('service_orders')
            ->whereIn('current_stage_id', array_keys($stageInfo))
            ->get(['id', 'business_id', 'order_type', 'current_stage_id']);

        $repointed = 0;
        $preservedWithHistory = [];

        foreach ($candidates as $os) {
            $current = $stageInfo[$os->current_stage_id] ?? null;
            if ($current === null) {
                continue;
            }

            // Stage em cacamba_locacao = órfã SEMPRE (locação erradicada — ADR 0265,
            // decisão [W]: ex-locação é REPARO → quadro oficina_mecanica_os). Demais:
            // processo correto resolvido pelo order_type atual.
            $fromLocacao = $current['process_key'] === 'cacamba_locacao';
            $expectedProcess = $fromLocacao
                ? 'oficina_mecanica_os'
                : (self::ORDER_TYPE_TO_PROCESS[$os->order_type ?? ''] ?? null);

            if ($expectedProcess === null || $current['process_key'] === $expectedProcess) {
                continue; // sem mismatch — não é órfã
            }

            // Transição FSM real? (action_id NOT NULL num processo da Oficina deste business)
            $hasRealTransition = DB::table('sale_stage_history as h')
                ->join('sale_stage_actions as a', 'a.id', '=', 'h.action_id')
                ->join('sale_process_stages as s', 's.id', '=', 'a.stage_id')
                ->join('sale_processes as p', 'p.id', '=', 's.process_id')
                ->where('h.transaction_id', $os->id)
                ->where('h.business_id', $os->business_id)
                ->whereIn('p.key', self::OFICINA_PROCESS_KEYS)
                ->exists();

            if ($hasRealTransition) {
                $preservedWithHistory[] = $os->id; // não tocar — auditável no log
                continue;
            }

            $toStageId = $initialStage["{$os->business_id}:{$expectedProcess}"] ?? null;

            // Órfã de locação vira REPARO de verdade: order_type='mecanica' junto com o
            // re-aponte — senão resolveProcessKey divergiria do stage novo (beco de novo).
            $update = ['current_stage_id' => $toStageId];
            if ($fromLocacao && $os->order_type !== 'mecanica') {
                $update['order_type'] = 'mecanica';
            }

            DB::table('service_orders')
                ->where('id', $os->id)
                ->update($update);

            // Trilha append-only (mesmo shape do pipeline_started do starter).
            DB::table('sale_stage_history')->insert([
                'business_id'      => $os->business_id,
                'transaction_id'   => $os->id,
                'action_id'        => null,
                'from_stage_id'    => $os->current_stage_id,
                'to_stage_id'      => $toStageId,
                'user_id'          => null,
                'payload_snapshot' => json_encode([
                    'pipeline_repointed' => true,
                    'pipeline_started'   => $toStageId !== null,
                    'subject_type'       => \Modules\OficinaAuto\Entities\ServiceOrder::class,
                    'service_order_id'   => $os->id,
                    'process_key'        => $expectedProcess,
                    'order_type'         => $update['order_type'] ?? $os->order_type,
                    'order_type_before'  => $os->order_type,
                    'migration'          => '2026_06_10_000001_repoint_orphan_service_orders_from_legacy_pipelines',
                ]),
                'executed_at' => now(),
            ]);

            $repointed++;
        }

        Log::info('OficinaAuto repoint órfãs (ADR 0265): migration executada', [
            'repointed'               => $repointed,
            'preserved_with_history'  => $preservedWithHistory,
        ]);
    }

    public function down(): void
    {
        // No-op documentado: from_stage_id de cada re-aponte está preservado no
        // payload_snapshot (`pipeline_repointed`) da sale_stage_history — reversão
        // manual caso a caso é possível; rollback cego devolveria as OS pro beco
        // do pipeline de locação (exatamente o bug que o up() conserta).
    }
};
