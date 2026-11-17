<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\PropsDaFicha;

/**
 * Ficha da OS por item (UC-PCV-10 · auditoria lote 3.2). Ver: `producao.access` + módulo no pacote. As
 * ações Liberar, Refazer e Cancelar item chamam as rotas JSON do OrdemSituacaoController, que conferem
 * o papel de novo; as flags `pode_*` aqui só escondem botão. A empresa vem da sessão (ADR 0093).
 *
 * O que a Ficha recebe é montado no PropsDaFicha, o mesmo que o painel lateral do Quadro usa (`?os=`).
 * Aqui as abas (custo, materiais, artes, pessoas) saem deferidas. Aba Custo (lote 3.3): só leitura e só com
 * `producao.desempenho.ver`; sem ela a prop vem nula.
 */
class OrdemFichaController extends Controller
{
    public function __construct(private readonly PropsDaFicha $ficha, private readonly ModuleUtil $moduleUtil) {}

    public function show(Request $request, int $id): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.access'), 403);

        try {
            $f = $this->ficha->montar($biz, $user, $id);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);
            throw $e;
        }

        return Inertia::render('Producao/Os/Show', $f['base'] + array_map(fn (\Closure $aba) => Inertia::defer($aba), $f['abas']));
    }
}
