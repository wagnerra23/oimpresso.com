<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\PrevistoRealizadoDaOrdem;

/**
 * Previsto × realizado (UC-FLX-15 · US-COMVIS-005): custo orçado na linha da venda × custo real
 * congelado da OS, diferença e margem. Por OS e por venda. Só leitura.
 *
 * Custo é do gestor: exige `producao.desempenho.ver` e o módulo no pacote, como o custo da OS
 * aberta (OrdemCustoController). A empresa vem da sessão; OS ou venda de outra empresa → 404.
 */
class PrevistoRealizadoController extends Controller
{
    public function __construct(private readonly PrevistoRealizadoDaOrdem $comparativo, private readonly ModuleUtil $moduleUtil) {}

    public function ordem(Request $request, int $id): JsonResponse
    {
        return $this->responder($request, fn (int $biz) => $this->comparativo->daOrdem($biz, $id));
    }

    public function venda(Request $request, int $id): JsonResponse
    {
        return $this->responder($request, fn (int $biz) => $this->comparativo->daVenda($biz, $id));
    }

    /** @param  callable(int): array<string, mixed>  $ler */
    private function responder(Request $request, callable $ler): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('producao.desempenho.ver'), 403);

        try {
            return response()->json($ler($biz));
        } catch (ApontamentoRecusado) {
            abort(404);
        }
    }
}
