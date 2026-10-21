<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Services\AssinaturaEntrega;

/**
 * Liga/desliga, por empresa, a exigência de assinatura do cliente na entrega da OS
 * (US-OFICINA-048 · UC-FLX-22). Desligada por padrão.
 *
 * Grava em `business.common_settings[oficina_entrega_exige_assinatura]` pelo próprio serviço — é o
 * caminho registrado para mudar o ajuste (nada de tinker em produção). Idempotente.
 *
 * Uso:
 *   php artisan oficina:entrega-assinatura 1            # mostra o estado
 *   php artisan oficina:entrega-assinatura 1 --ligar
 *   php artisan oficina:entrega-assinatura 1 --desligar
 */
class OficinaEntregaAssinaturaCommand extends Command
{
    protected $signature = 'oficina:entrega-assinatura {biz : business_id} {--ligar} {--desligar}';

    protected $description = 'Liga/desliga a exigência de assinatura do cliente na entrega da OS (por empresa).';

    public function handle(AssinaturaEntrega $assinatura): int
    {
        $biz = (int) $this->argument('biz');
        if ($biz <= 0 || ! DB::table('business')->where('id', $biz)->exists()) {
            $this->error("Empresa {$biz} não encontrada.");

            return self::FAILURE;
        }

        $ligar = (bool) $this->option('ligar');
        $desligar = (bool) $this->option('desligar');
        if ($ligar && $desligar) {
            $this->error('Use --ligar OU --desligar.');

            return self::INVALID;
        }

        if ($ligar || $desligar) {
            $assinatura->definir($biz, $ligar);
        }

        $this->info('Empresa ' . $biz . ': assinatura na entrega ' . ($assinatura->exigida($biz) ? 'EXIGIDA' : 'não exigida') . '.');

        return self::SUCCESS;
    }
}
