<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Services\PosCalculoDaOs;
use Modules\ComunicacaoVisual\Services\TerceirosDaOs;

/**
 * GET    /comunicacao-visual/api/os/{os}/pos-calculo            — custo orçado × real e margem da OS (US-COMVIS-005)
 * POST   /comunicacao-visual/api/os/{os}/terceiros              — lança custo de terceiro (201)
 * DELETE /comunicacao-visual/api/os/{os}/terceiros/{terceiro}   — remove o lançamento (204)
 *
 * Atrás de `comunicacao-visual.pos_calculo` (COMVIS_POS_CALCULO_ENABLED, desligada): desligada = 404.
 * Só quem vê custo (superadmin ou `comvis.material.manage`, a mesma regra da margem do orçamento);
 * OS de outra empresa = 404 (não confirma que o id existe). Tier 0: empresa vem da sessão.
 */
class OsPosCalculoController extends Controller
{
    public function __construct(
        private readonly PosCalculoDaOs $posCalculo,
        private readonly TerceirosDaOs $terceiros,
    ) {}

    public function show(int $os): JsonResponse
    {
        $this->exigir();

        $dados = $this->posCalculo->daOs($this->businessId(), $os);
        abort_if($dados === null, 404, 'OS não encontrada.');

        return response()->json(['data' => $dados]);
    }

    public function lancarTerceiro(Request $request, int $os): JsonResponse
    {
        $this->exigir();
        $dados = $request->validate([
            'descricao'     => ['required', 'string', 'max:150'],
            'valor'         => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'fornecedor_id' => ['nullable', 'integer', 'min:1'],
        ], ['valor.regex' => 'Informe o valor com ponto e até 2 casas (ex.: 120.50).', 'valor.not_regex' => 'O valor tem de ser maior que zero.']);

        try {
            $id = $this->terceiros->lancar($this->businessId(), $os, $dados, (int) auth()->id());
        } catch (ModelNotFoundException) {
            abort(404, 'OS não encontrada.');
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $id], 201);
    }

    public function removerTerceiro(int $os, int $terceiro): JsonResponse
    {
        $this->exigir();
        try {
            $this->terceiros->remover($this->businessId(), $os, $terceiro);
        } catch (ModelNotFoundException) {
            abort(404, 'Lançamento não encontrado.');
        }

        return response()->json(null, 204);
    }

    /** Flag desligada = 404; só quem vê custo (superadmin ou quem cuida do catálogo). */
    private function exigir(): void
    {
        abort_unless((bool) config('comunicacao-visual.pos_calculo'), 404);
        $user = auth()->user();
        abort_unless($user !== null && ($user->can('superadmin') || $user->can('comvis.material.manage')), 403);
    }

    private function businessId(): int
    {
        return (int) (session('user.business_id') ?? session('business.id'));
    }
}
