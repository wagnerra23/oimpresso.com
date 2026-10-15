<?php

namespace Modules\Producao\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\ApontamentoOperadorService;

/**
 * Fecha o cronômetro esquecido no fim do turno (UC-PCV-22) ou na saída do ponto (UC-PCV-36),
 * empresa por empresa. A regra é do `ApontamentoOperadorService`; aqui só se acha quem tem
 * apontamento aberto. Idempotente: a chave do fechamento é `auto-<id do início>`.
 */
class FecharTurnoCommand extends Command
{
    protected $signature = 'producao:fechar-turno';

    protected $description = 'Produção: fecha apontamentos abertos no fim do turno ou na saída do ponto';

    public function handle(ApontamentoOperadorService $svc): int
    {
        if (! Schema::hasTable('pcp_apontamentos')) {
            $this->warn('Tabelas pcp_* ausentes: nada medido.');

            return self::SUCCESS;
        }

        $empresas = DB::table('pcp_apontamentos as i')->where('i.tipo', 'inicio')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pcp_apontamentos as f')
                ->whereColumn('f.inicio_id', 'i.id')->whereColumn('f.business_id', 'i.business_id'))
            ->distinct()->pluck('i.business_id');

        $total = 0;
        foreach ($empresas as $biz) {
            $n = $svc->fecharAbertosNoFimDoTurno((int) $biz, Carbon::now());
            $total += $n;
            $this->line("business {$biz}: {$n} fechado(s)");
        }
        $this->info("{$empresas->count()} empresa(s) com apontamento aberto · {$total} fechado(s)");

        return self::SUCCESS;
    }
}
