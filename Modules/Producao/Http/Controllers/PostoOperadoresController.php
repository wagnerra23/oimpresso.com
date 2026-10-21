<?php

namespace Modules\Producao\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\OperadoresDoPosto;

/**
 * Operadores de cada posto (UC-FLX-08 · US-PCP-030). Ler pede `producao.access`; definir é do gestor
 * (`producao.etapa.mover`, a mesma do alocador). Posto de outra empresa → 404 (Tier 0, ADR 0093).
 */
class PostoOperadoresController extends Controller
{
    public function __construct(private readonly OperadoresDoPosto $operadores) {}

    public function index(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('producao.access'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        $posto = Posto::query()->where('business_id', $biz)->findOrFail($id);

        return response()->json(['posto_id' => $posto->id, 'operadores' => $this->lista($biz, (int) $posto->id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('producao.etapa.mover'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        $posto = Posto::query()->where('business_id', $biz)->findOrFail($id);
        $dados = $request->validate([
            'user_ids' => ['present', 'array', 'max:100'],
            'user_ids.*' => ['integer', 'distinct'],
        ]);

        try {
            $this->operadores->definir($biz, (int) $posto->id, $dados['user_ids'], (int) $request->user()->id);
        } catch (ApontamentoRecusado $e) {
            return response()->json(['message' => $e->getMessage(), 'codigo' => $e->codigo, 'errors' => ['user_ids' => [$e->getMessage()]]], 422);
        }

        return response()->json(['posto_id' => $posto->id, 'operadores' => $this->lista($biz, (int) $posto->id)]);
    }

    /** @return list<array{id: int, nome: string}> */
    private function lista(int $biz, int $postoId): array
    {
        $ids = $this->operadores->doPosto($biz, $postoId);

        return DB::table('users')->where('business_id', $biz)->whereIn('id', $ids)->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'nome' => trim($u->first_name.' '.$u->last_name)])->all();
    }
}
