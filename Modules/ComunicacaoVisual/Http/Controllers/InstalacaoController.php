<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\ComunicacaoVisual\Http\Requests\AgendarInstalacaoRequest;
use Modules\ComunicacaoVisual\Services\AgendaInstalacao;

/**
 * InstalacaoController — agenda da equipe de instalação (US-COMVIS-007 · UC-FLX-21).
 *
 *   GET  /comunicacao-visual/api/instalacoes?de=AAAA-MM-DD&ate=AAAA-MM-DD → agenda da janela (≤ 92 dias)
 *   POST /comunicacao-visual/api/os/{os}/instalacao                       → agenda equipe + veículo
 *
 * 201 = agendada · 422 = sem janela (conflito de equipe/veículo), equipe de fora ou dado inválido
 * 404 = OS de outra empresa (não 403: não confirma que o id existe) · 403 = sem permissão.
 * Sem tela neste PR — a agenda visual é follow-up.
 *
 * Tier 0 (ADR 0093): business_id vem da sessão, nunca do input.
 */
class InstalacaoController extends Controller
{
    public const JANELA_MAXIMA_DIAS = 92;

    public function __construct(private readonly AgendaInstalacao $agenda) {}

    public function index(Request $request): JsonResponse
    {
        $this->exigir(['comvis.os.view', 'comvis.os.update_status']);
        $dados = $request->validate([
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
        ]);
        $de = Carbon::parse($dados['de'])->startOfDay();
        $ate = Carbon::parse($dados['ate'])->endOfDay();
        if ($de->diffInDays($ate) > self::JANELA_MAXIMA_DIAS) {
            return response()->json(['message' => 'A janela da agenda é de no máximo '.self::JANELA_MAXIMA_DIAS.' dias.'], 422);
        }

        return response()->json(['data' => $this->agenda->listar($this->businessId(), $de, $ate)]);
    }

    public function agendar(AgendarInstalacaoRequest $request, int $os): JsonResponse
    {
        // A permissão (comvis.os.update_status) é conferida no authorize() do request, antes da validação.
        $dados = $request->validated();

        try {
            $instalacao = $this->agenda->agendar(
                $this->businessId(),
                $os,
                Carbon::parse($dados['inicio']),
                Carbon::parse($dados['fim']),
                $dados['equipe'],
                $dados['veiculo'] ?? null,
                $dados['observacoes'] ?? null,
            );
        } catch (ModelNotFoundException) {
            abort(404, 'OS não encontrada.');
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($instalacao, 201);
    }

    /** @param list<string> $permissoes qualquer uma basta; superadmin sempre passa */
    private function exigir(array $permissoes): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->can('superadmin')) {
            return;
        }
        foreach ($permissoes as $p) {
            if ($user->can($p)) {
                return;
            }
        }
        abort(403, 'Sem permissão na agenda de instalação ('.implode(' ou ', $permissoes).').');
    }

    private function businessId(): int
    {
        return (int) (session('user.business_id') ?? session('business.id'));
    }
}
