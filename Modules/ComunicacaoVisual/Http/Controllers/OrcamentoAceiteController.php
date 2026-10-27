<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Services\AceiteDoOrcamento;

/**
 * US-COMVIS-023 — o operador gera o link para o cliente aceitar o orçamento.
 *
 * POST /comunicacao-visual/api/orcamentos/{id}/link → { url, expira_em }. O token aparece só nesta
 * resposta (no banco fica o sha256). Mesma permissão de salvar e aprovar (`comvis.orcamento.create`).
 * Tier 0: `business_id` da sessão, explícito no serviço; orçamento de outra empresa = 404.
 */
class OrcamentoAceiteController extends Controller
{
    public function gerar(int $id, AceiteDoOrcamento $aceite): JsonResponse
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('comvis.orcamento.create')) {
            abort(403, 'Sem permissão para mandar orçamento ao cliente (comvis.orcamento.create).');
        }
        $businessId = (int) (session('user.business_id') ?? session('business.id'));

        try {
            $link = $aceite->gerar($businessId, $id, (int) $user->id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'url'       => url('/orcamento-cv/' . $link['token']),
            'expira_em' => $link['expira_em']->toIso8601String(),
        ], 201);
    }
}
