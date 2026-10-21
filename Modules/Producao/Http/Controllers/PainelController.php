<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Services\PainelDaProducao;
use Modules\Producao\Services\QuadroDaProducao;

/**
 * Painel da produção (thread 06 do playbook · UC-PCV-01..04 e 29 · US-PCP-017): indicadores que abrem o
 * Quadro já filtrado, alertas com ação, fila de aprovação de arte, carga das máquinas hoje e os eventos.
 * Só leitura. Ver: `producao.access` + módulo no pacote, a mesma porta do Quadro. A empresa vem da
 * sessão (ADR 0093).
 */
class PainelController extends Controller
{
    public function __construct(
        private readonly QuadroDaProducao $quadro,
        private readonly PainelDaProducao $painel,
        private readonly ModuleUtil $moduleUtil,
    ) {}

    public function index(Request $request): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.access'), 403);

        return Inertia::render('Producao/Painel/Index', [
            'hoje' => now()->toDateString(),
            'painel' => Inertia::defer(fn () => $this->painel->montar($biz, now(), $this->quadro->cartoes($biz, now()))),
        ]);
    }
}
