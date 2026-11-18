<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\ConsultaOs\Services\EmpresaPublica;

/**
 * consultaos:link {business_id} — imprime o link público do portal da empresa.
 *
 * Decisão [W] 2026-11-17 ("empresa na URL"): o portal só busca dentro da empresa do código na
 * URL. Desde 2026-11-18 o link nasce sozinho (configuração do Repair) e aparece lá com botão de
 * copiar; este comando segue para o suporte. Cria o código na primeira vez; depois devolve sempre
 * o mesmo.
 */
class ConsultaOsLinkCommand extends Command
{
    protected $signature = 'consultaos:link {business_id : id da empresa}';

    protected $description = 'Mostra (e cria na primeira vez) o link público /consulta-os/{codigo} de uma empresa.';

    public function handle(): int
    {
        $businessId = (int) $this->argument('business_id');

        if ($businessId <= 0 || ! DB::table('business')->where('id', $businessId)->exists()) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $this->line(EmpresaPublica::linkDe($businessId));

        return self::SUCCESS;
    }
}
