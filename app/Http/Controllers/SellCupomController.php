<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Sells\CupomDaVenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /sells/cupom — US-SELL-076. O caixa manda o código e o carrinho; recebe o desconto do
 * cupom pela regra de acúmulo, sem gravar nada. Desligado (`SELLS_CUPOM`) → 404.
 * Cupom de outra empresa = inválido (lido pelo business da sessão). Permissão: `sell.create`.
 */
class SellCupomController extends Controller
{
    private const MENSAGEM = [
        'invalido' => 'Cupom não encontrado.',
        'fora_da_validade' => 'Este cupom está fora da validade.',
        'esgotado' => 'Este cupom já foi usado o número máximo de vezes.',
        'abaixo_do_minimo' => 'A compra não chegou ao valor mínimo deste cupom.',
        'promocao_maior' => 'A promoção do carrinho já dá mais desconto que este cupom, e ele não acumula.',
    ];

    public function avaliar(Request $request, CupomDaVenda $cupom): JsonResponse
    {
        abort_unless(CupomDaVenda::ligado(), 404);
        abort_unless(auth()->user()?->can('sell.create'), 403);

        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'promocao' => ['nullable', 'numeric', 'min:0'],
        ]);

        $r = $cupom->avaliar((int) session('user.business_id'), $dados['codigo'],
            round((float) $dados['subtotal'], 2), round((float) ($dados['promocao'] ?? 0), 2), now());

        // JSON_PRESERVE_ZERO_FRACTION: o desconto é dinheiro (float); sem a flag, 30.0 sai como
        // `30` e o cliente recebe um inteiro — o contrato (UC-VCUP-05) pede o valor monetário.
        return response()->json(
            $r + ['mensagem' => $r['motivo'] ? self::MENSAGEM[$r['motivo']] : null],
            200, [], JSON_PRESERVE_ZERO_FRACTION
        );
    }
}
