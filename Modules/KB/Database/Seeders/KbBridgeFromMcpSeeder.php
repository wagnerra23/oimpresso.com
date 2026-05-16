<?php

declare(strict_types=1);

namespace Modules\KB\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\KB\Jobs\KbBridgeFromMcpJob;

/**
 * KbBridgeFromMcpSeeder — primeira execução do bridge job, sincronizando
 * o corpus canon (mcp_memory_documents) → kb_nodes.
 *
 * Contrato: SCHEMA-DB-V1.md §13
 *
 * Roda SÍNCRONO (job::dispatchSync) na primeira instalação pra Wagner ver
 * o grafo populado imediatamente. Em prod, schedule a cada 15min mantém fresh.
 *
 * Aceita $businessId — multi-tenant Tier 0 (sem session() em CLI).
 *
 * Wagner (biz=1) tem 352+ docs → ~700 nodes bridge esperados.
 * ROTA LIVRE (biz=4) tem ~50-70 docs relevantes.
 */
class KbBridgeFromMcpSeeder extends Seeder
{
    public function run(int $businessId, bool $forceFullSweep = true): void
    {
        $this->command?->info("KbBridgeFromMcpSeeder [biz={$businessId}]: iniciando bridge (forceFullSweep={$forceFullSweep})...");

        try {
            KbBridgeFromMcpJob::dispatchSync($businessId, $forceFullSweep);
            $this->command?->info("KbBridgeFromMcpSeeder [biz={$businessId}]: completo. Veja kb_bridge_state pra métricas.");
        } catch (\Throwable $e) {
            $this->command?->error("KbBridgeFromMcpSeeder [biz={$businessId}]: FALHOU — {$e->getMessage()}");
            // TODO[CL]: re-throw em prod? por ora swallow pra não derrubar Install.
        }
    }
}
