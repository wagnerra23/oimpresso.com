<?php

namespace App\Exceptions;

use Exception;

class PurchaseSellMismatch extends Exception
{
    /**
     * @param  string  $message
     * @param  int|null  $variationId  ID da variação do produto que causou o
     *                   mismatch (estoque/compra insuficiente). Permite o
     *                   frontend CONTORNAR a linha exata do carrinho com erro,
     *                   em vez de só mostrar um aviso genérico. 2026-06-04.
     */
    public function __construct($message, public ?int $variationId = null)
    {
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request)
    {
        $output = ['success' => 0,
            'msg' => $this->getMessage(),
        ];

        if ($request->ajax()) {
            return $output;
        } else {
            throw new Exception($this->getMessage());
        }
    }
}
