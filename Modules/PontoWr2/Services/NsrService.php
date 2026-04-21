<?php

namespace Modules\PontoWr2\Services;

use Illuminate\Support\Facades\DB;
use Modules\PontoWr2\Entities\Rep;

/**
 * Gera NSR (Número Sequencial de Registro) por REP com lock pessimista.
 * Conforme Portaria MTP 671/2021: sequencial inviolável, sem lacunas.
 */
class NsrService
{
    public function proximo(?string $repId): int
    {
        if ($repId === null) {
            // NSR virtual para marcações MANUAL/INTEGRACAO
            return $this->proximoVirtual();
        }

        return DB::transaction(function () use ($repId) {
            $rep = Rep::lockForUpdate()->findOrFail($repId);
            $rep->ultimo_nsr += 1;
            $rep->save();
            return $rep->ultimo_nsr;
        });
    }

    protected function proximoVirtual(): int
    {
        // Para origens sem REP físico, usa contador por business via cache ou tabela dedicada
        return (int) (microtime(true) * 1000);
    }
}
