<?php

declare(strict_types=1);

namespace Modules\Fisio\Console;

use Illuminate\Console\Command;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Services\CheckinDoPortal;

/**
 * `fisio:reenviar-alertas` — tenta de novo o aviso de sinal de alerta que falhou no check-in
 * (auditoria do Fisio d4). Pendente = `red_flags` menos `red_flags_avisados`. Olha os check-ins
 * dos últimos 7 dias. Cada check-in é tratado pelo business dele. Agendado a cada 15 minutos
 * pelo `FisioServiceProvider`. Sem pendência, não faz nada.
 */
class ReenviarAlertasCommand extends Command
{
    protected $signature = 'fisio:reenviar-alertas';

    protected $description = 'Reenvia ao profissional os sinais de alerta do check-in cujo aviso falhou.';

    public function handle(CheckinDoPortal $checkins): int
    {
        $enviados = 0;
        $falhas = 0;
        $desde = now()->subDays(7)->toDateString();

        foreach (Checkin::withoutGlobalScopes() // SUPERADMIN: varre todas as empresas; cada check-in segue no business dele.
            ->whereNotNull('red_flags')
            ->where('date', '>=', $desde)
            ->orderBy('id')
            ->cursor() as $checkin) {
            if (CheckinDoPortal::alertasPendentes($checkin) === []) {
                continue;
            }
            $checkins->avisarAlertasPendentes($checkin) ? $enviados++ : $falhas++;
        }

        $this->info("Alertas reenviados: {$enviados}. Falharam de novo: {$falhas}.");

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
