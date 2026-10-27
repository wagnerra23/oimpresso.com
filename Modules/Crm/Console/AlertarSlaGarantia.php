<?php

declare(strict_types=1);

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Services\ChamadoGarantiaService;

/**
 * Alerta de prazo dos chamados de garantia — US-CRM-094 fatia 2.
 *
 * Roda por empresa: lista as empresas com chamado de garantia ainda sem alerta e chama
 * `ChamadoGarantiaService::alertarVencendo($businessId)`, que só mexe nos chamados daquela
 * empresa. Com `CRM_GARANTIA_SLA_ENABLED` desligada, não faz nada.
 */
class AlertarSlaGarantia extends Command
{
    protected $signature = 'crm:garantia-sla-alertar';

    protected $description = 'Avisa o responsável quando o prazo (SLA) do chamado de garantia está vencendo ou venceu.';

    public function handle(ChamadoGarantiaService $servico): int
    {
        if (! config('crm.garantia_sla.enabled')) {
            $this->info('Chamado de garantia desligado (CRM_GARANTIA_SLA_ENABLED) — nada a fazer.');

            return self::SUCCESS;
        }

        $empresas = DB::table('crm_schedules')
            ->where('garantia', true)
            ->whereNull('sla_alertado_em')
            ->distinct()
            ->pluck('business_id');

        $total = 0;
        foreach ($empresas as $businessId) {
            $total += $servico->alertarVencendo((int) $businessId);
        }

        $this->info("Chamados de garantia avisados: {$total}.");

        return self::SUCCESS;
    }
}
