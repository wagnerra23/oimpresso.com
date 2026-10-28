<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sells;

use App\Http\Controllers\Controller;
use App\Services\Sells\AceiteDoOrcamentoVenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * US-SELL-074 — o vendedor gera o link para o cliente aprovar o orçamento.
 *
 *   POST /sells/{id}/orcamento-link → { url, expira_em }
 *
 * Atrás de `sells.orcamento_aceite.enabled` (default false → 404). Precisa de `quotation.update`.
 * A empresa vem da sessão; orçamento de outra empresa = 404 (ADR 0093). A URL pública nasce na
 * fatia 2 (página do cliente); até lá o caminho é reservado em `/orcamento/{token}`.
 */
class OrcamentoAceiteLinkController extends Controller
{
    public function __construct(private readonly AceiteDoOrcamentoVenda $aceite) {}

    public function gerar(Request $request, int $id): JsonResponse
    {
        abort_unless((bool) config('sells.orcamento_aceite.enabled'), 404);
        abort_unless(auth()->user()?->can('quotation.update'), 403);

        $businessId = (int) $request->session()->get('user.business_id');
        try {
            $link = $this->aceite->gerar($businessId, $id, (int) auth()->id());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'url' => url('/orcamento/' . $link['token']),
            'expira_em' => $link['expira_em']->toIso8601String(),
        ]);
    }
}
