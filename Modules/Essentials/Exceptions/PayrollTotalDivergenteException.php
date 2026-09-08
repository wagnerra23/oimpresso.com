<?php

declare(strict_types=1);

namespace Modules\Essentials\Exceptions;

use RuntimeException;

/**
 * Levantada quando o total que o formulário mandou para um contracheque diverge do total que
 * o servidor recalculou com a FORMA de uma inflação (razão ≥ 10× ou ≤ 1/10×).
 *
 * Divergência pequena (arredondamento) não chega aqui: ela vira ALERT no log e o número do
 * servidor prevalece. Esta exceção existe para o caso em que aceitar seria repetir o incidente
 * ROTA LIVRE de 2026-06-05 — quando um valor cru locale-ambíguo virou um total ~100.000× maior.
 *
 * @see Modules\Essentials\Services\PayrollTotalCalculator
 * @see memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE"
 */
class PayrollTotalDivergenteException extends RuntimeException {}
