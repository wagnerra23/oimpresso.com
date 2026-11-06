<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\FichaDaOrdem;
use Modules\Producao\Services\QuadroDaProducao;

/**
 * Ficha da OS por item (UC-PCV-10 · auditoria lote 3.2). Ver: `producao.access` + módulo no pacote. As
 * ações Liberar, Refazer e Cancelar item chamam as rotas JSON do OrdemSituacaoController, que conferem
 * o papel de novo; as flags `pode_*` aqui só escondem botão. A empresa vem da sessão (ADR 0093).
 */
class OrdemFichaController extends Controller
{
    public function __construct(private readonly FichaDaOrdem $ficha, private readonly QuadroDaProducao $quadro, private readonly ModuleUtil $moduleUtil) {}

    public function show(Request $request, int $id): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.access'), 403);

        try {
            $dados = $this->ficha->montar($biz, $id);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);
            throw $e;
        }

        $papel = fn (string $r) => $user->can('superadmin') || $user->hasRole(Schema::hasColumn('roles', 'business_id') ? "{$r}#{$biz}" : $r);
        $postos = array_values(array_unique(array_filter(array_column($dados['etapas'], 'posto_id'))));

        return Inertia::render('Producao/Os/Show', [
            'os' => $dados['os'],
            'etapas' => $dados['etapas'],
            'pode' => ['liberar' => $papel('producao.liberar'), 'cancelar' => $papel('vendas.gerente')],
            'pessoas' => Inertia::defer(fn () => $dados['os']['situacao'] === 'aguardando_liberacao' && $papel('producao.liberar')
                ? $this->quadro->pessoas($biz, $postos) : ['pessoas' => [], 'por_posto' => []]),
        ]);
    }
}
