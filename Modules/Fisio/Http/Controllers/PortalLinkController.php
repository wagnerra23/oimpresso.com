<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\PortalTokenService;

/**
 * Link do portal gerado pela clínica, na ficha do paciente (US-FISIO-007a; UC-FPS-33..35).
 *
 * `store` emite token + PIN e devolve os dois UMA vez: o banco guarda só os hashes, então
 * ninguém consegue ver o PIN de novo — perdeu, gera outro (e o anterior morre). `destroy`
 * revoga o link vivo. Os dois exigem `fisio.editar`; paciente de outra empresa é 404 pelo
 * escopo do model; paciente bloqueado não recebe link (Lei 13.787/2018 guarda, não reabre).
 *
 * Cada emissão e revogação vai ao `activity_log` com quem e quando — sem token nem PIN.
 */
class PortalLinkController extends Controller
{
    public function store(Request $request, int $id, PortalTokenService $tokens): JsonResponse
    {
        $this->exigirEdicao($request);
        $paciente = $this->paciente($id);
        if ($paciente->estaBloqueado()) {
            return response()->json(['erro' => 'paciente_bloqueado', 'message' => 'Paciente bloqueado não recebe link do portal.'], 422);
        }

        $g = $tokens->emitir($paciente);
        $this->registrar($request, $paciente, 'portal_link_gerado');

        return response()->json([
            'url' => url('/fisio/p/'.$g['token']),
            'pin' => $g['pin'],
            'expires_at' => $g['expires_at']->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, int $id, PortalTokenService $tokens): JsonResponse
    {
        $this->exigirEdicao($request);
        $paciente = $this->paciente($id);

        $tokens->revogar($paciente);
        $this->registrar($request, $paciente, 'portal_link_revogado');

        return response()->json(['link' => null]);
    }

    private function registrar(Request $request, Patient $paciente, string $evento): void
    {
        activity('fisio')
            ->performedOn($paciente)
            ->causedBy($request->user())
            ->event($evento)
            ->log($evento);
    }

    /** Paciente de outra empresa não existe para esta: 404 pelo escopo (UC-FPS-35). */
    private function paciente(int $id): Patient
    {
        return Patient::query()->findOrFail($id);
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}