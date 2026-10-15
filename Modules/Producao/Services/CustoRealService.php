<?php

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;

/**
 * Custo real da OS (E02 · UC-PCV-32) — cálculo de VALOR (regra mestre, proibicoes.md):
 *
 *   custo = Σ(horas máquina × custo_hora) + Σ(horas pessoa × custo_hora) + terceiros
 *
 * Conta em centavos × segundos (inteiros) e arredonda UMA vez por tipo de custo, meio para cima;
 * sem float. Só entram apontamentos FECHADOS (par inicio/fim). O custo-hora é o copiado no
 * início do apontamento. Terceiros chega por parâmetro: o lançamento de terceiro é da thread 19.
 */
class CustoRealService
{
    /** @return array{maquina: string, pessoa: string, terceiros: string, total: string} */
    public function daOs(int $businessId, string $osTipo, int $osId, string $terceiros = '0.00'): array
    {
        $pares = DB::table('pcp_apontamentos as i')
            ->join('pcp_apontamentos as f', function ($j) {
                $j->on('f.inicio_id', '=', 'i.id')->on('f.business_id', '=', 'i.business_id');
            })
            ->where('i.business_id', $businessId)->where('i.tipo', 'inicio')
            ->where('i.os_tipo', $osTipo)->where('i.os_id', $osId)
            ->get(['i.tipo_custo', 'i.custo_hora', 'i.momento as de', 'f.momento as ate']);

        $acumulado = ['maquina' => 0, 'pessoa' => 0];
        foreach ($pares as $p) {
            $segundos = max(0, strtotime($p->ate) - strtotime($p->de));
            $acumulado[$p->tipo_custo] += $this->centavos($p->custo_hora) * $segundos;
        }

        $centavos = array_map(fn (int $n) => intdiv($n + 1800, 3600), $acumulado);
        $centavos['terceiros'] = $this->centavos($terceiros);
        $centavos['total'] = $centavos['maquina'] + $centavos['pessoa'] + $centavos['terceiros'];

        return array_map(fn (int $c) => sprintf('%d.%02d', intdiv($c, 100), $c % 100), $centavos);
    }

    private function centavos(string|float|int $valor): int
    {
        [$inteiro, $fracao] = array_pad(explode('.', (string) $valor, 2), 2, '0');

        return (int) $inteiro * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');
    }
}
