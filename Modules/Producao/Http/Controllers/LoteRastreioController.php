<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\RastreioDoRolo;

/**
 * Rastreio do rolo (US-COM-025 · UC-FLX-18): em que OS cada fração do lote foi usada e o saldo. JSON, só leitura.
 *
 * Quem abre: quem vê compras (`purchase.view`, o aceite da US) com o módulo no pacote. A empresa vem da
 * sessão; lote de outra empresa → 404 (ADR 0093).
 */
class LoteRastreioController extends Controller
{
    public function __construct(private readonly RastreioDoRolo $rastreio, private readonly ModuleUtil $moduleUtil) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('purchase.view'), 403);

        try {
            return response()->json($this->rastreio->doLote($biz, $id));
        } catch (ApontamentoRecusado $e) {
            abort(404);
        }
    }
}
