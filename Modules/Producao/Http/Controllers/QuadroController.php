<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ColunaDoQuadro;
use Modules\Producao\Services\QuadroDaProducao;

/**
 * Quadro da produção (thread 06 do playbook · UC-PCV-05/06/07). Recebe os botões que tinham backend
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
        // Uma consulta só por pedido: `midias` sai dos mesmos cartões.
        $memo = null;
        $cartoes = function () use (&$memo, $biz) { return $memo ??= $this->quadro->cartoes($biz, now()); };
        // UC-PCV-02: o Painel abre o Quadro já filtrado (?so=atrasadas|arte, ?agrupar=maquina).
        $so = in_array($request->query('so'), ['atrasadas', 'arte'], true) ? $request->query('so') : 'todas';
        $agrupar = $request->query('agrupar') === 'maquina' ? 'maquina' : 'setor';
        // UC-PCV-06: o filtro mora na URL (busca, responsável e setor também), para a Linha do tempo
        // abrir com o mesmo filtro. Só texto curto entra; o resto volta vazio.
        $texto = fn (string $k, int $max) => is_string($v = $request->query($k)) ? mb_substr(trim($v), 0, $max) : '';

        return Inertia::render('Producao/Quadro/Index', [
            'hoje' => now()->toDateString(),
            'filtro' => ['so' => $so, 'agrupar' => $agrupar, 'q' => $texto('q', 80), 'resp' => $texto('resp', 120), 'setor' => $texto('setor', 60)],
            'pode_alocar' => $podeAlocar,
            'pode_aceitar_arranjo' => $user->can('superadmin') || $user->hasRole($papel),
            'postos' => $postos,
            'cartoes' => Inertia::defer($cartoes),
            'midias' => Inertia::defer(fn () => $this->quadro->midias($biz, $cartoes())),
            'pessoas' => Inertia::defer(fn () => $podeAlocar ? $this->quadro->pessoas($biz, array_column($postos, 'id')) : ['pessoas' => [], 'por_posto' => []]),
        ]);
    }

    /**
     * UC-PCV-07: o PCP move a OS para outro setor do quadro (menu "Mover para" ou arrastar). Só troca a
     * coluna e registra o evento; etapa e apontamento ficam como estão. Pede `producao.etapa.mover`.
     * OS de outra empresa → 404; setor que a empresa não tem, ou o mesmo → 422.
     */
    public function mover(Request $request, ColunaDoQuadro $coluna): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.etapa.mover'), 403);
        $dados = $request->validate([
            'os_tipo' => ['required', 'string', 'max:40'],
            'os_id' => ['required', 'integer', 'min:1'],
            'setor' => ['required', 'string', 'max:60'],
        ]);

        try {
            $setor = $coluna->mover($biz, $dados['os_tipo'], (int) $dados['os_id'], $dados['setor'], $user);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);

            return response()->json(['codigo' => $e->codigo, 'message' => $e->getMessage(), 'contexto' => $e->contexto], 422);
        }

        return response()->json(['os_tipo' => $dados['os_tipo'], 'os_id' => (int) $dados['os_id'], 'coluna' => $setor]);
    }
}
