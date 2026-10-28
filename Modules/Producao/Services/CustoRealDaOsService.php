<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Producao\CustoRealDaOs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horas e m² impresso de uma OS do PCP para o pós-cálculo de quem não é dono de `pcp_*`
 * (US-COMVIS-005). As horas vêm do CustoRealService, sem mudar a conta. O m² impresso é a soma do
 * que o operador informou nas linhas `fim` (US-COMVIS-004 · UC-FLX-09), lido como texto, sem float.
 *
 * Só lê. Tier 0 (ADR 0093): sempre o `$businessId` recebido, filtrado explicitamente.
 */
final class CustoRealDaOsService implements CustoRealDaOs
{
    public function __construct(private readonly CustoRealService $custo) {}

    public function horas(int $businessId, string $osTipo, int $osId): array
    {
        $c = $this->custo->daOs($businessId, $osTipo, $osId);

        return ['maquina' => $c['maquina'], 'pessoa' => $c['pessoa']];
    }

    public function m2Impresso(int $businessId, string $osTipo, int $osId): string
    {
        if (! Schema::hasColumn('pcp_apontamentos', 'm2_impresso')) {
            return '0.000';
        }

        $milesimos = DB::table('pcp_apontamentos')->where('business_id', $businessId)->where('tipo', 'fim')
            ->where('os_tipo', $osTipo)->where('os_id', $osId)->whereNotNull('m2_impresso')->pluck('m2_impresso')
            ->sum(function ($m2): int {
                [$inteiro, $fracao] = array_pad(explode('.', (string) $m2, 2), 2, '0');

                return (int) $inteiro * 1000 + (int) str_pad(substr($fracao, 0, 3), 3, '0');
            });

        return sprintf('%d.%03d', intdiv($milesimos, 1000), $milesimos % 1000);
    }
}
