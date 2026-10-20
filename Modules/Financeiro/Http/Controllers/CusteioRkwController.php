<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Financeiro\Services\CusteioRkwService;

/**
 * Custeio RKW e ponto de equilíbrio (US-FIN-070 · UC-FLX-20), em JSON.
 *
 *  - GET  /financeiro/custeio-rkw?mes=AAAA-MM            → cálculo, só leitura
 *  - POST /financeiro/custeio-rkw/aplicar/{posto}?mes=…  → grava o custo-hora sugerido; devolve antes→depois
 *
 * Permissão: `financeiro.relatorios.view` (gestor do Financeiro) E `producao.desempenho.ver`
 * (custo-hora é dado de desempenho, D-PERM do PCP). Falta qualquer uma → 403.
 * Tier 0: business da sessão, nunca do request; posto de outra empresa → 404.
 */
class CusteioRkwController extends Controller
{
    public function __construct(private CusteioRkwService $service)
    {
        $this->middleware('auth');
    }

    public function show(Request $request): JsonResponse
    {
        $biz = $this->autorizar($request);
        try {
            return response()->json($this->service->calcular($biz, $this->mes($request)));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function aplicar(Request $request, int $posto): JsonResponse
    {
        $biz = $this->autorizar($request);
        try {
            $troca = $this->service->aplicar($biz, $this->mes($request), $posto);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        abort_if($troca === null, 404);

        return response()->json($troca);
    }

    private function autorizar(Request $request): int
    {
        $u = $request->user();
        abort_unless($u->can('financeiro.relatorios.view') && $u->can('producao.desempenho.ver'), 403);

        return (int) $request->session()->get('user.business_id');
    }

    private function mes(Request $request): string
    {
        return (string) ($request->input('mes') ?: now()->subMonthNoOverflow()->format('Y-m'));
    }
}
