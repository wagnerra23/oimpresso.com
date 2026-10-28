<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financeiro\Contracts\FonteDda;
use Modules\Financeiro\Services\DdaService;

/**
 * `financeiro:dda-sync --business=N` — traz os boletos DDA de cada fonte ligada e grava como
 * contas a pagar SUGERIDAS (US-FIN-078). Não cria título: isso é ato do usuário na tela.
 *
 * Sai com erro, e não verde, quando a flag está desligada ou nenhuma fonte está registrada —
 * "nada importado" e "não consegui importar" não podem parecer a mesma coisa.
 */
class DdaSyncCommand extends Command
{
    protected $signature = 'financeiro:dda-sync {--business= : business a sincronizar} {--dias=30 : vencimentos a partir de hoje menos N dias}';

    protected $description = 'DDA: grava os boletos emitidos contra o CNPJ da empresa como contas a pagar sugeridas.';

    public function handle(DdaService $dda): int
    {
        if (! config('financeiro.dda.enabled')) {
            $this->error('DDA desligado (FINANCEIRO_DDA_ENABLED=false).');

            return self::FAILURE;
        }

        $biz = (int) $this->option('business');
        if ($biz <= 0) {
            $this->error('Informe --business.');

            return self::INVALID;
        }

        /** @var FonteDda[] $fontes */
        $fontes = iterator_to_array(app()->tagged('financeiro.fontes_dda'));
        if ($fontes === []) {
            $this->error('Nenhuma fonte de DDA registrada — a fonte depende de decisão do [W] (ver BRIEFING).');

            return self::FAILURE;
        }

        $desde = now()->subDays((int) $this->option('dias'))->startOfDay();
        $total = 0;
        foreach ($fontes as $fonte) {
            foreach ($fonte->boletos($biz, $desde) as $boleto) {
                $dda->registrar($biz, $fonte->nome(), $boleto);
                $total++;
            }
        }

        $this->info("DDA: {$total} boleto(s) lido(s) para o business {$biz}.");

        return self::SUCCESS;
    }
}
