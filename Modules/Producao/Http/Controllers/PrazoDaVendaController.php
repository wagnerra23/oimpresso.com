<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Services\PrazoPelaFila;

/**
 * Prazo de entrega da venda ou do orçamento calculado pela fila de produção (UC-FLX-06 ·
 * US-PCP-028). Só leitura: devolve a estimativa e as premissas; não grava data na venda.
 *
 * Quem vende (`sell.view`) ou quem produz (`producao.access`) consulta, com o módulo no pacote.
 * A empresa vem da sessão; venda de outra empresa → 404 (ADR 0093).
 */
class PrazoDaVendaController extends Controller
{
    public function __construct(private readonly PrazoPelaFila $prazo, private readonly ModuleUtil $moduleUtil) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pode = $user?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && ($user->can('sell.view') || $user->can('producao.access')), 403);

        $estimativa = $this->prazo->daVenda($biz, $id);
        abort_if($estimativa === null, 404);

        return response()->json($estimativa);
    }
}
