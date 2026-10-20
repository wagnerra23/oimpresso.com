<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\RomaneioDeImpressao;

/**
 * Romaneio de impressão peça a peça (UC-FLX-12 · US-PCP-023), em JSON (a tela é follow-up).
 *
 * Gerar é do PCP: o papel `producao.liberar` (o mesmo da ação Liberar do ProcessoDaOrdem). Ler basta
 * `producao.access` — o operador da impressão lê a lista. Módulo no pacote nas duas. A empresa vem da
 * sessão; OS, etapa ou romaneio de outra empresa → 404 (ADR 0093).
 */
class RomaneioController extends Controller
{
    public function __construct(private readonly RomaneioDeImpressao $romaneios, private readonly ModuleUtil $moduleUtil) {}

    public function store(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);
        $papel = Schema::hasColumn('roles', 'business_id') ? "producao.liberar#{$biz}" : 'producao.liberar';
        abort_unless($request->user()->can('superadmin') || $request->user()->hasRole($papel), 403);
        $dados = $request->validate(['programacao_id' => ['required', 'integer', 'min:1']]);

        try {
            $romaneio = $this->romaneios->gerar($biz, $id, (int) $dados['programacao_id'], (int) $request->user()->id);

            return response()->json($this->romaneios->ler($biz, (int) $romaneio->id), 201);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);

            return response()->json(['codigo' => $e->codigo, 'mensagem' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);
        abort_unless($request->user()->can('producao.access'), 403);

        try {
            return response()->json($this->romaneios->ler($biz, $id));
        } catch (ApontamentoRecusado $e) {
            abort(404);
        }
    }

    private function empresa(Request $request): int
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode, 403);

        return $biz;
    }
}
