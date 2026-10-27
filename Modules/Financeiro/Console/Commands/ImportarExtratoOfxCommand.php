<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financeiro\Services\Extrato\ImportarExtratoOfxService;

/**
 * `financeiro:importar-extrato-ofx` — importa um OFX no extrato de uma conta
 * (`fin_extrato_lancamentos`, lido pela Conciliação). US-FIN-084.
 *
 *   php artisan financeiro:importar-extrato-ofx --business=98 --conta=12 --arquivo=extrato.ofx --dry
 *
 * Idempotente pelo FITID. Com a flag desligada não grava nada.
 */
class ImportarExtratoOfxCommand extends Command
{
    protected $signature = 'financeiro:importar-extrato-ofx
        {--business= : ID do business (obrigatório — Tier 0)}
        {--conta= : ID da conta em fin_contas_bancarias}
        {--arquivo= : caminho do arquivo .ofx}
        {--dry : mostra o que leria sem gravar}';

    protected $description = 'Importa um arquivo OFX no extrato de uma conta bancária (conciliação).';

    public function handle(ImportarExtratoOfxService $service): int
    {
        if (! ImportarExtratoOfxService::ligado()) {
            $this->info('Importação de OFX por conta desligada (FINANCEIRO_EXTRATO_OFX_CONTA=false) — nada gravado.');

            return self::SUCCESS;
        }

        $businessId = (int) $this->option('business');
        $contaId = (int) $this->option('conta');
        $arquivo = (string) $this->option('arquivo');

        if ($businessId <= 0 || $contaId <= 0 || ! is_file($arquivo)) {
            $this->error('Informe --business, --conta e um --arquivo que exista.');

            return self::FAILURE;
        }

        $r = $service->importar($businessId, $contaId, (string) file_get_contents($arquivo), basename($arquivo), (bool) $this->option('dry'));

        $this->info(sprintf(
            '%slidas=%d novas=%d já existentes=%d · créditos R$ %s · débitos R$ %s',
            $this->option('dry') ? '[dry] ' : '',
            $r['lidas'], $r['novas'], $r['existentes'],
            number_format($r['creditos'], 2, ',', '.'),
            number_format($r['debitos'], 2, ',', '.'),
        ));

        return self::SUCCESS;
    }
}
