<?php

namespace Modules\Ponto\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\Rep;

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

    /**
     * NSR do REP-P: sequencial POR COLABORADOR ([W] 2026-09-29, thread 06).
     *
     * O REP-P não tem REP físico — o "registrador" é o aparelho do colaborador —, então a
     * sequência sem lacunas da Portaria MTP 671/2021 é contada por (business_id, colaborador),
     * sobre as marcações `REP_P` sem REP. Antes era `microtime` (não sequencial).
     *
     * Concorrência: `lockForUpdate` na linha do colaborador em `ponto_colaborador_config`. Chamar
     * DENTRO da transação do MarcacaoService, que é quem segura o lock até o INSERT.
     *
     * Legado: marcações REP-P gravadas antes desta regra carregam NSR de `microtime` (13 dígitos).
     * Elas ficam FORA do max — senão a sequência nova herdaria um número de 13 dígitos.
     */
    public function proximoRepP(int $businessId, int $colaboradorId): int
    {
        DB::table('ponto_colaborador_config')
            ->where('business_id', $businessId)
            ->where('id', $colaboradorId)
            ->lockForUpdate()
            ->first();

        $ultimo = DB::table('ponto_marcacoes')
            ->where('business_id', $businessId)
            ->where('colaborador_config_id', $colaboradorId)
            ->whereNull('rep_id')
            ->where('origem', 'REP_P')
            ->where('nsr', '<', self::NSR_LEGADO_MICROTIME)
            ->max('nsr');

        return (int) $ultimo + 1;
    }

    /** Acima disto o NSR veio do `microtime` antigo, não de uma sequência. */
    public const NSR_LEGADO_MICROTIME = 1_000_000_000;

    protected function proximoVirtual(): int
    {
        // Para origens sem REP físico, usa contador por business via cache ou tabela dedicada
        return (int) (microtime(true) * 1000);
    }
}
