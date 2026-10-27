<?php

declare(strict_types=1);

namespace Modules\Crm\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Services\Fidelidade\SegmentacaoRfmDoCrm;

/**
 * Recalcula a segmentação RFM (US-CRM-098). Sem `--business`, roda para cada empresa ativa.
 * Só lê vendas; grava `crm_rfm_clientes`. Agendado uma vez por dia quando `crm.rfm.enabled`.
 */
class RfmCalcularCommand extends Command
{
    protected $signature = 'crm:rfm-calcular {--business= : só esta empresa}';

    protected $description = 'Recalcula a segmentação RFM (recência, frequência e valor) dos clientes';

    public function handle(SegmentacaoRfmDoCrm $rfm): int
    {
        $empresas = $this->option('business') !== null
            ? [(int) $this->option('business')]
            : DB::table('business')->where('is_active', 1)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $hoje = CarbonImmutable::now();
        foreach ($empresas as $businessId) {
            $porSegmento = $rfm->calcular($businessId, $hoje);
            $this->line(sprintf('empresa %d: %d cliente(s) %s', $businessId, array_sum($porSegmento), json_encode($porSegmento)));
        }

        return self::SUCCESS;
    }
}
