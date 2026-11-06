<?php

namespace Modules\OficinaAuto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Services\LembreteDeRevisao;
use Modules\OficinaAuto\Services\OficinaAutoAcesso;

/**
 * US-AUTO-014 — manda os lembretes de revisão devidos hoje (decisão [W] 2026-10-26).
 *
 *   php artisan oficinaauto:lembretes-revisao                  → todas as empresas com meta de revisão
 *   php artisan oficinaauto:lembretes-revisao --business=98    → uma empresa
 *   php artisan oficinaauto:lembretes-revisao --dry-run        → só conta, não manda nem registra
 *
 * Agendado no Kernel, desligado por padrão (`oficina-auto.lembrete_revisao.enabled`): manda WhatsApp a
 * clientes reais, então ligar é decisão [W]. Tier 0: cada empresa é processada pelo próprio `business_id`.
 *
 * Só empresa com o pacote `oficina_auto_module` (UC-OVREV-03): sem o pacote o módulo não está
 * contratado e o cliente dela não recebe aviso — vale também para `--business` explícito.
 */
class OficinaLembreteRevisaoCommand extends Command
{
    protected $signature = 'oficinaauto:lembretes-revisao {--business= : só esta empresa} {--dry-run : só conta}';

    protected $description = 'Avisa por WhatsApp (opt-in) os clientes com revisão do veículo por km ou data chegando';

    public function handle(LembreteDeRevisao $lembrete, OficinaAutoAcesso $acesso): int
    {
        $empresas = $this->option('business') !== null
            ? [(int) $this->option('business')]
            : DB::table('vehicles')->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNotNull('next_service_km')->orWhereNotNull('next_service_date'))
                ->distinct()->pluck('business_id')->map(fn ($b) => (int) $b)->all();

        $hoje = Carbon::now();
        foreach ($empresas as $biz) {
            if (! $acesso->pacoteAtivo($biz)) {
                $this->line(sprintf('business %d: sem o pacote oficina_auto_module, pulada', $biz));

                continue;
            }
            $p = $lembrete->enviar($biz, $hoje, (bool) $this->option('dry-run'));
            $this->line(sprintf('business %d: %d devido(s), %d enviado(s), %d recusado(s)', $biz, $p['devidos'], $p['enviados'], $p['recusados']));
        }

        return self::SUCCESS;
    }
}
