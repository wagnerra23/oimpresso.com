<?php

namespace Modules\Copiloto\Services;

use Carbon\Carbon;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaApuracao;

/**
 * ApuracaoService — orquestra a execução de drivers e persiste realizado.
 *
 * STUB spec-ready: resolver de driver ainda não carregado; método apurar()
 * persiste placeholder. Regras de segurança dos drivers estão em
 * adr/tech/0001-drivers-apuracao-plugaveis.md.
 */
class ApuracaoService
{
    public function apurar(Meta $meta, Carbon $dataRef): MetaApuracao
    {
        // TODO: resolver driver via container tag 'copiloto.drivers'
        // TODO: executar com binds injetados (:business_id, :data_ini, :data_fim)
        // TODO: calcular fonte_query_hash e upsert idempotente

        return MetaApuracao::updateOrCreate(
            [
                'meta_id'           => $meta->id,
                'data_ref'          => $dataRef->toDateString(),
                'fonte_query_hash'  => 'stub-' . md5((string) $meta->id),
            ],
            [
                'valor_realizado'   => 0,
                'calculado_em'      => now(),
            ]
        );
    }
}
