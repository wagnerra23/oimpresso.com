<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\ExerciseFavorite;

/**
 * Favoritos da biblioteca (US-FISIO-004, comparativo Physitrack).
 *
 * Favorito é do PROFISSIONAL dentro da EMPRESA da sessão: `business_id` e `user_id` vêm sempre da
 * sessão, nunca do corpo (ADR 0093). Favoritar não altera o exercício, nem o global (ADR 0437 §4):
 * só grava ou apaga a linha em `fisio_exercise_favorites`.
 *
 * O exercício precisa ser visível para a sessão (global ou da empresa). Exercício próprio de outra
 * empresa responde 404, como na edição (UC-FBI-03). Favoritar é uso pessoal da biblioteca, então
 * basta `fisio.access` (garantido pelo grupo de rotas), não `fisio.editar`.
 *
 * Idempotente nos dois sentidos: favoritar de novo não duplica; desfavoritar o que não era favorito
 * não falha.
 */
class BibliotecaFavoritosController extends Controller
{
    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $exercicio = $this->exercicioVisivel($request, $id);

        ExerciseFavorite::query()->firstOrCreate([
            'business_id' => $this->businessId($request),
            'user_id' => $this->userId($request),
            'exercise_id' => $exercicio->id,
        ]);

        return $this->responder($request, $exercicio->id, true);
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $exercicio = $this->exercicioVisivel($request, $id);

        // Escopo do model + where explícito da sessão: nunca apaga favorito de outra empresa ou colega.
        ExerciseFavorite::query()
            ->where('business_id', $this->businessId($request))
            ->where('user_id', $this->userId($request))
            ->where('exercise_id', $exercicio->id)
            ->delete();

        return $this->responder($request, $exercicio->id, false);
    }

    private function exercicioVisivel(Request $request, int $id): Exercise
    {
        $biz = $this->businessId($request);

        return Exercise::query()
            ->where(fn ($w) => $w->whereNull('business_id')->orWhere('business_id', $biz))
            ->findOrFail($id);
    }

    private function responder(Request $request, int $exercicioId, bool $favorito): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['id' => $exercicioId, 'favorito' => $favorito]);
        }

        return back();
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function userId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
