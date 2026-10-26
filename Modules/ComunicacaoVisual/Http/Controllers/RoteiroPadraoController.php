<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Contracts\Producao\ProgramacaoDaOs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Services\RoteiroPadraoDaOs;

/**
 * US-COMVIS-003 — o roteiro padrão com que a OS da CV nasce (decisão [W] 2026-10-26).
 *
 *   GET /comunicacao-visual/api/roteiro-padrao → { roteiro_id, roteiros: [{id, nome, familia}] }
 *   PUT /comunicacao-visual/api/roteiro-padrao   { roteiro_id: int|null }
 *
 * Quem cuida do catálogo (`comvis.material.manage`) ou superadmin. Só aceita roteiro ativo da
 * própria empresa (a lista vem do Producao pelo contrato); null tira o padrão. Sem o módulo
 * Producao ligado, a lista vem vazia e só null é aceito.
 * Tier 0: `business_id` da sessão, explícito.
 */
class RoteiroPadraoController extends Controller
{
    public function __construct(private readonly RoteiroPadraoDaOs $padrao) {}

    public function show(): JsonResponse
    {
        $biz = $this->empresa();

        return response()->json(['roteiro_id' => $this->padrao->atual($biz), 'roteiros' => $this->roteiros($biz)]);
    }

    public function update(Request $request): JsonResponse
    {
        $biz = $this->empresa();
        $id = $request->validate(['roteiro_id' => ['present', 'nullable', 'integer', 'min:1']])['roteiro_id'];
        if ($id !== null && ! in_array((int) $id, array_column($this->roteiros($biz), 'id'), true)) {
            return response()->json(['message' => 'Escolha um roteiro ativo da sua empresa.'], 422);
        }
        $this->padrao->definir($biz, $id === null ? null : (int) $id);

        return response()->json(['roteiro_id' => $this->padrao->atual($biz)]);
    }

    /** @return list<array{id: int, nome: string, familia: string}> */
    private function roteiros(int $biz): array
    {
        return app()->bound(ProgramacaoDaOs::class) ? app(ProgramacaoDaOs::class)->roteiros($biz) : [];
    }

    private function empresa(): int
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('comvis.material.manage')) {
            abort(403, 'Sem permissão para definir o roteiro padrão (comvis.material.manage).');
        }

        return (int) (session('user.business_id') ?? session('business.id'));
    }
}
