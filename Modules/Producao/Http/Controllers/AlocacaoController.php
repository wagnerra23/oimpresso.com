<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\AlocacaoDaEtapa;

/**
 * Alocador (UC-FLX-10 · US-PCP-029): escolhe quem e quando começa cada etapa ainda não iniciada, e
 * vê a fila do posto pela ordem do início previsto. JSON; a tela do quadro é outra entrega.
 *
 * Alocar é do gestor: exige `producao.etapa.mover` (a permissão de mover a etapa no quadro) e o
 * módulo no pacote. Operador sem ela → 403. A empresa vem da sessão; etapa ou posto de outra
 * empresa → 404 (ADR 0093).
 */
class AlocacaoController extends Controller
{
    public function __construct(private readonly AlocacaoDaEtapa $alocacao, private readonly ModuleUtil $moduleUtil) {}

    public function alocar(Request $request, int $id): JsonResponse
    {
        $biz = $this->autorizar($request);
        $dados = $request->validate([
            'user_id' => ['nullable', 'integer', 'required_without:inicio_previsto'],
            'inicio_previsto' => ['nullable', 'date', 'required_without:user_id'],
        ]);

        try {
            $etapa = $this->alocacao->alocar($biz, $id, $request->user(),
                isset($dados['user_id']) ? (int) $dados['user_id'] : null, $dados['inicio_previsto'] ?? null);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);

            return response()->json(['codigo' => $e->codigo, 'message' => $e->getMessage(), 'contexto' => $e->contexto], 422);
        }

        return response()->json(['id' => (int) $etapa->id, 'user_id' => $etapa->user_id !== null ? (int) $etapa->user_id : null,
            'inicio_previsto' => $etapa->inicio_previsto->toDateTimeString(), 'fim_previsto' => $etapa->fim_previsto->toDateTimeString()]);
    }

    public function fila(Request $request, int $postoId): JsonResponse
    {
        $biz = $this->autorizar($request);

        try {
            return response()->json(['etapas' => $this->alocacao->fila($biz, $postoId)]);
        } catch (ApontamentoRecusado $e) {
            abort(404);
        }
    }

    private function autorizar(Request $request): int
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('producao.etapa.mover'), 403);

        return $biz;
    }
}
