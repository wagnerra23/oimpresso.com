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
 * URL. Enquanto o aviso do Repair/WhatsApp não manda o link sozinho, é por aqui que a empresa
 * (ou o suporte) obtém o link. Cria o código na primeira vez; depois devolve sempre o mesmo.
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

        $codigo = EmpresaPublica::codigoDe($businessId);
        $this->line(route('consulta-os.empresa', ['empresa' => $codigo]));

        return self::SUCCESS;
    }
}
