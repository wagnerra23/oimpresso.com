<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\FaltaDeMaterialDaOs;
use Modules\Producao\Services\SituacaoDaOrdem;

/**
 * Material da OS por item (D3 · revisão de 2026-10-21), em JSON (a tela é follow-up).
 *
 * Ver o que falta e o que está a caminho: `producao.access`. Conferir — que tira a OS de Aguardando
 * material quando não falta mais nada — é do PCP: o papel `producao.liberar`, o mesmo do Liberar.
 * Módulo no pacote nas duas. A empresa vem da sessão; OS de outra empresa → 404 (ADR 0093).
 */
class OrdemMaterialController extends Controller
{
    public function __construct(
        private readonly SituacaoDaOrdem $situacao,
        private readonly FaltaDeMaterialDaOs $falta,
        private readonly ModuleUtil $moduleUtil,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);
        abort_unless($request->user()->can('producao.access'), 403);

        try {
            $ordem = $this->situacao->ordem($biz, $id);
        } catch (ApontamentoRecusado) {
            abort(404);
        }

        return response()->json(['situacao' => $ordem->currentStage?->key, 'falta' => $this->falta->daOrdem($biz, $ordem)]);
    }

    public function conferir(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);
        $papel = Schema::hasColumn('roles', 'business_id') ? "producao.liberar#{$biz}" : 'producao.liberar';
        abort_unless($request->user()->can('superadmin') || $request->user()->hasRole($papel), 403);

        try {
            return response()->json($this->situacao->conferirMaterial($biz, $id, $request->user()));
        } catch (ApontamentoRecusado) {
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
