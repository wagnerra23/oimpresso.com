<?php

namespace App\Exceptions;

use Exception;

/**
 * Devolução de venda maior que a quantidade vendida na linha.
 *
 * Lançada por `TransactionUtil::addSellReturn` ANTES de qualquer escrita — sem esta guarda,
 * devolver 100 numa venda de 10 creditava 100 unidades no estoque sem lastro (medido em
 * 2026-08-18: `qty_available` 10 → 110).
 *
 * A mensagem é destinada ao usuário final: os dois chamadores (`SellReturnController@store`
 * e a API `Modules\Connector` .../Api/SellController@addSellReturn) a exibem.
 *
 * @see memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md (CU-DEV-08)
 */
class SellReturnExceedsSold extends Exception
{
}
