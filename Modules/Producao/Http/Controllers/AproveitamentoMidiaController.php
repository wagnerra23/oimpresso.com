<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\AproveitamentoDeMidia;

/**
 * Aproveitamento de mídia na bobina (UC-FLX-13 · US-PCP-024), em JSON (a tela é follow-up).
 *
 * Cadastrar a bobina e simular: `producao.access`. Aceitar o arranjo (grava e gera os romaneios): o
 * papel `producao.liberar`, o mesmo de gerar o romaneio. Módulo no pacote em todas. A empresa vem da
 * sessão; mídia ou OS de outra empresa → 404 (ADR 0093).
 */
class AproveitamentoMidiaController extends Controller
{
    public function __construct(private readonly AproveitamentoDeMidia $aproveitamento, private readonly ModuleUtil $moduleUtil) {}

    public function bobina(Request $request, int $material): JsonResponse
    {
        $biz = $this->empresa($request);
        abort_unless($request->user()->can('producao.access'), 403);
        $dados = $request->validate([
            'largura_util_m' => ['required', 'numeric', 'gt:0', 'max:20'],
            'margem_m' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'permite_girar' => ['nullable', 'boolean'],
        ]);

        return $this->responder(fn () => $this->aproveitamento->salvarBobina($biz, $material, $dados));
    }

    public function simular(Request $request): JsonResponse
    {
        $biz = $this->empresa($request);
        abort_unless($request->user()->can('producao.access'), 403);
        $dados = $request->validate([
            'material_id' => ['required', 'integer', 'min:1'],
            'os_ids' => ['required', 'array', 'min:1'],
            'os_ids.*' => ['integer', 'min:1'],
        ]);

        return $this->responder(fn () => $this->aproveitamento->simular($biz, (int) $dados['material_id'], array_map('intval', $dados['os_ids'])));
    }

    public function aceitar(Request $request): JsonResponse
    {
        $biz = $this->empresa($request);
        $papel = Schema::hasColumn('roles', 'business_id') ? "producao.liberar#{$biz}" : 'producao.liberar';
        abort_unless($request->user()->can('superadmin') || $request->user()->hasRole($papel), 403);
        $dados = $request->validate([
            'material_id' => ['required', 'integer', 'min:1'],
            'os' => ['required', 'array', 'min:1'],
            'os.*.os_id' => ['required', 'integer', 'min:1', 'distinct'],
            'os.*.programacao_id' => ['required', 'integer', 'min:1'],
        ]);
        $etapas = [];
        foreach ($dados['os'] as $linha) {
            $etapas[(int) $linha['os_id']] = (int) $linha['programacao_id'];
        }

        return $this->responder(fn () => $this->aproveitamento->aceitar($biz, (int) $dados['material_id'], $etapas, (int) $request->user()->id), 201);
    }

    /** @param callable(): array<string, mixed> $acao */
    private function responder(callable $acao, int $status = 200): JsonResponse
    {
        try {
            return response()->json($acao(), $status);
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);

            return response()->json(['codigo' => $e->codigo, 'mensagem' => $e->getMessage()] + $e->contexto, 422);
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
