<?php

declare(strict_types=1);

namespace Modules\Compras\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Compras\Services\PropostasDePreco;

/**
 * Aprovar e recusar o preço proposto pelo recebimento (US-COM-028 · UC-FLX-17).
 *
 * Mexe no preço de venda do produto: exige `product.update`. Tier 0 (ADR 0093): a empresa vem do
 * usuário logado e o serviço filtra proposta e produto por ela.
 */
class PropostasPrecoController extends Controller
{
    public function __construct(private readonly PropostasDePreco $propostas) {}

    public function aprovar(Request $request, int $id): RedirectResponse
    {
        return $this->decidir($request, fn (int $biz, int $user) => $this->propostas->aprovar($biz, $id, $user), 'Preço aprovado.');
    }

    public function recusar(Request $request, int $id): RedirectResponse
    {
        return $this->decidir($request, fn (int $biz, int $user) => $this->propostas->recusar($biz, $id, $user), 'Proposta recusada; o preço ficou como estava.');
    }

    private function decidir(Request $request, callable $acao, string $ok): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->can('product.update'), 403);

        try {
            $acao((int) $user->business_id, (int) $user->id);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }
}
