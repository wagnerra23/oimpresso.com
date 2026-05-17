<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Metrics;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MetricsSnapshotBuilder — encapsula leitura de snapshot agregado de envio
 * Whatsapp por janela de tempo (extraído de WhatsappObservabilityHealthCommand,
 * Wave 18 saturation D4 Architecture).
 *
 * Stateless puro (sem deps no constructor — pattern canon LicencaService).
 * Multi-tenant Tier 0 (ADR 0093): caller sempre passa $businessId explícito.
 * Zero-cost OtelHelper::spanBiz envolve queries (instrumentação ADR 0155 D9.a).
 *
 * @see Modules/Whatsapp/Services/Metrics/MetricsAggregator.php (consumidor)
 * @see Modules/Whatsapp/Tests/Feature/ObservabilityTest.php
 */
class MetricsSnapshotBuilder
{
    /** Tabela canonica de mensagens whatsapp. */
    private const T_MSGS = 'whatsapp_messages';

    /** Tabela canonica de phones (driver lookup). */
    private const T_PHONES = 'whatsapp_business_phones';

    /**
     * Snapshot de envio outbound: total/sucesso/falha/taxa numa janela.
     *
     * Retorna sempre estrutura completa (zeros se sem registros) pra UI degradar safely.
     *
     * @return array{total:int,sucesso:int,falha:int,taxa_sucesso:float,janela_horas:int}
     */
    public function snapshotOutbound(int $businessId, int $janelaHoras = 24): array
    {
        return OtelHelper::span('whatsapp.metrics.snapshot_outbound', [
            'business_id'  => $businessId,
            'janela_horas' => $janelaHoras,
            'module'       => 'Whatsapp',
        ], function () use ($businessId, $janelaHoras) {
            if (! Schema::hasTable(self::T_MSGS)) {
                return $this->zeros($janelaHoras);
            }

            $rows = DB::table(self::T_MSGS)
                ->where('business_id', $businessId)
                ->where('direction', 'outbound')
                ->where('created_at', '>=', now()->subHours($janelaHoras))
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $total = (int) array_sum($rows);
            $sucesso = (int) (($rows['delivered'] ?? 0) + ($rows['read'] ?? 0) + ($rows['sent'] ?? 0));
            $falha = (int) (($rows['failed'] ?? 0) + ($rows['error'] ?? 0));
            $taxa = $total > 0 ? round(($sucesso / $total) * 100, 2) : 0.0;

            return [
                'total'         => $total,
                'sucesso'       => $sucesso,
                'falha'         => $falha,
                'taxa_sucesso'  => $taxa,
                'janela_horas'  => $janelaHoras,
            ];
        });
    }

    /**
     * Snapshot por driver (z-api / meta_cloud / baileys) pra detectar driver degradado.
     *
     * @return array<string,array{total:int,phones:int}>
     */
    public function snapshotPorDriver(int $businessId): array
    {
        return OtelHelper::span('whatsapp.metrics.snapshot_por_driver', [
            'business_id' => $businessId,
            'module'      => 'Whatsapp',
        ], function () use ($businessId) {
            if (! Schema::hasTable(self::T_PHONES)) {
                return [];
            }

            return DB::table(self::T_PHONES)
                ->where('business_id', $businessId)
                ->select('driver', DB::raw('COUNT(*) as phones'))
                ->groupBy('driver')
                ->get()
                ->mapWithKeys(fn ($r) => [
                    $r->driver => ['total' => 0, 'phones' => (int) $r->phones],
                ])
                ->toArray();
        });
    }

    /**
     * Estrutura zeros pra fail-soft quando schema ausente.
     *
     * @return array{total:int,sucesso:int,falha:int,taxa_sucesso:float,janela_horas:int}
     */
    private function zeros(int $janelaHoras): array
    {
        return [
            'total'         => 0,
            'sucesso'       => 0,
            'falha'         => 0,
            'taxa_sucesso'  => 0.0,
            'janela_horas'  => $janelaHoras,
        ];
    }
}
