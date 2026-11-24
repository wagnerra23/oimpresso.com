<?php

declare(strict_types=1);

namespace Modules\Fisio\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Jobs\EnviarConfirmacaoConsultaJob;
use Modules\Fisio\Services\FisioAcesso;

/**
 * `fisio:confirmacoes` — enfileira um `EnviarConfirmacaoConsultaJob` por empresa com o pacote
 * `fisio_module` e atendimento ainda `agendado` (etapa 04 A6). Agendado de hora em hora pelo
 * `FisioServiceProvider`, só em produção. Idempotente: o job manda uma mensagem por atendimento.
 */
class ConfirmacoesCommand extends Command
{
    protected $signature = 'fisio:confirmacoes';

    protected $description = 'Enfileira a confirmação das consultas por WhatsApp para cada empresa com o módulo Fisio.';

    public function handle(FisioAcesso $acesso): int
    {
        $empresas = DB::table('fisio_appointments')->where('status', 'agendado')
            ->distinct()->orderBy('business_id')->pluck('business_id')->map('intval');
        $enfileirados = 0;
        foreach ($empresas as $biz) {
            if (! $acesso->pacoteAtivo($biz)) {
                continue;
            }
            EnviarConfirmacaoConsultaJob::dispatch($biz);
            $enfileirados++;
        }

        $this->info("Confirmações enfileiradas: {$enfileirados} empresa(s).");

        return self::SUCCESS;
    }
}
