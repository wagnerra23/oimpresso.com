<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Services;

use App\Business;

/**
 * Formata número e dinheiro da vitrine pública pela moeda da EMPRESA.
 *
 * A vitrine é anônima: a rota não passa por SetSessionData, então `session('currency')` vem
 * vazia e `@num_format` / `num_f` sem Business não têm separador. Os separadores e o símbolo
 * moram em `currencies` (via `$business->currency`); a precisão e a posição do símbolo, em
 * `business`.
 */
final class FormatoVitrine
{
    public function __construct(private Business $business)
    {
    }

    public function numero(float|int|string|null $valor): string
    {
        $moeda = $this->business->currency;

        return number_format(
            (float) $valor,
            (int) ($this->business->currency_precision ?? 2),
            (string) ($moeda->decimal_separator ?? ','),
            (string) ($moeda->thousand_separator ?? '.'),
        );
    }

    public function dinheiro(float|int|string|null $valor): string
    {
        $simbolo = (string) ($this->business->currency->symbol ?? '');
        $numero = $this->numero($valor);

        if ($simbolo === '') {
            return $numero;
        }

        return $this->business->currency_symbol_placement === 'after'
            ? $numero . ' ' . $simbolo
            : $simbolo . ' ' . $numero;
    }

    /**
     * Texto do selo de desconto. Segue o tipo como o PDV (`sale_pos/product_row.blade.php`):
     * só `percentage` é porcentagem; qualquer outro tipo é abatimento em dinheiro.
     */
    public function seloDesconto(object $desconto): string
    {
        if (($desconto->discount_type ?? null) === 'percentage') {
            return '- ' . $this->numero($desconto->discount_amount) . '%';
        }

        return '- ' . $this->dinheiro($desconto->discount_amount);
    }
}
