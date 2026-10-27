<?php

declare(strict_types=1);

namespace Modules\Crm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Services\Nps\PesquisaNps;

/**
 * Envia a pesquisa NPS de vendas e OS entregues (US-CRM-097). Flag `CRM_NPS_ENABLED`
 * desligada = não faz nada. Uma empresa por vez, `business_id` explícito (ADR 0093).
 */
class EnviarPesquisaNpsCommand extends Command
{
    protected $signature = 'crm:nps-enviar {--business= : só esta empresa}';

    protected $description = 'Envia a pesquisa NPS (0-10) pós-venda e pós-OS';

    public function handle(PesquisaNps $nps): int
    {
        if (! (bool) config('crm.nps_enabled', false)) {
            $this->info('NPS desligado (CRM_NPS_ENABLED).');

            return self::SUCCESS;
        }

        $ids = $this->option('business') !== null
            ? [(int) $this->option('business')]
            : DB::table('business')->where('is_active', 1)->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();

        foreach ($ids as $biz) {
            $r = $nps->enviarPendentes($biz);
            if ($r['enviadas'] + $r['nao_enviadas'] > 0) {
                $this->line("business {$biz}: {$r['enviadas']} enviada(s), {$r['nao_enviadas']} não enviada(s)");
            }
        }

        return self::SUCCESS;
    }
}
