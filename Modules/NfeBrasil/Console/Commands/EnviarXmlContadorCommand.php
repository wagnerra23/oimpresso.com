<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Services\PacoteXmlContadorService;

/**
 * US-NFE-073 · envia ao contador o XML do mês anterior (ou de `--mes`).
 *
 * Percorre as empresas com contador cadastrado (`nfe_contadores`). Idempotente por
 * empresa × competência (`nfe_envios_contador`). O agendamento mensal só roda com
 * `NFEBRASIL_ENVIO_XML_CONTADOR=true` (NfeBrasilServiceProvider); rodado à mão, roda sempre.
 */
class EnviarXmlContadorCommand extends Command
{
    protected $signature = 'nfe:enviar-xml-contador
                            {--mes= : Competência AAAA-MM (padrão: mês anterior)}
                            {--business-id= : Apenas 1 empresa}
                            {--dry-run : Monta o pacote e não envia nem grava}';

    protected $description = 'Envia ao contador o ZIP com o XML das notas autorizadas e canceladas do mês';

    public function handle(PacoteXmlContadorService $pacote): int
    {
        $mes = (string) ($this->option('mes') ?: now('America/Sao_Paulo')->subMonthNoOverflow()->format('Y-m'));

        $empresas = DB::table('nfe_contadores')->orderBy('business_id')->pluck('business_id')->map(fn ($id) => (int) $id);
        if ($this->option('business-id') !== null) {
            $empresas = $empresas->filter(fn (int $id) => $id === (int) $this->option('business-id'));
        }

        $contagem = [];
        foreach ($empresas as $businessId) {
            $r = $pacote->enviar($businessId, $mes, (bool) $this->option('dry-run'));
            $contagem[$r] = ($contagem[$r] ?? 0) + 1;
            $this->line("business {$businessId} · {$mes} · {$r}");
        }

        Log::info('nfe:enviar-xml-contador', ['competencia' => $mes, 'resultado' => $contagem, 'dry_run' => (bool) $this->option('dry-run')]);

        return ($contagem['falha'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
