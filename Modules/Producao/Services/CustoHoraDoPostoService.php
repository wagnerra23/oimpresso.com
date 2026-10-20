<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Producao\CustoHoraDoPosto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custo da hora de máquina do posto, lido de `pcp_postos.custo_hora` (UC-FLX-33 · US-COMVIS-019).
 *
 * Só lê. Só posto de máquina (`tipo_custo = maquina`): a hora da pessoa não entra no custo
 * previsto do orçamento. Custo zero é o default da coluna e conta como "não informado".
 *
 * Tier 0 (ADR 0093): sempre o `$businessId` recebido, filtrado explicitamente.
 */
final class CustoHoraDoPostoService implements CustoHoraDoPosto
{
    public function custoHora(int $businessId, int $postoId): ?string
    {
        if ($businessId <= 0 || $postoId <= 0 || ! Schema::hasTable('pcp_postos')) {
            return null;
        }

        $custo = DB::table('pcp_postos')
            ->where('business_id', $businessId)
            ->where('id', $postoId)
            ->where('tipo_custo', 'maquina')
            ->where('custo_hora', '>', 0)
            ->value('custo_hora');

        return $custo === null ? null : number_format((float) $custo, 2, '.', '');
    }
}
