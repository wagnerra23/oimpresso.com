<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Producao\HorasDosPostos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horas por posto no mês + troca do custo-hora (US-FIN-070 · UC-FLX-20).
 *
 * Trecho fechado = linha `inicio` + a linha `pausa`/`fim` com `inicio_id` apontando pra ela,
 * mesma regra do CustoRealService. O trecho entra no mês do INÍCIO, inteiro (um trecho que
 * cruza a virada do mês conta no mês em que começou).
 *
 * Tier 0 (ADR 0093): `$businessId` recebido entra em toda consulta, inclusive no join.
 */
final class HorasDosPostosService implements HorasDosPostos
{
    public function noMes(int $businessId, string $mes): array
    {
        if ($businessId <= 0 || ! Schema::hasTable('pcp_apontamentos')) {
            return [];
        }
        $de = Carbon::createFromFormat('Y-m-d H:i:s', $mes.'-01 00:00:00');
        $ate = $de->copy()->addMonthNoOverflow();

        $pares = DB::table('pcp_apontamentos as i')
            ->join('pcp_apontamentos as f', function ($j) {
                $j->on('f.inicio_id', '=', 'i.id')->on('f.business_id', '=', 'i.business_id');
            })
            ->where('i.business_id', $businessId)->where('i.tipo', 'inicio')
            ->where('i.momento', '>=', $de)->where('i.momento', '<', $ate)
            ->get(['i.posto_id', 'i.momento as de', 'f.momento as ate']);

        $segundos = [];
        foreach ($pares as $p) {
            $id = (int) $p->posto_id;
            $segundos[$id] = ($segundos[$id] ?? 0) + max(0, strtotime($p->ate) - strtotime($p->de));
        }
        if ($segundos === []) {
            return [];
        }

        return DB::table('pcp_postos')->where('business_id', $businessId)->whereIn('id', array_keys($segundos))
            ->orderBy('nome')->get(['id', 'codigo', 'nome', 'custo_hora'])
            ->map(fn ($p) => ['posto_id' => (int) $p->id, 'codigo' => (string) $p->codigo, 'nome' => (string) $p->nome,
                'custo_hora' => number_format((float) $p->custo_hora, 2, '.', ''), 'segundos' => $segundos[(int) $p->id]])
            ->values()->all();
    }

    public function trocarCustoHora(int $businessId, int $postoId, string $novo): ?array
    {
        $antes = DB::table('pcp_postos')->where('business_id', $businessId)->where('id', $postoId)->value('custo_hora');
        if ($antes === null) {
            return null;
        }
        DB::table('pcp_postos')->where('business_id', $businessId)->where('id', $postoId)
            ->update(['custo_hora' => $novo, 'updated_at' => now()]);

        return ['antes' => number_format((float) $antes, 2, '.', ''), 'depois' => $novo];
    }
}
