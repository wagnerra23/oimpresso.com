<?php

namespace Modules\Copiloto\Contracts;

use Carbon\Carbon;
use Modules\Copiloto\Entities\Meta;

/**
 * Interface para drivers de apuração.
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 */
interface CalculaMeta
{
    /**
     * Calcula o valor realizado da meta na janela de tempo informada.
     *
     * @param  Meta   $meta     Meta a apurar (contém business_id e fonte via $meta->fonte).
     * @param  Carbon $dataIni  Início da janela (inclusive).
     * @param  Carbon $dataFim  Fim da janela (inclusive).
     * @return float            Valor realizado no período.
     */
    public function apurar(Meta $meta, Carbon $dataIni, Carbon $dataFim): float;
}
