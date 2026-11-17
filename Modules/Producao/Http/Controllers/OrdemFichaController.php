<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\PropsDaFicha;

/**
 * Endereço antigo da Ficha da OS por item (UC-PCV-10 · auditoria lote 3.2). Desde a decisão [W] no #10850 a
 * Ficha abre no painel lateral, como no protótipo: `/producao/ordens/{id}` leva ao Quadro com a OS aberta
 * (`/producao/quadro?os={id}`, UC-PCOSF-10), para os links já copiados e os favoritos continuarem servindo.
 *
 * Os portões continuam aqui, antes do redirecionamento: sem `producao.access` ou sem o módulo no pacote dá 403;
 * OS de outra empresa ou inexistente dá 404 (ADR 0093, a empresa vem da sessão). O que a Ficha mostra é
 * montado no PropsDaFicha, o mesmo do painel.
 */
class OrdemFichaController extends Controller
{
    public function __construct(private readonly PropsDaFicha $ficha, private readonly ModuleUtil $moduleUtil) {}

    public function show(Request $request, int $id): RedirectResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.access'), 403);

        try {
            $this->ficha->montar($biz, $user, $id);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);
            throw $e;
        }

        return redirect('/producao/quadro?os='.$id);
    }
}
