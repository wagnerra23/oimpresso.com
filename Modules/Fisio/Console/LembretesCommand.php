<?php

declare(strict_types=1);

namespace Modules\Fisio\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Jobs\EnviarLembreteDiarioJob;
use Modules\Fisio\Services\FisioAcesso;

/**
 * `fisio:lembretes` — enfileira um `EnviarLembreteDiarioJob` por empresa com o pacote
 * `fisio_module` (UC-FPN-07). Empresa sem o pacote não recebe job. Agendado diariamente pelo
 * `FisioServiceProvider` (`fisio.whatsapp.lembrete_horario`). Idempotente: o job não repete
 * lembrete no mesmo dia.
 */
class LembretesCommand extends Command
{
    protected $signature = 'fisio:lembretes';

    protected $description = 'Enfileira o lembrete diário de exercícios (WhatsApp) para cada empresa com o módulo Fisio.';

    public function handle(FisioAcesso $acesso): int
    {
        // Só empresas que têm paciente no Fisio; o pacote é conferido uma a uma.
        $empresas = DB::table('fisio_patients')->distinct()->orderBy('business_id')->pluck('business_id')->map('intval');
        $enfileirados = 0;
        foreach ($empresas as $biz) {
            if (! $acesso->pacoteAtivo($biz)) {
                continue;
            }
            EnviarLembreteDiarioJob::dispatch($biz);
            $enfileirados++;
        }

        $this->info("Lembretes enfileirados: {$enfileirados} empresa(s).");

        return self::SUCCESS;
    }
}