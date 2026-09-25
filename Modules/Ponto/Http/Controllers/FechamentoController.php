<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Ponto\Services\FechamentoService;

/**
 * Fechamento da competência (ADR 0413). A rota exige `ponto.fechar` (D1) — permissão própria,
 * não `ponto.access`. Não existe ação de reabrir (D1) nem geração de AFD/AEJ aqui (D4).
 */
class FechamentoController extends Controller
{
    public function __construct(private FechamentoService $service)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'competencia'        => ['required', 'date_format:Y-m'],
            'aceitar_bloqueios'  => ['sometimes', 'boolean'],
        ]);

        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $competencia = CarbonImmutable::createFromFormat('!Y-m', $dados['competencia']);

        try {
            $fechada = $this->service->fechar(
                $businessId,
                $competencia,
                (int) $request->user()->id,
                (bool) ($dados['aceitar_bloqueios'] ?? false)
            );
        } catch (DomainException $e) {
            return back()->withErrors(['competencia' => $e->getMessage()]);
        }

        $aceitos = count($fechada->bloqueios_aceitos ?? []);

        return back()->with('success', 'Competência ' . $competencia->format('m/Y') . ' fechada'
            . ($aceitos ? " aceitando {$aceitos} bloqueio(s), registrados com seu nome e a data." : '.'));
    }
}
