<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Services\PosCalculoDaOs;

/**
 * GET /comunicacao-visual/api/os/{os}/pos-calculo — custo orçado × real e margem da OS (US-COMVIS-005).
 *
 * Atrás de `comunicacao-visual.pos_calculo` (COMVIS_POS_CALCULO_ENABLED, desligada): desligada = 404.
 * Só quem vê custo (superadmin ou `comvis.material.manage`, a mesma regra da margem do orçamento);
 * OS de outra empresa = 404 (não confirma que o id existe). Tier 0: empresa vem da sessão.
 */
class OsPosCalculoController extends Controller
{
    public function __construct(private readonly PosCalculoDaOs $posCalculo) {}

    public function show(int $os): JsonResponse
    {
        abort_unless((bool) config('comunicacao-visual.pos_calculo'), 404);
        $user = auth()->user();
        abort_unless($user !== null && ($user->can('superadmin') || $user->can('comvis.material.manage')), 403);

        $dados = $this->posCalculo->daOs((int) (session('user.business_id') ?? session('business.id')), $os);
        abort_if($dados === null, 404, 'OS não encontrada.');

        return response()->json(['data' => $dados]);
    }
}
