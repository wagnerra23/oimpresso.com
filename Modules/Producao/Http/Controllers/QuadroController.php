<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Services\QuadroDaProducao;

/**
 * Quadro da produção (thread 06 do playbook · UC-PCV-05/06). Recebe os botões que tinham backend
 * sem tela: o alocador no cartão (UC-FLX-10 · US-PCP-029, `PUT /producao/etapas/{id}/alocacao`) e o
 * simulador de bobina em lote (UC-FLX-13 · US-PCP-024, `/producao/aproveitamento*`).
 *
 * Ver o quadro: `producao.access` + módulo no pacote. Alocar: `producao.etapa.mover`. Aceitar o
 * arranjo: o papel `producao.liberar` (o mesmo do romaneio). As flags só escondem botão — cada
 * endpoint confere de novo. A empresa vem da sessão (ADR 0093).
 */
class QuadroController extends Controller
{
    public function __construct(private readonly QuadroDaProducao $quadro, private readonly ModuleUtil $moduleUtil) {}

    public function index(Request $request): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.access'), 403);

        $papel = Schema::hasColumn('roles', 'business_id') ? "producao.liberar#{$biz}" : 'producao.liberar';
        $podeAlocar = $user->can('producao.etapa.mover');
        $postos = $this->quadro->postos($biz);
        $cartoes = fn () => $this->quadro->cartoes($biz, now());

        return Inertia::render('Producao/Quadro/Index', [
            'hoje' => now()->toDateString(),
            'pode_alocar' => $podeAlocar,
            'pode_aceitar_arranjo' => $user->can('superadmin') || $user->hasRole($papel),
            'postos' => $postos,
            'cartoes' => Inertia::defer($cartoes),
            'midias' => Inertia::defer(fn () => $this->quadro->midias($biz, $cartoes())),
            'pessoas' => Inertia::defer(fn () => $podeAlocar ? $this->quadro->pessoas($biz, array_column($postos, 'id')) : ['pessoas' => [], 'por_posto' => []]),
        ]);
    }
}
