<?php

namespace Modules\Financeiro\Contracts;

use Carbon\CarbonInterface;
use Modules\Financeiro\Dto\BoletoDdaRecebido;

/**
 * Fonte dos boletos DDA de uma empresa (US-FIN-078).
 *
 * Nenhuma implementação está ligada: em 2026-10-27 a API pública do Inter não documenta
 * consulta de DDA, e escolher a fonte (outro banco ou provedor de DDA) é decisão do [W].
 * Quem implementar registra a classe no container com a tag `financeiro.fontes_dda`.
 */
interface FonteDda
{
    /** Identificador curto gravado em `fin_dda_boletos.fonte` (ex.: `inter`). */
    public function nome(): string;

    /**
     * Boletos com vencimento a partir de `$desde` emitidos contra o CNPJ da empresa.
     *
     * @return iterable<BoletoDdaRecebido>
     */
    public function boletos(int $businessId, CarbonInterface $desde): iterable;
}
