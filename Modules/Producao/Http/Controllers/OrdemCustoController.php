<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\PrevisaoCustoDaOrdem;

/**
 * Custo da OS por item em andamento (UC-FLX-36 · US-PCP-022): custo da abertura × custo de hoje
 * dos insumos apontados, e a previsão que a regra `ops` da compra manda usar. Só leitura.
 *
 * Custo é do gestor: exige `producao.desempenho.ver` (a mesma do custo-hora da pessoa) e o módulo
 * no pacote. A empresa vem da sessão; OS de outra empresa → 404 (ADR 0093).
 */
class OrdemCustoController extends Controller
{
    public function __construct(private readonly PrevisaoCustoDaOrdem $previsao, private readonly ModuleUtil $moduleUtil) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('producao.desempenho.ver'), 403);

        try {
            return response()->json($this->previsao->daOrdem($biz, $id));
        } catch (ApontamentoRecusado $e) {
            abort(404);
        }
    }
}
